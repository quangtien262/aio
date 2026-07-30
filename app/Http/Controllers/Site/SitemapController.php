<?php

namespace App\Http\Controllers\Site;

use App\Models\LocalizedRoute;
use App\Support\Localization\LocaleContext;
use App\Support\SiteContext;
use Illuminate\Http\Response;

class SitemapController
{
    public function __invoke(
        SiteContext $siteContext,
        LocaleContext $localeContext,
    ): Response {
        $websiteKey = $siteContext->websiteKey();
        $entries = LocalizedRoute::query()
            ->forWebsite($websiteKey)
            ->where('is_canonical', true)
            ->where('is_published', true)
            ->whereIn('locale', $localeContext->publicLocales($websiteKey))
            ->get()
            ->map(fn (LocalizedRoute $route): array => [
                'url' => url('/'.$route->locale.($route->path === '/' ? '' : $route->path)),
                'last_modified' => ($route->published_at ?? $route->updated_at)?->toAtomString(),
            ])
            ->merge(collect($localeContext->publicLocales($websiteKey))->map(
                fn (string $locale): array => [
                    'url' => route('site.home', ['locale' => $locale]),
                    'last_modified' => null,
                ],
            ))
            ->unique('url')
            ->sortBy('url')
            ->values();

        return response()
            ->view('sitemap', ['entries' => $entries])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
