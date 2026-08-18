<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Support\SiteContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminWebsiteAccess
{
    public function __construct(private readonly SiteContext $siteContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin/api/*')) {
            return $next($request);
        }

        $admin = $request->user('admin');
        abort_unless($admin, 403);

        $requestedWebsiteKey = trim((string) $request->header(
            'X-Website-Key',
            $this->siteContext->websiteKey(),
        ));
        $site = Site::query()
            ->where('status', 'active')
            ->where('website_key', $requestedWebsiteKey)
            ->first();

        if ($site && $admin->canAccessWebsite($site->website_key)) {
            $this->siteContext->set($site, $site->website_key);

            return $next($request);
        }

        if ($admin->hasOrganizationAssignmentScope()
            && ($request->is('admin/api/accounting-tax/*') || $request->routeIs('admin.api.me'))) {
            // Organization-only accounting operators do not need access to a
            // storefront website. Keep the site context detached so it cannot
            // be mistaken for website authorization; accounting routes still
            // require their explicit organization scope middleware.
            $this->siteContext->set(null, SiteContext::DEFAULT_WEBSITE_KEY);

            return $next($request);
        }

        if ($request->routeIs('admin.api.me')) {
            $fallbackSite = Site::query()
                ->where('status', 'active')
                ->orderByRaw('website_key = ? desc', [SiteContext::DEFAULT_WEBSITE_KEY])
                ->orderBy('website_key')
                ->get()
                ->first(fn (Site $candidate): bool => $admin->canAccessWebsite($candidate->website_key));

            abort_unless($fallbackSite, 403, 'Tài khoản chưa được phân quyền quản lý website đang hoạt động.');
            $this->siteContext->set($fallbackSite, $fallbackSite->website_key);

            return $next($request);
        }

        abort_if(! $site, 404, 'Website không tồn tại hoặc đã ngừng hoạt động.');
        abort(403, 'Bạn không được phân quyền quản lý website này.');
    }
}
