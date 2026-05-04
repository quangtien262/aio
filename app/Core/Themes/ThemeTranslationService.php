<?php

namespace App\Core\Themes;

use App\Models\ThemeTranslation;
use App\Support\FrontendLocalization;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class ThemeTranslationService
{
    private const CACHE_TTL_SECONDS = 3600;
    private const STATIC_GROUP = 'static';

    public function translations(?string $themeKey, string $locale): array
    {
        $resolvedThemeKey = $this->resolveThemeKey($themeKey);
        $resolvedLocale = FrontendLocalization::resolveEditableLocale($locale);
        $fallbackLocale = FrontendLocalization::fallbackLocale();

        return Cache::remember(
            $this->cacheKey($resolvedThemeKey, $resolvedLocale),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function () use ($fallbackLocale, $resolvedLocale, $resolvedThemeKey): array {
                $fallbackTranslations = $this->loadFileTranslations($resolvedThemeKey, $fallbackLocale);
                $localeTranslations = $resolvedLocale === $fallbackLocale
                    ? []
                    : $this->loadFileTranslations($resolvedThemeKey, $resolvedLocale);
                $overrides = $this->overrides($resolvedThemeKey, $resolvedLocale, self::STATIC_GROUP);

                return array_replace($fallbackTranslations, $localeTranslations, $overrides);
            },
        );
    }

    public function bladeText(?string $themeKey, string $locale, string $key, ?string $default = null): string
    {
        return (string) Arr::get($this->translations($themeKey, $locale), $key, $default ?? $key);
    }

    public function editableEntries(string $themeKey, string $locale): array
    {
        $resolvedLocale = FrontendLocalization::resolveEditableLocale($locale);
        $fallbackLocale = FrontendLocalization::fallbackLocale();
        $fallbackTranslations = $this->loadFileTranslations($themeKey, $fallbackLocale);
        $localeTranslations = $resolvedLocale === $fallbackLocale ? [] : $this->loadFileTranslations($themeKey, $resolvedLocale);
        $overrides = $this->overrides($themeKey, $resolvedLocale, self::STATIC_GROUP);

        $keys = collect(array_keys($fallbackTranslations))
            ->merge(array_keys($localeTranslations))
            ->merge(array_keys($overrides))
            ->unique()
            ->sort()
            ->values();

        return $keys->map(function (string $key) use ($fallbackTranslations, $localeTranslations, $overrides, $themeKey, $resolvedLocale): array {
            $defaultValue = Arr::get($localeTranslations, $key, Arr::get($fallbackTranslations, $key, ''));

            return [
                'key' => $key,
                'theme_key' => $themeKey,
                'locale' => $resolvedLocale,
                'default_value' => $defaultValue,
                'override_value' => $overrides[$key] ?? null,
                'effective_value' => Arr::get($overrides, $key, $defaultValue),
            ];
        })->all();
    }

    public function saveOverrides(string $themeKey, string $locale, array $entries): void
    {
        $resolvedLocale = FrontendLocalization::resolveEditableLocale($locale);
        $baseline = collect($this->editableEntries($themeKey, $resolvedLocale))
            ->mapWithKeys(fn (array $entry): array => [$entry['key'] => $entry['default_value']])
            ->all();

        foreach ($entries as $entry) {
            $key = trim((string) ($entry['key'] ?? ''));

            if ($key === '') {
                continue;
            }

            $value = trim((string) ($entry['value'] ?? ''));
            $defaultValue = trim((string) ($baseline[$key] ?? ''));

            if ($value === '' || $value === $defaultValue) {
                ThemeTranslation::query()
                    ->where('theme_key', $themeKey)
                    ->where('locale', $resolvedLocale)
                    ->where('group', self::STATIC_GROUP)
                    ->where('translation_key', $key)
                    ->delete();

                continue;
            }

            ThemeTranslation::query()->updateOrCreate(
                [
                    'theme_key' => $themeKey,
                    'locale' => $resolvedLocale,
                    'group' => self::STATIC_GROUP,
                    'translation_key' => $key,
                ],
                [
                    'value' => $value,
                ],
            );
        }

        Cache::forget($this->cacheKey($themeKey, $resolvedLocale));
    }

    private function overrides(string $themeKey, string $locale, string $group): array
    {
        return ThemeTranslation::query()
            ->where('theme_key', $themeKey)
            ->where('locale', $locale)
            ->where('group', $group)
            ->pluck('value', 'translation_key')
            ->all();
    }

    private function loadFileTranslations(string $themeKey, string $locale): array
    {
        $path = base_path(sprintf('themes/%s/lang/%s.json', $themeKey, $locale));

        if (! File::exists($path)) {
            return [];
        }

        /** @var array<string, string>|null $decoded */
        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function resolveThemeKey(?string $themeKey): string
    {
        return trim((string) $themeKey) !== '' ? (string) $themeKey : 'TH0001';
    }

    private function cacheKey(string $themeKey, string $locale): string
    {
        return sprintf(
            'theme-translations:%s:%s:%s',
            strtolower($themeKey),
            strtolower($locale),
            $this->translationSignature($themeKey, $locale),
        );
    }

    private function translationSignature(string $themeKey, string $locale): string
    {
        $fallbackLocale = FrontendLocalization::fallbackLocale();
        $paths = array_unique([
            base_path(sprintf('themes/%s/lang/%s.json', $themeKey, $fallbackLocale)),
            base_path(sprintf('themes/%s/lang/%s.json', $themeKey, $locale)),
        ]);

        $signature = collect($paths)
            ->map(fn (string $path): string => File::exists($path) ? (string) File::lastModified($path) : 'missing')
            ->implode('|');

        return substr(sha1($signature), 0, 12);
    }
}
