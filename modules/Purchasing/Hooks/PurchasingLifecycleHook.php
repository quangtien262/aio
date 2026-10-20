<?php

namespace Modules\Purchasing\Hooks;

use App\Core\Modules\Contracts\ModuleLifecycleHook;
use App\Core\Modules\Support\ModuleLifecycleContext;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PurchasingLifecycleHook implements ModuleLifecycleHook
{
    public function preInstall(ModuleLifecycleContext $context): void {}

    public function preEnable(ModuleLifecycleContext $context): void {}

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
        throw ValidationException::withMessages(['module' => 'Giữ lịch sử mua hàng và công nợ; không hỗ trợ gỡ module.']);
    }

    public function preDisable(ModuleLifecycleContext $context): void
    {
        if (Schema::hasTable('purchase_orders') && DB::table('purchase_orders')->whereIn('status', ['approved', 'partially_received'])->exists()) {
            throw ValidationException::withMessages(['module' => 'Còn đơn mua đang giao/nhận; hoàn tất hoặc hủy phần còn lại trước khi tắt.']);
        }
    }

    private function roles(): void
    {
        $role = Role::query()->firstOrCreate(['key' => 'purchasing.manager'], ['name' => 'Quản lý mua hàng', 'description' => 'Quản lý nhà cung cấp, đơn mua và công nợ vận hành.']);
        $role->permissions()->syncWithoutDetaching(Permission::query()->where('module_key', 'purchasing')->where('is_active', true)->pluck('id')->all());
    }
}
