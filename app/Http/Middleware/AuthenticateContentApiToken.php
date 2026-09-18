<?php

namespace App\Http\Middleware;

use App\Models\ContentApiToken;
use App\Models\Site;
use App\Support\SiteContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateContentApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $rawToken = trim((string) $request->bearerToken());

        if ($rawToken === '') {
            return $this->unauthorized();
        }

        $token = ContentApiToken::query()
            ->where('token_hash', hash('sha256', $rawToken))
            ->first();

        if ($token === null || ! $token->isUsable()) {
            return $this->unauthorized();
        }

        $site = Site::query()
            ->where('website_key', $token->website_key)
            ->where('status', 'active')
            ->first();

        if ($site === null) {
            return response()->json(['message' => 'Website của API token không khả dụng.'], 403);
        }

        app(SiteContext::class)->set($site, $token->website_key);
        $request->attributes->set('content_api_token', $token);

        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinutes(5))) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $next($request);
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json(['message' => 'API token không hợp lệ hoặc đã hết hạn.'], 401);
    }
}
