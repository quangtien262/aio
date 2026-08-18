<?php

namespace Modules\Catalog\Hooks;

use App\Core\Modules\Contracts\ModuleLifecycleHook;
use App\Core\Modules\Support\ModuleLifecycleContext;
use App\Models\SiteProfile;
use App\Support\SiteContext;

class CatalogLifecycleHook implements ModuleLifecycleHook
{
    public function preInstall(ModuleLifecycleContext $context): void {}

    public function postInstall(ModuleLifecycleContext $context): void
    {
        $this->updateBranding([
            'installed' => true,
            'currency' => config('catalog.currency', 'VND'),
            'version' => $context->module['latest_version'],
        ]);
    }

    public function preEnable(ModuleLifecycleContext $context): void {}

    public function postEnable(ModuleLifecycleContext $context): void
    {
        $this->updateBrandingState([
            'enabled' => true,
            'enabled_at' => now()->toIso8601String(),
        ]);
    }

    public function preDisable(ModuleLifecycleContext $context): void {}

    public function postDisable(ModuleLifecycleContext $context): void
    {
        $this->updateBrandingState([
            'enabled' => false,
            'disabled_at' => now()->toIso8601String(),
        ]);
    }

    public function preUpgrade(ModuleLifecycleContext $context): void {}

    public function postUpgrade(ModuleLifecycleContext $context): void
    {
        $this->updateBranding([
            'installed' => true,
            'currency' => config('catalog.currency', 'VND'),
            'version' => $context->module['latest_version'],
            'upgraded_from' => $context->fromVersion,
        ]);
    }

    public function preUninstall(ModuleLifecycleContext $context): void {}

    public function postUninstall(ModuleLifecycleContext $context): void
    {
        $profile = $this->defaultSiteProfile();

        if (! $profile) {
            return;
        }

        $branding = $profile->globalBranding();
        unset($branding['catalog']);

        $profile->forceFill([
            'branding' => $branding,
        ])->save();
    }

    private function updateBranding(array $catalogBranding): void
    {
        $profile = SiteProfile::query()
            ->withoutGlobalScope('current_website')
            ->firstOrCreate(
                ['website_key' => SiteContext::DEFAULT_WEBSITE_KEY],
                ['site_name' => 'AIO Website', 'completed_steps' => [], 'branding' => []],
            );

        $branding = $profile->globalBranding();
        $branding['catalog'] = $catalogBranding;

        $profile->forceFill([
            'branding' => $branding,
        ])->save();
    }

    private function defaultSiteProfile(): ?SiteProfile
    {
        return SiteProfile::query()
            ->withoutGlobalScope('current_website')
            ->where('website_key', SiteContext::DEFAULT_WEBSITE_KEY)
            ->first();
    }

    private function updateBrandingState(array $state): void
    {
        $profile = $this->defaultSiteProfile();

        if (! $profile) {
            return;
        }

        $branding = $profile->globalBranding();
        $branding['catalog'] = array_merge($branding['catalog'] ?? [], $state);

        $profile->forceFill([
            'branding' => $branding,
        ])->save();
    }
}
