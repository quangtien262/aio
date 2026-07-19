<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Modules\ModuleRegistry;
use App\Models\Site;
use App\Models\SiteProfile;
use App\Support\SiteContext;
use App\Support\FrontendLocalization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminCurrentProfileController
{
    public function __invoke(Request $request, ModuleRegistry $moduleRegistry): JsonResponse
    {
        $admin = $request->user('admin');
        $permissions = $admin?->permissions() ?? [];
        $siteProfile = SiteProfile::query()->first();
        $siteContext = app(SiteContext::class);
        $siteOptions = Schema::hasTable('sites')
            ? Site::query()
                ->orderByRaw('domain is null')
                ->orderBy('domain')
                ->orderBy('website_key')
                ->get(['id', 'domain', 'website_key', 'theme_key', 'name', 'status'])
                ->map(fn (Site $site): array => [
                    'id' => $site->id,
                    'label' => $site->name ?: ($site->domain ?: $site->website_key),
                    'domain' => $site->domain,
                    'website_key' => $site->website_key,
                    'theme_key' => $site->theme_key,
                    'status' => $site->status,
                ])
                ->values()
                ->all()
            : [];

        return response()->json([
            'data' => [
                'id' => $admin?->id,
                'name' => $admin?->name,
                'email' => $admin?->email,
                'is_active' => (bool) $admin?->is_active,
                'is_locked' => $admin?->isLocked() ?? false,
                'locked_reason' => $admin?->locked_reason,
                'permissions' => $permissions,
                'scopes' => $admin?->scopeMatrix() ?? [],
                'site_profile' => [
                    'site_name' => $siteProfile?->site_name,
                    'branding' => $siteProfile?->branding ?? [],
                ],
                'current_website' => [
                    'website_key' => $siteContext->websiteKey(),
                    'site_id' => $siteContext->site()?->id,
                    'theme_key' => $siteContext->themeKey(),
                ],
                'site_options' => $siteOptions,
                'frontend_localization' => [
                    'default_locale' => FrontendLocalization::defaultLocale(),
                    'fallback_locale' => FrontendLocalization::fallbackLocale(),
                    'source_locale' => FrontendLocalization::sourceLocale(),
                    'active_locales' => FrontendLocalization::supportedLocales(),
                    'locales' => FrontendLocalization::localeOptions(),
                ],
                'module_navigation' => $moduleRegistry->navigationForPermissions($permissions),
            ],
        ]);
    }
}
