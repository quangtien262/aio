<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Modules\ModuleRegistry;
use App\Models\ModuleInstallation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ModuleLifecycleController
{
    public function install(string $key, ModuleRegistry $moduleRegistry): JsonResponse
    {
        $module = $this->resolveModule($key, $moduleRegistry);

        $module->forceFill([
            'status' => 'installed',
            'installed_at' => $module->installed_at ?? Carbon::now(),
        ])->save();

        return response()->json([
            'message' => 'Module installed successfully.',
        ]);
    }

    public function enable(string $key, ModuleRegistry $moduleRegistry): JsonResponse
    {
        $module = $this->resolveModule($key, $moduleRegistry);

        $module->forceFill([
            'status' => 'enabled',
            'installed_at' => $module->installed_at ?? Carbon::now(),
            'enabled_at' => Carbon::now(),
        ])->save();

        return response()->json([
            'message' => 'Module enabled successfully.',
        ]);
    }

    public function disable(string $key, ModuleRegistry $moduleRegistry): JsonResponse
    {
        $module = $this->resolveModule($key, $moduleRegistry);

        $module->forceFill([
            'status' => 'disabled',
            'enabled_at' => null,
        ])->save();

        return response()->json([
            'message' => 'Module disabled successfully.',
        ]);
    }

    public function uninstall(string $key, ModuleRegistry $moduleRegistry): JsonResponse
    {
        $module = $this->resolveModule($key, $moduleRegistry);

        $module->forceFill([
            'status' => 'available',
            'installed_at' => null,
            'enabled_at' => null,
        ])->save();

        return response()->json([
            'message' => 'Module uninstalled successfully.',
        ]);
    }

    private function resolveModule(string $key, ModuleRegistry $moduleRegistry): ModuleInstallation
    {
        $manifest = $moduleRegistry->all()->firstWhere('key', $key);

        abort_if($manifest === null, 404, 'Module not found.');

        return ModuleInstallation::query()->firstOrCreate(
            ['key' => $key],
            [
                'name' => $manifest['name'],
                'version' => $manifest['version'],
                'status' => 'available',
                'website_types' => $manifest['website_types'] ?? [],
                'dependencies' => $manifest['dependencies'] ?? [],
            ],
        );
    }
}
