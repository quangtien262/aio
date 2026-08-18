<?php

namespace App\Support\AccountingTax\Providers;

use App\Models\ModuleInstallation;
use App\Support\AccountingTax\Providers\Exceptions\ProviderSafetyException;

class ProviderExecutionGuard
{
    public function assertConnectorEnabled(): void
    {
        if (! $this->connectorEnabled()) {
            throw new ProviderSafetyException('Module Minvoice Connector chưa được bật.');
        }
    }

    public function connectorEnabled(): bool
    {
        return ModuleInstallation::query()
            ->where('key', 'minvoice-connector')
            ->where('status', 'enabled')
            ->exists();
    }
}
