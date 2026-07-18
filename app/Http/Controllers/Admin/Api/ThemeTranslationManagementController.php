<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Themes\ThemeRegistry;
use App\Core\Themes\ThemeTranslationService;
use App\Models\SiteProfile;
use App\Support\BusinessContentTranslationService;
use App\Support\FrontendLocalization;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ThemeTranslationManagementController
{
    public function update(string $key, string $locale, Request $request, ThemeRegistry $themeRegistry, ThemeTranslationService $themeTranslationService, BusinessContentTranslationService $businessContentTranslationService): JsonResponse
    {
        abort_unless($themeRegistry->all()->contains(fn (array $theme): bool => $theme['key'] === $key), 404);

        $payload = $request->validate([
            'entries' => ['required', 'array'],
            'entries.*.key' => ['required', 'string', 'max:190'],
            'entries.*.value' => ['nullable', 'string'],
            'locale' => ['nullable', 'string', 'regex:/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})?$/'],
            'group' => ['nullable', 'string', Rule::in(['static', 'content'])],
        ]);

        $resolvedLocale = FrontendLocalization::resolveEditableLocale($payload['locale'] ?? $locale);
        $group = (string) ($payload['group'] ?? 'static');

        if ($group === 'content') {
            $businessContentTranslationService->saveOverrides($this->resolveWebsiteKey(), $resolvedLocale, $payload['entries'], $key);
        } else {
            $themeTranslationService->saveOverrides($key, $resolvedLocale, $payload['entries']);
        }

        return response()->json([
            'message' => $group === 'content'
                ? 'Đã cập nhật bản dịch nội dung storefront.'
                : 'Đã cập nhật bản dịch frontend cho theme.',
            'data' => [
                'theme_key' => $key,
                'locale' => $resolvedLocale,
                'group' => $group,
                'supported_locales' => FrontendLocalization::supportedLocales(),
            ],
        ]);
    }

    private function resolveWebsiteKey(): string
    {
        $branding = SiteProfile::query()->value('branding');
        $decoded = is_array($branding) ? $branding : json_decode((string) $branding, true);

        return (string) data_get($decoded, 'website_key', app(SiteContext::class)->websiteKey());
    }
}
