<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AdminPrivilegeGuard
{
    /**
     * @param  list<array{role_id: int, scope_type: string, scope_value: ?string}>  $assignments
     */
    public function assertCanDelegateAssignments(Admin $actor, array $assignments, string $errorKey = 'assignments'): void
    {
        if ($assignments === []) {
            return;
        }

        $roles = Role::query()
            ->with(['permissions' => fn ($query) => $query->where('is_active', true)])
            ->whereIn('id', collect($assignments)->pluck('role_id')->unique())
            ->get()
            ->keyBy('id');

        foreach ($assignments as $assignment) {
            $role = $roles->get((int) $assignment['role_id']);

            if (! $role instanceof Role) {
                $this->fail($errorKey, 'Vai trò được phân quyền không tồn tại.');
            }

            $scopeType = $assignment['scope_type'];
            $scopeValue = $assignment['scope_value'];

            if ($scopeType === 'organization') {
                if (blank($scopeValue)) {
                    $this->fail($errorKey, 'Phạm vi pháp nhân phải chọn một pháp nhân kế toán cụ thể.');
                }

                $allowedModuleKeys = config('aio.organization_scope_module_keys', []);
                $invalidPermission = $role->permissions->first(
                    fn (Permission $permission): bool => ! in_array($permission->module_key, $allowedModuleKeys, true),
                );

                if ($invalidPermission !== null) {
                    $this->fail(
                        $errorKey,
                        sprintf(
                            'Vai trò theo pháp nhân chỉ được chứa quyền Kế toán & Thuế/Minvoice; quyền không hợp lệ: %s.',
                            $invalidPermission->key,
                        ),
                    );
                }
            }

            if ($actor->isSuperAdmin()) {
                continue;
            }

            if ($scopeType === 'global' && ! $actor->hasGlobalAssignmentScope()) {
                $this->fail($errorKey, 'Không thể phân quyền toàn cục từ một tài khoản chỉ có phạm vi giới hạn.');
            }

            if ($scopeType === 'website' && (blank($scopeValue) || ! $actor->canAccessWebsite($scopeValue))) {
                $this->fail($errorKey, 'Không thể phân quyền cho website nằm ngoài phạm vi của tài khoản hiện tại.');
            }

            if ($scopeType === 'organization' && ! $actor->canAccessOrganization($scopeValue)) {
                $this->fail($errorKey, 'Không thể phân quyền cho pháp nhân nằm ngoài phạm vi của tài khoản hiện tại.');
            }

            $missingPermission = $role->permissions
                ->first(fn (Permission $permission): bool => ! $actor->canAccess(
                    $permission->key,
                    $scopeType,
                    $scopeValue,
                ));

            if ($missingPermission !== null) {
                $this->fail(
                    $errorKey,
                    sprintf('Không thể cấp vai trò chứa quyền vượt quá quyền hiện có: %s.', $missingPermission->key),
                );
            }
        }
    }

    /**
     * Ensure an account update cannot remove an assignment that the actor could
     * not create themselves, then validate every replacement assignment.
     *
     * @param  list<array{role_id: int, scope_type: string, scope_value: ?string}>  $assignments
     */
    public function assertCanReplaceAssignments(Admin $actor, Admin $target, array $assignments): void
    {
        $this->assertCanDelegateAssignments($actor, $this->assignmentPayload($target), 'assignments');
        $this->assertCanDelegateAssignments($actor, $assignments, 'assignments');
    }

    public function assertCanManageProtectedAccount(Admin $actor, Admin $target): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        $protectedAssignments = $target->roleAssignments()
            ->with('role:id,key')
            ->whereHas('role', fn ($query) => $query
                ->where('status', 'active')
                ->whereIn('key', [Role::SUPER_ADMIN_KEY, Role::PLATFORM_OWNER_KEY]))
            ->get();

        if ($protectedAssignments->isEmpty()) {
            return;
        }

        if ($protectedAssignments->contains(fn (AdminRoleAssignment $assignment): bool => $assignment->role?->key === Role::SUPER_ADMIN_KEY)) {
            abort(403, 'Chỉ Super Admin mới có thể quản lý tài khoản Super Admin khác.');
        }

        $this->assertCanDelegateAssignments(
            $actor,
            $this->assignmentPayload($target, $protectedAssignments),
            'admin',
        );
    }

    /**
     * A role manager may only create or change permission sets that are no more
     * powerful than their own global permission set.
     *
     * @param  list<int>  $permissionIds
     */
    public function assertCanManageRolePermissions(Admin $actor, array $permissionIds, ?Role $existingRole = null): void
    {
        if ($existingRole?->assignments()->where('scope_type', 'organization')->exists()) {
            $invalidPermission = Permission::query()
                ->whereIn('id', $permissionIds)
                ->where(fn ($query) => $query
                    ->whereNull('module_key')
                    ->orWhereNotIn('module_key', config('aio.organization_scope_module_keys', [])))
                ->first();

            if ($invalidPermission !== null) {
                $this->fail(
                    'permission_ids',
                    sprintf(
                        'Vai trò đang được gán theo pháp nhân nên không thể chứa quyền ngoài Kế toán & Thuế/Minvoice: %s.',
                        $invalidPermission->key,
                    ),
                );
            }
        }

        if ($actor->isSuperAdmin()) {
            return;
        }

        $permissionKeys = Permission::query()
            ->where('is_active', true)
            ->whereIn('id', $permissionIds)
            ->pluck('key');

        if ($existingRole !== null) {
            $permissionKeys = $permissionKeys->merge(
                $existingRole->permissions()
                    ->where('is_active', true)
                    ->pluck('key'),
            );
        }

        $missingPermission = $permissionKeys
            ->unique()
            ->first(fn (string $permission): bool => ! $actor->canAccess($permission, 'global'));

        if ($missingPermission !== null) {
            $this->fail(
                'permission_ids',
                sprintf('Không thể quản lý vai trò chứa quyền vượt quá quyền toàn cục hiện có: %s.', $missingPermission),
            );
        }
    }

    /**
     * @param  Collection<int, AdminRoleAssignment>|null  $assignments
     * @return list<array{role_id: int, scope_type: string, scope_value: ?string}>
     */
    private function assignmentPayload(Admin $target, ?Collection $assignments = null): array
    {
        $assignments ??= $target->roleAssignments()->get();

        return $assignments
            ->map(fn (AdminRoleAssignment $assignment): array => [
                'role_id' => (int) $assignment->role_id,
                'scope_type' => $assignment->scope_type,
                'scope_value' => $assignment->scope_value,
            ])
            ->values()
            ->all();
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => [$message]]);
    }
}
