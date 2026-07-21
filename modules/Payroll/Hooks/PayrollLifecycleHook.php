<?php

namespace Modules\Payroll\Hooks;

use App\Core\Modules\Contracts\ModuleLifecycleHook;
use App\Core\Modules\Support\ModuleLifecycleContext;
use App\Models\AdminRoleAssignment;
use App\Models\HrmEmployee;
use App\Models\Permission;
use App\Models\Role;

class PayrollLifecycleHook implements ModuleLifecycleHook
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
        Permission::query()->where('module_key', 'payroll')->update(['risk_level' => 'sensitive']);
        Permission::query()->whereIn('key', ['payroll.run.approve', 'payroll.run.publish', 'payroll.run.lock'])
            ->update(['risk_level' => 'critical']);

        $this->syncRole('payroll.employee-self', 'Nhân viên - Xem phiếu lương', ['payroll.payslip.self.view']);
        $this->syncRole('payroll.officer', 'Chuyên viên tiền lương', [
            'payroll.dashboard.view', 'payroll.period.manage', 'payroll.run.calculate', 'payroll.run.review',
            'payroll.payslip.view', 'payroll.report.view',
        ]);
        $this->syncRole('payroll.approver', 'Phê duyệt tiền lương', [
            'payroll.dashboard.view', 'payroll.run.approve', 'payroll.run.publish', 'payroll.run.lock',
            'payroll.payslip.view', 'payroll.report.view',
        ]);

        $selfRole = Role::query()->where('key', 'payroll.employee-self')->firstOrFail();
        HrmEmployee::query()->whereNotNull('admin_id')->pluck('admin_id')->each(function (int $adminId) use ($selfRole): void {
            AdminRoleAssignment::query()->firstOrCreate([
                'admin_id' => $adminId,
                'role_id' => $selfRole->id,
                'scope_type' => 'global',
                'scope_value' => null,
            ]);
        });
    }

    private function syncRole(string $key, string $name, array $permissions): void
    {
        $role = Role::query()->firstOrCreate(['key' => $key], ['name' => $name, 'description' => $name]);
        $role->forceFill(['name' => $name, 'status' => 'active'])->save();
        $role->permissions()->syncWithoutDetaching(Permission::query()->whereIn('key', $permissions)->pluck('id')->all());
    }
}
