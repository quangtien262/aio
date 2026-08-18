<?php

namespace App\Support\AccountingTax\Providers;

use App\Models\AcctDocument;
use App\Models\AcctEinvoiceInbound;
use App\Models\AcctParty;
use App\Models\AcctPartySource;
use App\Support\AccountingTax\AccountingDocumentService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InboundInvoiceReviewService
{
    public function __construct(private readonly AccountingDocumentService $documents) {}

    public function createInternalDraft(AcctEinvoiceInbound $invoice, ?int $adminId): AcctDocument
    {
        $invoice->loadMissing(['lines', 'organization']);

        if ($invoice->document_id !== null) {
            return AcctDocument::query()->findOrFail($invoice->document_id)->load(['party', 'lines']);
        }

        return DB::transaction(function () use ($invoice, $adminId): AcctDocument {
            $invoice = AcctEinvoiceInbound::query()
                ->with(['lines', 'organization'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if ($invoice->document_id !== null) {
                return AcctDocument::query()
                    ->findOrFail($invoice->document_id)
                    ->load(['party', 'lines']);
            }

            $party = $this->supplier($invoice);
            $payload = [
                'organization_id' => $invoice->organization_id,
                'party_id' => $party->id,
                'direction' => 'inbound',
                'document_type' => 'supplier_invoice',
                'document_no' => $this->documentNumber($invoice),
                'document_date' => $invoice->issued_at?->toDateString() ?? $invoice->invoice_date?->toDateString(),
                'currency' => $invoice->currency ?: 'VND',
                'exchange_rate' => (string) ($invoice->exchange_rate ?: '1'),
                'source_module' => 'minvoice-connector',
                'source_type' => 'msmi_input_invoice',
                'source_id' => (string) $invoice->id,
                'idempotency_key' => 'msmi-input-'.$invoice->connection_id.'-'.$invoice->provider_invoice_id,
                'tax_eligibility' => 'not_assessed',
                'notes' => 'Bản nháp tạo từ hóa đơn đầu vào mSMI; cần kế toán kiểm tra trước khi duyệt.',
                'metadata' => [
                    'provider' => 'minvoice',
                    'external_invoice_id' => $invoice->id,
                    'provider_invoice_id' => $invoice->provider_invoice_id,
                    'provider_total_amount' => (string) $invoice->total_amount,
                    'provider_tax_amount' => (string) $invoice->tax_amount,
                    'provider_warnings' => $invoice->warnings ?? [],
                ],
                'lines' => $this->documentLines($invoice),
            ];
            $document = $this->documents->create($payload, $adminId);
            $comparison = $this->compare($invoice, $document);
            $invoice->forceFill([
                'document_id' => $document->id,
                'reconciliation_status' => $comparison['matches'] ? 'matched' : 'mismatch',
            ])->save();

            return $document->load(['party', 'lines']);
        }, 3);
    }

    public function match(AcctEinvoiceInbound $invoice, AcctDocument $document): AcctEinvoiceInbound
    {
        if ((int) $invoice->organization_id !== (int) $document->organization_id
            || $document->direction !== 'inbound') {
            throw ValidationException::withMessages([
                'document_id' => ['Chỉ được liên kết chứng từ đầu vào cùng pháp nhân.'],
            ]);
        }

        $comparison = $this->compare($invoice, $document);
        $invoice->forceFill([
            'document_id' => $document->id,
            'reconciliation_status' => $comparison['matches'] ? 'matched' : 'mismatch',
        ])->save();

        return $invoice->fresh(['lines']);
    }

    public function unmatch(AcctEinvoiceInbound $invoice): AcctEinvoiceInbound
    {
        $invoice->forceFill([
            'document_id' => null,
            'reconciliation_status' => 'unmatched',
        ])->save();

        return $invoice->fresh(['lines']);
    }

    public function compare(AcctEinvoiceInbound $invoice, AcctDocument $document): array
    {
        $totalDifference = BigDecimal::of((string) $invoice->total_amount)
            ->minus((string) $document->grand_total)
            ->abs();
        $taxDifference = BigDecimal::of((string) $invoice->tax_amount)
            ->minus((string) $document->tax_total)
            ->abs();
        $tolerance = BigDecimal::of('0.01');

        return [
            'matches' => $totalDifference->isLessThanOrEqualTo($tolerance)
                && $taxDifference->isLessThanOrEqualTo($tolerance),
            'total_difference' => $totalDifference->__toString(),
            'tax_difference' => $taxDifference->__toString(),
        ];
    }

    private function supplier(AcctEinvoiceInbound $invoice): AcctParty
    {
        $taxCode = trim((string) $invoice->seller_tax_code);
        $party = AcctParty::query()
            ->where('organization_id', $invoice->organization_id)
            ->when($taxCode !== '', fn ($query) => $query->where('tax_code', $taxCode))
            ->when($taxCode === '', fn ($query) => $query->where('name', $invoice->seller_name))
            ->first();

        $party ??= AcctParty::query()->create([
            'organization_id' => $invoice->organization_id,
            'type' => 'supplier',
            'name' => $invoice->seller_name ?: 'Nhà cung cấp chưa xác định',
            'tax_code' => $invoice->seller_tax_code,
            'address' => $invoice->seller_address,
            'metadata' => ['review_required' => true, 'source' => 'minvoice_msmi'],
        ]);

        AcctPartySource::query()->updateOrCreate(
            [
                'organization_id' => $invoice->organization_id,
                'source_module' => 'minvoice-connector',
                'source_type' => 'msmi_supplier',
                'source_id' => $taxCode !== '' ? $taxCode : 'invoice-'.$invoice->provider_invoice_id,
            ],
            [
                'party_id' => $party->id,
                'source_key' => $taxCode !== '' ? $taxCode : null,
                'snapshot' => [
                    'name' => $invoice->seller_name,
                    'tax_code' => $invoice->seller_tax_code,
                    'address' => $invoice->seller_address,
                ],
                'synced_at' => now(),
            ],
        );

        return $party;
    }

    private function documentLines(AcctEinvoiceInbound $invoice): array
    {
        if ($invoice->lines->isEmpty()) {
            return [[
                'line_type' => 'item',
                'item_kind' => 'service',
                'name' => 'Hàng hóa/dịch vụ theo hóa đơn điện tử '.$invoice->invoice_series.'-'.$invoice->invoice_number,
                'unit' => 'invoice',
                'quantity' => '1',
                'unit_price' => (string) $invoice->subtotal_ex_vat,
                'discount_amount' => '0',
                'tax_category' => $invoice->tax_amount > 0 ? 'standard' : 'not_subject',
                'tax_rate' => $invoice->tax_amount > 0 && $invoice->subtotal_ex_vat > 0
                    ? BigDecimal::of((string) $invoice->tax_amount)
                        ->multipliedBy(100)
                        ->dividedBy((string) $invoice->subtotal_ex_vat, 2, RoundingMode::HalfUp)
                        ->__toString()
                    : '0',
                'snapshot' => ['external_invoice_id' => $invoice->id, 'provider_summary_line' => true],
            ]];
        }

        return $invoice->lines->map(function ($line): array {
            $quantity = BigDecimal::of((string) $line->quantity);
            $subtotal = BigDecimal::of((string) $line->subtotal_ex_vat);
            $unitPrice = BigDecimal::of((string) $line->unit_price);

            if ($quantity->isZero() && ! $subtotal->isZero()) {
                $quantity = BigDecimal::one();
                $unitPrice = $subtotal;
            }

            [$category, $rate] = $this->tax($line->vat_rate);

            return [
                'line_type' => 'item',
                'item_kind' => 'goods',
                'name' => $line->item_name,
                'unit' => $line->unit,
                'quantity' => $quantity->__toString(),
                'unit_price' => $unitPrice->__toString(),
                'discount_amount' => (string) $line->discount_amount,
                'tax_category' => $category,
                'tax_rate' => $rate,
                'snapshot' => [
                    'external_invoice_line_id' => $line->id,
                    'provider_line_id' => $line->provider_line_id,
                    'provider_vat_amount' => (string) $line->vat_amount,
                ],
            ];
        })->all();
    }

    private function tax(?string $rate): array
    {
        $normalized = strtoupper(trim((string) $rate));

        return match ($normalized) {
            '-1', 'KCT', 'KHÔNG CHỊU THUẾ', 'KHONG CHIU THUE' => ['not_subject', '0'],
            '-2', 'KKKNT', 'KHÔNG KÊ KHAI', 'KHONG KE KHAI' => ['not_declared', '0'],
            '0', '0%', 'ZERO' => ['zero_rated', '0'],
            default => ['standard', is_numeric(str_replace('%', '', $normalized))
                ? str_replace('%', '', $normalized)
                : '0'],
        };
    }

    private function documentNumber(AcctEinvoiceInbound $invoice): string
    {
        $sellerTaxCode = preg_replace('/[^A-Za-z0-9]/', '', (string) $invoice->seller_tax_code) ?: 'UNKNOWN';
        $series = preg_replace('/[^A-Za-z0-9]/', '', (string) $invoice->invoice_series) ?: 'NA';
        $number = preg_replace('/[^A-Za-z0-9]/', '', (string) $invoice->invoice_number) ?: (string) $invoice->id;

        return 'MSMI-'.$sellerTaxCode.'-'.$series.'-'.$number;
    }
}
