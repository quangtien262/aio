<?php

namespace App\Support;

final class FrontendRouteUrl
{
    public static function page(string $slug, ?string $locale = null, bool $absolute = true): string
    {
        $normalizedSlug = trim($slug, '/');
        $resolvedLocale = $locale ?: app()->getLocale() ?: FrontendLocalization::defaultLocale();

        return route('site.pages.show', [
            'locale' => $resolvedLocale,
            'slug' => $normalizedSlug,
        ], $absolute);
    }

    public static function pagePath(string $slug): string
    {
        $locale = FrontendLocalization::defaultLocale();
        $localizedPath = self::page($slug, $locale, false);
        $localePrefix = '/'.$locale;

        return str_starts_with($localizedPath, $localePrefix.'/')
            ? substr($localizedPath, strlen($localePrefix))
            : $localizedPath;
    }
}
