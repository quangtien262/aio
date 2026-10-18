<?php

namespace Modules\FnbPos\Services\Security;

use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\ModuleInstallation;
use App\Models\ModuleRoleDefinition;
use App\Models\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Security\FnbVersionedSecurity;

final class FnbScopedStaffService
{
    public function __construct(private readonly FnbOutletAccessService $access, private readonly FnbVersionedSecurity $security, private readonly FnbAuditLogger $audit) {}

    public function assign(Admin $actor, string $websiteKey, int $outletId, Admin $target, string $roleKey, ?int $expectedBindingVersion, ?int $expectedMembershipVersion, array $terminalIds): array
    {
        $this->transactionRequired();
        $actor = Admin::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        $this->access->authorize($actor, $websiteKey, $outletId, 'fnb.staff.assign');
        $target = $this->target($target);
        $this->assertHqTargetCeiling($actor, $websiteKey, $target);
        $role = $this->role($roleKey);
        $scope = $this->scope($websiteKey, $target->id, 'outlet', $outletId);
        $membership = DB::table('fnb_outlet_staff')->where('website_key', $websiteKey)->where('outlet_id', $outletId)->where('admin_id', $target->id)->lockForUpdate()->first();
        $this->version($scope?->version, $expectedBindingVersion, 'binding');
        $this->version($membership?->version, $expectedMembershipVersion, 'membership');
        $this->ceiling($actor, $websiteKey, $outletId, $roleKey);
        if ($scope) {
            $this->ceiling($actor, $websiteKey, $outletId, (string) Role::whereKey($scope->preset_role_id)->value('key'));
        }
        if ($terminalIds !== [] && DB::table('fnb_terminals')->where('website_key', $websiteKey)->where('outlet_id', $outletId)->where('status', 'active')->whereIn('id', $terminalIds)->count() !== count(array_unique($terminalIds))) {
            throw new AuthorizationException('Thiết bị không thuộc cửa hàng hoặc không hoạt động.');
        }
        $now = now();
        $membershipValues = ['is_active' => true, 'revoked_at' => null, 'revoked_by' => null, 'expires_at' => null,
            'assigned_by' => $actor->id, 'version' => (int) ($membership->version ?? 0) + 1, 'updated_at' => $now];
        if ($membership) {
            DB::table('fnb_outlet_staff')->where('id', $membership->id)->update($membershipValues);
            $membershipId = (int) $membership->id;
        } else {
            $membershipId = DB::table('fnb_outlet_staff')->insertGetId($membershipValues + ['website_key' => $websiteKey, 'outlet_id' => $outletId, 'admin_id' => $target->id, 'is_default' => false, 'created_at' => $now]);
        }
        $scopeId = $this->saveScope($scope, $websiteKey, $target->id, 'outlet', $outletId, $role->id, $actor->id);
        // Only the core owner may use the legacy owner assignment surface to grant HQ ownership.
        if ($roleKey === 'fnb-owner' && $this->access->isCoreOwner($actor)) {
            $this->saveScope($this->scope($websiteKey, $target->id, 'hq', 0), $websiteKey, $target->id, 'hq', 0, $role->id, $actor->id);
        }
        DB::table('fnb_outlet_staff_terminals')->where('outlet_staff_id', $membershipId)->delete();
        foreach (array_unique($terminalIds) as $terminalId) {
            DB::table('fnb_outlet_staff_terminals')->insert(['website_key' => $websiteKey, 'outlet_id' => $outletId, 'outlet_staff_id' => $membershipId, 'terminal_id' => $terminalId, 'created_at' => $now, 'updated_at' => $now]);
        }
        $this->syncCore($target, $websiteKey, $actor->id);
        $target->increment('auth_version');
        $fresh = DB::table('fnb_staff_role_scopes')->where('id', $scopeId)->first();
        $this->audit->record('fnb.staff.assigned', $websiteKey, $actor, $target, after: ['role_key' => $roleKey, 'scope_type' => 'outlet', 'scope_key' => $outletId], outletIds: [$outletId]);

        return ['membership_id' => $membershipId, 'display_name' => $target->name, 'role_key' => $roleKey, 'role_name' => $role->name,
            'active' => true, 'binding_status' => 'active', 'binding_version' => (int) $fresh->version, 'membership_version' => $membershipValues['version']];
    }

    public function revoke(Admin $actor, string $websiteKey, int $outletId, Admin $target, int $expectedBindingVersion, int $expectedMembershipVersion): array
    {
        $this->transactionRequired();
        $this->access->authorize($actor, $websiteKey, $outletId, 'fnb.staff.assign');
        $target = $this->target($target);
        $this->assertHqTargetCeiling($actor, $websiteKey, $target);
        $scope = $this->scope($websiteKey, $target->id, 'outlet', $outletId);
        $membership = DB::table('fnb_outlet_staff')->where('website_key', $websiteKey)->where('outlet_id', $outletId)->where('admin_id', $target->id)->lockForUpdate()->first();
        abort_unless($scope && $membership, 404);
        $this->version($scope->version, $expectedBindingVersion, 'binding');
        $this->version($membership->version, $expectedMembershipVersion, 'membership');
        $role = Role::findOrFail($scope->preset_role_id);
        $this->ceiling($actor, $websiteKey, $outletId, $role->key);
        DB::table('fnb_staff_role_scopes')->where('id', $scope->id)->update(['status' => 'revoked', 'revoked_at' => now(), 'version' => $scope->version + 1, 'updated_at' => now()]);
        DB::table('fnb_outlet_staff')->where('id', $membership->id)->update(['is_active' => false, 'is_default' => false, 'revoked_at' => now(), 'revoked_by' => $actor->id, 'version' => $membership->version + 1, 'updated_at' => now()]);
        DB::table('fnb_outlet_staff_terminals')->where('outlet_staff_id', $membership->id)->delete();
        $this->syncCore($target, $websiteKey, $actor->id);
        $target->increment('auth_version');
        $this->audit->record('fnb.staff.revoked', $websiteKey, $actor, $target, after: ['role_key' => $role->key, 'scope_key' => $outletId], outletIds: [$outletId]);

        return ['membership_id' => (int) $membership->id, 'display_name' => $target->name, 'role_key' => $role->key, 'role_name' => $role->name, 'active' => false,
            'binding_status' => 'revoked', 'binding_version' => $scope->version + 1, 'membership_version' => $membership->version + 1];
    }

    public function assignHq(Admin $actor, string $websiteKey, Admin $target, string $roleKey, ?int $expectedVersion): array
    {
        $this->transactionRequired();
        $this->access->authorizeHq($actor, $websiteKey, 'fnb.chain.staff.manage');
        $target = $this->target($target);
        abort_unless(in_array($roleKey, ['fnb-owner', 'fnb-hq-manager'], true), 422);
        $role = $this->role($roleKey);
        $scope = $this->scope($websiteKey, $target->id, 'hq', 0);
        $this->version($scope?->version, $expectedVersion, 'hq');
        $ownerRoleId = Role::where('key', 'fnb-owner')->value('id');
        if ($scope && (int) $scope->preset_role_id === (int) $ownerRoleId && $roleKey !== 'fnb-owner' && ! $this->access->isCoreOwner($actor)
            && DB::table('fnb_staff_role_scopes')->where('website_key', $websiteKey)->where('scope_type', 'hq')->where('status', 'active')->where('preset_role_id', $ownerRoleId)->count() <= 1) {
            throw new FnbConflictException('Không thể hạ quyền chủ chuỗi cuối cùng.');
        }
        $id = $this->saveScope($scope, $websiteKey, $target->id, 'hq', 0, $role->id, $actor->id);
        $this->syncCore($target, $websiteKey, $actor->id);
        $target->increment('auth_version');
        $this->audit->record('fnb.chain.hq.assigned', $websiteKey, $actor, $target, after: ['role_key' => $roleKey]);

        return ['id' => $id, 'display_name' => $target->name, 'role_key' => $roleKey, 'version' => (int) ($scope->version ?? 0) + 1];
    }

    public function revokeHq(Admin $actor, string $websiteKey, int $scopeId, int $expectedVersion): array
    {
        $this->transactionRequired();
        $this->access->authorizeHq($actor, $websiteKey, 'fnb.chain.staff.manage');
        $scope = DB::table('fnb_staff_role_scopes')->where('website_key', $websiteKey)->where('scope_type', 'hq')->where('scope_key', 0)->where('id', $scopeId)->lockForUpdate()->first();
        abort_unless($scope, 404);
        $this->version($scope->version, $expectedVersion, 'hq');
        $target = $this->target(Admin::findOrFail($scope->admin_id));
        $ownerRole = Role::where('key', 'fnb-owner')->value('id');
        if ((int) $scope->preset_role_id === (int) $ownerRole && ! $this->access->isCoreOwner($actor)
            && DB::table('fnb_staff_role_scopes')->where('website_key', $websiteKey)->where('scope_type', 'hq')->where('status', 'active')->where('preset_role_id', $ownerRole)->count() <= 1) {
            throw new FnbConflictException('Không thể thu hồi chủ chuỗi cuối cùng.');
        }
        DB::table('fnb_staff_role_scopes')->where('id', $scopeId)->update(['status' => 'revoked', 'revoked_at' => now(), 'version' => $expectedVersion + 1, 'updated_at' => now()]);
        $this->syncCore($target, $websiteKey, $actor->id);
        $target->increment('auth_version');
        $this->audit->record('fnb.chain.hq.revoked', $websiteKey, $actor, $target, after: ['scope_id' => $scopeId]);

        return ['id' => $scopeId, 'status' => 'revoked', 'version' => $expectedVersion + 1];
    }

    private function syncCore(Admin $target, string $websiteKey, int $actorId): void
    {
        $scopes = DB::table('fnb_staff_role_scopes')->where('website_key', $websiteKey)->where('admin_id', $target->id)->where('status', 'active')->whereNull('revoked_at')->get();
        foreach ($scopes->groupBy('preset_role_id') as $roleId => $group) {
            $assignment = AdminRoleAssignment::query()->firstOrCreate(['admin_id' => $target->id, 'role_id' => $roleId, 'scope_type' => 'website', 'scope_value' => $websiteKey], ['assigned_by' => $actorId]);
            $assignment->forceFill(['expires_at' => null, 'assigned_by' => $actorId])->save();
            DB::table('fnb_staff_role_scopes')->whereIn('id', $group->pluck('id'))->update(['core_assignment_id' => $assignment->id]);
        }
        $roleIds = ModuleRoleDefinition::where('module_key', 'fnb-pos')->pluck('role_id');
        AdminRoleAssignment::query()->where('admin_id', $target->id)->where('scope_type', 'website')->where('scope_value', $websiteKey)
            ->whereIn('role_id', $roleIds)->whereNotIn('role_id', $scopes->pluck('preset_role_id'))->delete();
        // Keep the v0.1 binding as a compatibility/audit mirror; v0.2 authorization uses scopes above.
        $mirror = DB::table('fnb_staff_role_bindings')->where('website_key', $websiteKey)->where('admin_id', $target->id)->first();
        $active = $scopes->first();
        $values = ['status' => $active ? 'active' : 'revoked', 'core_assignment_id' => $active ? DB::table('fnb_staff_role_scopes')->where('id', $active->id)->value('core_assignment_id') : null,
            'assigned_by' => $actorId, 'revoked_at' => $active ? null : now(), 'version' => (int) ($mirror->version ?? 0) + 1, 'updated_at' => now()];
        if ($active) {
            $values['preset_role_id'] = $active->preset_role_id;
        }
        if ($mirror) {
            DB::table('fnb_staff_role_bindings')->where('id', $mirror->id)->update($values);
        } elseif ($active) {
            DB::table('fnb_staff_role_bindings')->insert($values + ['website_key' => $websiteKey, 'admin_id' => $target->id, 'assigned_at' => now(), 'created_at' => now()]);
        }
    }

    private function ceiling(Admin $actor, string $websiteKey, int $outletId, string $roleKey): void
    {
        if ($this->access->isCoreOwner($actor)) {
            return;
        }
        if (in_array($roleKey, ['fnb-owner', 'fnb-hq-manager'], true)) {
            throw new AuthorizationException('Quyền trụ sở phải được cấp qua màn quản trị chuỗi.');
        }
        $actorPermissions = $this->access->permissionsForOutlet($actor, $websiteKey, $outletId);
        $role = $this->role($roleKey);
        $targetPermissions = $this->security->definition((string) ModuleInstallation::where('key', 'fnb-pos')->value('version'))->roles[$role->key]['permissions'];
        if (array_diff($targetPermissions, $actorPermissions) !== []) {
            throw new AuthorizationException('Vai trò vượt quá quyền tại cửa hàng hiện tại.');
        }
        $hq = $this->access->hqPermissions($actor, $websiteKey);
        if ($roleKey === 'fnb-manager' && ! in_array('fnb.chain.manage', $hq, true)) {
            throw new AuthorizationException('Quản lý cửa hàng không được cấp ngang quyền quản lý.');
        }
    }

    private function role(string $key): Role
    {
        $version = (string) ModuleInstallation::where('key', 'fnb-pos')->value('version');
        $definition = $this->security->definition($version);
        abort_unless(isset($definition->roles[$key]), 422);
        $owned = ModuleRoleDefinition::where('module_key', 'fnb-pos')->where('version', $version)->where('role_key', $key)->where('is_active', true)->firstOrFail();
        $role = Role::query()->findOrFail($owned->role_id);
        $expected = $definition->roles[$key]['permissions'];
        $actual = $role->permissions()->where('is_active', true)->pluck('key')->all();
        sort($expected);
        sort($actual);
        if ($role->key !== $key || $role->status !== 'active' || ! $role->is_system || $role->is_assignable || $actual !== $expected || ! hash_equals((string) $owned->definition_hash, $definition->roleDefinitionHash($key))) {
            throw ValidationException::withMessages(['role_key' => ['Preset role không còn khớp phiên bản đã cài.']]);
        }
        foreach ($role->permissions()->get() as $permission) {
            if ($permission->module_key !== 'fnb-pos' || ! $permission->is_active || $permission->risk_level !== ($definition->permissions[$permission->key]['risk_level'] ?? null)) {
                throw ValidationException::withMessages(['role_key' => ['Permission metadata đã lệch immutable definition.']]);
            }
        }

        return $role;
    }

    private function scope(string $website, int $adminId, string $type, int $key): ?object
    {
        return DB::table('fnb_staff_role_scopes')->where('website_key', $website)->where('admin_id', $adminId)->where('scope_type', $type)->where('scope_key', $key)->lockForUpdate()->first();
    }

    private function saveScope(?object $scope, string $website, int $adminId, string $type, int $key, int $roleId, int $actorId): int
    {
        $values = ['preset_role_id' => $roleId, 'status' => 'active', 'revoked_at' => null, 'assigned_by' => $actorId, 'version' => (int) ($scope->version ?? 0) + 1, 'updated_at' => now()];
        if ($scope) {
            DB::table('fnb_staff_role_scopes')->where('id', $scope->id)->update($values);

            return (int) $scope->id;
        }

        return DB::table('fnb_staff_role_scopes')->insertGetId($values + ['website_key' => $website, 'admin_id' => $adminId, 'scope_type' => $type, 'scope_key' => $key, 'created_at' => now()]);
    }

    private function assertHqTargetCeiling(Admin $actor, string $websiteKey, Admin $target): void
    {
        if (DB::table('fnb_staff_role_scopes')->where('website_key', $websiteKey)->where('admin_id', $target->id)->where('scope_type', 'hq')->where('status', 'active')->exists()
            && ! in_array('fnb.chain.staff.manage', $this->access->hqPermissions($actor, $websiteKey), true)) {
            throw new AuthorizationException('Tài khoản trụ sở được bảo vệ khỏi thay đổi của quản lý cửa hàng.');
        }
    }

    private function target(Admin $target): Admin
    {
        $target = Admin::whereKey($target->id)->lockForUpdate()->firstOrFail();
        if (! $target->isAvailable() || $target->isSuperAdmin() || $this->access->isCoreOwner($target)) {
            throw new AuthorizationException('Tài khoản này được bảo vệ khỏi phân quyền F&B.');
        }

        return $target;
    }

    private function version(mixed $actual, ?int $expected, string $kind): void
    {
        if (($actual === null) !== ($expected === null) || ($actual !== null && (int) $actual !== $expected)) {
            throw new FnbConflictException('Phân công đã thay đổi; vui lòng tải lại.', ['current_versions' => [$kind => $actual]]);
        }
    }

    private function transactionRequired(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \RuntimeException('Staff scopes require the protected command transaction.');
        }
    }
}
