<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Modules\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCurrentProfileController
{
    public function __invoke(Request $request, ModuleRegistry $moduleRegistry): JsonResponse
    {
        $admin = $request->user('admin');
        $permissions = $admin?->permissions() ?? [];

        return response()->json([
            'data' => [
                'id' => $admin?->id,
                'name' => $admin?->name,
                'email' => $admin?->email,
                'is_active' => (bool) $admin?->is_active,
                'is_locked' => $admin?->isLocked() ?? false,
                'locked_reason' => $admin?->locked_reason,
                'permissions' => $permissions,
                'scopes' => $admin?->scopeMatrix() ?? [],
                'module_navigation' => $moduleRegistry->navigationForPermissions($permissions),
            ],
        ]);
    }
}
