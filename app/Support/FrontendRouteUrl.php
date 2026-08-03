<?php

namespace App\Support;

use App\Support\Localization\CmsPageLocalization;
use App\Support\Localization\LocalizedRouteRegistry;

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

    public static function landing(string $slug, ?string $locale = null, bool $absolute = true): string
    {
        return route('site.landing.show', [
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

    public static function realEstate(?string $locale = null, bool $absolute = true): string
    {
        return route('site.real-estate.index', [
            'locale' => self::locale($locale),
        ], $absolute);
    }

    public static function realEstateListing(string $slug, ?string $locale = null, bool $absolute = true): string
    {
        return route('site.real-estate.show', [
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
        $resolvedLocale = self::locale($locale);

        if (in_array($segments[0] ?? '', FrontendLocalization::knownLocaleCodes(), true)) {
            $segments[0] = $resolvedLocale;
        } else {
            array_unshift($segments, $resolvedLocale);
        }

        if (isset($segments[1])) {
            foreach (self::localizedRouteSegmentKeys() as $segmentKey) {
                if (! in_array($segments[1], FrontendLocalization::segmentValues($segmentKey), true)) {
                    continue;
                }

                $segments[1] = FrontendLocalization::segment($segmentKey, $resolvedLocale);
                break;
            }
        }

        $localizedPath = '/'.implode('/', array_map(
            static fn (string $segment): string => rawurlencode(rawurldecode($segment)),
            $segments
        ));

        return ($absolute ? url($localizedPath) : $localizedPath).$query.$fragment;
    }

    public static function switchLocale(string $locale, bool $absolute = true): string
    {
        $resolvedLocale = FrontendLocalization::resolveLocale($locale);

        return self::localeSwitchUrls([$resolvedLocale], $absolute)[$resolvedLocale]
            ?? self::home($resolvedLocale, $absolute);
    }

    /**
     * Build locale-switch URLs in one pass so a header with many locales does
     * not query the localized route registry once per link.
     *
     * @param  iterable<int, string>  $locales
     * @return array<string, string>
     */
    public static function localeSwitchUrls(
        iterable $locales,
        bool $absolute = true,
    ): array {
        $resolvedLocales = collect($locales)
            ->map(fn (string $locale): string => FrontendLocalization::resolveLocale($locale))
            ->unique()
            ->values();

        if ($resolvedLocales->isEmpty()) {
            return [];
        }

        $path = '/'.ltrim(request()->path(), '/');
        $query = request()->getQueryString();
        $canonicalPaths = [];
        $hasLocalizedResource = false;

        if (filled(request()->route('locale'))) {
            $segments = request()->segments();
            $currentLocale = FrontendLocalization::resolveLocale((string) array_shift($segments));
            $resourcePath = '/'.implode('/', $segments);
            $resourcePath = $resourcePath === '/' ? $resourcePath : rtrim($resourcePath, '/');
            $registry = app(LocalizedRouteRegistry::class);
            $currentRoute = $registry->resolvePublic($currentLocale, $resourcePath);

            if ($currentRoute !== null) {
                $hasLocalizedResource = true;
                $canonicalPaths = $registry->canonicalPaths(
                    (string) $currentRoute->resource_type,
                    (string) $currentRoute->resource_id,
                    $resolvedLocales->all(),
                );
            }

            if (request()->routeIs('site.pages.show')) {
                $cmsPagePaths = self::cmsPageLocalePaths(
                    $currentLocale,
                    (string) request()->route('slug'),
                    $resolvedLocales->all(),
                );

                if ($cmsPagePaths !== null) {
                    $hasLocalizedResource = true;
                    $canonicalPaths = array_replace($cmsPagePaths, $canonicalPaths);
                }
            }
        }

        return $resolvedLocales
            ->mapWithKeys(function (string $locale) use (
                $absolute,
                $canonicalPaths,
                $hasLocalizedResource,
                $path,
                $query,
            ): array {
                if ($hasLocalizedResource) {
                    $url = isset($canonicalPaths[$locale])
                        ? self::localized($canonicalPaths[$locale], $locale, $absolute)
                        : self::home($locale, $absolute);
                } else {
                    $url = self::localized($path, $locale, $absolute);
                }

                return [$locale => $query ? $url.'?'.$query : $url];
            })
            ->all();
    }

    /**
     * Resolve published CMS Page translations directly as a compatibility
     * bridge for legacy data that may be missing a localized_routes row.
     *
     * @param  list<string>  $locales
     * @return array<string, string>|null
     */
    private static function cmsPageLocalePaths(
        string $currentLocale,
        string $slug,
        array $locales,
    ): ?array {
        if ($slug === '') {
            return null;
        }

        $resolution = app(CmsPageLocalization::class)->resolvePublic(
            app(SiteContext::class)->websiteKey(),
            $currentLocale,
            $slug,
        );

        if ($resolution === null || $resolution->usedFallback) {
            return null;
        }

        return $resolution->page->translations
            ->filter(fn ($translation): bool => (
                in_array($translation->locale, $locales, true)
                && $translation->isPublishedTranslation()
            ))
            ->mapWithKeys(fn ($translation): array => [
                $translation->locale => '/p/'.rawurlencode($translation->slug),
            ])
            ->all();
    }

    public static function pagePath(string $slug): string
    {
        $locale = FrontendLocalization::defaultLocale();

        return self::withoutLocale(self::page($slug, $locale, false), $locale);
    }

    public static function landingPath(string $slug): string
    {
        $locale = FrontendLocalization::defaultLocale();

        return self::withoutLocale(self::landing($slug, $locale, false), $locale);
    }

    public static function productPath(string $slug, ?string $locale = null): string
    {
        $resolvedLocale = self::locale($locale);

        return self::withoutLocale(
            self::product($slug, $resolvedLocale, false),
            $resolvedLocale,
        );
    }

    public static function categoryPath(string $slug, ?string $locale = null): string
    {
        $resolvedLocale = self::locale($locale);

        return self::withoutLocale(
            self::category($slug, $resolvedLocale, false),
            $resolvedLocale,
        );
    }

    public static function blogCategoryPath(string $slug): string
    {
        $locale = FrontendLocalization::defaultLocale();

        return self::withoutLocale(self::blogCategory($slug, $locale, false), $locale);
    }

    public static function postPath(string $slug): string
    {
        $locale = FrontendLocalization::defaultLocale();

        return self::withoutLocale(self::post($slug, $locale, false), $locale);
    }

    public static function serviceCategoryPath(string $slug): string
    {
        $locale = FrontendLocalization::defaultLocale();

        return self::withoutLocale(self::serviceCategory($slug, $locale, false), $locale);
    }

    public static function servicePath(string $slug): string
    {
        $locale = FrontendLocalization::defaultLocale();

        return self::withoutLocale(self::service($slug, $locale, false), $locale);
    }

    public static function projectCategoryPath(string $slug): string
    {
        $locale = FrontendLocalization::defaultLocale();

        return self::withoutLocale(self::projectCategory($slug, $locale, false), $locale);
    }

    public static function projectPath(string $slug): string
    {
        $locale = FrontendLocalization::defaultLocale();

        return self::withoutLocale(self::project($slug, $locale, false), $locale);
    }

    public static function contactPath(): string
    {
        $locale = FrontendLocalization::defaultLocale();

        return self::withoutLocale(self::contact($locale, false), $locale);
    }

    public static function homePath(): string
    {
        $locale = FrontendLocalization::defaultLocale();

        return self::withoutLocale(self::home($locale, false), $locale);
    }

    public static function catalogSearchPath(): string
    {
        return self::namedRoutePath('site.catalog.search');
    }

    public static function blogPath(): string
    {
        return self::namedRoutePath('site.blog.index');
    }

    public static function servicesPath(): string
    {
        return self::namedRoutePath('site.services.index');
    }

    public static function projectsPath(): string
    {
        return self::namedRoutePath('site.projects.index');
    }

    public static function realEstatePath(): string
    {
        $locale = FrontendLocalization::defaultLocale();

        return self::withoutLocale(self::realEstate($locale, false), $locale);
    }

    private static function namedRoutePath(string $routeName): string
    {
        $locale = FrontendLocalization::defaultLocale();

        return self::withoutLocale(
            route($routeName, ['locale' => $locale], false),
            $locale,
        );
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

    /**
     * Route parameters that are localized in routes/web.php.
     *
     * @return list<string>
     */
    private static function localizedRouteSegmentKeys(): array
    {
        return [
            'login',
            'register',
            'account',
            'favorite',
            'newsletter',
            'preview',
            'cart',
            'checkout',
            'search',
            'category',
            'product',
        ];
    }

    private static function withoutLocale(
        string $localizedPath,
        ?string $locale = null,
    ): string {
        $localePrefix = '/'.self::locale($locale);

        if ($localizedPath === $localePrefix) {
            return '/';
        }

        return str_starts_with($localizedPath, $localePrefix.'/')
            ? substr($localizedPath, strlen($localePrefix))
            : $localizedPath;
    }
}
