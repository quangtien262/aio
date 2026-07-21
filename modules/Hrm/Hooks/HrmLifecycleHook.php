<?php

namespace Modules\Hrm\Hooks;

use App\Core\Modules\Contracts\ModuleLifecycleHook;
use App\Core\Modules\Support\ModuleLifecycleContext;
use App\Models\Permission;
use App\Models\Role;

class HrmLifecycleHook implements ModuleLifecycleHook
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
        Permission::query()->whereIn('key', [
            'hrm.employee.sensitive.view', 'hrm.employee.account.assign', 'hrm.contract.manage', 'hrm.leave.approve',
        ])->update(['risk_level' => 'sensitive']);

        $this->syncRole('hrm.employee-self', 'Nhân viên - Tự phục vụ', 'Xem hồ sơ cá nhân và gửi đơn nghỉ phép.', [
            'hrm.profile.self.view', 'hrm.profile.self.update', 'hrm.leave.request', 'hrm.attendance.self.view',
        ]);
        $this->syncRole('hrm.staff', 'Chuyên viên nhân sự', 'Quản lý hồ sơ, hợp đồng và theo dõi nghỉ phép.', [
            'hrm.dashboard.view', 'hrm.employee.view', 'hrm.employee.create', 'hrm.employee.update',
            'hrm.contract.view', 'hrm.contract.manage', 'hrm.leave.request', 'hrm.leave.team.view', 'hrm.attendance.view', 'hrm.attendance.manage', 'hrm.attendance.self.view', 'hrm.organization.manage', 'hrm.report.view',
        ]);
        $this->syncRole('hrm.manager', 'Quản lý nhân sự', 'Toàn quyền nghiệp vụ trong module quản lý nhân sự.',
            Permission::query()->where('module_key', 'hrm')->where('is_active', true)->pluck('key')->all());
    }

    private function syncRole(string $key, string $name, string $description, array $permissions): void
    {
        $role = Role::query()->firstOrCreate(['key' => $key], ['name' => $name, 'description' => $description]);
        $role->forceFill(['name' => $name, 'description' => $description, 'status' => 'active'])->save();
        $role->permissions()->syncWithoutDetaching(Permission::query()->whereIn('key', $permissions)->pluck('id')->all());
    }
}
