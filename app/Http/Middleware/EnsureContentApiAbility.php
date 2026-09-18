<?php

namespace App\Http\Middleware;

use App\Models\ContentApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureContentApiAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $token = $request->attributes->get('content_api_token');

        if (! $token instanceof ContentApiToken || ! $token->hasAbility($ability)) {
            return response()->json(['message' => 'API token không có quyền '.$ability.'.'], 403);
        }

        return $next($request);
    }
}
