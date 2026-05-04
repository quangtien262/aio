<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Themes\ThemeRegistry;
use App\Models\SystemLocale;
use App\Support\FrontendLocalization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ThemeLocaleController
{
    public function index(Request $request, ThemeRegistry $themeRegistry): JsonResponse
    {
        $validated = $request->validate([
            'theme_key' => ['nullable', 'string', 'max:120'],
        ]);

        $theme = $this->resolveTheme($themeRegistry, $validated['theme_key'] ?? null);

        return response()->json([
            'data' => [
                'default_locale' => FrontendLocalization::defaultLocale(),
                'fallback_locale' => FrontendLocalization::fallbackLocale(),
                'source_locale' => FrontendLocalization::sourceLocale(),
                'theme' => $theme,
                'locales' => $this->localePayload($theme),
                'available_builtin_locales' => $this->availableBuiltinLocales($theme),
            ],
        ]);
    }

    public function store(Request $request, ThemeRegistry $themeRegistry): JsonResponse
    {
        $payload = $request->validate([
            'theme_key' => ['nullable', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})?$/'],
            'name' => ['nullable', 'string', 'max:120'],
            'native_name' => ['nullable', 'string', 'max:120'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $theme = $this->resolveTheme($themeRegistry, $payload['theme_key'] ?? null);
        $code = $this->normalizeLocaleCode((string) $payload['code']);
        $preset = FrontendLocalization::presetLocale($code);

        $locale = SystemLocale::query()->firstOrNew(['code' => $code]);
        $locale->name = (string) ($payload['name'] ?? $preset['name']);
        $locale->native_name = $payload['native_name'] ?? $preset['native_name'];
        $locale->is_active = true;
        $locale->is_published = (bool) ($payload['is_published'] ?? false);
        $locale->sort_order = $locale->exists ? (int) $locale->sort_order : ((int) SystemLocale::query()->max('sort_order') + 1);

        if (! SystemLocale::query()->exists()) {
            $locale->is_default = true;
            $locale->is_published = true;
        }

        $locale->save();

        FrontendLocalization::flushCache();

        return response()->json([
            'message' => 'Đã thêm ngôn ngữ mới vào hệ thống.',
            'data' => [
                'locale' => $this->localeItemPayload($locale->fresh(), $theme),
                'default_locale' => FrontendLocalization::defaultLocale(),
                'locales' => $this->localePayload($theme),
                'available_builtin_locales' => $this->availableBuiltinLocales($theme),
            ],
        ], 201);
    }

    public function update(string $code, Request $request, ThemeRegistry $themeRegistry): JsonResponse
    {
        $payload = $request->validate([
            'theme_key' => ['nullable', 'string', 'max:120'],
            'name' => ['nullable', 'string', 'max:120'],
            'native_name' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $theme = $this->resolveTheme($themeRegistry, $payload['theme_key'] ?? null);
        $locale = SystemLocale::query()->where('code', $this->normalizeLocaleCode($code))->firstOrFail();
        $sourceLocale = FrontendLocalization::sourceLocale();

        if (array_key_exists('name', $payload) && $payload['name'] !== null) {
            $locale->name = (string) $payload['name'];
        }

        if (array_key_exists('native_name', $payload)) {
            $locale->native_name = $payload['native_name'];
        }

        if (array_key_exists('is_active', $payload)) {
            if ($locale->code === $sourceLocale && $payload['is_active'] === false) {
                throw ValidationException::withMessages([
                    'is_active' => 'Không thể tắt ngôn ngữ nguồn của hệ thống.',
                ]);
            }

            if ($locale->is_default && $payload['is_active'] === false) {
                throw ValidationException::withMessages([
                    'is_active' => 'Không thể tắt ngôn ngữ mặc định hiện tại.',
                ]);
            }

            $locale->is_active = (bool) $payload['is_active'];
        }

        if (array_key_exists('is_published', $payload)) {
            if (($locale->code === $sourceLocale || $locale->is_default) && $payload['is_published'] === false) {
                throw ValidationException::withMessages([
                    'is_published' => 'Không thể bỏ publish ngôn ngữ nguồn hoặc mặc định hiện tại.',
                ]);
            }

            $locale->is_published = (bool) $payload['is_published'];
        }

        if (($payload['is_default'] ?? false) === true) {
            SystemLocale::query()->where('is_default', true)->update(['is_default' => false]);
            $locale->is_default = true;
            $locale->is_active = true;
            $locale->is_published = true;
        }

        $locale->save();

        FrontendLocalization::flushCache();

        return response()->json([
            'message' => 'Đã cập nhật cấu hình ngôn ngữ.',
            'data' => [
                'locale' => $this->localeItemPayload($locale->fresh(), $theme),
                'default_locale' => FrontendLocalization::defaultLocale(),
                'locales' => $this->localePayload($theme),
                'available_builtin_locales' => $this->availableBuiltinLocales($theme),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $theme
     * @return array<int, array<string, mixed>>
     */
    private function localePayload(?array $theme): array
    {
        return collect(FrontendLocalization::localeOptions())
            ->map(function (array $locale) use ($theme): array {
                $persisted = SystemLocale::query()->where('code', $locale['code'])->first();

                return $this->localeItemPayload($persisted, $theme, $locale);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $theme
     * @param  array{code:string,name:string,native_name:?string,is_default:bool,is_active:bool,is_published:bool,sort_order:int}|null  $fallbackLocale
     * @return array<string, mixed>
     */
    private function localeItemPayload(?SystemLocale $locale, ?array $theme, ?array $fallbackLocale = null): array
    {
        $code = (string) ($locale?->code ?? $fallbackLocale['code'] ?? 'vi');
        $preset = FrontendLocalization::presetLocale($code);
        $themeSupportedLocales = collect(data_get($theme, 'localization.supported_locales', []));

        return [
            'code' => $code,
            'name' => (string) ($locale?->name ?? $fallbackLocale['name'] ?? $preset['name']),
            'native_name' => $locale?->native_name ?? $fallbackLocale['native_name'] ?? $preset['native_name'],
            'is_default' => (bool) ($locale?->is_default ?? $fallbackLocale['is_default'] ?? false),
            'is_active' => (bool) ($locale?->is_active ?? $fallbackLocale['is_active'] ?? false),
            'is_published' => (bool) ($locale?->is_published ?? $fallbackLocale['is_published'] ?? false),
            'is_source' => $code === FrontendLocalization::sourceLocale(),
            'is_theme_supported' => $themeSupportedLocales->contains($code),
            'theme_support_status' => $themeSupportedLocales->contains($code) ? 'built_in' : 'custom',
            'sort_order' => (int) ($locale?->sort_order ?? $fallbackLocale['sort_order'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $theme
     * @return array<int, array<string, mixed>>
     */
    private function availableBuiltinLocales(?array $theme): array
    {
        $themeSupportedLocales = collect(data_get($theme, 'localization.supported_locales', []));
        $activeCodes = collect(FrontendLocalization::localeOptions())->pluck('code');

        return $themeSupportedLocales
            ->reject(fn (string $code): bool => $activeCodes->contains($code))
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

        $theme = $themeRegistry->all()->first(fn (array $item): bool => $item['key'] === $themeKey);

        if ($theme === null) {
            throw ValidationException::withMessages([
                'theme_key' => 'Theme không tồn tại.',
            ]);
        }

        return $theme;
    }

    private function normalizeLocaleCode(string $code): string
    {
        $parts = collect(explode('-', str_replace('_', '-', trim($code))))
            ->filter(fn (?string $part): bool => $part !== null && $part !== '')
            ->values();

        if ($parts->isEmpty()) {
            return 'vi';
        }

        return $parts
            ->map(function (string $part, int $index): string {
                return $index === 0 ? Str::lower($part) : Str::upper($part);
            })
            ->implode('-');
    }
}
