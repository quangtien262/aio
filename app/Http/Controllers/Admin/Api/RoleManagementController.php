<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleManagementController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $role = Role::query()->create([
            'name' => $validated['name'],
            'key' => $validated['key'],
            'description' => $validated['description'] ?? null,
        ]);

        $role->permissions()->sync($validated['permission_ids'] ?? []);

        return response()->json([
            'message' => 'Đã tạo role.',
        ], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $this->validatePayload($request, $role);

        $role->update([
            'name' => $validated['name'],
            'key' => $validated['key'],
            'description' => $validated['description'] ?? null,
        ]);

        $role->permissions()->sync($validated['permission_ids'] ?? []);

        return response()->json([
            'message' => 'Đã cập nhật role.',
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        abort_if($role->key === 'super-admin', 422, 'Không được xóa role super-admin.');

        $role->admins()->detach();
        $role->permissions()->detach();
        $role->delete();

        return response()->json([
            'message' => 'Đã xóa role.',
        ]);
    }

    private function validatePayload(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('roles', 'key')->ignore($role?->id),
            ],
            'description' => ['nullable', 'string'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);
    }
}
