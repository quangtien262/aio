<?php

namespace App\Support\Localization;

use App\Models\SystemLocale;
use App\Models\WebsiteLocale;
use App\Support\SiteContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class LocaleContext
{
    private const CACHE_INDEX_KEY = 'localization:website-cache-keys';

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $memory = [];

    public function __construct(private readonly SiteContext $siteContext) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function options(?string $websiteKey = null): array
    {
        $websiteKey = $this->websiteKey($websiteKey);

        if (array_key_exists($websiteKey, $this->memory)) {
            return $this->memory[$websiteKey];
        }

        $cacheKey = $this->cacheKey($websiteKey);

        if (! $this->configuredCacheStoreIsAvailable()) {
            // The default database cache is not available until the first
            // migration creates its tables. Localization is boot-critical,
            // so use the config/schema fallback without making cache storage
            // a prerequisite for running `artisan migrate`.
            return $this->memory[$websiteKey] = $this->loadOptions($websiteKey);
        }

        $this->rememberCacheKey($cacheKey);

        return $this->memory[$websiteKey] = Cache::rememberForever(
            $cacheKey,
            fn (): array => $this->loadOptions($websiteKey),
        );
    }

    /**
     * @return list<string>
     */
    public function editableLocales(?string $websiteKey = null): array
    {
        return collect($this->options($websiteKey))
            ->filter(fn (array $locale): bool => (bool) $locale['is_enabled_for_editing'])
            ->pluck('code')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function publicLocales(?string $websiteKey = null): array
    {
        return collect($this->options($websiteKey))
            ->filter(fn (array $locale): bool => (
                (bool) $locale['is_enabled_for_editing']
                && (bool) $locale['is_published']
            ))
            ->pluck('code')
            ->values()
            ->all();
    }

    public function defaultLocale(?string $websiteKey = null): string
    {
        $options = collect($this->options($websiteKey));
        $default = $options->firstWhere('is_default', true)
            ?? $options->first(fn (array $locale): bool => (
                $locale['is_enabled_for_editing'] && $locale['is_published']
            ))
            ?? $options->firstWhere('is_enabled_for_editing', true)
            ?? $options->first();

        return (string) ($default['code']
            ?? LocaleCode::tryNormalize((string) config('localization.default_locale', 'vi'))
            ?? 'vi');
    }

    public function fallbackLocale(?string $websiteKey = null): string
    {
        $options = collect($this->options($websiteKey));
        $defaultLocale = $this->defaultLocale($websiteKey);
        $default = $options->firstWhere('code', $defaultLocale);
        $fallback = LocaleCode::tryNormalize((string) ($default['fallback_locale'] ?? ''));

        if ($fallback !== null && $options->contains('code', $fallback)) {
            return $fallback;
        }

        $configured = LocaleCode::tryNormalize(
            (string) config('localization.fallback_locale', $defaultLocale),
        );

        return $configured !== null && $options->contains('code', $configured)
            ? $configured
            : $defaultLocale;
    }

    public function sourceLocale(): string
    {
        return LocaleCode::tryNormalize((string) config('localization.source_locale', 'vi')) ?? 'vi';
    }

    public function isEditable(?string $locale, ?string $websiteKey = null): bool
    {
        $locale = LocaleCode::tryNormalize($locale);

        return $locale !== null && in_array($locale, $this->editableLocales($websiteKey), true);
    }

    public function isPublic(?string $locale, ?string $websiteKey = null): bool
    {
        $locale = LocaleCode::tryNormalize($locale);

        return $locale !== null && in_array($locale, $this->publicLocales($websiteKey), true);
    }

    public function resolveEditable(?string $locale, ?string $websiteKey = null): string
    {
        $locale = LocaleCode::tryNormalize($locale);

        return $locale !== null && $this->isEditable($locale, $websiteKey)
            ? $locale
            : $this->defaultLocale($websiteKey);
    }

    public function resolvePublic(?string $locale, ?string $websiteKey = null): string
    {
        $locale = LocaleCode::tryNormalize($locale);

        return $locale !== null && $this->isPublic($locale, $websiteKey)
            ? $locale
            : $this->defaultPublicLocale($websiteKey);
    }

    /**
     * @return list<string>
     */
    public function fallbackChain(string $locale, ?string $websiteKey = null): array
    {
        $websiteKey = $this->websiteKey($websiteKey);
        $locale = LocaleCode::tryNormalize($locale) ?? $this->defaultLocale($websiteKey);
        $options = collect($this->options($websiteKey))->keyBy('code');
        $chain = [];

        $appendWithConfiguredFallbacks = function (?string $candidate) use (&$appendWithConfiguredFallbacks, &$chain, $options): void {
            $candidate = LocaleCode::tryNormalize($candidate);

            if ($candidate === null || in_array($candidate, $chain, true)) {
                return;
            }

            $chain[] = $candidate;
            $option = $options->get($candidate);

            if (is_array($option)) {
                $appendWithConfiguredFallbacks((string) ($option['fallback_locale'] ?? ''));
            }
        };

        foreach ([
            $locale,
            $this->defaultLocale($websiteKey),
            $this->sourceLocale(),
            $this->fallbackLocale($websiteKey),
        ] as $candidate) {
            $appendWithConfiguredFallbacks($candidate);
        }

        return $chain;
    }

    /**
     * @return list<string>
     */
    public function knownLocaleCodes(): array
    {
        $databaseCodes = [];

        if ($this->canReadSystemLocales()) {
            $databaseCodes = SystemLocale::query()
                ->orderBy('sort_order')
                ->pluck('code')
                ->all();
        }

        return collect($databaseCodes)
            ->merge(array_keys((array) config('localization.preset_locales', [])))
            ->merge((array) config('localization.supported_locales', []))
            ->push($this->sourceLocale())
            ->map(fn ($locale): ?string => LocaleCode::tryNormalize((string) $locale))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function flush(?string $websiteKey = null): void
    {
        $websiteKey = $this->websiteKey($websiteKey);
        unset($this->memory[$websiteKey]);
        Cache::forget($this->cacheKey($websiteKey));
    }

    public function flushAll(): void
    {
        $this->memory = [];

        foreach ((array) Cache::get(self::CACHE_INDEX_KEY, []) as $cacheKey) {
            Cache::forget((string) $cacheKey);
        }

        Cache::forget(self::CACHE_INDEX_KEY);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadOptions(string $websiteKey): array
    {
        if ($this->canReadWebsiteLocales()) {
            $websiteLocales = WebsiteLocale::query()
                ->forWebsite($websiteKey)
                ->with('systemLocale')
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('locale')
                ->get();

            if ($websiteLocales->isNotEmpty()) {
                return $websiteLocales
                    ->map(fn (WebsiteLocale $locale): array => $this->websiteLocalePayload($locale))
                    ->values()
                    ->all();
            }
        }

        return $this->legacyOptions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function legacyOptions(): array
    {
        if ($this->canReadSystemLocales()) {
            try {
                return SystemLocale::query()
                    ->orderByDesc('is_default')
                    ->orderBy('sort_order')
                    ->orderBy('code')
                    ->get()
                    ->map(fn (SystemLocale $locale): array => [
                        'code' => (string) $locale->code,
                        'name' => (string) $locale->name,
                        'native_name' => $locale->native_name,
                        ...LocaleIcon::metadata(
                            (string) $locale->code,
                            (string) ($locale->native_name ?: $locale->name),
                        ),
                        'is_default' => (bool) $locale->is_default,
                        'is_active' => (bool) $locale->is_active,
                        'is_enabled_for_editing' => (bool) $locale->is_active,
                        'is_published' => (bool) $locale->is_active && (bool) $locale->is_published,
                        'fallback_locale' => (string) config('localization.fallback_locale', 'vi'),
                        'sort_order' => (int) $locale->sort_order,
                        'domain' => null,
                        'path_prefix' => null,
                        'currency_code' => null,
                        'timezone' => null,
                        'date_format' => null,
                        'number_format' => null,
                    ])
                    ->values()
                    ->all();
            } catch (Throwable) {
                //
            }
        }

        return collect((array) config('localization.supported_locales', ['vi']))
            ->map(function (string $code, int $index): array {
                $code = LocaleCode::tryNormalize($code) ?? 'vi';
                $preset = (array) config('localization.preset_locales.'.$code, []);

                return [
                    'code' => $code,
                    'name' => (string) ($preset['name'] ?? strtoupper($code)),
                    'native_name' => $preset['native_name'] ?? null,
                    ...LocaleIcon::metadata(
                        $code,
                        (string) ($preset['native_name'] ?? $preset['name'] ?? strtoupper($code)),
                    ),
                    'is_default' => $code === config('localization.default_locale', 'vi'),
                    'is_active' => true,
                    'is_enabled_for_editing' => true,
                    'is_published' => true,
                    'fallback_locale' => (string) config('localization.fallback_locale', 'vi'),
                    'sort_order' => $index,
                    'domain' => null,
                    'path_prefix' => null,
                    'currency_code' => null,
                    'timezone' => null,
                    'date_format' => null,
                    'number_format' => null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function websiteLocalePayload(WebsiteLocale $locale): array
    {
        return [
            'code' => (string) $locale->locale,
            'name' => (string) ($locale->systemLocale?->name ?? strtoupper($locale->locale)),
            'native_name' => $locale->systemLocale?->native_name,
            ...LocaleIcon::metadata(
                (string) $locale->locale,
                (string) ($locale->systemLocale?->native_name
                    ?: $locale->systemLocale?->name
                    ?: strtoupper($locale->locale)),
            ),
            'is_default' => (bool) $locale->is_default,
            'is_active' => (bool) $locale->is_enabled_for_editing,
            'is_enabled_for_editing' => (bool) $locale->is_enabled_for_editing,
            'is_published' => (bool) $locale->is_published,
            'fallback_locale' => $locale->fallback_locale,
            'sort_order' => (int) $locale->sort_order,
            'domain' => $locale->domain,
            'path_prefix' => $locale->path_prefix,
            'currency_code' => $locale->currency_code,
            'timezone' => $locale->timezone,
            'date_format' => $locale->date_format,
            'number_format' => $locale->number_format,
        ];
    }

    private function defaultPublicLocale(?string $websiteKey = null): string
    {
        $publicLocales = $this->publicLocales($websiteKey);
        $default = $this->defaultLocale($websiteKey);

        return in_array($default, $publicLocales, true)
            ? $default
            : (string) ($publicLocales[0] ?? $default);
    }

    private function websiteKey(?string $websiteKey): string
    {
        return $this->siteContext->normalizeWebsiteKey(
            $websiteKey ?? $this->siteContext->websiteKey(),
        );
    }

    private function cacheKey(string $websiteKey): string
    {
        return 'localization:website:'.sha1($websiteKey).':options:v2';
    }

    private function rememberCacheKey(string $cacheKey): void
    {
        $keys = collect((array) Cache::get(self::CACHE_INDEX_KEY, []))
            ->push($cacheKey)
            ->unique()
            ->values()
            ->all();

        Cache::forever(self::CACHE_INDEX_KEY, $keys);
    }

    private function configuredCacheStoreIsAvailable(): bool
    {
        $store = (string) config('cache.default', 'database');

        if ((string) config("cache.stores.{$store}.driver") !== 'database') {
            return true;
        }

        $table = (string) config("cache.stores.{$store}.table", 'cache');
        $connection = config("cache.stores.{$store}.connection");

        return is_string($connection) && $connection !== ''
            ? Schema::connection($connection)->hasTable($table)
            : Schema::hasTable($table);
    }

    private function canReadWebsiteLocales(): bool
    {
        try {
            return Schema::hasTable('website_locales');
        } catch (Throwable) {
            return false;
        }
    }

    private function canReadSystemLocales(): bool
    {
        try {
            return Schema::hasTable('system_locales');
        } catch (Throwable) {
            return false;
        }
    }
}
