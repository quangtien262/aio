<?php

namespace Modules\MinvoiceConnector\Hooks;

use App\Core\Modules\Contracts\ModuleLifecycleHook;
use App\Core\Modules\Support\ModuleLifecycleContext;
use App\Models\Permission;
use App\Models\Role;

class MinvoiceConnectorLifecycleHook implements ModuleLifecycleHook
{
    public function preInstall(ModuleLifecycleContext $context): void {}

    public function preEnable(ModuleLifecycleContext $context): void {}

    public function preDisable(ModuleLifecycleContext $context): void {}

    public function postDisable(ModuleLifecycleContext $context): void {}

    public function preUpgrade(ModuleLifecycleContext $context): void {}

    public function preUninstall(ModuleLifecycleContext $context): void {}

    public function postUninstall(ModuleLifecycleContext $context): void {}

    public function postInstall(ModuleLifecycleContext $context): void
    {
        $this->syncDefaults();
    }

    public function postEnable(ModuleLifecycleContext $context): void
    {
        $this->syncDefaults();
    }

    public function postUpgrade(ModuleLifecycleContext $context): void
    {
        $this->syncDefaults();
    }

    private function syncDefaults(): void
    {
        Permission::query()->where('module_key', 'minvoice-connector')->update(['risk_level' => 'sensitive']);
        Permission::query()->whereIn('key', [
            'minvoice.configure',
            'minvoice.connection.manage',
            'minvoice.outbound.issue',
        ])->update(['risk_level' => 'critical']);

        $role = Role::query()->firstOrCreate(
            ['key' => 'minvoice.operator'],
            ['name' => 'Vận hành Minvoice', 'description' => 'Vận hành connector Minvoice trong phạm vi được phê duyệt.'],
        );
        $role->forceFill(['name' => 'Vận hành Minvoice', 'status' => 'active'])->save();
        $role->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('key', [
                'minvoice.view',
                'minvoice.outbound.preview',
                'minvoice.outbound.sync',
                'minvoice.inbound.sync',
                'minvoice.artifact.download',
                'minvoice.audit.view',
            ])->pluck('id')->all(),
        );
    }
}
