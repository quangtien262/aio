<?php

namespace App\Jobs\AccountingTax;

use App\Models\AcctEinvoiceTransmission;
use App\Support\AccountingTax\Providers\ProviderExecutionGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchPendingEinvoiceTransmissions implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(ProviderExecutionGuard $guard): void
    {
        if (! $guard->connectorEnabled() || ! config('accounting_einvoice.network_enabled', false)) {
            return;
        }

        AcctEinvoiceTransmission::query()
            ->whereIn('status', ['queued', 'failed'])
            ->where('attempt_count', '<', 10)
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')
            ->limit(200)
            ->pluck('id')
            ->each(fn (int $id) => ProcessEinvoiceTransmission::dispatch($id)->afterCommit());
    }
}
