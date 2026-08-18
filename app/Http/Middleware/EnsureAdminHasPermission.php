<?php

namespace App\Http\Middleware;

use App\Core\Modules\ModuleRegistry;
use App\Models\AcctOrganization;
use App\Models\AcctProviderConnection;
use App\Models\ModuleInstallation;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminHasPermission
{
    public function __construct(private readonly ModuleRegistry $moduleRegistry) {}

    public function handle(Request $request, Closure $next, string $permission, ?string $scopeType = null, ?string $scopeSource = null): Response
    {
        $admin = $request->user('admin');
        abort_unless($admin, 403);

        $scopeValue = $this->resolveScopeValue($request, $scopeType, $scopeSource);

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

    private function resolveScopeValue(Request $request, ?string $scopeType, ?string $scopeSource): mixed
    {
        if ($scopeType === null) {
            return null;
        }

        if ($scopeType !== 'organization') {
            return $scopeSource === null
                ? null
                : ($request->route($scopeSource) ?? $request->input($scopeSource));
        }

        if ($scopeSource === '*') {
            return '*';
        }

        $scopeSource ??= 'organization_id';
        [$routeSource, $nestedSource] = array_pad(explode('.', $scopeSource, 2), 2, null);
        $candidate = $request->route($routeSource) ?? ($nestedSource === null ? $request->input($routeSource) : null);

        if ($candidate instanceof Model && $nestedSource !== null) {
            $candidate = data_get($candidate, $nestedSource);
        }

        if ($candidate instanceof AcctOrganization) {
            return (string) $candidate->getKey();
        }

        if ($candidate instanceof Model) {
            $organizationId = $candidate->getAttribute('organization_id');

            return filled($organizationId) ? (string) $organizationId : null;
        }

        if ($scopeSource === 'connection_id'
            && filled($candidate)
            && ctype_digit((string) $candidate)
            && (int) $candidate > 0) {
            $organizationId = AcctProviderConnection::query()
                ->whereKey((int) $candidate)
                ->value('organization_id');

            return filled($organizationId) ? (string) $organizationId : null;
        }

        // A scalar route id is only safe when it explicitly represents an
        // organization. Treat other unbound model ids as unresolved so a
        // document id can never be confused with an organization id.
        if (! in_array($scopeSource, ['organization', 'organization_id'], true)) {
            return null;
        }

        return filled($candidate) && ctype_digit((string) $candidate) && (int) $candidate > 0
            ? (string) $candidate
            : null;
    }
}
