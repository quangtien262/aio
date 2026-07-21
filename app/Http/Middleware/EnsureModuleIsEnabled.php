<?php

namespace App\Http\Middleware;

use App\Models\ModuleInstallation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleIsEnabled
{
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        abort_unless(
            ModuleInstallation::query()->where('key', $moduleKey)->where('status', 'enabled')->exists(),
            404,
            'Module is not enabled.',
        );

        return $next($request);
    }
}
