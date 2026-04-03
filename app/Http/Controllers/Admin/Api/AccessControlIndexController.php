<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class AccessControlIndexController
{
    public function __invoke(): JsonResponse
    {
        $roles = Role::query()
            ->with(['permissions:id', 'admins:id'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'key' => $role->key,
                'description' => $role->description,
                'permission_ids' => $role->permissions->pluck('id')->all(),
                'admin_ids' => $role->admins->pluck('id')->all(),
                'permissions_count' => $role->permissions->count(),
                'admins_count' => $role->admins->count(),
            ])
            ->values()
            ->all();

        $permissions = Permission::query()
            ->orderBy('module_key')
            ->orderBy('name')
            ->get(['id', 'name', 'key', 'module_key'])
            ->map(fn (Permission $permission): array => [
                'id' => $permission->id,
                'name' => $permission->name,
                'key' => $permission->key,
                'module_key' => $permission->module_key,
            ])
            ->values()
            ->all();

        $admins = Admin::query()
            ->with(['roles:id'])
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (Admin $admin): array => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role_ids' => $admin->roles->pluck('id')->all(),
                'permissions' => $admin->permissions(),
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'roles' => $roles,
                'permissions' => $permissions,
                'admins' => $admins,
                'scope_types' => config('aio.scope_types', []),
            ],
        ]);
    }
}
