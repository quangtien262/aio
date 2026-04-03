<?php

namespace App\Core\Modules;

use App\Core\Modules\Support\ModuleLifecycleContext;
use App\Core\Modules\Support\ModuleLifecycleHooks;
use App\Core\Modules\Support\ModuleLifecycleRunner;
use App\Models\ModuleInstallation;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ModuleManager
{
    public function __construct(
        private readonly ModuleRegistry $moduleRegistry,
        private readonly ModuleLifecycleRunner $lifecycleRunner,
        private readonly ModuleLifecycleHooks $lifecycleHooks,
    ) {
    }

    public function install(string $key): void
    {
        $module = $this->requireActionableModule($key, 'install');
        $installation = $this->resolveInstallation($module);
        $context = ModuleLifecycleContext::forOperation('install', $module, $installation);

        $this->lifecycleHooks->dispatch('preInstall', $context);

        $this->lifecycleRunner->install($module);

        DB::transaction(function () use ($module, $installation): void {
            $this->syncPermissions($module);

            $installation->forceFill([
                'name' => $module['name'],
                'version' => $module['latest_version'],
                'status' => 'installed',
                'website_types' => $module['website_types'] ?? [],
                'dependencies' => $module['dependencies'] ?? [],
                'installed_at' => $installation->installed_at ?? now(),
                'enabled_at' => null,
                'last_upgraded_at' => null,
            ])->save();
        });

        $this->lifecycleHooks->dispatch('postInstall', $context->withInstallation($installation->fresh()));
    }

    public function enable(string $key): void
    {
        $module = $this->requireActionableModule($key, 'enable');
        $installation = $this->resolveInstallation($module);
        $context = ModuleLifecycleContext::forOperation('enable', $module, $installation, $installation->version);

        $this->lifecycleHooks->dispatch('preEnable', $context);

        DB::transaction(function () use ($module, $installation): void {
            $this->syncPermissions($module);

            $installation->forceFill([
                'name' => $module['name'],
                'version' => $module['latest_version'],
                'status' => 'enabled',
                'website_types' => $module['website_types'] ?? [],
                'dependencies' => $module['dependencies'] ?? [],
                'installed_at' => $installation->installed_at ?? now(),
                'enabled_at' => now(),
            ])->save();
        });

        $this->lifecycleHooks->dispatch('postEnable', $context->withInstallation($installation->fresh()));
    }

    public function upgrade(string $key): void
    {
        $module = $this->requireActionableModule($key, 'upgrade');
        $installation = $this->resolveInstallation($module);
        $context = ModuleLifecycleContext::forOperation('upgrade', $module, $installation, $installation->version);

        $this->lifecycleHooks->dispatch('preUpgrade', $context);

        $this->lifecycleRunner->upgrade($module, $installation->version);

        DB::transaction(function () use ($module, $installation): void {
            $this->syncPermissions($module);

            $installation->forceFill([
                'name' => $module['name'],
                'version' => $module['latest_version'],
                'website_types' => $module['website_types'] ?? [],
                'dependencies' => $module['dependencies'] ?? [],
                'last_upgraded_at' => now(),
            ])->save();
        });

        $this->lifecycleHooks->dispatch('postUpgrade', $context->withInstallation($installation->fresh()));
    }

    public function disable(string $key): void
    {
        $module = $this->requireActionableModule($key, 'disable');
        $installation = $this->resolveInstallation($module);
        $context = ModuleLifecycleContext::forOperation('disable', $module, $installation, $installation->version);

        $this->lifecycleHooks->dispatch('preDisable', $context);

        DB::transaction(function () use ($installation): void {
            $installation->forceFill([
                'status' => 'disabled',
                'enabled_at' => null,
            ])->save();
        });

        $this->lifecycleHooks->dispatch('postDisable', $context->withInstallation($installation->fresh()));
    }

    public function uninstall(string $key): void
    {
        $module = $this->requireActionableModule($key, 'uninstall');
        $installation = $this->resolveInstallation($module);
        $context = ModuleLifecycleContext::forOperation('uninstall', $module, $installation, $installation->version);

        $this->lifecycleHooks->dispatch('preUninstall', $context);

        $this->lifecycleRunner->uninstall($module);

        DB::transaction(function () use ($module, $installation): void {
            Permission::query()
                ->where('module_key', $module['key'])
                ->get()
                ->each(function (Permission $permission): void {
                    $permission->roles()->detach();
                    $permission->delete();
                });

            $installation->forceFill([
                'status' => 'available',
                'installed_at' => null,
                'enabled_at' => null,
                'last_upgraded_at' => null,
            ])->save();
        });

        $this->lifecycleHooks->dispatch('postUninstall', $context->withInstallation($installation->fresh()));
    }

    private function requireActionableModule(string $key, string $action): array
    {
        $module = $this->moduleRegistry->find($key);

        abort_if($module === null, 404, 'Module not found.');

        if (($module['available_actions'][$action] ?? false) === true) {
            return $module;
        }

        $message = collect($module['blockers'][$action] ?? [])->first() ?? 'Không thể thực hiện thao tác với module này.';

        throw ValidationException::withMessages([
            'module' => [$message],
        ]);
    }

    private function resolveInstallation(array $module): ModuleInstallation
    {
        return ModuleInstallation::query()->firstOrCreate(
            ['key' => $module['key']],
            [
                'name' => $module['name'],
                'version' => $module['latest_version'],
                'status' => 'available',
                'website_types' => $module['website_types'] ?? [],
                'dependencies' => $module['dependencies'] ?? [],
            ],
        );
    }

    private function syncPermissions(array $module): void
    {
        $permissionKeys = collect($module['permissions'] ?? [])->filter()->values();

        foreach ($module['permissions'] ?? [] as $permissionKey) {
            Permission::query()->updateOrCreate(
                ['key' => $permissionKey],
                [
                    'name' => str($permissionKey)->replace('.', ' ')->title()->toString(),
                    'module_key' => $module['key'],
                ],
            );
        }

        Permission::query()
            ->where('module_key', $module['key'])
            ->whereNotIn('key', $permissionKeys->all())
            ->get()
            ->each(function (Permission $permission): void {
                $permission->roles()->detach();
                $permission->delete();
            });
    }
}
