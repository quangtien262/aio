<?php

namespace App\Support;

use App\Support\Localization\LocaleCode;
use App\Support\Localization\LocaleContext;

/**
 * Backward-compatible facade for storefront and admin localization.
 *
 * Public storefront code should use supportedLocales(); administrative editors
 * should use editableLocales(). New domain services may inject LocaleContext
 * directly when an explicit website key is required.
 */
class FrontendLocalization
{
    /**
     * @return list<string>
     */
    public static function supportedLocales(): array
    {
        return self::context()->publicLocales();
    }

    /**
     * @return list<string>
     */
    public static function publicLocales(): array
    {
        return self::context()->publicLocales();
    }

    /**
     * @return list<string>
     */
    public static function editableLocales(): array
    {
        return self::context()->editableLocales();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function localeOptions(): array
    {
        return self::context()->options();
    }

    public static function defaultLocale(): string
    {
        return self::context()->defaultLocale();
    }

    public static function fallbackLocale(): string
    {
        return self::context()->fallbackLocale();
    }

    public static function sourceLocale(): string
    {
        return self::context()->sourceLocale();
    }

    public static function isSupported(?string $locale): bool
    {
        return self::context()->isPublic($locale);
    }

    public static function resolveLocale(?string $locale): string
    {
        return self::context()->resolvePublic($locale);
    }

    public static function isEditableLocale(?string $locale): bool
    {
        return self::context()->isEditable($locale);
    }

    public static function resolveEditableLocale(?string $locale): string
    {
        return self::context()->resolveEditable($locale);
    }

    public static function segment(string $key, ?string $locale = null): string
    {
        $resolvedLocale = LocaleCode::tryNormalize($locale)
            ?? self::defaultLocale();
        $baseLocale = explode('-', $resolvedLocale)[0];

        return (string) (
            data_get(self::routeSegments($resolvedLocale), $key)
            ?? data_get(self::routeSegments($baseLocale), $key)
            ?? data_get(self::routeSegments('en'), $key)
            ?? data_get(self::routeSegments(self::fallbackLocale()), $key)
            ?? $key
        );
    }

    /**
     * @return array<string, string>
     */
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
            'servicesSegment' => self::segment('services', $resolvedLocale),
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

    /**
     * Return all configured route words, not only the locales that happened to
     * be public while the route collection was booted.
     *
     * @return list<string>
     */
    public static function segmentValues(string $key): array
    {
        return collect((array) config('localization.route_segments', []))
            ->map(fn (mixed $segments): mixed => data_get($segments, $key))
            ->push($key)
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{name:string,native_name:?string}
     */
    public static function presetLocale(string $code): array
    {
        $code = LocaleCode::tryNormalize($code) ?? trim($code);
        $baseCode = explode('-', $code)[0];
        $preset = (array) (
            config('localization.preset_locales.'.$code)
            ?? config('localization.preset_locales.'.$baseCode)
            ?? []
        );

        return [
            'name' => (string) ($preset['name'] ?? strtoupper($code)),
            'native_name' => isset($preset['native_name']) ? (string) $preset['native_name'] : null,
        ];
    }

    public static function flushCache(): void
    {
        self::context()->flush();
    }

    /**
     * @return list<string>
     */
    public static function knownLocaleCodes(): array
    {
        return self::context()->knownLocaleCodes();
    }

    public static function routeLocalePattern(): string
    {
        return LocaleCode::routePattern();
    }

    /**
     * @return array<string, string>
     */
    private static function routeSegments(string $locale): array
    {
        return (array) config('localization.route_segments.'.$locale, []);
    }

    private static function context(): LocaleContext
    {
        return app(LocaleContext::class);
    }
}
