<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleManagementController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $role = Role::query()->create([
            'name' => $validated['name'],
            'key' => $validated['key'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
            'is_assignable' => true,
            'status' => 'active',
        ]);

        $role->permissions()->sync($validated['permission_ids'] ?? []);
        $this->auditLogger->record('rbac.role.created', $role, null, $role->load('permissions')->toArray());

        return response()->json(['message' => 'Đã tạo vai trò.'], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        abort_if($role->is_system, 422, 'Vai trò hệ thống không thể chỉnh sửa.');
        abort_if($request->input('key') !== $role->key, 422, 'Key của vai trò không thể thay đổi sau khi tạo.');

        $before = $role->load('permissions')->toArray();
        $validated = $this->validatePayload($request, $role);

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);
        $role->permissions()->sync($validated['permission_ids'] ?? []);
        $this->auditLogger->record('rbac.role.updated', $role, $before, $role->fresh()->load('permissions')->toArray());

        return response()->json(['message' => 'Đã cập nhật vai trò.']);
    }

    public function destroy(Role $role): JsonResponse
    {
        abort_if($role->is_system, 422, 'Vai trò hệ thống không thể xóa.');

        $before = $role->load('permissions')->toArray();
        $role->admins()->detach();
        $role->permissions()->detach();
        $role->delete();
        $this->auditLogger->record('rbac.role.deleted', Role::class, $before);

        return response()->json(['message' => 'Đã xóa vai trò.']);
    }

    private function validatePayload(Request $request, ?Role $role = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('roles', 'key')->ignore($role?->id),
            ],
            'description' => ['nullable', 'string'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $permissionIds = array_values(array_unique(array_map('intval', $validated['permission_ids'] ?? [])));
        $activeCount = Permission::query()->whereIn('id', $permissionIds)->where('is_active', true)->count();
        abort_unless($activeCount === count($permissionIds), 422, 'Chỉ được gán permission đang hoạt động.');
        $validated['permission_ids'] = $permissionIds;

        return $validated;
    }
}
