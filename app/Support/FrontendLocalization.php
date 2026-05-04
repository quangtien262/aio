<?php

namespace App\Support;

use App\Models\SystemLocale;
use Illuminate\Support\Facades\Schema;
use Throwable;

class FrontendLocalization
{
    /**
     * @var array<int, array{code:string,name:string,native_name:?string,is_default:bool,is_active:bool,is_published:bool,sort_order:int}>|null
     */
    private static ?array $localeCache = null;

    public static function supportedLocales(): array
    {
        return collect(self::localeOptions())
            ->filter(fn (array $locale): bool => $locale['is_active'])
            ->pluck('code')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{code:string,name:string,native_name:?string,is_default:bool,is_active:bool,is_published:bool,sort_order:int}>
     */
    public static function localeOptions(): array
    {
        if (self::$localeCache !== null) {
            return self::$localeCache;
        }

        self::$localeCache = self::loadLocaleOptions();

        return self::$localeCache;
    }

    public static function defaultLocale(): string
    {
        $default = collect(self::localeOptions())->firstWhere('is_default', true);

        return (string) ($default['code'] ?? config('localization.default_locale', config('app.locale', 'vi')));
    }

    public static function fallbackLocale(): string
    {
        return (string) config('localization.fallback_locale', config('localization.source_locale', self::defaultLocale()));
    }

    public static function sourceLocale(): string
    {
        return (string) config('localization.source_locale', self::fallbackLocale());
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array(self::normalizeLocaleCode($locale), self::supportedLocales(), true);
    }

    public static function resolveLocale(?string $locale): string
    {
        return self::isSupported($locale) ? $locale : self::defaultLocale();
    }

    public static function isEditableLocale(?string $locale): bool
    {
        if ($locale === null || trim($locale) === '') {
            return false;
        }

        return in_array(self::normalizeLocaleCode($locale), self::knownLocaleCodes(), true);
    }

    public static function resolveEditableLocale(?string $locale): string
    {
        return self::isEditableLocale($locale) ? self::normalizeLocaleCode((string) $locale) : self::defaultLocale();
    }

    public static function segment(string $key, ?string $locale = null): string
    {
        $resolvedLocale = self::resolveLocale($locale);

        return (string) data_get(
            self::routeSegments($resolvedLocale),
            $key,
            data_get(self::routeSegments('en'), $key, data_get(self::routeSegments(self::fallbackLocale()), $key, $key))
        );
    }

    public static function routeParameterDefaults(?string $locale = null): array
    {
        $resolvedLocale = self::resolveLocale($locale);

        return [
            'locale' => $resolvedLocale,
            'loginSegment' => self::segment('login', $resolvedLocale),
            'registerSegment' => self::segment('register', $resolvedLocale),
            'accountSegment' => self::segment('account', $resolvedLocale),
            'favoriteSegment' => self::segment('favorite', $resolvedLocale),
            'newsletterSegment' => self::segment('newsletter', $resolvedLocale),
            'subscribeSegment' => self::segment('subscribe', $resolvedLocale),
            'previewSegment' => self::segment('preview', $resolvedLocale),
            'pagesSegment' => self::segment('pages', $resolvedLocale),
            'postsSegment' => self::segment('posts', $resolvedLocale),
            'productsSegment' => self::segment('products', $resolvedLocale),
            'blogSegment' => self::segment('blog', $resolvedLocale),
            'contactSegment' => self::segment('contact', $resolvedLocale),
            'cartSegment' => self::segment('cart', $resolvedLocale),
            'buyNowSegment' => self::segment('buy_now', $resolvedLocale),
            'cartUpdateSegment' => self::segment('cart_update', $resolvedLocale),
            'cartRemoveSegment' => self::segment('cart_remove', $resolvedLocale),
            'checkoutSegment' => self::segment('checkout', $resolvedLocale),
            'checkoutSuccessSegment' => self::segment('checkout_success', $resolvedLocale),
            'searchSegment' => self::segment('search', $resolvedLocale),
            'suggestionsSegment' => self::segment('suggestions', $resolvedLocale),
            'categorySegment' => self::segment('category', $resolvedLocale),
            'productSegment' => self::segment('product', $resolvedLocale),
        ];
    }

    public static function segmentValues(string $key): array
    {
        return collect(self::supportedLocales())
            ->push(self::fallbackLocale())
            ->push('en')
            ->unique()
            ->map(fn (string $locale): string => self::segment($key, $locale))
            ->push($key)
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{name:string,native_name:?string}|array<string, mixed>
     */
    public static function presetLocale(string $code): array
    {
        $preset = (array) config('localization.preset_locales.'.trim($code), []);

        return [
            'name' => (string) ($preset['name'] ?? strtoupper($code)),
            'native_name' => isset($preset['native_name']) ? (string) $preset['native_name'] : null,
        ];
    }

    public static function flushCache(): void
    {
        self::$localeCache = null;
    }

    /**
     * @return list<string>
     */
    public static function knownLocaleCodes(): array
    {
        return collect(self::localeOptions())
            ->pluck('code')
            ->merge(array_keys((array) config('localization.preset_locales', [])))
            ->merge((array) config('localization.supported_locales', []))
            ->push(self::defaultLocale())
            ->push(self::fallbackLocale())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function routeSegments(string $locale): array
    {
        return (array) config('localization.route_segments.'.trim($locale), []);
    }

    /**
     * @return array<int, array{code:string,name:string,native_name:?string,is_default:bool,is_active:bool,is_published:bool,sort_order:int}>
     */
    private static function loadLocaleOptions(): array
    {
        $databaseLocales = self::databaseLocaleOptions();

        if ($databaseLocales !== null && $databaseLocales !== []) {
            return $databaseLocales;
        }

        return collect((array) config('localization.supported_locales', ['vi']))
            ->map(function (string $code, int $index): array {
                $preset = self::presetLocale($code);

                return [
                    'code' => $code,
                    'name' => (string) $preset['name'],
                    'native_name' => $preset['native_name'],
                    'is_default' => $code === config('localization.default_locale', 'vi'),
                    'is_active' => true,
                    'is_published' => true,
                    'sort_order' => $index,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{code:string,name:string,native_name:?string,is_default:bool,is_active:bool,is_published:bool,sort_order:int}>|null
     */
    private static function databaseLocaleOptions(): ?array
    {
        if (! self::canReadDatabaseLocales()) {
            return null;
        }

        try {
            return SystemLocale::query()
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get(['code', 'name', 'native_name', 'is_default', 'is_active', 'is_published', 'sort_order'])
                ->map(function (SystemLocale $locale): array {
                    return [
                        'code' => (string) $locale->code,
                        'name' => (string) $locale->name,
                        'native_name' => $locale->native_name !== null ? (string) $locale->native_name : null,
                        'is_default' => (bool) $locale->is_default,
                        'is_active' => (bool) $locale->is_active,
                        'is_published' => (bool) $locale->is_published,
                        'sort_order' => (int) $locale->sort_order,
                    ];
                })
                ->values()
                ->all();
        } catch (Throwable) {
            return null;
        }
    }

    private static function canReadDatabaseLocales(): bool
    {
        try {
            return Schema::hasTable('system_locales');
        } catch (Throwable) {
            return false;
        }
    }

    private static function normalizeLocaleCode(string $locale): string
    {
        $parts = collect(explode('-', str_replace('_', '-', trim($locale))))
            ->filter(fn (?string $part): bool => $part !== null && $part !== '')
            ->values();

        if ($parts->isEmpty()) {
            return self::defaultLocale();
        }

        return $parts
            ->map(fn (string $part, int $index): string => $index === 0 ? strtolower($part) : strtoupper($part))
            ->implode('-');
    }
}
