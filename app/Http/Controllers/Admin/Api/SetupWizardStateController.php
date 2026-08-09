<?php

namespace App\Http\Controllers\Admin\Api;

use App\Enums\TranslationStatus;
use App\Models\Admin;
use App\Models\ContentTranslation;
use App\Models\ModuleInstallation;
use App\Models\SiteProfile;
use App\Support\FrontendLocalization;
use App\Support\Localization\SiteProfileLocalization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SetupWizardStateController
{
    public function __invoke(Request $request, SiteProfileLocalization $siteProfileLocalization): JsonResponse
    {
        $siteProfile = SiteProfile::query()->first();
        $selectedLocale = FrontendLocalization::resolveEditableLocale(
            (string) $request->query('locale', FrontendLocalization::sourceLocale()),
        );
        $sourceLocale = FrontendLocalization::sourceLocale();
        $editableProfile = $siteProfile
            ? $siteProfileLocalization->localizeForEditor($siteProfile, $selectedLocale)
            : null;
        $allSteps = config('aio.setup_steps', []);
        $stepMeta = config('aio.setup_step_meta', []);
        $completedSteps = collect($siteProfile?->completed_steps ?? []);
        $websiteTypes = config('aio.website_types', []);
        $branding = $editableProfile?->branding ?? [];
        $themePalettes = $siteProfile?->theme_palettes ?? [];

        $signals = [
            'active_admins' => Admin::query()->where('is_active', true)->count(),
            'enabled_modules' => ModuleInstallation::query()->where('status', 'enabled')->count(),
            'installed_modules' => ModuleInstallation::query()->count(),
        ];

        $derivedCompletion = [
            'website_type' => filled($siteProfile?->site_name) && filled($siteProfile?->website_type),
            'theme' => filled($siteProfile?->active_theme_key),
            'branding' => filled($branding['company_name'] ?? null)
                || filled($branding['primary_color'] ?? null)
                || filled($branding['primary_color_deep'] ?? null)
                || filled($branding['accent_color'] ?? null)
                || filled($branding['accent_soft_color'] ?? null)
                || filled($branding['background_color'] ?? null)
                || filled($branding['surface_color'] ?? null)
                || filled($branding['surface_tint_color'] ?? null)
                || collect($themePalettes)->contains(fn (mixed $palette): bool => is_array($palette) && $palette !== []),
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
        $translationStatus = $editableProfile?->translation_status;
        $translationStatusValue = $translationStatus instanceof TranslationStatus
            ? $translationStatus->value
            : ((string) $translationStatus ?: TranslationStatus::Missing->value);
        $translationStatuses = ContentTranslation::query()
            ->where('website_key', (string) $siteProfile?->website_key)
            ->where('resource_type', 'site_profile')
            ->where('resource_id', (string) $siteProfile?->getKey())
            ->get(['locale', 'translation_status'])
            ->mapWithKeys(function (ContentTranslation $translation): array {
                $status = $translation->translation_status;

                return [
                    $translation->locale => $status instanceof TranslationStatus
                        ? $status->value
                        : (string) $status,
                ];
            })
            ->put($sourceLocale, TranslationStatus::Published->value)
            ->all();

        return response()->json([
            'data' => [
                'site_name' => $editableProfile?->site_name,
                'description' => $editableProfile?->description,
                'website_type' => $siteProfile?->website_type,
                'website_type_label' => $websiteTypes[$siteProfile?->website_type] ?? null,
                'website_type_options' => collect($websiteTypes)
                    ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                    ->values()
                    ->all(),
                'active_theme_key' => $siteProfile?->active_theme_key,
                'branding' => $branding,
                'selected_locale' => $selectedLocale,
                'source_locale' => $sourceLocale,
                'is_source_locale' => $selectedLocale === $sourceLocale,
                'translation_status' => $selectedLocale === $sourceLocale
                    ? 'published'
                    : $translationStatusValue,
                'translation_statuses' => $translationStatuses,
                'locale_options' => FrontendLocalization::localeOptions(),
                'theme_palettes' => $themePalettes,
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
