<?php

namespace App\Http\Middleware;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\ModuleInstallation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleIsEnabled
{
    public function __construct(private readonly ModuleCapabilityChecker $capabilities) {}

    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $status = ModuleInstallation::query()->where('key', $moduleKey)->value('status');
        $legacyTestBypass = $moduleKey !== 'fnb-pos'
            && $status === null
            && app()->runningUnitTests();

        abort_unless(
            $this->capabilities->enabled($moduleKey) || $legacyTestBypass,
            404,
            'Module is not enabled.',
        );

        return $next($request);
    }
}
