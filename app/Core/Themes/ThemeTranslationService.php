<?php

namespace App\Core\Themes;

use App\Enums\TranslationStatus;
use App\Models\ThemeTranslation;
use App\Support\FrontendLocalization;
use App\Support\Localization\TranslationRevision;
use App\Support\SiteContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class ThemeTranslationService
{
    private const CACHE_TTL_SECONDS = 3600;

    private const STATIC_GROUP = 'static';

    public function __construct(private readonly SiteContext $siteContext) {}

    public function translations(?string $themeKey, string $locale): array
    {
        $resolvedThemeKey = $this->resolveThemeKey($themeKey);
        $resolvedLocale = FrontendLocalization::resolveLocale($locale);
        $fallbackLocale = FrontendLocalization::fallbackLocale();

        return Cache::remember(
            $this->cacheKey($resolvedThemeKey, $resolvedLocale),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function () use ($fallbackLocale, $resolvedLocale, $resolvedThemeKey): array {
                $fileTranslations = $this->loadInheritedFileTranslations(
                    $resolvedThemeKey,
                    $resolvedLocale,
                    $fallbackLocale,
                );
                $overrides = $this->overrides(
                    $resolvedThemeKey,
                    $resolvedLocale,
                    self::STATIC_GROUP,
                    true,
                );

                return array_replace($fileTranslations, $overrides);
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
        $localeTranslations = $this->loadInheritedFileTranslations(
            $themeKey,
            $resolvedLocale,
            $fallbackLocale,
        );
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
                    'translation_status' => TranslationStatus::Published,
                    'translation_revision' => TranslationRevision::fingerprint(['value' => $value]),
                    'is_machine_translated' => false,
                    'translated_at' => now(),
                    'translation_published_at' => now(),
                ],
            );
        }

        Cache::forget($this->cacheKey($themeKey, $resolvedLocale));
    }

    private function overrides(
        string $themeKey,
        string $locale,
        string $group,
        bool $publishedOnly = false,
    ): array {
        return ThemeTranslation::query()
            ->where('theme_key', $themeKey)
            ->where('locale', $locale)
            ->where('group', $group)
            ->when($publishedOnly, fn ($query) => $query->publishedTranslation())
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

    private function loadInheritedFileTranslations(
        string $themeKey,
        string $locale,
        string $fallbackLocale,
    ): array {
        return collect($this->localeFileChain($locale, $fallbackLocale))
            ->reduce(
                fn (array $translations, string $candidate): array => array_replace(
                    $translations,
                    $this->loadFileTranslations($themeKey, $candidate),
                ),
                [],
            );
    }

    private function resolveThemeKey(?string $themeKey): string
    {
        return trim((string) $themeKey) !== '' ? (string) $themeKey : 'corporate-starter';
    }

    private function cacheKey(string $themeKey, string $locale): string
    {
        return sprintf(
            'theme-translations:%s:%s:%s:%s',
            strtolower($this->siteContext->websiteKey()),
            strtolower($themeKey),
            strtolower($locale),
            $this->translationSignature($themeKey, $locale),
        );
    }

    private function translationSignature(string $themeKey, string $locale): string
    {
        $fallbackLocale = FrontendLocalization::fallbackLocale();
        $paths = collect($this->localeFileChain($locale, $fallbackLocale))
            ->map(fn (string $candidate): string => base_path(
                sprintf('themes/%s/lang/%s.json', $themeKey, $candidate),
            ))
            ->all();

        $signature = collect($paths)
            ->map(fn (string $path): string => File::exists($path)
                ? File::lastModified($path).':'.File::size($path)
                : 'missing')
            ->implode('|');

        return substr(sha1($signature), 0, 12);
    }

    /**
     * @return list<string>
     */
    private function localeFileChain(string $locale, string $fallbackLocale): array
    {
        $parts = explode('-', $locale);
        $progressive = [];

        for ($length = 1; $length <= count($parts); $length++) {
            $progressive[] = implode('-', array_slice($parts, 0, $length));
        }

        return collect([$fallbackLocale])
            ->merge($progressive)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
