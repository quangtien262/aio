<?php

namespace App\Support\AccountingTax\Providers;

use App\Models\AcctEinvoiceInbound;
use App\Models\AcctProviderConnection;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MsmiInboundSyncService
{
    public function __construct(
        private readonly ProviderFactory $factory,
        private readonly ProviderExecutionGuard $guard,
        private readonly ProviderResponseSanitizer $sanitizer,
    ) {}

    public function sync(AcctProviderConnection $connection, array $filters): array
    {
        $this->guard->assertConnectorEnabled();

        if ($connection->channel !== 'inbound') {
            throw ValidationException::withMessages(['connection_id' => ['Kết nối này không phải mSMI đầu vào.']]);
        }

        $from = CarbonImmutable::parse((string) $filters['date_from'])->startOfDay();
        $to = CarbonImmutable::parse((string) $filters['date_to'])->endOfDay();

        if ($to->isBefore($from) || $from->diffInDays($to) > 366) {
            throw ValidationException::withMessages([
                'date_to' => ['Khoảng đồng bộ phải hợp lệ và không vượt quá 366 ngày.'],
            ]);
        }

        $client = $this->factory->inbound($connection);
        $page = 1;
        $totalPages = 1;
        $synced = 0;
        $ids = [];

        do {
            $payload = $client->invoices($this->providerFilters($filters, $from, $to, $page));
            $rows = $this->extractRows($payload);
            $totalPages = max(1, min(1000, (int) ($payload['totalPage'] ?? $payload['totalPages'] ?? 1)));

            foreach ($rows as $row) {
                if (! is_array($row) || trim((string) ($row['_id'] ?? '')) === '') {
                    continue;
                }

                $invoice = $this->upsert($connection, $row);
                $ids[] = $invoice->id;
                $synced++;
            }

            $page++;
        } while ($page <= $totalPages);

        $connection->forceFill([
            'last_used_at' => now(),
            'healthy_at' => now(),
            'last_health_checked_at' => now(),
            'sandbox_verified_at' => $connection->environment === 'sandbox'
                ? ($connection->sandbox_verified_at ?? now())
                : $connection->sandbox_verified_at,
            'health_status' => 'healthy',
            'readiness_state' => $connection->environment === 'production'
                ? 'production_allowed'
                : 'healthy',
            'last_error' => null,
        ])->save();

        return [
            'connection_id' => $connection->id,
            'synced' => $synced,
            'pages' => $totalPages,
            'invoice_ids' => $ids,
        ];
    }

    private function providerFilters(
        array $filters,
        CarbonImmutable $from,
        CarbonImmutable $to,
        int $page,
    ): array {
        $result = [
            'page' => $page,
            'size' => min(200, max(1, (int) ($filters['size'] ?? 100))),
            'invoiceType' => 'INPUT_ELECTRONIC_INVOICE',
            'invoiceReleaseDateFrom' => $from->format('d/m/Y'),
            'invoiceReleaseDateTo' => $to->format('d/m/Y'),
        ];

        foreach ([
            'seller_tax_code' => 'sellerTaxNo',
            'seller_name' => 'sellerName',
            'series' => 'generalNotation',
            'invoice_number' => 'generalInvoiceNo',
        ] as $local => $provider) {
            if (($filters[$local] ?? null) !== null && trim((string) $filters[$local]) !== '') {
                $result[$provider] = trim((string) $filters[$local]);
            }
        }

        return $result;
    }

    private function extractRows(array $payload): array
    {
        foreach (['listInvoice', 'data', 'items'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_is_list($payload[$key]) ? $payload[$key] : [];
            }
        }

        return [];
    }

    private function upsert(AcctProviderConnection $connection, array $row): AcctEinvoiceInbound
    {
        return DB::transaction(function () use ($connection, $row): AcctEinvoiceInbound {
            $issuedAt = $this->dateTime($row['tdlap'] ?? null);
            $warnings = array_values(array_filter([
                ((string) ($row['bhphap'] ?? '0')) === '1' ? 'illegal_invoice' : null,
                filter_var($row['isHDTrung'] ?? false, FILTER_VALIDATE_BOOL) ? 'duplicate_invoice' : null,
                $this->buyerMismatch($connection, $row) ? 'buyer_tax_code_mismatch' : null,
            ]));

            $invoice = AcctEinvoiceInbound::query()->updateOrCreate(
                [
                    'connection_id' => $connection->id,
                    'provider_invoice_id' => (string) $row['_id'],
                ],
                [
                    'organization_id' => $connection->organization_id,
                    'provider' => 'minvoice',
                    'direction' => 'inbound',
                    'provider_tax_id' => $row['id'] ?? null,
                    'provider_type' => $row['type'] ?? null,
                    'seller_tax_code' => $row['nbmst'] ?? null,
                    'seller_name' => $row['nbten'] ?? null,
                    'seller_address' => $row['nbdchi'] ?? null,
                    'buyer_tax_code' => $row['nmmst'] ?? null,
                    'buyer_name' => $row['nmten'] ?? null,
                    'template_code' => $row['khmshdon'] ?? null,
                    'invoice_series' => $row['khhdon'] ?? null,
                    'invoice_number' => $row['shdon'] ?? null,
                    'invoice_code' => $row['mhdon'] ?? null,
                    'invoice_date' => $issuedAt?->toDateString(),
                    'issued_at' => $issuedAt,
                    'tax_authority_code_issued_at' => $this->dateTime($row['ncma'] ?? null),
                    'tax_authority_received_at' => $this->dateTime($row['ntnhan'] ?? null),
                    'provider_updated_at' => $this->dateTime($row['last_upDated_Date'] ?? null),
                    'currency' => strtoupper((string) ($row['dvtte'] ?? 'VND')),
                    'exchange_rate' => $this->decimal($row['tgia'] ?? 1),
                    'subtotal_ex_vat' => $this->decimal($row['tgtcthue'] ?? 0),
                    'total_amount' => $this->decimal($row['tgtttbso'] ?? 0),
                    'tax_amount' => $this->decimal($row['tgtthue'] ?? 0),
                    'non_taxable_amount' => $this->decimal($row['tgtkcthue'] ?? 0),
                    'discount_amount' => $this->decimal($row['ttcktmai'] ?? 0),
                    'fee_amount' => $this->decimal($row['tgtphi'] ?? 0),
                    'other_amount' => $this->decimal($row['tgtkhac'] ?? 0),
                    'provider_status' => $row['ttxly'] ?? null,
                    'invoice_status_code' => $row['tthai'] ?? null,
                    'processing_status_code' => $row['ttxly'] ?? null,
                    'illegal_status' => $row['bhphap'] ?? null,
                    'illegal_reason' => $row['bhpldo'] ?? null,
                    'duplicate_status' => is_scalar($row['isHDTrung'] ?? null) ? (string) $row['isHDTrung'] : null,
                    'warnings' => $warnings,
                    'vat_breakdown' => $this->sanitizer->sanitize((array) ($row['thttltsuat'] ?? [])),
                    'payload_snapshot' => $this->sanitizer->sanitize($row),
                    'sync_status' => 'synced',
                    'synced_at' => now(),
                ],
            );

            $invoice->lines()->delete();
            $invoice->vatBreakdowns()->delete();

            foreach ((array) ($row['hdhhdvu'] ?? []) as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }

                $invoice->lines()->create([
                    'provider_line_id' => $line['id'] ?? null,
                    'provider_header_id' => $line['idhdon'] ?? null,
                    'line_no' => (int) ($line['stt'] ?? $index + 1),
                    'item_name' => (string) ($line['ten'] ?? 'Không có tên'),
                    'unit' => $line['dvtinh'] ?? null,
                    'quantity' => $this->decimal($line['sluong'] ?? 0, 4),
                    'unit_price' => $this->decimal($line['dgia'] ?? 0),
                    'subtotal_ex_vat' => $this->decimal($line['thtien'] ?? 0),
                    'vat_rate' => isset($line['tsuat']) ? (string) $line['tsuat'] : null,
                    'vat_amount' => $this->decimal($line['tthue'] ?? 0),
                    'discount_amount' => $this->decimal($line['stckhau'] ?? 0),
                    'discount_rate' => $this->decimal($line['tlckhau'] ?? 0, 4),
                    'line_type' => isset($line['tchat']) ? (string) $line['tchat'] : null,
                    'payload_snapshot' => $this->sanitizer->sanitize($line),
                ]);
            }

            foreach ((array) ($row['thttltsuat'] ?? []) as $vat) {
                if (! is_array($vat)) {
                    continue;
                }

                $invoice->vatBreakdowns()->create([
                    'vat_rate' => isset($vat['tsuat']) ? (string) $vat['tsuat'] : (isset($vat['gttsuat']) ? (string) $vat['gttsuat'] : null),
                    'taxable_amount' => $this->decimal($vat['thtien'] ?? 0),
                    'vat_amount' => $this->decimal($vat['tthue'] ?? 0),
                    'payload_snapshot' => $this->sanitizer->sanitize($vat),
                ]);
            }

            return $invoice->fresh(['lines', 'vatBreakdowns']);
        });
    }

    private function buyerMismatch(AcctProviderConnection $connection, array $row): bool
    {
        $organizationTaxCode = preg_replace('/\D/', '', (string) $connection->organization?->tax_code);
        $buyerTaxCode = preg_replace('/\D/', '', (string) ($row['nmmst'] ?? ''));

        return $organizationTaxCode !== '' && $buyerTaxCode !== '' && $organizationTaxCode !== $buyerTaxCode;
    }

    private function dateTime(mixed $value): ?CarbonImmutable
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function decimal(mixed $value, int $scale = 2): string
    {
        $normalized = is_string($value) ? str_replace(',', '', $value) : $value;

        if (! is_scalar($normalized) || ! is_numeric((string) $normalized)) {
            return BigDecimal::zero()->toScale($scale)->__toString();
        }

        return BigDecimal::of((string) $normalized)
            ->toScale($scale, RoundingMode::HalfUp)
            ->__toString();
    }
}
