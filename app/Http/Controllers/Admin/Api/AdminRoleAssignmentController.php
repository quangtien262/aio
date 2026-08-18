<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\Admin;
use App\Models\Role;
use App\Support\AdminPrivilegeGuard;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminRoleAssignmentController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AdminPrivilegeGuard $privilegeGuard,
    ) {}

    public function __invoke(Request $request, Admin $admin): JsonResponse
    {
        abort_if($admin->isSystemOwner(), 422, 'Không thể thay đổi vai trò của System Owner.');
        $actor = $request->user('admin');
        abort_unless($actor instanceof Admin, 403);
        $this->privilegeGuard->assertCanManageProtectedAccount($actor, $admin);

        $validated = $request->validate([
            'role_ids' => ['required', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);
        $roleIds = collect($validated['role_ids'])->map(fn ($id): int => (int) $id)->unique()->values();
        abort_unless(Role::query()->whereIn('id', $roleIds)->where('is_assignable', true)->where('status', 'active')->count() === $roleIds->count(), 422, 'Không thể gán vai trò hệ thống.');

        if ($admin->roleAssignments()->where('scope_type', '!=', 'global')->exists()) {
            throw ValidationException::withMessages([
                'role_ids' => ['Endpoint role_ids cũ không hỗ trợ tài khoản có phân quyền theo website. Hãy cập nhật bằng payload assignments có scope tường minh.'],
            ]);
        }

        $assignments = $roleIds
            ->map(fn (int $roleId): array => [
                'role_id' => $roleId,
                'scope_type' => 'global',
                'scope_value' => null,
            ])
            ->all();
        $this->privilegeGuard->assertCanReplaceAssignments($actor, $admin, $assignments);

        $before = $admin->roleAssignments()->get()->toArray();
        DB::transaction(function () use ($actor, $admin, $assignments): void {
            $admin->roleAssignments()->delete();

            foreach ($assignments as $assignment) {
                $admin->roleAssignments()->create([
                    ...$assignment,
                    'assigned_by' => $actor->id,
                ]);
            }

            $admin->increment('auth_version');
        });
        $this->auditLogger->record('admin.roles.updated', $admin, $before, $admin->roleAssignments()->get()->toArray());

        return response()->json(['message' => 'Đã cập nhật vai trò quản trị.']);
    }
}
