<?php

namespace App\Support;

use App\Models\ModuleRoleDefinition;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Prevent module-owned preset/custom-role policies being bypassed by generic RBAC APIs. */
class ModulePermissionAssignmentGuard
{
    /** @param list<int> $permissionIds */
    public function assertGenericRolePermissionsAllowed(
        array $permissionIds,
        ?Role $existingRole = null,
        string $errorKey = 'permission_ids',
    ): void {
        $allPermissionIds = collect($permissionIds)
            ->merge($existingRole?->permissions()->pluck('permissions.id') ?? [])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $moduleKeys = Permission::query()
            ->whereIn('id', $allPermissionIds)
            ->whereNotNull('module_key')
            ->pluck('module_key')
            ->unique()
            ->values();

        foreach ($moduleKeys as $moduleKey) {
            if ($this->customRolePolicy((string) $moduleKey) === 'deny') {
                $this->fail(
                    $errorKey,
                    "Module [{$moduleKey}] không cho phép đưa permission vào vai trò tùy chỉnh.",
                );
            }
        }
    }

    /** @param list<int> $roleIds */
    public function assertGenericRoleAssignmentsAllowed(array $roleIds, string $errorKey = 'assignments'): void
    {
        if ($roleIds === []) {
            return;
        }

        if (Schema::hasTable('module_role_definitions')) {
            $owned = ModuleRoleDefinition::query()
                ->whereIn('role_id', $roleIds)
                ->where('is_active', true)
                ->where('assignment_channel', '!=', 'core')
                ->first();

            if ($owned !== null) {
                $this->fail(
                    $errorKey,
                    "Vai trò [{$owned->role_key}] thuộc module [{$owned->module_key}] và chỉ được gán qua dịch vụ chuyên dụng.",
                );
            }
        }

        $moduleKeys = Permission::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $roleIds))
            ->whereNotNull('module_key')
            ->pluck('module_key')
            ->unique();

        foreach ($moduleKeys as $moduleKey) {
            if ($this->customRolePolicy((string) $moduleKey) === 'deny') {
                $this->fail(
                    $errorKey,
                    "Vai trò chứa permission của module [{$moduleKey}] không thể được gán qua API phân quyền chung.",
                );
            }
        }
    }

    private function customRolePolicy(string $moduleKey): string
    {
        if (Schema::hasTable('module_role_definitions')) {
            $policy = ModuleRoleDefinition::query()
                ->where('module_key', $moduleKey)
                ->where('is_active', true)
                ->value('custom_role_policy');

            if (is_string($policy) && $policy !== '') {
                return $policy;
            }
        }

        // F&B 0.1.x is deliberately fail-closed even for legacy/drifted rows.
        return $moduleKey === 'fnb-pos' ? 'deny' : 'allow';
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => [$message]]);
    }
}
