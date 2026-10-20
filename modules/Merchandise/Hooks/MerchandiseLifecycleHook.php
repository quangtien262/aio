<?php

namespace Modules\Merchandise\Hooks;

use App\Core\Modules\Contracts\ModuleLifecycleHook;
use App\Core\Modules\Support\ModuleLifecycleContext;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Validation\ValidationException;

class MerchandiseLifecycleHook implements ModuleLifecycleHook
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
        throw ValidationException::withMessages(['module' => 'Giữ danh mục hàng hóa để bảo toàn lịch sử giao dịch.']);
    }

    private function roles(): void
    {
        $role = Role::query()->firstOrCreate(['key' => 'merchandise.manager'], ['name' => 'Quản lý danh mục hàng hóa', 'description' => 'Quản lý master hàng hóa dùng chung.']);
        $role->permissions()->syncWithoutDetaching(Permission::query()->where('module_key', 'merchandise')->where('is_active', true)->pluck('id')->all());
    }
}
