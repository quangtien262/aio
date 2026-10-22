<?php

namespace Modules\Hrm\Services;

use App\Core\Modules\TestData\ModuleTestDataContext;
use App\Core\Modules\TestData\ModuleTestDataProvider;

final class HrmTestDataProvider implements ModuleTestDataProvider
{
    public function preview(): array
    {
        return ['Một phòng ban, chức vụ và nhân viên TEST không gắn tài khoản đăng nhập.', 'Một hợp đồng nháp và đơn nghỉ phép chờ duyệt; không ghi nhận công hoặc phê duyệt.'];
    }

    public function create(ModuleTestDataContext $context): void
    {
        $employee = self::employee($context);
        $context->insert('hrm_contracts', ['employee_id' => $employee, 'contract_number' => $context->code('CONTRACT'), 'contract_type' => 'fixed_term', 'start_date' => now()->toDateString(), 'end_date' => now()->addYear()->toDateString(), 'base_salary' => '10000000.00', 'status' => 'draft', 'note' => '[TEST] Hợp đồng giả lập chưa ký.']);
        $context->insert('hrm_leave_requests', ['employee_id' => $employee, 'leave_type' => 'annual', 'start_date' => now()->addDays(7)->toDateString(), 'end_date' => now()->addDays(7)->toDateString(), 'days' => '1.00', 'reason' => '[TEST] Đơn nghỉ mẫu để kiểm tra quy trình.', 'status' => 'pending']);
    }

    /** Payroll creates its own employee instead of attaching a real employee. */
    public static function employee(ModuleTestDataContext $context): int
    {
        $department = $context->insert('hrm_departments', ['code' => $context->code('DEPT'), 'name' => '[TEST] Phòng ban mẫu', 'is_active' => true]);
        $position = $context->insert('hrm_positions', ['code' => $context->code('POSITION'), 'name' => '[TEST] Chức vụ mẫu', 'is_active' => true]);

        return (int) $context->insert('hrm_employees', ['employee_code' => $context->code('EMPLOYEE'), 'department_id' => $department, 'position_id' => $position, 'full_name' => '[TEST] Nhân viên giả lập', 'work_email' => strtolower($context->code('EMAIL')).'@example.invalid', 'join_date' => now()->toDateString(), 'employment_status' => 'active', 'note' => 'Dữ liệu tổng hợp phục vụ thử nghiệm, không gắn tài khoản thật.']);
    }
}
