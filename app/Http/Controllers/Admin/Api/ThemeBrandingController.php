<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Themes\ThemeRegistry;
use App\Models\SiteProfile;
use App\Support\SiteContext;
use App\Support\ThemeBrandingResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ThemeBrandingController
{
    public function show(
        string $key,
        ThemeRegistry $themeRegistry,
        SiteContext $siteContext,
        ThemeBrandingResolver $resolver,
    ): JsonResponse {
        $themeKey = $this->validateTheme($key, $themeRegistry);
        $siteProfile = SiteProfile::query()->first();

        return response()->json([
            'data' => [
                'website_key' => $siteContext->websiteKey(),
                'theme_key' => $themeKey,
                'branding' => $resolver->resolve(
                    $siteContext->websiteKey(),
                    $themeKey,
                    $siteProfile?->globalBranding() ?? [],
                ),
            ],
        ]);
    }

    public function update(
        Request $request,
        string $key,
        ThemeRegistry $themeRegistry,
        SiteContext $siteContext,
        ThemeBrandingResolver $resolver,
    ): JsonResponse {
        $themeKey = $this->validateTheme($key, $themeRegistry);
        $validated = $request->validate([
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'slogan' => ['sometimes', 'nullable', 'string', 'max:255'],
            'logo_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'favicon_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'support_hotline' => ['sometimes', 'nullable', 'string', 'max:120'],
            'support_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'support_location' => ['sometimes', 'nullable', 'string', 'max:500'],
            'copyright_text' => ['sometimes', 'nullable', 'string', 'max:500'],
            'boc_status' => ['sometimes', 'nullable', 'string', Rule::in(['notified', 'not_notified', 'pending'])],
            'boc_confirmation_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'boc_footer_note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);
        $siteProfile = SiteProfile::query()->first();
        $profile = $resolver->update(
            $siteContext->websiteKey(),
            $themeKey,
            $validated,
            $siteProfile?->globalBranding() ?? [],
        );

        return response()->json([
            'message' => 'Đã lưu thông tin riêng của theme.',
            'data' => [
                'website_key' => $profile->website_key,
                'theme_key' => $profile->theme_key,
                'branding' => $profile->branding,
            ],
        ]);
    }

    private function validateTheme(string $key, ThemeRegistry $themeRegistry): string
    {
        $theme = $themeRegistry->all()->first(
            fn (array $theme): bool => strcasecmp((string) ($theme['key'] ?? ''), trim($key)) === 0,
        );
        abort_unless($theme !== null, 404, 'Theme not found.');

        return strtoupper((string) $theme['key']);
    }
}
