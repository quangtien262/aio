<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Modules\ModuleRegistry;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\ModuleInstallation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SiteProfile;
use App\Models\ThemeInstallation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController
{
    public function __invoke(Request $request, ModuleRegistry $moduleRegistry): JsonResponse
    {
        $siteProfile = SiteProfile::query()->first();
        $permissions = $request->user('admin')?->permissions() ?? [];
        $activeModules = $moduleRegistry->all()
            ->where('status', 'enabled')
            ->map(function (array $module) use ($permissions): array {
                $menus = collect($module['menus'] ?? [])
                    ->filter(fn (array $menu): bool => empty($menu['permission']) || in_array($menu['permission'], $permissions, true))
                    ->values()
                    ->all();

                return [
                    'key' => $module['key'],
                    'name' => $module['name'],
                    'description' => $module['description'],
                    'status' => $module['status'],
                    'icon' => $menus[0]['icon'] ?? 'appstore',
                    'color' => $menus[0]['color'] ?? 'geekblue',
                    'route' => $menus[0]['route'] ?? "/admin/modules/{$module['key']}",
                    'website_types' => $module['website_types'] ?? [],
                    'installed_version' => $module['installed_version'],
                    'latest_version' => $module['latest_version'],
                    'menus' => $menus,
                ];
            })
            ->filter(fn (array $module): bool => ! empty($module['menus']))
            ->values()
            ->all();

        return response()->json([
            'metrics' => [
                'admins' => Admin::query()->count(),
                'customers' => Customer::query()->count(),
                'roles' => Role::query()->count(),
                'permissions' => Permission::query()->count(),
                'modules' => ModuleInstallation::query()->count(),
                'themes' => ThemeInstallation::query()->count(),
            ],
            'setup' => [
                'website_type' => $siteProfile?->website_type,
                'active_theme_key' => $siteProfile?->active_theme_key,
                'is_setup_completed' => (bool) $siteProfile?->is_setup_completed,
                'completed_steps' => $siteProfile?->completed_steps ?? [],
            ],
            'active_modules' => $activeModules,
        ]);
    }
}
