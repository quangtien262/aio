<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Support\SiteContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentSite
{
    public function __construct(private readonly SiteContext $siteContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = $this->normalizeHost($request->getHost());
        $site = null;

        if (Schema::hasTable('sites')) {
            $site = Site::query()
                ->where('status', 'active')
                ->where(function ($query) use ($host): void {
                    $query->where('domain', $host);

                    if (str_starts_with($host, 'www.')) {
                        $query->orWhere('domain', substr($host, 4));
                    } else {
                        $query->orWhere('domain', 'www.'.$host);
                    }
                })
                ->first();
        }

        $this->siteContext->set($site, $site?->website_key ?? SiteContext::DEFAULT_WEBSITE_KEY);

        return $next($request);
    }

    private function normalizeHost(string $host): string
    {
        return strtolower(trim(preg_replace('/:\d+$/', '', $host) ?? $host));
    }
}
