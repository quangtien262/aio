<?php

namespace Modules\Catalog\Hooks;

use App\Core\Modules\Contracts\ModuleLifecycleHook;
use App\Core\Modules\Support\ModuleLifecycleContext;
use App\Models\SiteProfile;

class CatalogLifecycleHook implements ModuleLifecycleHook
{
    public function preInstall(ModuleLifecycleContext $context): void
    {
    }

    public function postInstall(ModuleLifecycleContext $context): void
    {
        $this->updateBranding([
            'installed' => true,
            'currency' => config('catalog.currency', 'VND'),
            'version' => $context->module['latest_version'],
        ]);
    }

    public function preEnable(ModuleLifecycleContext $context): void
    {
    }

    public function postEnable(ModuleLifecycleContext $context): void
    {
        $this->updateBrandingState([
            'enabled' => true,
            'enabled_at' => now()->toIso8601String(),
        ]);
    }

    public function preDisable(ModuleLifecycleContext $context): void
    {
    }

    public function postDisable(ModuleLifecycleContext $context): void
    {
        $this->updateBrandingState([
            'enabled' => false,
            'disabled_at' => now()->toIso8601String(),
        ]);
    }

    public function preUpgrade(ModuleLifecycleContext $context): void
    {
    }

    public function postUpgrade(ModuleLifecycleContext $context): void
    {
        $this->updateBranding([
            'installed' => true,
            'currency' => config('catalog.currency', 'VND'),
            'version' => $context->module['latest_version'],
            'upgraded_from' => $context->fromVersion,
        ]);
    }

    public function preUninstall(ModuleLifecycleContext $context): void
    {
    }

    public function postUninstall(ModuleLifecycleContext $context): void
    {
        $profile = SiteProfile::query()->first();

        if (! $profile) {
            return;
        }

        $branding = $profile->branding ?? [];
        unset($branding['catalog']);

        $profile->forceFill([
            'branding' => $branding,
        ])->save();
    }

    private function updateBranding(array $catalogBranding): void
    {
        $profile = SiteProfile::query()->firstOrCreate(
            ['site_name' => 'AIO Website'],
            ['completed_steps' => [], 'branding' => []],
        );

        $branding = $profile->branding ?? [];
        $branding['catalog'] = $catalogBranding;

        $profile->forceFill([
            'branding' => $branding,
        ])->save();
    }

    private function updateBrandingState(array $state): void
    {
        $profile = SiteProfile::query()->first();

        if (! $profile) {
            return;
        }

        $branding = $profile->branding ?? [];
        $branding['catalog'] = array_merge($branding['catalog'] ?? [], $state);

        $profile->forceFill([
            'branding' => $branding,
        ])->save();
    }
}
