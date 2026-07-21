<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Modules\ModuleManager;
use App\Core\Modules\ModuleRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use App\Support\AuditLogger;

class ModuleLifecycleController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

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

    public function demoData(string $key, Request $request, ModuleRegistry $moduleRegistry): JsonResponse
    {
        $module = $moduleRegistry->find($key);
        $validated = $request->validate([
            'remove_existing' => ['nullable', 'boolean'],
        ]);
        $removeExisting = $validated['remove_existing'] ?? true;

        abort_if($module === null, 404, 'Module not found.');
        abort_if($key !== 'project', 404, 'Module does not support demo data.');
        abort_if(! ($module['is_installed'] ?? false), 422, 'Cần cài đặt module trước khi tạo data test.');

        $this->runSeeder(
            base_path('modules/Project/database/seeders/ProjectSampleDataSeeder.php'),
            'Modules\\Project\\Database\\Seeders\\ProjectSampleDataSeeder',
            ['remove_existing' => $removeExisting],
        );

        return response()->json([
            'message' => 'Project demo data generated successfully.',
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

    private function runSeeder(string $seederPath, string $seederClass, array $options = []): void
    {
        if (! class_exists($seederClass)) {
            if (! is_file($seederPath)) {
                throw new RuntimeException("Seeder file [{$seederPath}] does not exist.");
            }

            require_once $seederPath;
        }

        if (! class_exists($seederClass)) {
            throw new RuntimeException("Seeder class [{$seederClass}] does not exist.");
        }

        $seeder = new $seederClass();

        if (! $seeder instanceof Seeder) {
            throw new RuntimeException("Seeder class [{$seederClass}] is invalid.");
        }

        if (method_exists($seeder, 'configure')) {
            $seeder->configure($options);
        }

        $seeder->setContainer(app());
        $seeder->__invoke();
    }
}
