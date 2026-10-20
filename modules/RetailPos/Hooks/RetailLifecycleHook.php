<?php

namespace Modules\RetailPos\Hooks;

use App\Core\Modules\Contracts\ModuleLifecycleHook;
use App\Core\Modules\ModuleCapabilityChecker;
use App\Core\Modules\Support\ModuleLifecycleContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RetailLifecycleHook implements ModuleLifecycleHook
{
    public function preInstall(ModuleLifecycleContext $context): void {}

    public function postInstall(ModuleLifecycleContext $context): void {}

    public function postEnable(ModuleLifecycleContext $context): void {}

    public function preUpgrade(ModuleLifecycleContext $context): void
    {
        $this->drained();
    }

    public function postUpgrade(ModuleLifecycleContext $context): void {}

    public function postDisable(ModuleLifecycleContext $context): void {}

    public function postUninstall(ModuleLifecycleContext $context): void {}

    public function preEnable(ModuleLifecycleContext $context): void
    {
        foreach (['inventory.availability.read.v2', 'inventory.reservations.manage.v2', 'inventory.movements.post.v2'] as $capability) {
            if (! app(ModuleCapabilityChecker::class)->has('inventory', $capability)) {
                throw ValidationException::withMessages(['module' => ['Inventory chưa sẵn sàng: '.$capability]]);
            }
        }
    }

    public function preDisable(ModuleLifecycleContext $context): void
    {
        $this->drained();
    }

    public function preUninstall(ModuleLifecycleContext $context): void
    {
        throw ValidationException::withMessages(['module' => ['Giữ lịch sử giao dịch; Retail không hỗ trợ gỡ dữ liệu.']]);
    }

    private function drained(): void
    {
        if (Schema::hasTable('retail_shifts') && DB::table('retail_shifts')->whereIn('status', ['open', 'closed'])->exists()) {
            throw ValidationException::withMessages(['module' => ['Cần đóng và đối soát ca trước khi thay đổi trạng thái Retail.']]);
        }
        if (Schema::hasTable('retail_sales') && DB::table('retail_sales')->where('status', 'priced')->exists()) {
            throw ValidationException::withMessages(['module' => ['Cần hoàn tất hoặc hủy các bản tính giá trước khi thay đổi trạng thái Retail.']]);
        }
        if (Schema::hasTable('retail_returns') && DB::table('retail_returns')->where('refund_status', 'pending')->exists()) {
            throw ValidationException::withMessages(['module' => ['Cần xử lý các khoản hoàn tiền đang chờ trước khi thay đổi trạng thái Retail.']]);
        }
    }
}
