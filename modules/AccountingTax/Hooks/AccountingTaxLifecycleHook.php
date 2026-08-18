<?php

namespace Modules\AccountingTax\Hooks;

use App\Core\Modules\Contracts\ModuleLifecycleHook;
use App\Core\Modules\Support\ModuleLifecycleContext;
use App\Models\Permission;
use App\Models\Role;

class AccountingTaxLifecycleHook implements ModuleLifecycleHook
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
        Permission::query()->where('module_key', 'accounting-tax')->update(['risk_level' => 'sensitive']);
        Permission::query()->whereIn('key', [
            'accounting.organization.manage',
            'accounting.document.approve',
            'accounting.document.post',
            'accounting.document.void',
            'accounting.tax.assess',
            'accounting.period.manage',
            'accounting.inventory.post',
            'accounting.einvoice.issue',
            'accounting.einvoice.configure',
        ])->update(['risk_level' => 'critical']);

        $this->syncRole('accounting.viewer', 'Kế toán - Chỉ xem', [
            'accounting.view',
            'accounting.report.view',
            'accounting.audit.view',
        ]);
        $this->syncRole('accounting.clerk', 'Kế toán viên', [
            'accounting.view',
            'accounting.party.manage',
            'accounting.item.manage',
            'accounting.document.create',
            'accounting.document.update',
            'accounting.document.payment.manage',
            'accounting.export.create',
            'accounting.mail.send',
            'accounting.integration.sync',
        ]);
        $this->syncRole('accounting.approver', 'Kế toán phê duyệt', [
            'accounting.view',
            'accounting.document.approve',
            'accounting.document.post',
            'accounting.document.void',
            'accounting.tax.assess',
            'accounting.period.manage',
            'accounting.inventory.post',
            'accounting.report.view',
            'accounting.audit.view',
        ]);
    }

    /** @param array<int, string> $permissions */
    private function syncRole(string $key, string $name, array $permissions): void
    {
        $role = Role::query()->firstOrCreate(
            ['key' => $key],
            ['name' => $name, 'description' => $name],
        );
        $role->forceFill(['name' => $name, 'status' => 'active'])->save();
        $role->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('key', $permissions)->pluck('id')->all(),
        );
    }
}
