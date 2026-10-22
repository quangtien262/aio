<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Modules\ModuleManager;
use App\Core\Modules\TestData\ModuleTestDataService;
use App\Support\AuditLogger;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ModuleLifecycleController
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function install(string $key, ModuleManager $moduleManager): JsonResponse
    {
        $moduleManager->install($key);
        $this->auditLogger->record('module.installed', $key, null, ['module_key' => $key], moduleKey: $key);

        return response()->json([
            'message' => 'Module installed successfully.',
        ]);
    }

    public function enable(string $key, ModuleManager $moduleManager): JsonResponse
    {
        $moduleManager->enable($key);
        $this->auditLogger->record('module.enabled', $key, null, ['module_key' => $key], moduleKey: $key);

        return response()->json([
            'message' => 'Module enabled successfully.',
        ]);
    }

    public function disable(string $key, ModuleManager $moduleManager): JsonResponse
    {
        $moduleManager->disable($key);
        $this->auditLogger->record('module.disabled', $key, null, ['module_key' => $key], moduleKey: $key);

        return response()->json([
            'message' => 'Module disabled successfully.',
        ]);
    }

    public function upgrade(string $key, ModuleManager $moduleManager): JsonResponse
    {
        $moduleManager->upgrade($key);
        $this->auditLogger->record('module.upgraded', $key, null, ['module_key' => $key], moduleKey: $key);

        return response()->json([
            'message' => 'Module upgraded successfully.',
        ]);
    }

    public function demoData(string $key, Request $request, ModuleTestDataService $testData, SiteContext $site): JsonResponse
    {
        $validated = $request->validate([
            'request_id' => ['nullable', 'uuid'],
            'remove_existing' => ['prohibited'],
        ]);
        $batch = $testData->create($key, $site->websiteKey(), $request->user('admin'), $validated['request_id'] ?? (string) Str::uuid());

        return response()->json([
            'message' => 'Đã tạo đợt dữ liệu demo mới.',
            'data' => $batch,
        ]);
    }

    public function uninstall(string $key, ModuleManager $moduleManager): JsonResponse
    {
        $moduleManager->uninstall($key);
        $this->auditLogger->record('module.uninstalled', $key, null, ['module_key' => $key], moduleKey: $key);

        return response()->json([
            'message' => 'Module uninstalled successfully.',
        ]);
    }
}
