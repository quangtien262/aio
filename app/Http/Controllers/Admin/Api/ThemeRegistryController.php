<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Core\Themes\ThemeRegistry;
use App\Support\FrontendLocalization;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ThemeRegistryController
{
    public function __invoke(ThemeRegistry $themeRegistry, SiteContext $siteContext, ThemeDemoContentGenerator $demoGenerator): JsonResponse
    {
        $activeThemeKey = $siteContext->themeKey();

        return response()->json([
            'data' => $themeRegistry->all()->map(fn (array $theme): array => array_replace($theme, [
                'is_active' => $activeThemeKey !== null && strcasecmp($theme['key'], $activeThemeKey) === 0,
                'demo' => array_merge($theme['demo'] ?? [], ['presets' => $demoGenerator->presetsForTheme($theme['key'])]),
            ]))->all(),
            'meta' => [
                'website_key' => $siteContext->websiteKey(),
                'active_theme_key' => $activeThemeKey,
                'default_locale' => FrontendLocalization::defaultLocale(),
                'fallback_locale' => FrontendLocalization::fallbackLocale(),
                'source_locale' => FrontendLocalization::sourceLocale(),
                'locales' => FrontendLocalization::localeOptions(),
                'current_admin_id' => Auth::id(),
                'can_manage_theme_avatar' => Auth::id() === 1,
            ],
        ]);
    }
}
