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
        $status = ModuleInstallation::query()->where('key', $moduleKey)->value('status');

        abort_unless(
            $status === 'enabled' || ($status === null && app()->runningUnitTests()),
            404,
            'Module is not enabled.',
        );

        return $next($request);
    }
}
