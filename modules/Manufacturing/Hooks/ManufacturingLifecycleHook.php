<?php

namespace Modules\Manufacturing\Hooks;

use App\Core\Modules\Contracts\ModuleLifecycleHook;
use App\Core\Modules\ModuleCapabilityChecker;
use App\Core\Modules\Support\ModuleLifecycleContext;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Validation\ValidationException;

class ManufacturingLifecycleHook implements ModuleLifecycleHook
{
    public function preInstall(ModuleLifecycleContext $context): void {}

    public function preUpgrade(ModuleLifecycleContext $context): void {}

    public function preDisable(ModuleLifecycleContext $context): void {}

    public function postDisable(ModuleLifecycleContext $context): void {}

    public function postUninstall(ModuleLifecycleContext $context): void {}

    public function preEnable(ModuleLifecycleContext $context): void
    {
        foreach (['merchandise' => ['merchandise.skus.read.v1', 'merchandise.variants.read.v1', 'merchandise.variants.manage.v1'], 'inventory' => ['inventory.availability.read.v2', 'inventory.trace_units.manage.v1', 'inventory.quality.dispositions.v1', 'inventory.production.reservations.v1', 'inventory.production.post.v1']] as $module => $capabilities) {
            foreach ($capabilities as $capability) {
                if (! app(ModuleCapabilityChecker::class)->has($module, $capability)) {
                    throw ValidationException::withMessages(['module' => 'Chưa sẵn sàng capability bắt buộc: '.$capability]);
                }
            }
        }
    }

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
        throw ValidationException::withMessages(['module' => 'Giữ dữ liệu sản xuất và WIP; không hỗ trợ gỡ lịch sử.']);
    }

    private function roles(): void
    {
        foreach (['manager' => null, 'operator' => ['manufacturing.view', 'manufacturing.execute', 'manufacturing.quality', 'manufacturing.fulfill', 'manufacturing.documents'], 'reader' => ['manufacturing.view']] as $key => $keys) {
            $role = Role::query()->firstOrCreate(['key' => 'manufacturing.'.$key], ['name' => 'Sản xuất: '.$key, 'description' => 'Quyền thao tác cần kết hợp native company/factory membership.']);
            $permissions = Permission::query()->where('module_key', 'manufacturing')->where('is_active', true);
            if ($keys !== null) {
                $permissions->whereIn('key', $keys);
            }
            $role->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
        }
    }
}
