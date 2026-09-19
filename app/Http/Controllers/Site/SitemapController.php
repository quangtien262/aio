<?php

namespace App\Http\Controllers\Site;

use App\Support\{SiteContext, SitemapService};
use Illuminate\Http\Response;

class SitemapController
{
    public function __invoke(SiteContext $context, SitemapService $sitemaps): Response
    {
        return response()->view('sitemaps.index', ['snapshot' => $sitemaps->snapshot($context->websiteKey())])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function part(string $name, SiteContext $context, SitemapService $sitemaps): Response
    {
        $snapshot = $sitemaps->snapshot($context->websiteKey());
        abort_unless(isset($snapshot['files'][$name]), 404);
        return response()->view('sitemaps.urls', ['entries' => $snapshot['files'][$name]])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(SiteContext $context, SitemapService $sitemaps): Response
    {
        return response("User-agent: *\nDisallow: /admin\n\nSitemap: ".$sitemaps->baseUrl($context->websiteKey())."/sitemap.xml\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function status(SiteContext $context, SitemapService $sitemaps): \Illuminate\Http\JsonResponse
    {
        return $this->summary($sitemaps->snapshot($context->websiteKey()));
    }

    public function refresh(SiteContext $context, SitemapService $sitemaps): \Illuminate\Http\JsonResponse
    {
        return $this->summary($sitemaps->snapshot($context->websiteKey(), true));
    }

    private function summary(array $snapshot): \Illuminate\Http\JsonResponse
    {
        $snapshot['files'] = collect($snapshot['files'])->map(fn ($items, $name) => [
            'name' => $name.'.xml', 'count' => count($items), 'url' => $snapshot['base_url'].'/sitemaps/'.$name.'.xml',
        ])->values()->all();
        return response()->json(['data' => $snapshot]);
    }
}
