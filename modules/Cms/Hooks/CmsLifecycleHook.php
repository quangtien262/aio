<?php

namespace Modules\Cms\Hooks;

use App\Core\Modules\Contracts\ModuleLifecycleHook;
use App\Core\Modules\Support\ModuleLifecycleContext;
use App\Models\SiteProfile;

class CmsLifecycleHook implements ModuleLifecycleHook
{
    public function preInstall(ModuleLifecycleContext $context): void
    {
    }

    public function postInstall(ModuleLifecycleContext $context): void
    {
        $profile = SiteProfile::query()->firstOrCreate(
            ['site_name' => 'AIO Website'],
            ['completed_steps' => [], 'branding' => []],
        );

        $branding = $profile->branding ?? [];
        $branding['cms'] = [
            'hooks' => [
                'installed' => true,
                'version' => $context->module['latest_version'],
            ],
        ];

        $completedSteps = collect($profile->completed_steps ?? [])->push('modules')->unique()->values()->all();

        $profile->forceFill([
            'branding' => $branding,
            'completed_steps' => $completedSteps,
        ])->save();
    }

    public function preEnable(ModuleLifecycleContext $context): void
    {
    }

    public function postEnable(ModuleLifecycleContext $context): void
    {
        $this->updateHookState([
            'enabled' => true,
            'enabled_at' => now()->toIso8601String(),
        ]);
    }

    public function preDisable(ModuleLifecycleContext $context): void
    {
    }

    public function postDisable(ModuleLifecycleContext $context): void
    {
        $this->updateHookState([
            'enabled' => false,
            'disabled_at' => now()->toIso8601String(),
        ]);
    }

    public function preUpgrade(ModuleLifecycleContext $context): void
    {
    }

    public function postUpgrade(ModuleLifecycleContext $context): void
    {
        $profile = SiteProfile::query()->first();

        if (! $profile) {
            return;
        }

        $branding = $profile->branding ?? [];
        $branding['cms']['hooks']['version'] = $context->module['latest_version'];
        $branding['cms']['hooks']['upgraded_from'] = $context->fromVersion;

        $profile->forceFill([
            'branding' => $branding,
        ])->save();
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
        unset($branding['cms']);

        $profile->forceFill([
            'branding' => $branding,
        ])->save();
    }

    private function updateHookState(array $state): void
    {
        $profile = SiteProfile::query()->first();

        if (! $profile) {
            return;
        }

        $branding = $profile->branding ?? [];
        $branding['cms']['hooks'] = array_merge($branding['cms']['hooks'] ?? [], $state);

        $profile->forceFill([
            'branding' => $branding,
        ])->save();
    }
}
