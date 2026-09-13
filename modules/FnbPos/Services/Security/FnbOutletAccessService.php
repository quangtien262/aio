<?php

namespace Modules\FnbPos\Services\Security;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\Admin;
use App\Models\ModuleInstallation;
use App\Models\ModuleRoleDefinition;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\FnbPos\Security\FnbVersionedSecurity;
use RuntimeException;

final class FnbOutletAccessService
{
    public function __construct(
        private readonly ModuleCapabilityChecker $capabilities,
        private readonly FnbVersionedSecurity $security,
    ) {}

    /** @throws AuthorizationException */
    public function authorize(
        Admin $actor,
        string $websiteKey,
        int $outletId,
        string $permission,
    ): void {
        $this->assertActorAndPermission($actor, $permission, $websiteKey);

        if ($outletId < 1 || ! DB::table('fnb_outlets')
            ->where('website_key', $websiteKey)
            ->where('id', $outletId)
            ->where('status', 'active')
            ->exists()) {
            throw new AuthorizationException('Điểm bán không thuộc website hiện tại hoặc không còn hoạt động.');
        }

        if ($this->isCoreOwner($actor)) {
            return;
        }

        $binding = $this->validBinding($actor, $websiteKey, $permission);
        if ($binding === null) {
            throw new AuthorizationException('Quyền F&B không đến từ preset website hợp lệ.');
        }

        $membership = DB::table('fnb_outlet_staff')
            ->where('website_key', $websiteKey)
            ->where('outlet_id', $outletId)
            ->where('admin_id', $actor->id)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if ($membership === null) {
            throw new AuthorizationException('Tài khoản chưa được phân công vào điểm bán này.');
        }
    }

    /**
     * Applies an optional terminal selector after the ordinary website/outlet
     * authorization. A terminal-restricted membership must always provide an
     * allowed active terminal; an unrestricted membership may omit it.
     */
    public function authorizeTerminal(
        Admin $actor,
        string $websiteKey,
        int $outletId,
        ?int $terminalId,
        string $permission,
    ): void {
        $this->authorize($actor, $websiteKey, $outletId, $permission);

        if ($terminalId !== null && ! DB::table('fnb_terminals')
            ->where('website_key', $websiteKey)
            ->where('outlet_id', $outletId)
            ->where('id', $terminalId)
            ->where('status', 'active')
            ->exists()) {
            throw new AuthorizationException('Thiết bị không thuộc điểm bán hoặc không còn hoạt động.');
        }

        if ($this->isCoreOwner($actor)) {
            return;
        }

        $membershipId = DB::table('fnb_outlet_staff')
            ->where('website_key', $websiteKey)
            ->where('outlet_id', $outletId)
            ->where('admin_id', $actor->id)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->value('id');

        $restrictedTerminalIds = DB::table('fnb_outlet_staff_terminals')
            ->where('website_key', $websiteKey)
            ->where('outlet_id', $outletId)
            ->where('outlet_staff_id', $membershipId)
            ->pluck('terminal_id')
            ->map(fn (mixed $id): int => (int) $id);
        if ($restrictedTerminalIds->isNotEmpty()
            && ($terminalId === null || ! $restrictedTerminalIds->contains($terminalId))) {
            throw new AuthorizationException('Tài khoản không được phân công vào thiết bị này.');
        }
    }

    /**
     * A null result means no terminal restriction (including a core-owner
     * bypass). An empty list means the actor cannot access this outlet.
     *
     * @return list<int>|null
     */
    public function accessibleTerminalIds(Admin $actor, string $websiteKey, int $outletId, string $permission): ?array
    {
        $this->assertActorAndPermission($actor, $permission, $websiteKey);

        if ($this->isCoreOwner($actor)) {
            return null;
        }

        $binding = $this->validBinding($actor, $websiteKey, $permission);
        if ($binding === null) {
            return [];
        }

        $membership = DB::table('fnb_outlet_staff')
            ->where('website_key', $websiteKey)
            ->where('outlet_id', $outletId)
            ->where('admin_id', $actor->id)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
        if ($membership === null) {
            return [];
        }

        $restrictionQuery = DB::table('fnb_outlet_staff_terminals')
            ->where('fnb_outlet_staff_terminals.website_key', $websiteKey)
            ->where('fnb_outlet_staff_terminals.outlet_id', $outletId)
            ->where('fnb_outlet_staff_terminals.outlet_staff_id', $membership->id);
        if (! (clone $restrictionQuery)->exists()) {
            return null;
        }

        return $restrictionQuery
            ->join('fnb_terminals', function ($join): void {
                $join->on('fnb_terminals.website_key', '=', 'fnb_outlet_staff_terminals.website_key')
                    ->on('fnb_terminals.outlet_id', '=', 'fnb_outlet_staff_terminals.outlet_id')
                    ->on('fnb_terminals.id', '=', 'fnb_outlet_staff_terminals.terminal_id');
            })
            ->where('fnb_terminals.status', 'active')
            ->orderBy('fnb_outlet_staff_terminals.terminal_id')
            ->pluck('fnb_outlet_staff_terminals.terminal_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * A null result means a core System/Platform Owner bypasses outlet filtering.
     *
     * @return list<int>|null
     */
    public function accessibleOutletIds(Admin $actor, string $websiteKey, string $permission): ?array
    {
        $this->assertActorAndPermission($actor, $permission, $websiteKey);

        if ($this->isCoreOwner($actor)) {
            return null;
        }

        if ($this->validBinding($actor, $websiteKey, $permission) === null) {
            return [];
        }

        return DB::table('fnb_outlet_staff')
            ->join('fnb_outlets', function ($join): void {
                $join->on('fnb_outlets.website_key', '=', 'fnb_outlet_staff.website_key')
                    ->on('fnb_outlets.id', '=', 'fnb_outlet_staff.outlet_id');
            })
            ->where('fnb_outlet_staff.website_key', $websiteKey)
            ->where('fnb_outlet_staff.admin_id', $actor->id)
            ->where('fnb_outlet_staff.is_active', true)
            ->whereNull('fnb_outlet_staff.revoked_at')
            ->where(fn ($query) => $query->whereNull('fnb_outlet_staff.expires_at')->orWhere('fnb_outlet_staff.expires_at', '>', now()))
            ->where('fnb_outlets.status', 'active')
            ->orderBy('fnb_outlet_staff.outlet_id')
            ->pluck('fnb_outlet_staff.outlet_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function canBootstrapWebsite(
        Admin $actor,
        string $websiteKey,
        string $permission = 'fnb.outlet.view',
    ): bool {
        try {
            $this->assertActorAndPermission($actor, $permission, $websiteKey);

            return $this->isCoreOwner($actor)
                || $this->validBinding($actor, $websiteKey, $permission) !== null;
        } catch (AuthorizationException) {
            return false;
        }
    }

    public function isCoreOwner(Admin $actor): bool
    {
        if ($actor->isSystemOwner()) {
            return true;
        }

        return $actor->roleAssignments()
            ->where('scope_type', 'global')
            ->whereNull('scope_value')
            ->whereHas('role', fn ($query) => $query
                ->where('key', Role::PLATFORM_OWNER_KEY)
                ->where('is_system', true)
                ->where('status', 'active'))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    public function validBinding(Admin $actor, string $websiteKey, string $permission): ?object
    {
        if (! Schema::hasTable('fnb_staff_role_bindings') || ! Schema::hasTable('module_role_definitions')) {
            return null;
        }

        $installation = ModuleInstallation::query()->where('key', 'fnb-pos')->first();
        if ($installation === null) {
            return null;
        }

        $binding = DB::table('fnb_staff_role_bindings')
            ->where('website_key', $websiteKey)
            ->where('admin_id', $actor->id)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->first();
        if ($binding === null || $binding->core_assignment_id === null) {
            return null;
        }

        $assignment = DB::table('admin_role_assignments')
            ->join('roles', 'roles.id', '=', 'admin_role_assignments.role_id')
            ->where('admin_role_assignments.id', $binding->core_assignment_id)
            ->where('admin_role_assignments.admin_id', $actor->id)
            ->where('admin_role_assignments.role_id', $binding->preset_role_id)
            ->where('admin_role_assignments.scope_type', 'website')
            ->where('admin_role_assignments.scope_value', $websiteKey)
            ->where('roles.status', 'active')
            ->where('roles.is_system', true)
            ->where('roles.is_assignable', false)
            ->where(fn ($query) => $query->whereNull('admin_role_assignments.expires_at')->orWhere('admin_role_assignments.expires_at', '>', now()))
            ->first();
        if ($assignment === null) {
            return null;
        }

        $definition = ModuleRoleDefinition::query()
            ->where('module_key', 'fnb-pos')
            ->where('role_id', $binding->preset_role_id)
            ->where('version', $installation->version)
            ->where('is_active', true)
            ->first();
        $role = Role::query()->find($binding->preset_role_id);
        if ($definition === null || $role === null || $definition->role_key !== $role->key) {
            return null;
        }

        try {
            $immutable = $this->security->definition((string) $installation->version);
        } catch (RuntimeException) {
            return null;
        }
        $immutableRole = $immutable->roles[$role->key] ?? null;
        if ($immutableRole === null
            || $definition->custom_role_policy !== $immutable->customRolePolicy
            || $definition->assignment_channel !== $immutable->assignmentChannel
            || ! hash_equals((string) $definition->definition_hash, $immutable->roleDefinitionHash($role->key))) {
            return null;
        }

        $expectedPermissionKeys = array_values(array_unique($immutableRole['permissions']));
        $storedPermissionKeys = array_values(array_unique($definition->permission_keys ?? []));
        $actualPermissions = $role->permissions()->get([
            'permissions.key',
            'permissions.module_key',
            'permissions.risk_level',
            'permissions.is_active',
        ]);
        $actualPermissionKeys = $actualPermissions->pluck('key')->all();
        sort($expectedPermissionKeys);
        sort($storedPermissionKeys);
        sort($actualPermissionKeys);
        if ($storedPermissionKeys !== $expectedPermissionKeys || $actualPermissionKeys !== $expectedPermissionKeys) {
            return null;
        }

        foreach ($actualPermissions as $actualPermission) {
            $immutablePermission = $immutable->permissions[$actualPermission->key] ?? null;
            if ($immutablePermission === null
                || $actualPermission->module_key !== 'fnb-pos'
                || ! $actualPermission->is_active
                || $actualPermission->risk_level !== $immutablePermission['risk_level']) {
                return null;
            }
        }

        return in_array($permission, $expectedPermissionKeys, true) ? $binding : null;
    }

    /** @throws AuthorizationException */
    private function assertActorAndPermission(Admin $actor, string $permission, ?string $websiteKey = null): void
    {
        if (! $actor->isAvailable()) {
            throw new AuthorizationException('Tài khoản quản trị không còn hoạt động.');
        }
        if ($websiteKey !== null && ! $actor->canAccessWebsite($websiteKey)) {
            throw new AuthorizationException('Tài khoản không có phạm vi website hiện tại.');
        }
        if (! $this->capabilities->enabled('fnb-pos')) {
            throw new AuthorizationException('Module F&B hiện không khả dụng.');
        }

        $installation = ModuleInstallation::query()->where('key', 'fnb-pos')->first();
        try {
            $definition = $installation === null
                ? null
                : $this->security->definition((string) $installation->version);
        } catch (RuntimeException) {
            $definition = null;
        }
        if ($definition === null
            || ! in_array($permission, $definition->permissionKeys(), true)
            || ! Permission::query()->where('key', $permission)->where('module_key', 'fnb-pos')->where('is_active', true)->exists()) {
            throw new AuthorizationException('Permission F&B không khả dụng ở phiên bản đang cài.');
        }
    }
}
