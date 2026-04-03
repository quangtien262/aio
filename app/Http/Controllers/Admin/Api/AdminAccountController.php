<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\Admin;
use App\Models\AdminRoleScope;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class AdminAccountController
{
    public function index(): JsonResponse
    {
        $admins = Admin::query()
            ->with(['roles:id,name', 'roleScopes:id,admin_id,role_id,scope_type,scope_value'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_active', 'locked_at', 'locked_reason', 'last_login_at'])
            ->map(fn (Admin $admin): array => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'is_active' => (bool) $admin->is_active,
                'is_locked' => $admin->isLocked(),
                'locked_reason' => $admin->locked_reason,
                'last_login_at' => $admin->last_login_at?->toIso8601String(),
                'role_ids' => $admin->roles->pluck('id')->all(),
                'roles' => $admin->roles->map(fn ($role): array => ['id' => $role->id, 'name' => $role->name])->values()->all(),
                'scopes' => $admin->roleScopes->map(fn (AdminRoleScope $scope): array => [
                    'id' => $scope->id,
                    'role_id' => $scope->role_id,
                    'scope_type' => $scope->scope_type,
                    'scope_value' => $scope->scope_value,
                ])->values()->all(),
                'permissions' => $admin->permissions(),
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'admins' => $admins,
                'roles' => Role::query()->orderBy('name')->get(['id', 'name', 'key'])->toArray(),
                'scope_types' => config('aio.scope_types', []),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request, requirePassword: true);

        DB::transaction(function () use ($validated): void {
            $admin = Admin::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $this->syncRolesAndScopes($admin, $validated['role_ids'] ?? [], $validated['scopes'] ?? []);
        });

        return response()->json([
            'message' => 'Tạo tài khoản admin thành công.',
        ], 201);
    }

    public function update(Request $request, Admin $admin): JsonResponse
    {
        $validated = $this->validatePayload($request, $admin);

        DB::transaction(function () use ($admin, $validated): void {
            $admin->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $this->syncRolesAndScopes($admin, $validated['role_ids'] ?? [], $validated['scopes'] ?? []);
        });

        return response()->json([
            'message' => 'Cập nhật tài khoản admin thành công.',
        ]);
    }

    public function resetPassword(Request $request, Admin $admin): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $admin->update([
            'password' => $validated['password'],
        ]);

        return response()->json([
            'message' => 'Đã đặt lại mật khẩu admin.',
        ]);
    }

    public function lock(Request $request, Admin $admin): JsonResponse
    {
        abort_if($request->user('admin')?->is($admin), 422, 'Không thể khóa tài khoản admin đang sử dụng.');

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $admin->update([
            'is_active' => false,
            'locked_at' => now(),
            'locked_reason' => $validated['reason'] ?? 'Khóa bởi quản trị viên.',
        ]);

        return response()->json([
            'message' => 'Đã khóa tài khoản admin.',
        ]);
    }

    public function unlock(Admin $admin): JsonResponse
    {
        $admin->update([
            'is_active' => true,
            'locked_at' => null,
            'locked_reason' => null,
        ]);

        return response()->json([
            'message' => 'Đã mở khóa tài khoản admin.',
        ]);
    }

    private function validatePayload(Request $request, ?Admin $admin = null, bool $requirePassword = false): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($admin?->id)],
            'password' => [$requirePassword ? 'required' : 'nullable', 'confirmed', Password::min(8)],
            'is_active' => ['nullable', 'boolean'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'scopes' => ['nullable', 'array'],
            'scopes.*.role_id' => ['required', 'integer', 'exists:roles,id'],
            'scopes.*.scope_type' => ['required', 'string', Rule::in(array_keys(config('aio.scope_types', [])))],
            'scopes.*.scope_value' => ['required', 'string', 'max:255'],
        ]);

        $roleIds = collect($validated['role_ids'] ?? [])->map(fn ($id) => (int) $id)->all();

        foreach ($validated['scopes'] ?? [] as $scope) {
            if (! in_array((int) $scope['role_id'], $roleIds, true)) {
                throw ValidationException::withMessages([
                    'scopes' => ['Scope phải được gắn vào role đã chọn cho admin.'],
                ]);
            }
        }

        return $validated;
    }

    private function syncRolesAndScopes(Admin $admin, array $roleIds, array $scopes): void
    {
        $admin->roles()->sync($roleIds);
        $admin->roleScopes()->delete();

        foreach ($scopes as $scope) {
            $admin->roleScopes()->create([
                'role_id' => $scope['role_id'],
                'scope_type' => $scope['scope_type'],
                'scope_value' => $scope['scope_value'],
            ]);
        }
    }
}
