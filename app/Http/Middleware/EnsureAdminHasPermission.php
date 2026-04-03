<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminHasPermission
{
    public function handle(Request $request, Closure $next, string $permission, ?string $scopeType = null, ?string $scopeSource = null): Response
    {
        $admin = $request->user('admin');

        $scopeValue = null;

        if ($scopeType !== null && $scopeSource !== null) {
            $scopeValue = $request->route($scopeSource) ?? $request->input($scopeSource);
        }

        abort_unless($admin && $admin->canAccess($permission, $scopeType, $scopeValue), 403);

        return $next($request);
    }
}
