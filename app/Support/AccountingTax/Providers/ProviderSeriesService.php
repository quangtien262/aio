<?php

namespace App\Support\AccountingTax\Providers;

use App\Models\AcctProviderConnection;
use App\Models\AcctProviderSeries;
use Illuminate\Support\Facades\DB;

class ProviderSeriesService
{
    public function __construct(
        private readonly ProviderFactory $factory,
        private readonly ProviderExecutionGuard $guard,
    ) {}

    public function sync(AcctProviderConnection $connection, int $invoiceType = 1): array
    {
        $this->guard->assertConnectorEnabled();
        $payload = $this->factory->outbound($connection)->series($invoiceType);
        $rows = $this->extractRows($payload);

        return DB::transaction(function () use ($connection, $rows): array {
            $seen = [];

            foreach ($rows as $row) {
                $series = trim((string) ($row['khhdon'] ?? $row['value'] ?? $row['series'] ?? ''));

                if ($series === '') {
                    continue;
                }

                $seen[] = $series;
                AcctProviderSeries::query()->updateOrCreate(
                    ['connection_id' => $connection->id, 'series' => $series],
                    [
                        'provider_series_id' => $row['id'] ?? $row['value'] ?? null,
                        'invoice_form' => $row['invoiceForm'] ?? $row['invoice_form'] ?? null,
                        'invoice_year' => $row['invoiceYear'] ?? $row['invoice_year'] ?? null,
                        'invoice_type_name' => $row['invoiceTypeName'] ?? $row['invoice_type_name'] ?? null,
                        'is_active' => true,
                        'payload_snapshot' => $row,
                        'synced_at' => now(),
                    ],
                );
            }

            if ($seen !== []) {
                AcctProviderSeries::query()
                    ->where('connection_id', $connection->id)
                    ->whereNotIn('series', $seen)
                    ->update(['is_active' => false]);
            }

            $connection->forceFill(['last_used_at' => now()])->save();

            return AcctProviderSeries::query()
                ->where('connection_id', $connection->id)
                ->orderByDesc('is_default')
                ->orderBy('series')
                ->get()
                ->all();
        });
    }

    private function extractRows(array $payload): array
    {
        foreach (['data', 'result', 'items'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_is_list($payload[$key]) ? $payload[$key] : (array) ($payload[$key]['data'] ?? []);
            }
        }

        return array_is_list($payload) ? $payload : [];
    }
}
