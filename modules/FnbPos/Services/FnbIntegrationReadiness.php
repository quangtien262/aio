<?php

namespace Modules\FnbPos\Services;

use App\Core\Modules\ModuleCapabilityChecker;

class FnbIntegrationReadiness
{
    public function __construct(private readonly ModuleCapabilityChecker $capabilities) {}

    public function summary(): array
    {
        return collect([
            ['key' => 'inventory', 'name' => 'Kho', 'capability' => 'inventory.documents.write.v2'],
            ['key' => 'accounting-tax', 'name' => 'Kế toán', 'capability' => 'accounting.documents.manage.v1'],
            ['key' => 'hrm', 'name' => 'Nhân sự', 'capability' => 'hrm.identity.read.v1'],
            ['key' => 'payroll', 'name' => 'Tiền lương', 'capability' => 'payroll.work-facts.write.v1'],
        ])->map(function (array $destination): array {
            $module = $this->capabilities->module($destination['key']);
            $supported = $this->capabilities->has($destination['key'], $destination['capability']);

            return $destination + [
                'available' => $module !== null,
                'installed' => (bool) ($module['is_installed'] ?? false),
                'enabled' => $this->capabilities->enabled($destination['key']),
                'capability_supported' => $supported,
                'ready' => false,
                'status' => 'pending',
                'reason' => $supported
                    ? 'Cần cấu hình mapping và kiểm chứng adapter trước khi đồng bộ.'
                    : 'Module đích chưa cung cấp contract đang được yêu cầu.',
            ];
        })->all();
    }
}
