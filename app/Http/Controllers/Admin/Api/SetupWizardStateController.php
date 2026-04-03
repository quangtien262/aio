<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\Admin;
use App\Models\ModuleInstallation;
use App\Models\SiteProfile;
use Illuminate\Http\JsonResponse;

class SetupWizardStateController
{
    public function __invoke(): JsonResponse
    {
        $siteProfile = SiteProfile::query()->first();
        $allSteps = config('aio.setup_steps', []);
        $stepMeta = config('aio.setup_step_meta', []);
        $completedSteps = collect($siteProfile?->completed_steps ?? []);
        $websiteTypes = config('aio.website_types', []);
        $branding = $siteProfile?->branding ?? [];

        $signals = [
            'active_admins' => Admin::query()->where('is_active', true)->count(),
            'enabled_modules' => ModuleInstallation::query()->where('status', 'enabled')->count(),
            'installed_modules' => ModuleInstallation::query()->count(),
        ];

        $derivedCompletion = [
            'website_type' => filled($siteProfile?->site_name) && filled($siteProfile?->website_type),
            'theme' => filled($siteProfile?->active_theme_key),
            'branding' => filled($branding['company_name'] ?? null) || filled($branding['primary_color'] ?? null),
            'modules' => $signals['enabled_modules'] > 0,
            'admin_account' => $signals['active_admins'] > 0,
            'finish' => (bool) $siteProfile?->is_setup_completed,
        ];

        $steps = [];
        $allPreviousStepsCompleted = true;

        foreach ($allSteps as $step) {
            $meta = $stepMeta[$step] ?? [];
            $isCompleted = (bool) (($derivedCompletion[$step] ?? false) || $completedSteps->contains($step));
            $canComplete = ! $isCompleted && $allPreviousStepsCompleted && ($meta['manual_completion'] ?? true);
            $isBlocked = ! $isCompleted && ! $allPreviousStepsCompleted;

            $steps[] = [
                'key' => $step,
                'label' => $meta['label'] ?? $step,
                'description' => $meta['description'] ?? null,
                'route' => $meta['route'] ?? '/setup',
                'manual_completion' => (bool) ($meta['manual_completion'] ?? true),
                'is_completed' => $isCompleted,
                'is_blocked' => $isBlocked,
                'can_complete' => $canComplete,
                'completion_source' => ($derivedCompletion[$step] ?? false) && ! $completedSteps->contains($step) ? 'derived' : 'state',
            ];

            $allPreviousStepsCompleted = $allPreviousStepsCompleted && $isCompleted;
        }

        $completedCount = collect($steps)->where('is_completed', true)->count();
        $nextStep = collect($steps)->first(fn (array $step): bool => ! $step['is_completed']);

        return response()->json([
            'data' => [
                'site_name' => $siteProfile?->site_name,
                'website_type' => $siteProfile?->website_type,
                'website_type_label' => $websiteTypes[$siteProfile?->website_type] ?? null,
                'website_type_options' => collect($websiteTypes)
                    ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                    ->values()
                    ->all(),
                'active_theme_key' => $siteProfile?->active_theme_key,
                'branding' => $branding,
                'is_setup_completed' => (bool) $siteProfile?->is_setup_completed,
                'setup_completed_at' => $siteProfile?->setup_completed_at?->toDateTimeString(),
                'summary' => [
                    'completed_steps' => $completedCount,
                    'total_steps' => count($steps),
                    'completion_percentage' => count($steps) > 0 ? (int) round(($completedCount / count($steps)) * 100) : 0,
                    'next_step_key' => $nextStep['key'] ?? null,
                    'next_step_label' => $nextStep['label'] ?? null,
                ],
                'signals' => $signals,
                'steps' => $steps,
            ],
        ]);
    }
}
