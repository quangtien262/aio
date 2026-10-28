<?php

namespace Modules\Pharmacy\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Pharmacy\Services\Integrations\PharmacyIntegrationService;

final class SendPharmacyIntegration implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public int $tries = 1;

    public function __construct(public readonly string $attemptId) {}

    public function handle(): void
    {
        app(PharmacyIntegrationService::class)->process($this->attemptId);
    }
}
