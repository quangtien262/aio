<?php

namespace App\Support\AccountingTax\Providers\Minvoice;

use App\Models\AcctDocument;
use App\Models\AcctDocumentLine;
use App\Models\AcctEinvoiceTransmission;
use App\Models\AcctProviderConnection;
use App\Support\AccountingTax\Providers\InvoiceIssuancePolicy;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class MinvoicePayloadBuilder
{
    public function __construct(private readonly InvoiceIssuancePolicy $issuancePolicy) {}

    public function preview(
        AcctDocument $document,
        AcctProviderConnection $connection,
        ?string $series = null,
    ): array {
        $document->loadMissing(['organization', 'party', 'lines']);
        $series ??= (string) data_get($connection->settings, 'default_series', '');
        $warnings = $this->warnings($document, $connection, $series);
        $buyer = $document->buyer_snapshot ?? [
            'legal_name' => $document->party?->name,
            'tax_code' => $document->party?->tax_code,
            'address' => $document->party?->address,
            'email' => $document->party?->email,
        ];
        $payload = [
            'editmode' => 1,
            'data' => [[
                'inv_invoiceSeries' => $series,
                'inv_invoiceIssuedDate' => $document->document_date?->format('d/m/Y'),
                'inv_currencyCode' => $document->currency,
                'inv_exchangeRate' => $document->currency === 'VND' ? '1' : (string) $document->exchange_rate,
                'inv_buyerDisplayName' => data_get($buyer, 'legal_name'),
                'inv_buyerLegalName' => data_get($buyer, 'legal_name'),
                'inv_buyerTaxCode' => data_get($buyer, 'tax_code'),
                'inv_buyerAddressLine' => data_get($buyer, 'address'),
                'inv_buyerEmail' => data_get($buyer, 'email'),
                'inv_paymentMethodName' => (string) data_get($document->metadata, 'payment_method', 'TM/CK'),
                'inv_discountAmount' => $this->money($document->discount_total),
                'inv_TotalAmountWithoutVat' => $this->money($document->subtotal),
                'inv_vatAmount' => $this->money($document->tax_total),
                'inv_TotalAmount' => $this->money($document->grand_total),
                'key_api' => $this->operationKey($document, $connection),
                'details' => [[
                    'data' => $document->lines
                        ->map(fn (AcctDocumentLine $line, int $index): array => $this->line($line, $index + 1))
                        ->values()
                        ->all(),
                ]],
            ]],
        ];

        return ['payload' => $payload, 'warnings' => $warnings];
    }

    public function buildForIssue(
        AcctDocument $document,
        AcctProviderConnection $connection,
        ?string $series = null,
    ): array {
        $preview = $this->preview($document, $connection, $series);
        $blocking = collect($preview['warnings'])->where('blocking', true)->pluck('message')->values()->all();

        if ($blocking !== []) {
            throw ValidationException::withMessages(['document' => $blocking]);
        }

        return $preview['payload'];
    }

    public function operationKey(AcctDocument $document, AcctProviderConnection $connection): string
    {
        return sprintf(
            'aio-%s-org-%d-document-%d',
            $connection->environment,
            $document->organization_id,
            $document->id,
        );
    }

    public function legalOperationPreview(
        AcctDocument $document,
        AcctProviderConnection $connection,
        string $operation,
        array $options = [],
    ): array {
        if (! in_array($operation, ['adjust', 'replace', 'cancel'], true)) {
            throw ValidationException::withMessages(['operation' => ['Nghiệp vụ pháp lý không hợp lệ.']]);
        }

        $document->loadMissing(['originalDocument', 'organization', 'party', 'lines']);

        if (in_array($operation, ['adjust', 'replace'], true) && $document->original_document_id === null) {
            throw ValidationException::withMessages([
                'document' => ['Chứng từ điều chỉnh/thay thế phải tham chiếu chứng từ gốc.'],
            ]);
        }

        $expectedCorrectionTypes = [
            'adjust' => ['adjustment', 'adjust'],
            'replace' => ['replacement', 'replace'],
        ];

        if (isset($expectedCorrectionTypes[$operation])
            && ! in_array($document->correction_type, $expectedCorrectionTypes[$operation], true)) {
            throw ValidationException::withMessages([
                'document' => ['Loại quan hệ chứng từ không khớp nghiệp vụ pháp lý đã chọn.'],
            ]);
        }

        $preview = $this->preview($document, $connection, $options['series'] ?? null);
        $originalDocumentId = $operation === 'cancel' ? $document->id : $document->original_document_id;
        $originalProviderDocumentId = AcctEinvoiceTransmission::query()
            ->where('document_id', $originalDocumentId)
            ->where('connection_id', $connection->id)
            ->whereNotNull('provider_document_id')
            ->latest('id')
            ->value('provider_document_id');
        $legalWarnings = [[
            'code' => 'provider_contract_not_approved',
            'message' => 'Chỉ cho phép preview/audit; không phát request adjust/replace/cancel đến Minvoice.',
            'blocking' => true,
        ]];

        if ($originalProviderDocumentId === null) {
            $legalWarnings[] = [
                'code' => 'original_provider_document_missing',
                'message' => 'Chưa tìm thấy provider document ID của hóa đơn gốc.',
                'blocking' => true,
            ];
        }

        return [
            'operation' => $operation,
            'provider_supported' => false,
            'document_id' => $document->id,
            'original_document_id' => $originalDocumentId,
            'original_provider_document_id' => $originalProviderDocumentId,
            'reason' => $options['reason'] ?? null,
            'legal_reference' => $options['legal_reference'] ?? null,
            'invoice_payload' => $preview['payload'],
            'warnings' => [
                ...$preview['warnings'],
                ...$legalWarnings,
            ],
        ];
    }

    private function line(AcctDocumentLine $line, int $lineNo): array
    {
        $taxCategory = (string) ($line->tax_category ?: data_get($line->snapshot, 'tax_category', 'standard'));
        $taxRate = $line->tax_rate;

        return [
            'inv_itemCode' => $line->sku ?: 'AIO-'.$line->id,
            'inv_itemName' => $line->name,
            'inv_unitCode' => $line->unit,
            'inv_quantity' => $this->quantity($line->quantity),
            'inv_unitPrice' => $this->money($line->unit_price),
            'inv_discountAmount' => $this->money($line->discount_amount),
            'inv_TotalAmountWithoutVat' => $this->money(
                BigDecimal::of((string) $line->line_total)->minus((string) $line->tax_amount),
            ),
            'inv_vatAmount' => $this->money($line->tax_amount),
            'inv_TotalAmount' => $this->money($line->line_total),
            'ma_thue' => match ($taxCategory) {
                'not_subject', 'non_taxable', 'exempt' => '-1',
                'not_declared' => '-2',
                'zero_rated' => '0',
                default => $taxRate === null ? '-1' : $this->quantity($taxRate),
            },
            'line_no' => $lineNo,
        ];
    }

    private function warnings(AcctDocument $document, AcctProviderConnection $connection, string $series): array
    {
        $warnings = [];
        $buyer = $document->buyer_snapshot ?? [];
        $seller = $document->seller_snapshot ?? [];
        $add = function (string $code, string $message, bool $blocking = true) use (&$warnings): void {
            $warnings[] = compact('code', 'message', 'blocking');
        };

        if ($document->organization_id !== $connection->organization_id) {
            $add('organization_mismatch', 'Chứng từ và kết nối Minvoice không thuộc cùng pháp nhân.');
        }

        if ($document->direction !== 'outbound') {
            $add('wrong_direction', 'Chỉ chứng từ đầu ra mới được phát hành hóa đơn điện tử.');
        }

        if ($document->workflow_status !== 'posted') {
            $add('not_posted', 'Chứng từ phải được duyệt và ghi sổ trước khi phát hành.');
        }

        if ($document->document_type !== 'tax_invoice') {
            $add(
                'unsupported_document_type',
                'Luồng phát hành tiêu chuẩn chỉ nhận hóa đơn thuế; chứng từ nội bộ và điều chỉnh phải dùng quy trình riêng.',
            );
        }

        if ($document->document_date === null) {
            $add('missing_issue_date', 'Thiếu ngày lập hóa đơn.');
        }

        if ($buyer === [] || ! data_get($buyer, 'legal_name')) {
            $add('missing_buyer', 'Thiếu thông tin người mua.');
        }

        if ($document->lines->isEmpty()) {
            $add('missing_lines', 'Hóa đơn chưa có dòng hàng hóa/dịch vụ.');
        }

        $unsupportedLineTypes = $document->lines
            ->whereIn('line_type', ['discount', 'adjustment', 'note'])
            ->pluck('line_type')
            ->unique()
            ->values();

        if ($unsupportedLineTypes->isNotEmpty()) {
            $add(
                'unsupported_provider_line_types',
                'Provider payload chưa xác nhận mapping cho dòng: '.$unsupportedLineTypes->implode(', ').'.',
            );
        }

        if ($series === '') {
            $add('missing_series', 'Chưa chọn ký hiệu hóa đơn.');
        }

        if ($document->currency !== 'VND'
            && BigDecimal::of((string) $document->exchange_rate)->isLessThanOrEqualTo(BigDecimal::zero())) {
            $add('missing_exchange_rate', 'Hóa đơn ngoại tệ cần tỷ giá đã được kế toán xác nhận.');
        }

        if (! data_get($buyer, 'tax_code')) {
            $add('missing_buyer_tax_code', 'Người mua chưa có mã số thuế; cần kiểm tra lại trước khi phát hành.', false);
        }

        $sellerTaxCode = preg_replace('/\D/', '', (string) data_get($seller, 'tax_code'));
        $connectionTaxCode = preg_replace('/\D/', '', (string) data_get($connection->credentials, 'tax_code'));

        if ($sellerTaxCode === '' || $sellerTaxCode !== $connectionTaxCode) {
            $add('seller_tax_code_mismatch', 'MST người bán trong snapshot không khớp kết nối Minvoice.');
        }

        return [...$warnings, ...$this->issuancePolicy->warnings($document)];
    }

    private function money(mixed $value): string
    {
        return BigDecimal::of((string) $value)->toScale(2, RoundingMode::HalfUp)->__toString();
    }

    private function quantity(mixed $value): string
    {
        return rtrim(rtrim(
            BigDecimal::of((string) $value)->toScale(4, RoundingMode::HalfUp)->__toString(),
            '0',
        ), '.');
    }
}
