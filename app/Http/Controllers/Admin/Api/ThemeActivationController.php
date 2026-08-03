<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Core\Themes\ThemeRegistry;
use App\Models\ModuleInstallation;
use App\Models\Site;
use App\Models\SiteProfile;
use App\Models\ThemeInstallation;
use App\Support\AuditLogger;
use App\Support\SiteContext;
use App\Support\ThemeBrandingResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ThemeActivationController
{
    public function __invoke(Request $request, string $key, ThemeRegistry $themeRegistry, ThemeDemoContentGenerator $demoGenerator, AuditLogger $auditLogger, SiteContext $siteContext, ThemeBrandingResolver $brandingResolver): JsonResponse
    {
        $validated = $request->validate([
            'create_demo_data' => ['sometimes', 'boolean'],
        ]);
        [$theme, $manifest] = $this->resolveTheme($key, $themeRegistry);
        $this->assertRequiredModulesEnabled($manifest);
        $websiteKey = $siteContext->websiteKey();

        $siteProfile = DB::transaction(function () use ($manifest, $theme, $websiteKey): SiteProfile {
            $siteProfile = SiteProfile::query()
                ->withoutGlobalScopes()
                ->firstOrNew(['website_key' => $websiteKey]);
            $existingWebsiteType = trim((string) $siteProfile->website_type);

            if (
                $siteProfile->exists
                && $siteProfile->is_setup_completed
                && $existingWebsiteType !== ''
                && $existingWebsiteType !== $theme->website_type
            ) {
                throw ValidationException::withMessages([
                    'theme' => "Theme {$theme->key} chỉ hỗ trợ website loại {$theme->website_type}.",
                ]);
            }

            $theme->forceFill([
                'name' => $manifest['name'],
                'version' => $manifest['version'],
                'website_type' => $manifest['website_type'],
                'blocks' => $manifest['blocks'] ?? [],
                'status' => 'active',
                'installed_at' => $theme->installed_at ?? Carbon::now(),
                'activated_at' => Carbon::now(),
            ])->save();

            $completedSteps = collect($siteProfile->completed_steps ?? [])
                ->push('theme')
                ->unique()
                ->values()
                ->all();

            $siteProfile->forceFill([
                'site_name' => $siteProfile->site_name ?? 'AIO Website',
                'website_type' => $theme->website_type,
                'active_theme_key' => $theme->key,
                'completed_steps' => $completedSteps,
            ])->save();

            Site::query()
                ->where('website_key', $websiteKey)
                ->update(['theme_key' => $theme->key]);

            $activeThemeKeys = SiteProfile::query()
                ->withoutGlobalScopes()
                ->whereNotNull('active_theme_key')
                ->pluck('active_theme_key')
                ->filter()
                ->unique()
                ->values()
                ->all();
            ThemeInstallation::query()->whereIn('key', $activeThemeKeys)->update(['is_active' => true]);
            ThemeInstallation::query()->whereNotIn('key', $activeThemeKeys)->update(['is_active' => false]);

            return $siteProfile->fresh();
        });

        $brandingResolver->ensure(
            $siteContext->websiteKey(),
            $theme->key,
            $siteProfile->globalBranding(),
        );

        $demo = null;
        if (($validated['create_demo_data'] ?? false) === true && ($preset = $demoGenerator->defaultPresetForTheme($theme->key))) {
            $demo = $demoGenerator->generate($theme->key, $preset);
        }

        $auditLogger->record('theme.activated', $theme, null, ['theme_key' => $theme->key, 'create_demo_data' => (bool) ($validated['create_demo_data'] ?? false)], moduleKey: 'theme');

        return response()->json([
            'message' => $demo ? 'Đã kích hoạt theme và tạo dữ liệu mẫu.' : 'Đã kích hoạt theme.',
            'data' => ['demo' => $demo],
        ]);
    }

    /** @return array{0:ThemeInstallation,1:array<string,mixed>} */
    private function resolveTheme(string $key, ThemeRegistry $themeRegistry): array
    {
        $manifest = $themeRegistry->all()->first(
            fn (array $theme): bool => strcasecmp((string) ($theme['key'] ?? ''), trim($key)) === 0,
        );

        abort_if($manifest === null, 404, 'Theme not found.');

        $theme = ThemeInstallation::query()->firstOrCreate(
            ['key' => $manifest['key']],
            [
                'name' => $manifest['name'],
                'version' => $manifest['version'],
                'website_type' => $manifest['website_type'],
                'status' => 'installed',
                'is_active' => false,
                'blocks' => $manifest['blocks'] ?? [],
            ],
        );

        return [$theme, $manifest];
    }

    /** @param array<string, mixed> $manifest */
    private function assertRequiredModulesEnabled(array $manifest): void
    {
        $requiredModules = collect($manifest['requires_modules'] ?? [])
            ->filter(fn (mixed $key): bool => is_string($key) && trim($key) !== '')
            ->values();
        $enabledModules = ModuleInstallation::query()
            ->whereIn('key', $requiredModules)
            ->where('status', 'enabled')
            ->pluck('key');
        $missingModules = $requiredModules->diff($enabledModules)->values();

        if ($missingModules->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages([
            'theme' => 'Cần bật module trước khi kích hoạt theme: '.$missingModules->implode(', ').'.',
        ]);
    }
}
