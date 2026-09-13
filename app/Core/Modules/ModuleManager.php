<?php

namespace App\Core\Modules;

use App\Core\Modules\Contracts\OperationalModuleStateProvider;
use App\Core\Modules\Support\ModuleLifecycleContext;
use App\Core\Modules\Support\ModuleLifecycleHooks;
use App\Core\Modules\Support\ModuleLifecycleLease;
use App\Core\Modules\Support\ModuleLifecycleRunner;
use App\Models\ModuleInstallation;
use App\Models\ModuleLifecycleOperation;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionLabel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ModuleManager
{
    public function __construct(
        private readonly ModuleRegistry $moduleRegistry,
        private readonly ModuleLifecycleRunner $lifecycleRunner,
        private readonly ModuleLifecycleHooks $lifecycleHooks,
        private readonly ModuleLifecycleCoordinator $lifecycleCoordinator,
        private readonly VersionedModuleSecuritySynchronizer $securitySynchronizer,
    ) {}

    public function install(string $key): void
    {
        $module = $this->requireActionableModule($key, 'install');
        $installation = $this->resolveInstallation($module);
        $lease = $this->lifecycleCoordinator->begin(
            $key,
            'install',
            null,
            $module['latest_version'],
            $this->actorId(),
        );
        [$module, $installation] = $this->revalidateActionUnderLease($key, 'install', $lease);

        $this->performInstall($module, $installation, $lease);
    }

    public function enable(string $key): void
    {
        $module = $this->requireActionableModule($key, 'enable');
        $installation = $this->resolveInstallation($module);
        $lease = $this->lifecycleCoordinator->begin(
            $key,
            'enable',
            $installation->version,
            $installation->version,
            $this->actorId(),
        );
        [$module, $installation] = $this->revalidateActionUnderLease($key, 'enable', $lease);

        $this->performEnable($module, $installation, $lease);
    }

    public function upgrade(string $key): void
    {
        $module = $this->requireActionableModule($key, 'upgrade');
        $installation = $this->resolveInstallation($module);
        $lease = $this->lifecycleCoordinator->begin(
            $key,
            'upgrade',
            $installation->version,
            $module['latest_version'],
            $this->actorId(),
        );
        [$module, $installation] = $this->revalidateActionUnderLease($key, 'upgrade', $lease);

        $this->performUpgrade($module, $installation, $lease);
    }

    public function disable(string $key): void
    {
        $module = $this->requireActionableModule($key, 'disable');
        $installation = $this->resolveInstallation($module);
        $lease = $this->lifecycleCoordinator->begin(
            $key,
            'disable',
            $installation->version,
            $installation->version,
            $this->actorId(),
        );
        [$module, $installation] = $this->revalidateActionUnderLease($key, 'disable', $lease);

        $this->performDisable($module, $installation, $lease);
    }

    public function uninstall(string $key): void
    {
        $module = $this->requireActionableModule($key, 'uninstall');
        $installation = $this->resolveInstallation($module);
        $lease = $this->lifecycleCoordinator->begin(
            $key,
            'uninstall',
            $installation->version,
            null,
            $this->actorId(),
        );
        [$module, $installation] = $this->revalidateActionUnderLease($key, 'uninstall', $lease);

        $this->performUninstall($module, $installation, $lease);
    }

    /** Resume the same durable operation after an audited stale/recovery decision. */
    public function resume(string $operationId, ?int $initiatedBy = null): void
    {
        $operation = ModuleLifecycleOperation::query()->where('operation_id', $operationId)->firstOrFail();
        $module = $this->moduleRegistry->find($operation->module_key);
        abort_if($module === null, 404, 'Module not found.');
        $installation = $this->resolveInstallation($module);
        $lease = $this->lifecycleCoordinator->resume($operationId, $initiatedBy ?? $this->actorId());

        if ($this->finalizeCommittedResume($module, $installation, $lease)) {
            return;
        }

        match ($lease->operation) {
            'install' => $this->performInstall($module, $installation, $lease),
            'enable' => $this->performEnable($module, $installation, $lease),
            'upgrade' => $this->performUpgrade($module, $installation, $lease),
            'disable' => $this->performDisable($module, $installation, $lease),
            'uninstall' => $this->performUninstall($module, $installation, $lease),
            default => throw new RuntimeException("Unsupported lifecycle operation [{$lease->operation}]."),
        };
    }

    private function performInstall(array $module, ModuleInstallation $installation, ModuleLifecycleLease $lease): void
    {
        $phase = 'preparing';
        $context = $this->context($lease, $module, $installation);

        try {
            $this->prepareOperationalState($module, $lease);
            $this->lifecycleHooks->dispatch('preInstall', $context);
            $phase = 'migrating';
            $this->lifecycleCoordinator->heartbeat($lease, $phase);
            $this->lifecycleRunner->install($module);
            $phase = 'committing';
            $this->lifecycleCoordinator->heartbeat($lease, $phase);

            DB::transaction(function () use ($module, $installation, $lease): void {
                $locked = $this->lockInstallation($installation);
                $this->syncSecurity($module, (string) $lease->targetVersion);
                $this->commitOperationalState($module, $lease);
                $locked->forceFill([
                    'name' => $module['name'],
                    'version' => $lease->targetVersion,
                    'status' => 'installed',
                    'website_types' => $module['website_types'] ?? [],
                    'dependencies' => $module['dependencies'] ?? [],
                    'installed_at' => $locked->installed_at ?? now(),
                    'enabled_at' => null,
                    'last_upgraded_at' => null,
                ])->save();
                $this->markStateCommitted($lease, $locked);
            }, 3);

            $this->lifecycleCoordinator->complete($lease);
        } catch (Throwable $exception) {
            $this->failOperation($module, $lease, $exception, in_array($phase, ['migrating', 'committing'], true));
            throw $exception;
        }

        $this->dispatchPostHook('postInstall', $context->withInstallation($installation->fresh()), $lease);
    }

    private function performEnable(array $module, ModuleInstallation $installation, ModuleLifecycleLease $lease): void
    {
        $phase = 'preparing';
        $context = $this->context($lease, $module, $installation);

        try {
            $this->prepareOperationalState($module, $lease);
            $this->lifecycleHooks->dispatch('preEnable', $context);
            $phase = 'committing';
            $this->lifecycleCoordinator->heartbeat($lease, $phase);

            DB::transaction(function () use ($module, $installation, $lease): void {
                $locked = $this->lockInstallation($installation);
                $installedVersion = (string) $locked->version;
                $this->syncSecurity($module, $installedVersion);
                $this->commitOperationalState($module, $lease);
                $locked->forceFill([
                    'name' => $module['name'],
                    'version' => $installedVersion,
                    'status' => 'enabled',
                    'website_types' => $module['website_types'] ?? [],
                    'dependencies' => $module['dependencies'] ?? [],
                    'installed_at' => $locked->installed_at ?? now(),
                    'enabled_at' => now(),
                ])->save();
                $this->markStateCommitted($lease, $locked);
            }, 3);

            $this->lifecycleCoordinator->complete($lease);
        } catch (Throwable $exception) {
            $this->failOperation($module, $lease, $exception, false);
            throw $exception;
        }

        $this->dispatchPostHook('postEnable', $context->withInstallation($installation->fresh()), $lease);
    }

    private function performUpgrade(array $module, ModuleInstallation $installation, ModuleLifecycleLease $lease): void
    {
        $phase = 'preparing';
        $context = $this->context($lease, $module, $installation);

        try {
            $this->prepareOperationalState($module, $lease);
            $this->lifecycleHooks->dispatch('preUpgrade', $context);
            $phase = 'migrating';
            $this->lifecycleCoordinator->heartbeat($lease, $phase);
            $this->lifecycleRunner->upgrade($module, $lease->fromVersion);
            $phase = 'committing';
            $this->lifecycleCoordinator->heartbeat($lease, $phase);

            DB::transaction(function () use ($module, $installation, $lease): void {
                $locked = $this->lockInstallation($installation);
                $this->syncSecurity($module, (string) $lease->targetVersion);
                $this->commitOperationalState($module, $lease);
                $locked->forceFill([
                    'name' => $module['name'],
                    'version' => $lease->targetVersion,
                    'website_types' => $module['website_types'] ?? [],
                    'dependencies' => $module['dependencies'] ?? [],
                    'last_upgraded_at' => now(),
                ])->save();
                $this->markStateCommitted($lease, $locked);
            }, 3);

            $this->lifecycleCoordinator->complete($lease);
        } catch (Throwable $exception) {
            $this->failOperation($module, $lease, $exception, in_array($phase, ['migrating', 'committing'], true));
            throw $exception;
        }

        $this->dispatchPostHook('postUpgrade', $context->withInstallation($installation->fresh()), $lease);
    }

    private function performDisable(array $module, ModuleInstallation $installation, ModuleLifecycleLease $lease): void
    {
        $phase = 'preparing';
        $context = $this->context($lease, $module, $installation);

        try {
            $this->prepareOperationalState($module, $lease);
            $this->lifecycleHooks->dispatch('preDisable', $context);
            $phase = 'committing';
            $this->lifecycleCoordinator->heartbeat($lease, $phase);

            DB::transaction(function () use ($module, $installation, $lease): void {
                $locked = $this->lockInstallation($installation);
                Permission::query()->where('module_key', $module['key'])->update(['is_active' => false]);
                $this->commitOperationalState($module, $lease);
                $locked->forceFill(['status' => 'disabled', 'enabled_at' => null])->save();
                $this->markStateCommitted($lease, $locked);
            }, 3);

            $this->lifecycleCoordinator->complete($lease);
        } catch (Throwable $exception) {
            $this->failOperation($module, $lease, $exception, false);
            throw $exception;
        }

        $this->dispatchPostHook('postDisable', $context->withInstallation($installation->fresh()), $lease);
    }

    private function performUninstall(array $module, ModuleInstallation $installation, ModuleLifecycleLease $lease): void
    {
        $phase = 'preparing';
        $context = $this->context($lease, $module, $installation);

        try {
            $this->prepareOperationalState($module, $lease);
            $this->lifecycleHooks->dispatch('preUninstall', $context);
            $phase = 'migrating';
            $this->lifecycleCoordinator->heartbeat($lease, $phase);
            $this->lifecycleRunner->uninstall($module);
            $phase = 'committing';
            $this->lifecycleCoordinator->heartbeat($lease, $phase);

            DB::transaction(function () use ($module, $installation, $lease): void {
                $locked = $this->lockInstallation($installation);
                Permission::query()->where('module_key', $module['key'])->update([
                    'is_active' => false,
                    'deprecated_at' => now(),
                ]);
                $this->commitOperationalState($module, $lease);
                $locked->forceFill([
                    'status' => 'available',
                    'installed_at' => null,
                    'enabled_at' => null,
                    'last_upgraded_at' => null,
                ])->save();
                $this->markStateCommitted($lease, $locked);
            }, 3);

            $this->lifecycleCoordinator->complete($lease);
        } catch (Throwable $exception) {
            $this->failOperation($module, $lease, $exception, in_array($phase, ['migrating', 'committing'], true));
            throw $exception;
        }

        $this->dispatchPostHook('postUninstall', $context->withInstallation($installation->fresh()), $lease);
    }

    private function requireActionableModule(string $key, string $action): array
    {
        $module = $this->moduleRegistry->find($key);
        abort_if($module === null, 404, 'Module not found.');

        if (($module['available_actions'][$action] ?? false) === true) {
            return $module;
        }

        throw ValidationException::withMessages([
            'module' => [collect($module['blockers'][$action] ?? [])->first() ?? 'Không thể thực hiện thao tác với module này.'],
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

    /** @return array{0:array<string,mixed>,1:ModuleInstallation} */
    private function revalidateActionUnderLease(
        string $key,
        string $action,
        ModuleLifecycleLease $lease,
    ): array {
        try {
            $module = $this->requireActionableModule($key, $action);

            return [$module, $this->resolveInstallation($module)];
        } catch (Throwable $exception) {
            $this->lifecycleCoordinator->fail($lease, $exception, false);

            throw $exception;
        }
    }

    private function lockInstallation(ModuleInstallation $installation): ModuleInstallation
    {
        return ModuleInstallation::query()->whereKey($installation->getKey())->lockForUpdate()->firstOrFail();
    }

    private function syncSecurity(array $module, string $targetVersion): void
    {
        if ($this->securitySynchronizer->supports($module)) {
            $this->securitySynchronizer->synchronize($module, $targetVersion);

            return;
        }

        $this->syncLegacyPermissions($module);
    }

    private function prepareOperationalState(array $module, ModuleLifecycleLease $lease): void
    {
        $this->operationalStateProvider($module)?->prepare($lease);
    }

    private function commitOperationalState(array $module, ModuleLifecycleLease $lease): void
    {
        $this->operationalStateProvider($module)?->commit($lease);
    }

    private function failOperation(
        array $module,
        ModuleLifecycleLease $lease,
        Throwable $exception,
        bool $recoveryRequired,
    ): void {
        if (! $recoveryRequired) {
            try {
                $this->operationalStateProvider($module)?->compensate($lease);
            } catch (Throwable $compensationException) {
                report($compensationException);
                $recoveryRequired = true;
            }
        }

        $this->lifecycleCoordinator->fail($lease, $exception, $recoveryRequired);
    }

    private function operationalStateProvider(array $module): ?OperationalModuleStateProvider
    {
        $providerClass = (string) ($module['lifecycle_state_provider'] ?? '');
        if ($providerClass === '') {
            return null;
        }
        if (! class_exists($providerClass)) {
            throw new RuntimeException("Lifecycle state provider [{$providerClass}] does not exist.");
        }

        $provider = app($providerClass);
        if (! $provider instanceof OperationalModuleStateProvider) {
            throw new RuntimeException("Lifecycle state provider [{$providerClass}] is invalid.");
        }

        return $provider;
    }

    private function markStateCommitted(ModuleLifecycleLease $lease, ModuleInstallation $installation): void
    {
        $this->lifecycleCoordinator->markStateCommitted($lease, [
            'installation_id' => (int) $installation->getKey(),
            'status' => (string) $installation->status,
            'version' => (string) $installation->version,
        ]);
    }

    private function finalizeCommittedResume(
        array $module,
        ModuleInstallation $installation,
        ModuleLifecycleLease $lease,
    ): bool {
        $operation = ModuleLifecycleOperation::query()
            ->where('operation_id', $lease->operationId)
            ->firstOrFail();
        $state = $operation->context['lifecycle_commit']['state'] ?? null;
        if (! is_array($state)) {
            return false;
        }

        $installation = $installation->fresh();
        $matches = $installation instanceof ModuleInstallation
            && (int) ($state['installation_id'] ?? 0) === (int) $installation->getKey()
            && hash_equals((string) ($state['status'] ?? ''), (string) $installation->status)
            && hash_equals((string) ($state['version'] ?? ''), (string) $installation->version);
        if (! $matches) {
            $exception = new RuntimeException('Committed module lifecycle state no longer matches its durable recovery marker.');
            $this->lifecycleCoordinator->fail($lease, $exception, true);

            throw $exception;
        }

        $context = $this->context($lease, $module, $installation);
        $this->lifecycleCoordinator->complete($lease, [
            'recovery' => ['finalized_committed_state_at' => now()->toIso8601String()],
        ]);
        $this->dispatchPostHook('post'.ucfirst($lease->operation), $context, $lease);

        return true;
    }

    private function syncLegacyPermissions(array $module): void
    {
        $permissionKeys = collect($module['permissions'] ?? [])->filter()->values();

        foreach ($module['permissions'] ?? [] as $permissionKey) {
            Permission::query()->updateOrCreate(
                ['key' => $permissionKey],
                [
                    'name' => PermissionLabel::make($permissionKey),
                    'module_key' => $module['key'],
                    'is_active' => true,
                    'deprecated_at' => null,
                ],
            );
        }

        Permission::query()
            ->where('module_key', $module['key'])
            ->whereNotIn('key', $permissionKeys->all())
            ->update(['is_active' => false, 'deprecated_at' => now()]);

        $activePermissionIds = Permission::query()->where('is_active', true)->pluck('id')->all();
        Role::query()
            ->whereIn('key', [Role::SUPER_ADMIN_KEY, Role::PLATFORM_OWNER_KEY])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($activePermissionIds));
    }

    private function context(ModuleLifecycleLease $lease, array $module, ModuleInstallation $installation): ModuleLifecycleContext
    {
        return ModuleLifecycleContext::forOperation(
            $lease->operation,
            $module,
            $installation,
            $lease->fromVersion,
            $lease->operationId,
        );
    }

    private function dispatchPostHook(string $phase, ModuleLifecycleContext $context, ModuleLifecycleLease $lease): void
    {
        try {
            $this->lifecycleHooks->dispatch($phase, $context);
        } catch (Throwable $exception) {
            $this->lifecycleCoordinator->recordPostHookFailure($lease->operationId, $exception);
            report($exception);
        }
    }

    private function actorId(): ?int
    {
        $id = auth('admin')->id();

        return $id === null ? null : (int) $id;
    }
}
