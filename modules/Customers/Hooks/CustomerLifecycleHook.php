<?php

namespace Modules\Customers\Hooks;

use App\Core\Modules\Contracts\ModuleLifecycleHook;
use App\Core\Modules\Support\ModuleLifecycleContext;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Validation\ValidationException;

class CustomerLifecycleHook implements ModuleLifecycleHook
{
    public function preInstall(ModuleLifecycleContext $context): void {}

    public function preEnable(ModuleLifecycleContext $context): void {}

    public function preDisable(ModuleLifecycleContext $context): void {}

    public function postDisable(ModuleLifecycleContext $context): void {}

    public function preUpgrade(ModuleLifecycleContext $context): void {}

    public function postUninstall(ModuleLifecycleContext $context): void {}

    public function postInstall(ModuleLifecycleContext $context): void
    {
        $this->roles();
    }

    public function postEnable(ModuleLifecycleContext $context): void
    {
        $this->roles();
    }

    public function postUpgrade(ModuleLifecycleContext $context): void
    {
        $this->roles();
    }

    public function preUninstall(ModuleLifecycleContext $context): void
    {
        throw ValidationException::withMessages(['module' => 'Lưu giữ hồ sơ và mapping để bảo toàn lịch sử giao dịch.']);
    }

    private function roles(): void
    {
        foreach (['manager' => null, 'editor' => ['view', 'lookup', 'create', 'update', 'sensitive', 'archive', 'consent.manage'], 'reader' => ['view', 'lookup']] as $key => $keys) {
            $role = Role::query()->firstOrCreate(['key' => 'customers.'.$key], ['name' => ['manager' => 'Quản lý khách hàng', 'editor' => 'Nhân viên khách hàng', 'reader' => 'Tra cứu khách hàng'][$key], 'description' => 'Quyền khách hàng kết hợp cấp quyền nhóm dữ liệu.']);
            $permissions = Permission::query()->where('module_key', 'customers')->where('is_active', true);
            if ($keys !== null) {
                $permissions->whereIn('key', array_map(fn ($k) => 'customers.'.$k, $keys));
            }
            $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
        }
    }
}
