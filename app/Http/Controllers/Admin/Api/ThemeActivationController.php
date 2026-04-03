<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Themes\ThemeRegistry;
use App\Models\SiteProfile;
use App\Models\ThemeInstallation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ThemeActivationController
{
    public function __invoke(string $key, ThemeRegistry $themeRegistry): JsonResponse
    {
        $theme = $this->resolveTheme($key, $themeRegistry);

        ThemeInstallation::query()->update([
            'is_active' => false,
        ]);

        $theme->forceFill([
            'status' => 'active',
            'is_active' => true,
            'installed_at' => $theme->installed_at ?? Carbon::now(),
            'activated_at' => Carbon::now(),
        ])->save();

        $siteProfile = SiteProfile::query()->firstOrNew();
        $completedSteps = collect($siteProfile->completed_steps ?? [])
            ->push('theme')
            ->unique()
            ->values()
            ->all();

        $siteProfile->forceFill([
            'site_name' => $siteProfile->site_name ?? 'AIO Website',
            'website_type' => $siteProfile->website_type ?? $theme->website_type,
            'active_theme_key' => $theme->key,
            'completed_steps' => $completedSteps,
        ])->save();

        return response()->json([
            'message' => 'Theme activated successfully.',
        ]);
    }

    private function resolveTheme(string $key, ThemeRegistry $themeRegistry): ThemeInstallation
    {
        $manifest = $themeRegistry->all()->firstWhere('key', $key);

        abort_if($manifest === null, 404, 'Theme not found.');

        return ThemeInstallation::query()->firstOrCreate(
            ['key' => $key],
            [
                'name' => $manifest['name'],
                'version' => $manifest['version'],
                'website_type' => $manifest['website_type'],
                'status' => 'installed',
                'is_active' => false,
                'blocks' => $manifest['blocks'] ?? [],
            ],
        );
    }
}
