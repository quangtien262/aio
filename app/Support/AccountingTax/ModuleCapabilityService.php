<?php

namespace App\Support\AccountingTax;

use App\Core\Modules\ModuleRegistry;
use App\Models\AcctProviderConnection;
use Illuminate\Support\Facades\Schema;

class ModuleCapabilityService
{
    /** @var array<string, array<string, mixed>|null> */
    private array $modules = [];

    public function __construct(private readonly ModuleRegistry $registry) {}

    public function moduleEnabled(string $moduleKey): bool
    {
        return ($this->module($moduleKey)['is_enabled'] ?? false) === true;
    }

    public function has(string $moduleKey, string $capability): bool
    {
        $module = $this->module($moduleKey);

        return ($module['is_enabled'] ?? false) === true
            && array_key_exists($capability, $module['provides'] ?? []);
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function accountingIntegrations(?int $organizationId = null): array
    {
        $outbound = $this->providerReadiness('outbound', $organizationId);
        $inbound = $this->providerReadiness('inbound', $organizationId);

        return [
            'catalog.items.read.v1' => [
                'enabled' => $this->has('catalog', 'catalog.items.read.v1'),
                'available' => $this->module('catalog') !== null,
            ],
            'cms.services.read.v1' => [
                'enabled' => $this->has('cms', 'cms.services.read.v1'),
                'available' => $this->module('cms') !== null,
            ],
            'inventory.stock.read.v1' => [
                'enabled' => $this->has('inventory', 'inventory.stock.read.v1'),
                'available' => $this->module('inventory') !== null,
            ],
            'inventory.documents.write.v1' => [
                'enabled' => $this->has('inventory', 'inventory.documents.write.v1'),
                'available' => $this->module('inventory') !== null,
            ],
            'einvoice.minvoice.outbound.v1' => [
                'enabled' => $this->has('minvoice-connector', 'einvoice.minvoice.outbound.v1'),
                'available' => $this->module('minvoice-connector') !== null,
                ...$outbound,
            ],
            'einvoice.minvoice.inbound.v1' => [
                'enabled' => $this->has('minvoice-connector', 'einvoice.minvoice.inbound.v1'),
                'available' => $this->module('minvoice-connector') !== null,
                ...$inbound,
            ],
        ];
    }

    /**
     * Module lifecycle and provider runtime readiness are deliberately separate.
     * An enabled connector without a verified connection must never be presented
     * as ready to send or receive legal documents.
     *
     * @return array{installed: bool, configured: bool, healthy: bool, production_allowed: bool, ready: bool}
     */
    private function providerReadiness(string $channel, ?int $organizationId): array
    {
        $module = $this->module('minvoice-connector');
        $installed = in_array($module['status'] ?? 'available', ['installed', 'enabled', 'disabled'], true);
        $enabled = ($module['is_enabled'] ?? false) === true;

        if (! $enabled || ! Schema::hasTable('acct_provider_connections')) {
            return [
                'installed' => $installed,
                'configured' => false,
                'healthy' => false,
                'production_allowed' => false,
                'ready' => false,
            ];
        }

        $query = AcctProviderConnection::query()
            ->where('provider', 'minvoice')
            ->where('channel', $channel)
            ->where('is_enabled', true)
            ->where('kill_switch', false);

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        $connections = $query->get();
        $configured = $connections->contains(fn (AcctProviderConnection $connection): bool => $connection->configured_at !== null);
        $networkEnabled = (bool) config('accounting_einvoice.network_enabled', false);
        $productionNetworkEnabled = (bool) config('accounting_einvoice.production_enabled', false);
        $healthCutoff = now()->subHours(max(1, (int) config('accounting_einvoice.health_max_age_hours', 24)));
        $healthy = $networkEnabled && $connections->contains(fn (AcctProviderConnection $connection): bool => $connection->healthy_at !== null
            && $connection->last_health_checked_at?->isAfter($healthCutoff)
            && $connection->health_status === 'healthy');
        $productionAllowed = $networkEnabled && $productionNetworkEnabled
            && $connections->contains(fn (AcctProviderConnection $connection): bool => $connection->environment === 'production'
            && $connection->production_allowed_at !== null
            && $connection->healthy_at !== null
            && $connection->last_health_checked_at?->isAfter($healthCutoff)
            && $connection->health_status === 'healthy');
        $sandboxReady = $networkEnabled && $connections->contains(fn (AcctProviderConnection $connection): bool => $connection->environment === 'sandbox'
            && $connection->sandbox_verified_at !== null
            && $connection->healthy_at !== null
            && $connection->last_health_checked_at?->isAfter($healthCutoff)
            && $connection->health_status === 'healthy');

        return [
            'installed' => $installed,
            'configured' => $configured,
            'healthy' => $healthy,
            'production_allowed' => $productionAllowed,
            'ready' => $sandboxReady || $productionAllowed,
        ];
    }

    private function module(string $moduleKey): ?array
    {
        if (! array_key_exists($moduleKey, $this->modules)) {
            $this->modules[$moduleKey] = $this->registry->find($moduleKey);
        }

        return $this->modules[$moduleKey];
    }
}
