<?php

namespace Modules\FnbPos\Services\Security;

use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\ModuleInstallation;
use App\Models\ModuleRoleDefinition;
use App\Models\Permission;
use App\Models\Role;
use App\Support\AdminPrivilegeGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Models\FnbOutletStaff;
use Modules\FnbPos\Models\FnbStaffRoleBinding;
use Modules\FnbPos\Security\FnbVersionedSecurity;
use RuntimeException;

final class FnbStaffAssignmentService
{
    /** @var array<string,list<string>> */
    private const DELEGATION = [
        'fnb-owner' => [
            'fnb-manager', 'fnb-cashier', 'fnb-waiter', 'fnb-kitchen',
            'fnb-stockkeeper', 'fnb-accountant', 'fnb-viewer',
        ],
        'fnb-manager' => [
            'fnb-cashier', 'fnb-waiter', 'fnb-kitchen',
            'fnb-stockkeeper', 'fnb-accountant', 'fnb-viewer',
        ],
    ];

    public function __construct(
        private readonly FnbOutletAccessService $access,
        private readonly AdminPrivilegeGuard $privileges,
        private readonly FnbAuditLogger $audit,
        private readonly FnbVersionedSecurity $security,
    ) {}

    /** @return array<string,mixed> */
    public function assign(
        Admin $actor,
        string $websiteKey,
        int $outletId,
        Admin $target,
        string $presetRoleKey,
        ?int $expectedBindingVersion = null,
        ?int $expectedMembershipVersion = null,
        array $terminalIds = [],
    ): array {
        $this->requireTransaction();
        $actor = Admin::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
        $this->access->authorize($actor, $websiteKey, $outletId, 'fnb.staff.assign');
        $target = Admin::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
        $this->assertTarget($target);

        [$role, $definition] = $this->exactPreset($presetRoleKey);
        $this->assertDelegationCeiling($actor, $websiteKey, $presetRoleKey, $definition->permission_keys ?? []);

        $binding = FnbStaffRoleBinding::query()
            ->where('website_key', $websiteKey)
            ->where('admin_id', $target->id)
            ->lockForUpdate()
            ->first();
        $memberships = FnbOutletStaff::query()
            ->where('website_key', $websiteKey)
            ->where('admin_id', $target->id)
            ->lockForUpdate()
            ->get();
        $membership = $memberships->firstWhere('outlet_id', $outletId);

        $this->assertExpectedVersion('binding', $binding?->version, $expectedBindingVersion);
        $this->assertExpectedVersion('membership', $membership?->version, $expectedMembershipVersion);
        $this->assertNoLegacyResidue($target, $websiteKey, $binding);
        $this->assertExistingBindingCeiling($actor, $websiteKey, $binding);

        $affectedOutletIds = $memberships
            ->filter(fn (FnbOutletStaff $item): bool => $item->is_active
                && $item->revoked_at === null
                && ($item->expires_at === null || $item->expires_at->isFuture()))
            ->pluck('outlet_id')
            ->push($outletId)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        foreach ($affectedOutletIds as $affectedOutletId) {
            $this->access->authorize($actor, $websiteKey, $affectedOutletId, 'fnb.staff.assign');
        }

        $this->privileges->assertCanDelegateAssignments($actor, [[
            'role_id' => (int) $role->id,
            'scope_type' => 'website',
            'scope_value' => $websiteKey,
        ]]);

        $assignment = $this->upsertCoreAssignment($actor, $target, $websiteKey, $role, $binding);
        $previousRoleId = $binding?->preset_role_id;
        if ($binding === null) {
            $binding = new FnbStaffRoleBinding;
            $binding->website_key = $websiteKey;
            $binding->admin_id = $target->id;
            $binding->version = 0;
        }
        $binding->forceFill([
            'preset_role_id' => $role->id,
            'core_assignment_id' => $assignment->id,
            'status' => 'active',
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
            'replaced_by' => $previousRoleId !== null && (int) $previousRoleId !== (int) $role->id ? $actor->id : $binding->replaced_by,
            'replaced_at' => $previousRoleId !== null && (int) $previousRoleId !== (int) $role->id ? now() : $binding->replaced_at,
            'revoked_by' => null,
            'revoked_at' => null,
            'version' => (int) $binding->version + 1,
        ])->save();

        if ($membership === null) {
            $membership = new FnbOutletStaff;
            $membership->website_key = $websiteKey;
            $membership->outlet_id = $outletId;
            $membership->admin_id = $target->id;
            $membership->version = 0;
        }
        $membership->forceFill([
            'is_active' => true,
            'is_default' => $memberships->where('is_active', true)->isEmpty(),
            'assigned_by' => $actor->id,
            'expires_at' => null,
            'revoked_at' => null,
            'revoked_by' => null,
            'version' => (int) $membership->version + 1,
        ])->save();

        $this->syncTerminals($websiteKey, $outletId, (int) $membership->id, $terminalIds);
        $target->increment('auth_version');

        $this->audit->record(
            'fnb.staff.assigned',
            $websiteKey,
            $actor,
            $target,
            before: ['preset_role_id' => $previousRoleId],
            after: [
                'preset_role_id' => (int) $role->id,
                'role_key' => $presetRoleKey,
                'membership_id' => (int) $membership->id,
                'binding_version' => (int) $binding->version,
                'membership_version' => (int) $membership->version,
            ],
            outletIds: $affectedOutletIds,
        );

        return $this->resource($target, $role, $binding, $membership);
    }

    /** @return array<string,mixed> */
    public function revoke(
        Admin $actor,
        string $websiteKey,
        int $outletId,
        Admin $target,
        int $expectedBindingVersion,
        int $expectedMembershipVersion,
    ): array {
        $this->requireTransaction();
        $actor = Admin::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
        $this->access->authorize($actor, $websiteKey, $outletId, 'fnb.staff.assign');
        $target = Admin::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
        $this->assertTarget($target);

        $binding = FnbStaffRoleBinding::query()
            ->where('website_key', $websiteKey)
            ->where('admin_id', $target->id)
            ->lockForUpdate()
            ->firstOrFail();
        $membership = FnbOutletStaff::query()
            ->where('website_key', $websiteKey)
            ->where('outlet_id', $outletId)
            ->where('admin_id', $target->id)
            ->lockForUpdate()
            ->firstOrFail();
        $this->assertExpectedVersion('binding', $binding->version, $expectedBindingVersion);
        $this->assertExpectedVersion('membership', $membership->version, $expectedMembershipVersion);
        $this->assertNoLegacyResidue($target, $websiteKey, $binding);

        $boundRole = Role::query()->findOrFail($binding->preset_role_id);
        [$role] = $this->exactPreset((string) $boundRole->key);
        if ((int) $role->id !== (int) $binding->preset_role_id) {
            throw new AuthorizationException('F&B role binding không khớp version đang cài.');
        }
        $this->assertDelegationCeiling(
            $actor,
            $websiteKey,
            (string) $role->key,
            $role->permissions()->pluck('permissions.key')->all(),
        );
        $membership->forceFill([
            'is_active' => false,
            'is_default' => false,
            'revoked_at' => now(),
            'revoked_by' => $actor->id,
            'version' => (int) $membership->version + 1,
        ])->save();
        DB::table('fnb_outlet_staff_terminals')->where('outlet_staff_id', $membership->id)->delete();

        $hasOtherMembership = FnbOutletStaff::query()
            ->where('website_key', $websiteKey)
            ->where('admin_id', $target->id)
            ->where('id', '!=', $membership->id)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();

        $bindingValues = ['version' => (int) $binding->version + 1];
        if (! $hasOtherMembership) {
            $assignmentId = $binding->core_assignment_id;
            $bindingValues += [
                'status' => 'revoked',
                'core_assignment_id' => null,
                'revoked_at' => now(),
                'revoked_by' => $actor->id,
            ];
            $binding->forceFill($bindingValues)->save();
            if ($assignmentId !== null) {
                AdminRoleAssignment::query()->whereKey($assignmentId)->delete();
            }
        } else {
            $binding->forceFill($bindingValues)->save();
        }

        $target->increment('auth_version');
        $this->audit->record(
            'fnb.staff.revoked',
            $websiteKey,
            $actor,
            $target,
            before: ['role_key' => $role->key, 'membership_id' => (int) $membership->id],
            after: [
                'binding_status' => $binding->status,
                'binding_version' => (int) $binding->version,
                'membership_version' => (int) $membership->version,
            ],
            outletIds: [$outletId],
        );

        return $this->resource($target, $role, $binding, $membership);
    }

    /** @return array{0:Role,1:ModuleRoleDefinition} */
    private function exactPreset(string $roleKey): array
    {
        $installation = ModuleInstallation::query()->where('key', 'fnb-pos')->lockForUpdate()->firstOrFail();
        if ($installation->status !== 'enabled') {
            throw new AuthorizationException('Module F&B không còn enabled.');
        }

        $definition = ModuleRoleDefinition::query()
            ->where('module_key', 'fnb-pos')
            ->where('role_key', $roleKey)
            ->where('version', $installation->version)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();
        $role = $definition === null ? null : Role::query()->whereKey($definition->role_id)->lockForUpdate()->first();
        if ($definition === null || $role === null || $role->key !== $roleKey
            || $role->status !== 'active' || ! $role->is_system || $role->is_assignable) {
            throw ValidationException::withMessages(['role_key' => ['Preset role không hợp lệ cho version F&B đang cài.']]);
        }

        $immutable = $this->security->definition((string) $installation->version);
        $immutableRole = $immutable->roles[$roleKey] ?? null;
        $expectedPermissionKeys = array_values(array_unique($immutableRole['permissions'] ?? []));
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
        $permissionDrift = $immutableRole === null
            || $expectedPermissionKeys !== $storedPermissionKeys
            || $expectedPermissionKeys !== $actualPermissionKeys
            || $actualPermissions->contains(function (Permission $permission) use ($immutable): bool {
                $expected = $immutable->permissions[$permission->key] ?? null;

                return $expected === null || $permission->module_key !== 'fnb-pos'
                    || ! $permission->is_active || $permission->risk_level !== $expected['risk_level'];
            });
        if ($permissionDrift
            || $definition->custom_role_policy !== $immutable->customRolePolicy
            || $definition->assignment_channel !== $immutable->assignmentChannel
            || ! hash_equals((string) $definition->definition_hash, $immutable->roleDefinitionHash($roleKey))) {
            throw ValidationException::withMessages([
                'role_key' => ['Preset role đã lệch immutable security definition; cần audited reconcile trước.'],
            ]);
        }

        return [$role, $definition];
    }

    /** @param list<string> $targetPermissions */
    private function assertDelegationCeiling(
        Admin $actor,
        string $websiteKey,
        string $targetRoleKey,
        array $targetPermissions,
    ): void {
        if ($this->access->isCoreOwner($actor)) {
            return;
        }

        $binding = $this->access->validBinding($actor, $websiteKey, 'fnb.staff.assign');
        $actorDefinition = $binding === null ? null : ModuleRoleDefinition::query()
            ->where('module_key', 'fnb-pos')
            ->where('role_id', $binding->preset_role_id)
            ->where('is_active', true)
            ->first();
        $allowed = $actorDefinition === null ? [] : (self::DELEGATION[$actorDefinition->role_key] ?? []);
        if (! in_array($targetRoleKey, $allowed, true)) {
            throw new AuthorizationException('Preset role vượt quá privilege ceiling của người phân công.');
        }

        $missing = array_diff($targetPermissions, $actorDefinition->permission_keys ?? []);
        if ($missing !== []) {
            throw new AuthorizationException('Preset role chứa permission vượt quá authority hiện tại.');
        }
    }

    private function assertNoLegacyResidue(Admin $target, string $websiteKey, ?FnbStaffRoleBinding $binding): void
    {
        $fnbRoleIds = ModuleRoleDefinition::query()->where('module_key', 'fnb-pos')->pluck('role_id');
        $assignmentQuery = AdminRoleAssignment::query()
            ->where('admin_id', $target->id)
            ->where(function ($scope) use ($websiteKey): void {
                $scope->where(function ($global): void {
                    $global->where('scope_type', 'global')->whereNull('scope_value');
                })->orWhere(function ($website) use ($websiteKey): void {
                    $website->where('scope_type', 'website')->where('scope_value', $websiteKey);
                });
            })
            ->where(function ($query) use ($fnbRoleIds): void {
                $query->whereIn('role_id', $fnbRoleIds)
                    ->orWhereHas('role.permissions', fn ($permission) => $permission->where('module_key', 'fnb-pos'));
            })
            ->lockForUpdate();
        $assignments = $assignmentQuery->get();

        if ($binding !== null && $binding->status === 'active') {
            $valid = $assignments->filter(fn (AdminRoleAssignment $assignment): bool => (int) $assignment->id === (int) $binding->core_assignment_id
                && (int) $assignment->role_id === (int) $binding->preset_role_id
                && $assignment->scope_type === 'website'
                && $assignment->scope_value === $websiteKey
            );
            if ($valid->count() !== 1 || $assignments->count() !== 1) {
                throw ValidationException::withMessages([
                    'admin' => ['Phát hiện F&B role residue hoặc binding lệch; cần audited reconcile trước.'],
                ]);
            }

            return;
        }

        if ($assignments->isNotEmpty()) {
            throw ValidationException::withMessages([
                'admin' => ['Phát hiện F&B role residue không có binding hợp lệ; cần audited reconcile trước.'],
            ]);
        }
    }

    private function assertExistingBindingCeiling(
        Admin $actor,
        string $websiteKey,
        ?FnbStaffRoleBinding $binding,
    ): void {
        if ($binding === null || $binding->status !== 'active') {
            return;
        }

        $boundRole = Role::query()->find($binding->preset_role_id);
        if ($boundRole === null) {
            throw new AuthorizationException('F&B role binding hiện tại không còn hợp lệ.');
        }
        [$role, $definition] = $this->exactPreset((string) $boundRole->key);
        if ((int) $role->id !== (int) $binding->preset_role_id) {
            throw new AuthorizationException('F&B role binding không khớp version đang cài.');
        }

        $this->assertDelegationCeiling(
            $actor,
            $websiteKey,
            (string) $role->key,
            array_values($definition->permission_keys ?? []),
        );
    }

    private function upsertCoreAssignment(
        Admin $actor,
        Admin $target,
        string $websiteKey,
        Role $role,
        ?FnbStaffRoleBinding $binding,
    ): AdminRoleAssignment {
        $assignment = $binding?->core_assignment_id === null
            ? null
            : AdminRoleAssignment::query()->whereKey($binding->core_assignment_id)->lockForUpdate()->first();
        $assignment ??= new AdminRoleAssignment;
        $assignment->forceFill([
            'admin_id' => $target->id,
            'role_id' => $role->id,
            'scope_type' => 'website',
            'scope_value' => $websiteKey,
            'assigned_by' => $actor->id,
            'expires_at' => null,
        ])->save();

        return $assignment;
    }

    /** @param list<int> $terminalIds */
    private function syncTerminals(string $websiteKey, int $outletId, int $membershipId, array $terminalIds): void
    {
        $terminalIds = collect($terminalIds)->map(fn (mixed $id): int => (int) $id)->unique()->values()->all();
        if ($terminalIds !== []) {
            $count = DB::table('fnb_terminals')
                ->where('website_key', $websiteKey)
                ->where('outlet_id', $outletId)
                ->whereIn('id', $terminalIds)
                ->where('status', 'active')
                ->count();
            if ($count !== count($terminalIds)) {
                throw ValidationException::withMessages(['terminal_ids' => ['Thiết bị không thuộc outlet hoặc không còn active.']]);
            }
        }

        DB::table('fnb_outlet_staff_terminals')->where('outlet_staff_id', $membershipId)->delete();
        foreach ($terminalIds as $terminalId) {
            DB::table('fnb_outlet_staff_terminals')->insert([
                'website_key' => $websiteKey,
                'outlet_id' => $outletId,
                'outlet_staff_id' => $membershipId,
                'terminal_id' => $terminalId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function assertExpectedVersion(string $resource, mixed $actual, ?int $expected): void
    {
        if ($actual === null && $expected === null) {
            return;
        }
        if ($actual === null || $expected === null || (int) $actual !== $expected) {
            throw new FnbConflictException('Dữ liệu phân công đã thay đổi; vui lòng tải lại.', [
                'current_versions' => [$resource => $actual === null ? null : (int) $actual],
            ]);
        }
    }

    private function assertTarget(Admin $target): void
    {
        if (! $target->isAvailable() || $target->isSuperAdmin() || $this->access->isCoreOwner($target)) {
            throw new AuthorizationException('Tài khoản này không thể được phân công qua module F&B.');
        }
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('F&B staff assignment must run inside the command transaction.');
        }
    }

    /** @return array<string,mixed> */
    private function resource(
        Admin $target,
        Role $role,
        FnbStaffRoleBinding $binding,
        FnbOutletStaff $membership,
    ): array {
        return [
            'membership_id' => (int) $membership->id,
            'display_name' => $target->name,
            'role_key' => $role->key,
            'role_name' => $role->name,
            'active' => (bool) $membership->is_active,
            'binding_status' => $binding->status,
            'binding_version' => (int) $binding->version,
            'membership_version' => (int) $membership->version,
        ];
    }
}
