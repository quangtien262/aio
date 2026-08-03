<?php

namespace App\Http\Middleware;

use App\Core\Modules\ModuleRegistry;
use App\Models\ModuleInstallation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminHasPermission
{
    public function __construct(private readonly ModuleRegistry $moduleRegistry) {}

    public function handle(Request $request, Closure $next, string $permission, ?string $scopeType = null, ?string $scopeSource = null): Response
    {
        $admin = $request->user('admin');

        $scopeValue = null;

        if ($scopeType !== null && $scopeSource !== null) {
            $scopeValue = $request->route($scopeSource) ?? $request->input($scopeSource);
        }

        abort_unless($admin, 403);

        $moduleKey = $this->moduleRegistry->moduleKeyForPermission($permission);

        if ($moduleKey !== null) {
            $moduleStatus = ModuleInstallation::query()
                ->where('key', $moduleKey)
                ->value('status');

            abort_unless(
                $moduleStatus === 'enabled'
                    || ($moduleStatus === null && app()->runningUnitTests()),
                404,
                'Module is not enabled.',
            );
        }

        if ($scopeType !== null) {
            abort_unless($admin->canAccess($permission, $scopeType, $scopeValue), 403);
        } else {
            abort_unless(
                Gate::forUser($admin)->allows($permission) || $admin->hasPermission($permission),
                403,
            );
        }

        return $next($request);
    }
}
