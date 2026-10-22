<?php

namespace Modules\Payroll\Services;

use App\Core\Modules\TestData\ModuleTestDataContext;
use App\Core\Modules\TestData\ModuleTestDataProvider;
use Modules\Hrm\Services\HrmTestDataProvider;

final class PayrollTestDataProvider implements ModuleTestDataProvider
{
    public function preview(): array
    {
        return ['Một kỳ lương và phiếu lương TEST ở trạng thái nháp, gắn nhân viên giả lập riêng.', 'Không duyệt, công bố, khóa kỳ hoặc thay bảng lương nhân viên thật.'];
    }

    public function create(ModuleTestDataContext $context): void
    {
        $employee = HrmTestDataProvider::employee($context);
        $period = $context->insert('payroll_periods', ['code' => $context->code('PAYROLL'), 'name' => '[TEST] Kỳ lương mẫu', 'start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->endOfMonth()->toDateString(), 'status' => 'draft']);
        $payslip = $context->insert('payroll_payslips', ['payroll_period_id' => $period, 'employee_id' => $employee, 'base_salary' => '10000000.00', 'allowances' => '500000.00', 'deductions' => '0.00', 'net_salary' => '10500000.00', 'status' => 'draft', 'note' => '[TEST] Số liệu giả lập, không dùng chi trả.']);
        $context->insert('payroll_payslip_lines', ['payslip_id' => $payslip, 'type' => 'allowance', 'code' => $context->code('ALLOWANCE'), 'label' => '[TEST] Phụ cấp mẫu', 'amount' => '500000.00', 'sort_order' => 0]);
    }
}
