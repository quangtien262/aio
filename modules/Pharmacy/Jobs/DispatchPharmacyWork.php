<?php

namespace Modules\Pharmacy\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Pharmacy\Services\Integrations\PharmacyIntegrationService;
use Modules\Pharmacy\Services\Integrations\PharmacyOutboxService;
use Modules\Pharmacy\Services\Integrations\PharmacyRecoveryService;

final class DispatchPharmacyWork implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 1;

    public function handle(): void
    {
        $deadline = microtime(true) + 40;
        app(PharmacyOutboxService::class)->dispatchDue(25, $deadline);
        app(PharmacyRecoveryService::class)->recoverExpiring(25, $deadline);
        app(PharmacyIntegrationService::class)->dispatchDue(25, $deadline);
    }
}
