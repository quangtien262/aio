<?php

namespace App\Support\AccountingTax;

use App\Models\AcctDocument;
use App\Models\AcctDocumentLine;
use App\Models\AcctOrganization;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class AccountingTaxReportService
{
    private const TAX_DOCUMENT_TYPES = ['tax_invoice', 'credit_note', 'debit_note'];

    private const VALID_LEGAL_STATUSES = ['accepted', 'validated', 'valid', 'issued'];

    /**
     * @return array<string, mixed>
     */
    public function build(
        int $organizationId,
        ?CarbonInterface $from,
        ?CarbonInterface $to,
        string $mode = 'operational',
    ): array {
        $documents = $this->query($organizationId, $from, $to, $mode)
            ->with('lines')
            ->orderBy('document_date')
            ->orderBy('id')
            ->get();
        $baseCurrency = (string) (AcctOrganization::query()
            ->whereKey($organizationId)
            ->value('default_currency') ?: 'VND');

        $summary = [
            'outbound_count' => 0,
            'outbound_total_exact' => '0.00',
            'outbound_tax_exact' => '0.00',
            'inbound_count' => 0,
            'inbound_total_exact' => '0.00',
            'inbound_tax_exact' => '0.00',
        ];
        $breakdown = [];

        foreach ($documents as $document) {
            $direction = $document->direction === 'inbound' ? 'inbound' : 'outbound';
            $sign = (string) ((int) ($document->effect_sign ?? 1) < 0 ? '-1' : '1');
            $exchangeRate = $this->decimal($document->exchange_rate ?? 1, 6);
            $baseTotal = $this->resolvedBaseAmount($document->base_grand_total ?? null, $document->grand_total, $exchangeRate);
            $baseTax = $this->resolvedBaseAmount($document->base_tax_total ?? null, $document->tax_total, $exchangeRate);

            $summary[$direction.'_count']++;
            $summary[$direction.'_total_exact'] = bcadd(
                $summary[$direction.'_total_exact'],
                bcmul($baseTotal, $sign, 2),
                2,
            );
            $summary[$direction.'_tax_exact'] = bcadd(
                $summary[$direction.'_tax_exact'],
                bcmul($baseTax, $sign, 2),
                2,
            );

            foreach ($document->lines as $line) {
                $this->addBreakdownLine($breakdown, $line, $direction, $sign, $exchangeRate);
            }
        }

        $summary['vat_payable_estimate_exact'] = bcsub(
            $summary['outbound_tax_exact'],
            $summary['inbound_tax_exact'],
            2,
        );

        foreach ([
            'outbound_total', 'outbound_tax', 'inbound_total', 'inbound_tax', 'vat_payable_estimate',
        ] as $key) {
            $summary[$key] = (float) $summary[$key.'_exact'];
        }

        return [
            'mode' => $mode,
            'filing_ready' => false,
            'filing_blockers' => $mode === 'tax'
                ? [
                    ...$this->filingBlockers($organizationId, $from, $to),
                    'Kỳ thuế phải được khóa và lưu snapshot trước khi dùng để kê khai.',
                ]
                : ['Báo cáo vận hành không phải báo cáo kê khai thuế.'],
            'base_currency' => $baseCurrency,
            'document_count' => $documents->count(),
            'summary' => $summary,
            'tax_breakdown' => array_values($breakdown),
        ];
    }

    /**
     * Return unresolved conditions that would make a locked filing snapshot
     * incomplete. Documents explicitly assessed as ineligible are reviewed and
     * intentionally excluded, so they are not blockers.
     *
     * @return list<string>
     */
    public function filingBlockers(
        int $organizationId,
        ?CarbonInterface $from,
        ?CarbonInterface $to,
    ): array {
        $base = $this->postedScope($organizationId, $from, $to)
            ->whereIn('document_type', self::TAX_DOCUMENT_TYPES);
        $blockers = [];

        $unassessedInbound = (clone $base)
            ->where('direction', 'inbound')
            ->whereNotIn('tax_eligibility', ['eligible', 'ineligible'])
            ->count();

        if ($unassessedInbound > 0) {
            $blockers[] = "Còn {$unassessedInbound} hóa đơn đầu vào chưa được đánh giá điều kiện thuế.";
        }

        $invalidEligibleInbound = (clone $base)
            ->where('direction', 'inbound')
            ->where('tax_eligibility', 'eligible')
            ->whereNotIn('legal_status', self::VALID_LEGAL_STATUSES)
            ->count();

        if ($invalidEligibleInbound > 0) {
            $blockers[] = "Có {$invalidEligibleInbound} hóa đơn đầu vào từng đủ điều kiện nhưng trạng thái pháp lý hiện không hợp lệ.";
        }

        $unverifiedOutbound = (clone $base)
            ->where('direction', 'outbound')
            ->whereNotIn('legal_status', self::VALID_LEGAL_STATUSES)
            ->count();

        if ($unverifiedOutbound > 0) {
            $blockers[] = "Còn {$unverifiedOutbound} hóa đơn đầu ra chưa có trạng thái pháp lý hợp lệ.";
        }

        return $blockers;
    }

    private function query(
        int $organizationId,
        ?CarbonInterface $from,
        ?CarbonInterface $to,
        string $mode,
    ): Builder {
        $query = $this->postedScope($organizationId, $from, $to);

        if ($mode !== 'tax') {
            return $query;
        }

        return $query->where(function (Builder $eligible): void {
            $eligible->where(function (Builder $outbound): void {
                $outbound
                    ->where('direction', 'outbound')
                    ->whereIn('document_type', self::TAX_DOCUMENT_TYPES)
                    ->whereIn('legal_status', self::VALID_LEGAL_STATUSES);
            })->orWhere(function (Builder $inbound): void {
                $inbound
                    ->where('direction', 'inbound')
                    ->whereIn('document_type', self::TAX_DOCUMENT_TYPES)
                    ->where('tax_eligibility', 'eligible')
                    ->whereIn('legal_status', self::VALID_LEGAL_STATUSES);
            });
        });
    }

    private function postedScope(
        int $organizationId,
        ?CarbonInterface $from,
        ?CarbonInterface $to,
    ): Builder {
        return AcctDocument::query()
            ->where('organization_id', $organizationId)
            ->where('workflow_status', 'posted')
            ->whereNull('voided_at')
            ->when($from, fn (Builder $query): Builder => $query->whereDate('document_date', '>=', $from))
            ->when($to, fn (Builder $query): Builder => $query->whereDate('document_date', '<=', $to));
    }

    /** @param array<string, array<string, mixed>> $breakdown */
    private function addBreakdownLine(
        array &$breakdown,
        AcctDocumentLine $line,
        string $direction,
        string $sign,
        string $exchangeRate,
    ): void {
        if ($line->line_type === 'note') {
            return;
        }

        $category = (string) ($line->tax_category ?: 'vat');
        $rate = $line->tax_rate === null ? 'none' : $this->decimal($line->tax_rate, 2);
        $key = $direction.'|'.$category.'|'.$rate;
        $taxBase = $this->resolvedBaseAmount(null, $line->tax_base ?? $line->line_subtotal ?? 0, $exchangeRate);
        $taxAmount = $this->resolvedBaseAmount(null, $line->tax_amount, $exchangeRate);

        $breakdown[$key] ??= [
            'direction' => $direction,
            'tax_category' => $category,
            'tax_rate' => $line->tax_rate === null ? null : (float) $rate,
            'tax_base_exact' => '0.00',
            'tax_amount_exact' => '0.00',
        ];
        $breakdown[$key]['tax_base_exact'] = bcadd(
            $breakdown[$key]['tax_base_exact'],
            bcmul($taxBase, $sign, 2),
            2,
        );
        $breakdown[$key]['tax_amount_exact'] = bcadd(
            $breakdown[$key]['tax_amount_exact'],
            bcmul($taxAmount, $sign, 2),
            2,
        );
        $breakdown[$key]['tax_base'] = (float) $breakdown[$key]['tax_base_exact'];
        $breakdown[$key]['tax_amount'] = (float) $breakdown[$key]['tax_amount_exact'];
    }

    private function resolvedBaseAmount(mixed $storedBase, mixed $amount, string $exchangeRate): string
    {
        if ($storedBase !== null) {
            return $this->decimal($storedBase, 2);
        }

        return bcmul($this->decimal($amount, 2), $exchangeRate, 2);
    }

    private function decimal(mixed $value, int $scale): string
    {
        $normalized = is_string($value) ? $value : sprintf('%.'.$scale.'F', (float) $value);

        return bcadd($normalized, '0', $scale);
    }
}
