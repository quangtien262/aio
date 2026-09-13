<?php

namespace Modules\FnbPos\Services\Security;

use App\Models\Admin;
use App\Models\ModuleInstallation;
use Illuminate\Support\Facades\DB;

final class FnbAuthoritySnapshotService
{
    public function __construct(private readonly FnbOutletAccessService $access) {}

    /** @param list<string> $permissions */
    public function capture(Admin $actor, string $websiteKey, int $outletId, array $permissions): FnbAuthoritySnapshot
    {
        foreach (array_values(array_unique($permissions)) as $permission) {
            if ($outletId === 0) {
                abort_unless(
                    $this->access->canBootstrapWebsite($actor, $websiteKey, $permission),
                    403,
                    'F&B website authority is no longer valid.',
                );
            } else {
                $this->access->authorize($actor, $websiteKey, $outletId, $permission);
            }
        }

        $installation = ModuleInstallation::query()->where('key', 'fnb-pos')->firstOrFail();
        $assignments = DB::table('admin_role_assignments')
            ->join('roles', 'roles.id', '=', 'admin_role_assignments.role_id')
            ->where('admin_role_assignments.admin_id', $actor->id)
            ->where(function ($query) use ($websiteKey): void {
                $query->where(function ($global): void {
                    $global->where('scope_type', 'global')->whereNull('scope_value');
                })->orWhere(function ($website) use ($websiteKey): void {
                    $website->where('scope_type', 'website')->where('scope_value', $websiteKey);
                });
            })
            ->where(fn ($query) => $query->whereNull('admin_role_assignments.expires_at')->orWhere('admin_role_assignments.expires_at', '>', now()))
            ->orderBy('admin_role_assignments.id')
            ->get([
                'admin_role_assignments.id', 'admin_role_assignments.role_id',
                'admin_role_assignments.scope_type', 'admin_role_assignments.scope_value',
                'admin_role_assignments.expires_at', 'admin_role_assignments.updated_at',
                'roles.key as role_key', 'roles.status as role_status',
            ])->map(fn ($row): array => (array) $row)->all();

        $binding = DB::table('fnb_staff_role_bindings')
            ->where('website_key', $websiteKey)->where('admin_id', $actor->id)->first();
        $membership = $outletId > 0
            ? DB::table('fnb_outlet_staff')->where('website_key', $websiteKey)
                ->where('outlet_id', $outletId)->where('admin_id', $actor->id)->first()
            : null;

        $payload = [
            'admin' => [
                'id' => (int) $actor->id,
                'auth_version' => (int) $actor->auth_version,
                'status' => $actor->status,
                'is_active' => (bool) $actor->is_active,
                'locked_at' => $actor->locked_at?->toISOString(),
            ],
            'module' => ['version' => (string) $installation->version, 'status' => $installation->status],
            'website_key' => $websiteKey,
            'outlet_id' => $outletId,
            'permissions' => array_values(array_unique($permissions)),
            'assignments' => $assignments,
            'binding' => $binding === null ? null : (array) $binding,
            'membership' => $membership === null ? null : (array) $membership,
        ];
        sort($payload['permissions']);

        $bindingVersion = (int) ($binding->version ?? 0);
        $membershipVersion = (int) ($membership->version ?? 0);
        $revision = min(4_294_967_295, (int) $actor->auth_version + $bindingVersion + $membershipVersion);

        return new FnbAuthoritySnapshot(
            hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            $revision,
            (string) $installation->version,
        );
    }
}
