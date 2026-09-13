<?php

namespace Modules\FnbPos\Hooks;

use App\Core\Modules\Contracts\ModuleLifecycleHook;
use App\Core\Modules\Support\ModuleLifecycleContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class FnbLifecycleHook implements ModuleLifecycleHook
{
    public function preInstall(ModuleLifecycleContext $context): void {}

    public function postInstall(ModuleLifecycleContext $context): void {}

    public function preEnable(ModuleLifecycleContext $context): void {}

    public function postEnable(ModuleLifecycleContext $context): void {}

    public function preUpgrade(ModuleLifecycleContext $context): void {}

    public function postUpgrade(ModuleLifecycleContext $context): void {}

    public function preDisable(ModuleLifecycleContext $context): void
    {
        $blockers = [];
        foreach ([
            ['fnb_shifts', ['closed'], 'Ca thu ngân chưa đóng'],
            ['fnb_service_sessions', ['closed', 'cancelled', 'merged'], 'Phiên phục vụ chưa kết thúc'],
            ['fnb_payments', ['succeeded', 'failed', 'cancelled', 'expired', 'rejected'], 'Thanh toán đang xử lý'],
            ['fnb_refunds', ['succeeded', 'failed', 'cancelled', 'expired', 'rejected'], 'Hoàn tiền đang xử lý'],
            ['fnb_fulfillment_compensations', ['compensated', 'cancelled'], 'Yêu cầu hủy món đã thanh toán chưa hoàn tất'],
        ] as [$table, $terminalStates, $label]) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->whereNotIn('status', $terminalStates)->count();
                if ($count > 0) {
                    $blockers[] = $label.': '.$count.'.';
                }
            }
        }

        if ($blockers !== []) {
            throw ValidationException::withMessages(['module' => $blockers]);
        }
    }

    public function postDisable(ModuleLifecycleContext $context): void {}

    public function preUninstall(ModuleLifecycleContext $context): void
    {
        throw ValidationException::withMessages(['module' => ['Dữ liệu bán hàng được giữ lại; module F&B không hỗ trợ gỡ bỏ.']]);
    }

    public function postUninstall(ModuleLifecycleContext $context): void {}
}
