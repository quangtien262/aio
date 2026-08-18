<?php

namespace App\Jobs\AccountingTax;

use App\Models\AcctProviderConnection;
use App\Support\AccountingTax\Providers\ProviderExecutionGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScheduleMsmiInboundSyncs implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(ProviderExecutionGuard $guard): void
    {
        if (! $guard->connectorEnabled() || ! config('accounting_einvoice.network_enabled', false)) {
            return;
        }

        AcctProviderConnection::query()
            ->where('provider', 'minvoice')
            ->where('channel', 'inbound')
            ->where('is_enabled', true)
            ->where('kill_switch', false)
            ->orderBy('id')
            ->each(function (AcctProviderConnection $connection): void {
                if (! data_get($connection->settings, 'scheduled_sync_enabled', false)) {
                    return;
                }

                if ($connection->environment === 'production'
                    && (! config('accounting_einvoice.production_enabled', false)
                        || $connection->production_allowed_at === null)) {
                    return;
                }

                if ($connection->environment === 'sandbox' && $connection->sandbox_verified_at === null) {
                    return;
                }

                $lookbackDays = min(90, max(1, (int) data_get($connection->settings, 'sync_lookback_days', 7)));
                $today = now()->timezone((string) config(
                    'accounting_einvoice.legal_timezone',
                    'Asia/Ho_Chi_Minh',
                ));
                SyncMsmiInboundInvoices::dispatch($connection->id, [
                    'date_from' => $today->copy()->subDays($lookbackDays)->toDateString(),
                    'date_to' => $today->toDateString(),
                    'size' => 200,
                ])->afterCommit();
            });
    }
}
