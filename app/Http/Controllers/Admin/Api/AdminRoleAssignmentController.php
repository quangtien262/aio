<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\Admin;
use App\Models\Role;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRoleAssignmentController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function __invoke(Request $request, Admin $admin): JsonResponse
    {
        abort_if($admin->isSystemOwner(), 422, 'Không thể thay đổi vai trò của System Owner.');

        $validated = $request->validate([
            'role_ids' => ['required', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);
        $roleIds = collect($validated['role_ids'])->map(fn ($id): int => (int) $id)->unique()->values();
        abort_unless(Role::query()->whereIn('id', $roleIds)->where('is_assignable', true)->where('status', 'active')->count() === $roleIds->count(), 422, 'Không thể gán vai trò hệ thống.');

        $before = $admin->roleAssignments()->get()->toArray();
        $admin->roleAssignments()->delete();

        foreach ($roleIds as $roleId) {
            $admin->roleAssignments()->create([
                'role_id' => $roleId,
                'scope_type' => 'global',
                'scope_value' => null,
                'assigned_by' => $request->user('admin')?->id,
            ]);
        }

        $admin->increment('auth_version');
        $this->auditLogger->record('admin.roles.updated', $admin, $before, $admin->roleAssignments()->get()->toArray());

        return response()->json(['message' => 'Đã cập nhật vai trò quản trị.']);
    }
}
