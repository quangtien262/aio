<?php

namespace App\Http\Controllers\Admin\Api\Payroll;

use App\Models\HrmEmployee;
use App\Models\PayrollPayslip;
use App\Models\PayrollPeriod;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollPayslipController
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $query = PayrollPayslip::query()->with(['period', 'employee.department', 'lines'])->latest('id');
        if ($request->filled('payroll_period_id')) {
            $query->where('payroll_period_id', $request->integer('payroll_period_id'));
        }

        return response()->json(['data' => [
            'items' => $query->get(),
            'periods' => PayrollPeriod::query()->latest('start_date')->get(),
            'employees' => HrmEmployee::query()->whereIn('employment_status', ['active', 'probation', 'leave'])->orderBy('full_name')->get(['id', 'employee_code', 'full_name']),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $period = PayrollPeriod::query()->findOrFail($data['payroll_period_id']);
        abort_if($period->status !== 'draft', 422, 'Chỉ có thể nhập phiếu lương cho kỳ đang ở trạng thái nháp.');
        $employee = HrmEmployee::query()->findOrFail($data['employee_id']);
        $payslip = DB::transaction(function () use ($data, $employee): PayrollPayslip {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);
            $data['net_salary'] = (float) $data['base_salary'] + (float) $data['allowances'] - (float) $data['deductions'];
            $data['status'] = 'draft';
            $data['snapshot'] = $employee->only(['employee_code', 'full_name', 'department_id', 'position_id']);
            $payslip = PayrollPayslip::query()->updateOrCreate(
                ['payroll_period_id' => $data['payroll_period_id'], 'employee_id' => $data['employee_id']],
                $data,
            );
            $payslip->lines()->delete();
            foreach ($lines as $index => $line) {
                $payslip->lines()->create($line + ['sort_order' => $index + 1]);
            }

            return $payslip;
        });
        $this->auditLogger->record('payroll.payslip.saved', $payslip, null, $payslip->fresh('lines')->toArray(), moduleKey: 'payroll');

        return response()->json(['message' => 'Đã lưu phiếu lương.', 'data' => $payslip->fresh(['period', 'employee', 'lines'])], 201);
    }

    public function self(Request $request): JsonResponse
    {
        $employee = HrmEmployee::query()->where('admin_id', $request->user('admin')->id)->first();
        abort_if(! $employee, 404, 'Tài khoản chưa được liên kết với hồ sơ nhân sự.');
        $items = PayrollPayslip::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['published', 'locked'])
            ->with(['period', 'lines'])
            ->latest('id')
            ->get();

        return response()->json(['data' => $items]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'payroll_period_id' => ['required', 'integer', 'exists:payroll_periods,id'],
            'employee_id' => ['required', 'integer', 'exists:hrm_employees,id'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'allowances' => ['required', 'numeric', 'min:0'],
            'deductions' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['nullable', 'array'],
            'lines.*.type' => ['required', 'in:earning,deduction'],
            'lines.*.code' => ['required', 'string', 'max:80'],
            'lines.*.label' => ['required', 'string', 'max:255'],
            'lines.*.amount' => ['required', 'numeric'],
        ]);
    }
}
