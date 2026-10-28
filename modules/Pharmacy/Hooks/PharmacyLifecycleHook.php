<?php

namespace Modules\Pharmacy\Hooks;

use App\Core\Modules\Contracts\ModuleLifecycleHook;
use App\Core\Modules\ModuleCapabilityChecker;
use App\Core\Modules\ModuleRegistry;
use App\Core\Modules\Support\ModuleLifecycleContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class PharmacyLifecycleHook implements ModuleLifecycleHook
{
    public function preInstall(ModuleLifecycleContext $context): void {}

    public function preUpgrade(ModuleLifecycleContext $context): void {}

    public function preEnable(ModuleLifecycleContext $context): void
    {
        foreach (['merchandise' => ['merchandise.skus.read.v1'], 'inventory' => ['inventory.availability.read.v2', 'inventory.reservations.manage.v2', 'inventory.managed_stock.v1']] as $module => $capabilities) {
            foreach ($capabilities as $capability) {
                if (! app(ModuleCapabilityChecker::class)->has($module, $capability)) {
                    $name = app(ModuleRegistry::class)->find($module)['name'] ?? $module;
                    throw ValidationException::withMessages(['module' => 'Cần cài đặt hoặc cập nhật và bật ứng dụng “'.$name.'” trước khi bật Nhà thuốc.']);
                }
            }
        }
        foreach (['ph_chains', 'ph_prescriptions', 'ph_dispensings', 'ph_sales', 'inv_stock_holds'] as $table) {
            if (! Schema::hasTable($table)) {
                throw ValidationException::withMessages(['module' => 'Schema Nhà thuốc chưa sẵn sàng: '.$table]);
            }
        }
    }

    public function preDisable(ModuleLifecycleContext $context): void
    {
        if (Schema::hasTable('ph_integration_attempts') && DB::table('ph_integration_attempts')->whereIn('status', ['processing', 'uncertain'])->exists()) {
            throw ValidationException::withMessages(['module' => 'Cần chờ kết nối đang xử lý và đối soát yêu cầu chưa rõ kết quả trước khi tắt app.']);
        }
        if (Schema::hasTable('ph_dispensings') && DB::table('ph_dispensings')->whereNotIn('status', ['handed_over', 'cancelled', 'returned', 'completed', 'draft'])->exists()) {
            throw ValidationException::withMessages(['module' => 'Cần xử lý hồ sơ cấp thuốc đang thực hiện trước khi tắt app.']);
        }
    }

    public function preUninstall(ModuleLifecycleContext $context): void
    {
        throw ValidationException::withMessages(['module' => 'Giữ lịch sử nhà thuốc; không hỗ trợ gỡ dữ liệu nghiệp vụ.']);
    }

    public function postInstall(ModuleLifecycleContext $context): void {}

    public function postEnable(ModuleLifecycleContext $context): void {}

    public function postUpgrade(ModuleLifecycleContext $context): void {}

    public function postDisable(ModuleLifecycleContext $context): void {}

    public function postUninstall(ModuleLifecycleContext $context): void {}
}
