<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Modules\ModuleManager;
use Illuminate\Http\JsonResponse;

class ModuleLifecycleController
{
    public function install(string $key, ModuleManager $moduleManager): JsonResponse
    {
        $moduleManager->install($key);

        return response()->json([
            'message' => 'Module installed successfully.',
        ]);
    }

    public function enable(string $key, ModuleManager $moduleManager): JsonResponse
    {
        $moduleManager->enable($key);

        return response()->json([
            'message' => 'Module enabled successfully.',
        ]);
    }

    public function disable(string $key, ModuleManager $moduleManager): JsonResponse
    {
        $moduleManager->disable($key);

        return response()->json([
            'message' => 'Module disabled successfully.',
        ]);
    }

    public function upgrade(string $key, ModuleManager $moduleManager): JsonResponse
    {
        $moduleManager->upgrade($key);

        return response()->json([
            'message' => 'Module upgraded successfully.',
        ]);
    }

    public function uninstall(string $key, ModuleManager $moduleManager): JsonResponse
    {
        $moduleManager->uninstall($key);

        return response()->json([
            'message' => 'Module uninstalled successfully.',
        ]);
    }
}
