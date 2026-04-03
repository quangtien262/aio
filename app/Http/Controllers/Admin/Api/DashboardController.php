<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\ModuleInstallation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SiteProfile;
use App\Models\ThemeInstallation;
use Illuminate\Http\JsonResponse;

class DashboardController
{
    public function __invoke(): JsonResponse
    {
        $siteProfile = SiteProfile::query()->first();

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
        ]);
    }
}
