<?php

namespace Modules\FnbPos\Jobs;

use App\Core\Modules\ModuleCapabilityChecker;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Schema;
use Modules\FnbPos\Services\FnbIntegrationService;

final class ReconcileFnbIntegration implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 120;

    public function __construct(public readonly int $mappingId)
    {
        $this->onQueue('fnb');
    }

    public function uniqueId(): string
    {
        return 'fnb-integration:'.$this->mappingId;
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(ModuleCapabilityChecker $capabilities, FnbIntegrationService $integrations): void
    {
        if (! $capabilities->enabled('fnb-pos') || ! Schema::hasTable('fnb_integration_mappings')) {
            return;
        }
        $integrations->runAutomatic($this->mappingId);
    }
}
