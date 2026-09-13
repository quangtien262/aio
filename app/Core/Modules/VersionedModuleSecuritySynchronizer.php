<?php

namespace App\Core\Modules;

use App\Core\Modules\Contracts\VersionedModuleSecurityProvider;
use App\Core\Modules\Support\ModuleSecurityDefinition;
use App\Models\ModuleRoleDefinition;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class VersionedModuleSecuritySynchronizer
{
    public function supports(array $module): bool
    {
        return filled($module['security_provider'] ?? null);
    }

    public function synchronize(array $module, string $targetVersion): void
    {
        if (! $this->supports($module)) {
            throw new RuntimeException("Module [{$module['key']}] has no versioned security provider.");
        }

        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('Versioned security synchronization must run inside the lifecycle state transaction.');
        }

        if (! Schema::hasTable('module_role_definitions')) {
            throw new RuntimeException('Core module security schema is not installed.');
        }

        $definition = $this->provider($module)->definition($targetVersion);

        if ($definition->version !== $targetVersion) {
            throw new RuntimeException(sprintf(
                'Security provider returned version [%s] while [%s] was requested.',
                $definition->version,
                $targetVersion,
            ));
        }

        $permissionIds = $this->syncPermissions($module['key'], $definition);
        $this->syncRoles($module['key'], $definition, $permissionIds);
        $this->syncFullAccessRoles();
    }

    private function provider(array $module): VersionedModuleSecurityProvider
    {
        $providerClass = (string) ($module['security_provider'] ?? '');

        if ($providerClass === '' || ! class_exists($providerClass)) {
            throw new RuntimeException("Versioned security provider [{$providerClass}] does not exist.");
        }

        $provider = app($providerClass);

        if (! $provider instanceof VersionedModuleSecurityProvider) {
            throw new RuntimeException("Versioned security provider [{$providerClass}] is invalid.");
        }

        return $provider;
    }

    /**
     * @return array<string, int>
     */
    private function syncPermissions(string $moduleKey, ModuleSecurityDefinition $definition): array
    {
        $ids = [];

        foreach ($definition->permissions as $key => $permissionDefinition) {
            $existing = Permission::query()->where('key', $key)->lockForUpdate()->first();

            if ($existing !== null && $existing->module_key !== null && $existing->module_key !== $moduleKey) {
                throw new RuntimeException("Permission [{$key}] is already owned by module [{$existing->module_key}].");
            }

            $permission = Permission::query()->updateOrCreate(
                ['key' => $key],
                [
                    'name' => $permissionDefinition['name'],
                    'description' => $permissionDefinition['description'],
                    'module_key' => $moduleKey,
                    'risk_level' => $permissionDefinition['risk_level'],
                    'is_active' => true,
                    'deprecated_at' => null,
                ],
            );
            $ids[$key] = (int) $permission->getKey();
        }

        Permission::query()
            ->where('module_key', $moduleKey)
            ->whereNotIn('key', $definition->permissionKeys())
            ->update(['is_active' => false, 'deprecated_at' => now()]);

        return $ids;
    }

    /**
     * @param  array<string, int>  $permissionIds
     */
    private function syncRoles(string $moduleKey, ModuleSecurityDefinition $definition, array $permissionIds): void
    {
        $activeRoleKeys = [];

        foreach ($definition->roles as $roleKey => $roleDefinition) {
            $role = Role::query()->where('key', $roleKey)->lockForUpdate()->first();
            $ownership = $role === null ? null : ModuleRoleDefinition::query()
                ->where('role_id', $role->id)
                ->lockForUpdate()
                ->first();

            if ($role !== null && ($ownership === null || $ownership->module_key !== $moduleKey)) {
                throw new RuntimeException("Role key [{$roleKey}] already exists outside module [{$moduleKey}].");
            }

            $role ??= new Role(['key' => $roleKey]);
            $role->forceFill([
                'name' => $roleDefinition['name'],
                'key' => $roleKey,
                'description' => $roleDefinition['description'],
                'is_system' => true,
                'is_assignable' => $definition->assignmentChannel === 'core',
                'status' => 'active',
            ])->save();

            $keys = array_values(array_unique($roleDefinition['permissions']));
            sort($keys);
            $role->permissions()->sync(array_map(fn (string $key): int => $permissionIds[$key], $keys));

            $moduleRole = ModuleRoleDefinition::query()->firstOrNew(
                ['module_key' => $moduleKey, 'role_key' => $roleKey],
            );
            $moduleRole->forceFill([
                'role_id' => $role->id,
                'version' => $definition->version,
                'introduced_version' => $moduleRole->introduced_version ?: $definition->version,
                'retired_version' => null,
                'assignment_channel' => $definition->assignmentChannel,
                'permission_keys' => $keys,
                'definition_hash' => $definition->roleDefinitionHash($roleKey),
                'custom_role_policy' => $definition->customRolePolicy,
                'is_active' => true,
            ])->save();

            $activeRoleKeys[] = $roleKey;
        }

        $staleDefinitions = ModuleRoleDefinition::query()
            ->where('module_key', $moduleKey)
            ->whereNotIn('role_key', $activeRoleKeys)
            ->lockForUpdate()
            ->get();

        foreach ($staleDefinitions as $stale) {
            $stale->role?->forceFill(['status' => 'inactive', 'is_assignable' => false])->save();
            $stale->forceFill([
                'is_active' => false,
                'version' => $definition->version,
                'retired_version' => $definition->version,
            ])->save();
        }
    }

    private function syncFullAccessRoles(): void
    {
        $activePermissionIds = Permission::query()->where('is_active', true)->pluck('id')->all();

        Role::query()
            ->whereIn('key', [Role::SUPER_ADMIN_KEY, Role::PLATFORM_OWNER_KEY])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($activePermissionIds));
    }
}
