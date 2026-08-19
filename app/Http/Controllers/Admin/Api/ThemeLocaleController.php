<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Themes\ThemeRegistry;
use App\Models\SystemLocale;
use App\Rules\ValidLocaleCode;
use App\Support\AuditLogger;
use App\Support\FrontendLocalization;
use App\Support\Localization\LocaleCode;
use App\Support\Localization\LocaleContext;
use App\Support\Localization\LocalizationReleaseReadiness;
use App\Support\Localization\WebsiteLocaleManager;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ThemeLocaleController
{
    public function __construct(
        private readonly LocaleContext $localeContext,
        private readonly WebsiteLocaleManager $localeManager,
        private readonly SiteContext $siteContext,
        private readonly AuditLogger $auditLogger,
        private readonly LocalizationReleaseReadiness $releaseReadiness,
    ) {}

    public function index(Request $request, ThemeRegistry $themeRegistry): JsonResponse
    {
        $validated = $request->validate([
            'theme_key' => ['nullable', 'string', 'max:120'],
        ]);
        $theme = $this->resolveTheme($themeRegistry, $validated['theme_key'] ?? null);

        return response()->json(['data' => $this->responsePayload($theme)]);
    }

    public function preflight(string $code, Request $request, ThemeRegistry $themeRegistry): JsonResponse
    {
        $validated = $request->validate([
            'theme_key' => ['nullable', 'string', 'max:120'],
        ]);
        $theme = $this->resolveTheme($themeRegistry, $validated['theme_key'] ?? null);
        $websiteKey = $this->siteContext->websiteKey();
        $locale = LocaleCode::tryNormalize($code);

        abort_if($locale === null, 404);
        $option = collect($this->localeContext->options($websiteKey))->firstWhere('code', $locale);
        abort_if($option === null, 404);

        $readiness = $this->releaseReadiness->report($websiteKey, [$locale])[$locale] ?? [
            'ready' => true,
            'strict_ready' => true,
            'publishable' => true,
            'required' => 0,
            'translated' => 0,
            'pending' => 0,
            'coverage' => 100.0,
            'critical' => ['ready' => true, 'required' => 0, 'translated' => 0, 'pending' => 0, 'coverage' => 100.0, 'scopes' => []],
            'extended' => ['ready' => true, 'required' => 0, 'translated' => 0, 'pending' => 0, 'coverage' => 100.0, 'scopes' => []],
        ];

        return response()->json(['data' => [
            'website_key' => $websiteKey,
            'locale' => $this->localeItemPayload($option, $theme, $readiness),
            'checked_at' => now()->toIso8601String(),
        ]]);
    }

    public function store(Request $request, ThemeRegistry $themeRegistry): JsonResponse
    {
        $payload = $request->validate([
            'theme_key' => ['nullable', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:35', new ValidLocaleCode],
            'name' => ['nullable', 'string', 'max:120'],
            'native_name' => ['nullable', 'string', 'max:120'],
            'is_published' => ['nullable', 'boolean'],
            'fallback_locale' => ['nullable', 'string', 'max:35', new ValidLocaleCode],
        ]);
        $theme = $this->resolveTheme($themeRegistry, $payload['theme_key'] ?? null);
        $websiteKey = $this->siteContext->websiteKey();
        $code = LocaleCode::normalize((string) $payload['code']);
        $this->localeManager->provisionWebsite($websiteKey);

        if (collect($this->localeContext->options($websiteKey))->contains('code', $code)) {
            throw ValidationException::withMessages([
                'code' => 'Ngôn ngữ này đã được thêm vào website.',
            ]);
        }

        $systemLocale = $this->localeManager->ensureSystemLocale(
            $code,
            $payload['name'] ?? null,
            $payload['native_name'] ?? null,
        );
        $locale = $this->localeManager->addLocale($websiteKey, $code, [
            'is_enabled_for_editing' => true,
            'is_published' => (bool) ($payload['is_published'] ?? false),
            'fallback_locale' => $payload['fallback_locale'] ?? null,
        ]);

        $this->auditLogger->record(
            'localization.locale_added',
            $locale,
            null,
            [
                'website_key' => $websiteKey,
                'locale' => $systemLocale->code,
                'is_published' => $locale->is_published,
            ],
            moduleKey: 'cms',
        );

        return response()->json([
            'message' => 'Đã thêm ngôn ngữ vào website hiện tại.',
            'data' => array_merge($this->responsePayload($theme), [
                'locale' => $this->localeItemPayload(
                    collect($this->localeContext->options($websiteKey))->firstWhere('code', $code) ?? [],
                    $theme,
                ),
            ]),
        ], 201);
    }

    public function update(string $code, Request $request, ThemeRegistry $themeRegistry): JsonResponse
    {
        $payload = $request->validate([
            'theme_key' => ['nullable', 'string', 'max:120'],
            'name' => ['nullable', 'string', 'max:120'],
            'native_name' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'is_enabled_for_editing' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'fallback_locale' => ['nullable', 'string', 'max:35', new ValidLocaleCode],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'domain' => ['nullable', 'string', 'max:255'],
            'path_prefix' => ['nullable', 'string', 'max:64'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'timezone'],
            'date_format' => ['nullable', 'string', 'max:32'],
            'number_format' => ['nullable', 'array'],
        ]);
        $theme = $this->resolveTheme($themeRegistry, $payload['theme_key'] ?? null);
        $websiteKey = $this->siteContext->websiteKey();
        $normalizedCode = LocaleCode::tryNormalize($code);

        abort_if($normalizedCode === null, 404);

        $before = collect($this->localeContext->options($websiteKey))
            ->firstWhere('code', $normalizedCode);
        abort_if($before === null, 404);

        $systemLocale = SystemLocale::query()->where('code', $normalizedCode)->firstOrFail();

        if (array_key_exists('name', $payload) && $payload['name'] !== null) {
            $systemLocale->name = (string) $payload['name'];
        }

        if (array_key_exists('native_name', $payload)) {
            $systemLocale->native_name = $payload['native_name'];
        }

        if ($systemLocale->isDirty()) {
            $systemLocale->save();
        }

        $attributes = collect($payload)
            ->only([
                'is_enabled_for_editing',
                'is_published',
                'is_default',
                'fallback_locale',
                'sort_order',
                'domain',
                'path_prefix',
                'currency_code',
                'timezone',
                'date_format',
                'number_format',
            ])
            ->all();

        if (array_key_exists('is_active', $payload)) {
            $attributes['is_enabled_for_editing'] = (bool) $payload['is_active'];
        }

        $locale = $this->localeManager->updateLocale(
            $websiteKey,
            $normalizedCode,
            $attributes,
        );
        $after = collect($this->localeContext->options($websiteKey))
            ->firstWhere('code', $normalizedCode);

        $this->auditLogger->record(
            'localization.locale_updated',
            $locale,
            is_array($before) ? $before : null,
            is_array($after) ? $after : null,
            moduleKey: 'cms',
        );

        return response()->json([
            'message' => 'Đã cập nhật cấu hình ngôn ngữ của website.',
            'data' => array_merge($this->responsePayload($theme), [
                'locale' => $this->localeItemPayload($after ?? [], $theme),
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $theme
     * @return array<string, mixed>
     */
    private function responsePayload(?array $theme): array
    {
        $websiteKey = $this->siteContext->websiteKey();
        $this->localeManager->provisionWebsite($websiteKey);
        $options = collect($this->localeContext->options($websiteKey));
        $readiness = $this->releaseReadiness->report(
            $websiteKey,
            $options->pluck('code')->all(),
        );

        return [
            'website_key' => $websiteKey,
            'default_locale' => $this->localeContext->defaultLocale($websiteKey),
            'fallback_locale' => $this->localeContext->fallbackLocale($websiteKey),
            'source_locale' => $this->localeContext->sourceLocale(),
            'theme' => $theme,
            'locales' => $options
                ->map(fn (array $locale): array => $this->localeItemPayload(
                    $locale,
                    $theme,
                    $readiness[(string) ($locale['code'] ?? '')] ?? null,
                ))
                ->values()
                ->all(),
            'available_builtin_locales' => $this->availableBuiltinLocales($theme),
        ];
    }

    /**
     * @param  array<string, mixed>  $locale
     * @param  array<string, mixed>|null  $theme
     * @return array<string, mixed>
     */
    private function localeItemPayload(array $locale, ?array $theme, ?array $readiness = null): array
    {
        $code = (string) ($locale['code'] ?? FrontendLocalization::defaultLocale());
        $preset = FrontendLocalization::presetLocale($code);
        $themeSupportedLocales = collect(data_get($theme, 'localization.supported_locales', []))
            ->map(fn (string $item): string => LocaleCode::tryNormalize($item) ?? $item);
        $isThemeSupported = $themeSupportedLocales->contains($code);

        return array_merge($locale, [
            'code' => $code,
            'name' => (string) ($locale['name'] ?? $preset['name']),
            'native_name' => $locale['native_name'] ?? $preset['native_name'],
            'is_active' => (bool) ($locale['is_enabled_for_editing'] ?? $locale['is_active'] ?? false),
            'is_enabled_for_editing' => (bool) ($locale['is_enabled_for_editing'] ?? $locale['is_active'] ?? false),
            'is_published' => (bool) ($locale['is_published'] ?? false),
            'is_source' => $code === $this->localeContext->sourceLocale(),
            'is_theme_supported' => $isThemeSupported,
            'theme_support_status' => $isThemeSupported ? 'built_in' : 'custom',
            'sort_order' => (int) ($locale['sort_order'] ?? 0),
            'release_readiness' => $readiness,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $theme
     * @return array<int, array<string, mixed>>
     */
    private function availableBuiltinLocales(?array $theme): array
    {
        $existingCodes = collect($this->localeContext->options())->pluck('code');

        return collect(data_get($theme, 'localization.supported_locales', []))
            ->map(fn (string $code): string => LocaleCode::tryNormalize($code) ?? $code)
            ->reject(fn (string $code): bool => $existingCodes->contains($code))
            ->map(function (string $code): array {
                $preset = FrontendLocalization::presetLocale($code);

                return [
                    'code' => $code,
                    'name' => (string) $preset['name'],
                    'native_name' => $preset['native_name'],
                    'theme_support_status' => 'built_in',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveTheme(ThemeRegistry $themeRegistry, ?string $themeKey): ?array
    {
        if ($themeKey === null || trim($themeKey) === '') {
            return null;
        }

        $theme = $themeRegistry->all()->first(
            fn (array $item): bool => $item['key'] === $themeKey,
        );

        if ($theme === null) {
            throw ValidationException::withMessages([
                'theme_key' => 'Theme không tồn tại.',
            ]);
        }

        return $theme;
    }
}
