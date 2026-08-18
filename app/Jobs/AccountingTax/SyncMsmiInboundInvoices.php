<?php

namespace App\Jobs\AccountingTax;

use App\Models\AcctProviderConnection;
use App\Support\AccountingTax\Providers\MsmiInboundSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncMsmiInboundInvoices implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $connectionId,
        public readonly array $filters,
    ) {}

    public function backoff(): array
    {
        return [60, 300, 1800];
    }

    public function uniqueId(): string
    {
        $filters = $this->filters;
        ksort($filters);

        return $this->connectionId.':'.hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR));
    }

    public function handle(MsmiInboundSyncService $service): void
    {
        $connection = AcctProviderConnection::query()->findOrFail($this->connectionId);
        $service->sync($connection, $this->filters);
    }
}
