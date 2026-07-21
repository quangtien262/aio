<?php

namespace App\Http\Middleware;

use App\Support\SiteContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminWebsiteAccess
{
    public function __construct(private readonly SiteContext $siteContext)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin/api/*')) {
            return $next($request);
        }

        $admin = $request->user('admin');
        $websiteKey = $this->siteContext->websiteKey();

        abort_unless($admin && $admin->canAccessWebsite($websiteKey), 403, 'Bạn không được phân quyền quản lý website này.');

        return $next($request);
    }
}
