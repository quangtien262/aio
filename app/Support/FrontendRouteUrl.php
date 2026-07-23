<?php

namespace App\Support;

final class FrontendRouteUrl
{
    public static function home(?string $locale = null, bool $absolute = true): string
    {
        return route('site.home', [
            'locale' => self::locale($locale),
        ], $absolute);
    }

    public static function page(string $slug, ?string $locale = null, bool $absolute = true): string
    {
        return route('site.pages.show', [
            'locale' => self::locale($locale),
            'slug' => self::slug($slug),
        ], $absolute);
    }

    public static function product(string $slug, ?string $locale = null, bool $absolute = true): string
    {
        $resolvedLocale = self::locale($locale);

        return route('site.catalog.product', [
            'locale' => $resolvedLocale,
            'productSegment' => FrontendLocalization::segment('product', $resolvedLocale),
            'slug' => self::slug($slug),
        ], $absolute);
    }

    public static function category(string $slug, ?string $locale = null, bool $absolute = true): string
    {
        $resolvedLocale = self::locale($locale);

        return route('site.catalog.category', [
            'locale' => $resolvedLocale,
            'categorySegment' => FrontendLocalization::segment('category', $resolvedLocale),
            'slug' => self::slug($slug),
        ], $absolute);
    }

    public static function blogCategory(string $slug, ?string $locale = null, bool $absolute = true): string
    {
        return route('site.blog.category', [
            'locale' => self::locale($locale),
            'slug' => self::slug($slug),
        ], $absolute);
    }

    public static function post(string $slug, ?string $locale = null, bool $absolute = true): string
    {
        return route('site.blog.show', [
            'locale' => self::locale($locale),
            'slug' => self::slug($slug),
        ], $absolute);
    }

    public static function serviceCategory(string $slug, ?string $locale = null, bool $absolute = true): string
    {
        return route('site.services.category', [
            'locale' => self::locale($locale),
            'slug' => self::slug($slug),
        ], $absolute);
    }

    public static function service(string $slug, ?string $locale = null, bool $absolute = true): string
    {
        return route('site.services.show', [
            'locale' => self::locale($locale),
            'slug' => self::slug($slug),
        ], $absolute);
    }

    public static function projectCategory(string $slug, ?string $locale = null, bool $absolute = true): string
    {
        return route('site.projects.category', [
            'locale' => self::locale($locale),
            'slug' => self::slug($slug),
        ], $absolute);
    }

    public static function project(string $slug, ?string $locale = null, bool $absolute = true): string
    {
        return route('site.projects.show', [
            'locale' => self::locale($locale),
            'slug' => self::slug($slug),
        ], $absolute);
    }

    public static function contact(?string $locale = null, bool $absolute = true): string
    {
        return route('site.contact', [
            'locale' => self::locale($locale),
        ], $absolute);
    }

    public static function previewProduct(string|int $productId, ?string $locale = null, bool $absolute = true): string
    {
        $resolvedLocale = self::locale($locale);

        return route('site.preview.products', [
            'locale' => $resolvedLocale,
            'previewSegment' => FrontendLocalization::segment('preview', $resolvedLocale),
            'productsSegment' => FrontendLocalization::segment('products', $resolvedLocale),
            'product' => $productId,
        ], $absolute);
    }

    public static function localized(?string $href, ?string $locale = null, bool $absolute = true): string
    {
        $href = trim((string) $href);

        if (
            $href === ''
            || $href === '#'
            || str_starts_with($href, '#')
            || preg_match('/^(https?:)?\/\//i', $href)
            || preg_match('/^(mailto|tel):/i', $href)
        ) {
            return $href !== '' ? $href : '#';
        }

        $parts = parse_url($href) ?: [];
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#'.$parts['fragment'] : '';

        if ($path === '') {
            return self::home($locale, $absolute).$query.$fragment;
        }

        $segments = explode('/', $path);

        if (! in_array($segments[0] ?? '', FrontendLocalization::knownLocaleCodes(), true)) {
            array_unshift($segments, self::locale($locale));
        }

        $localizedPath = '/'.implode('/', array_map(
            static fn (string $segment): string => rawurlencode(rawurldecode($segment)),
            $segments
        ));

        return ($absolute ? url($localizedPath) : $localizedPath).$query.$fragment;
    }

    public static function switchLocale(string $locale, bool $absolute = true): string
    {
        $segments = request()->segments();
        $resolvedLocale = FrontendLocalization::resolveLocale($locale);

        if (isset($segments[0]) && in_array($segments[0], FrontendLocalization::knownLocaleCodes(), true)) {
            $segments[0] = $resolvedLocale;
        } else {
            array_unshift($segments, $resolvedLocale);
        }

        $path = '/'.implode('/', $segments);
        $url = $absolute ? url($path) : $path;
        $query = request()->getQueryString();

        return $query ? $url.'?'.$query : $url;
    }

    public static function pagePath(string $slug): string
    {
        return self::withoutLocale(self::page($slug, FrontendLocalization::defaultLocale(), false));
    }

    public static function productPath(string $slug): string
    {
        return self::withoutLocale(self::product($slug, FrontendLocalization::defaultLocale(), false));
    }

    public static function categoryPath(string $slug): string
    {
        return self::withoutLocale(self::category($slug, FrontendLocalization::defaultLocale(), false));
    }

    public static function blogCategoryPath(string $slug): string
    {
        return self::withoutLocale(self::blogCategory($slug, FrontendLocalization::defaultLocale(), false));
    }

    public static function postPath(string $slug): string
    {
        return self::withoutLocale(self::post($slug, FrontendLocalization::defaultLocale(), false));
    }

    public static function serviceCategoryPath(string $slug): string
    {
        return self::withoutLocale(self::serviceCategory($slug, FrontendLocalization::defaultLocale(), false));
    }

    public static function servicePath(string $slug): string
    {
        return self::withoutLocale(self::service($slug, FrontendLocalization::defaultLocale(), false));
    }

    public static function projectCategoryPath(string $slug): string
    {
        return self::withoutLocale(self::projectCategory($slug, FrontendLocalization::defaultLocale(), false));
    }

    public static function projectPath(string $slug): string
    {
        return self::withoutLocale(self::project($slug, FrontendLocalization::defaultLocale(), false));
    }

    public static function contactPath(): string
    {
        return self::withoutLocale(self::contact(FrontendLocalization::defaultLocale(), false));
    }

    private static function locale(?string $locale): string
    {
        return FrontendLocalization::resolveLocale(
            $locale ?: app()->getLocale() ?: FrontendLocalization::defaultLocale()
        );
    }

    private static function slug(string $slug): string
    {
        return trim($slug, '/');
    }

    private static function withoutLocale(string $localizedPath): string
    {
        $localePrefix = '/'.FrontendLocalization::defaultLocale();

        return str_starts_with($localizedPath, $localePrefix.'/')
            ? substr($localizedPath, strlen($localePrefix))
            : $localizedPath;
    }
}
