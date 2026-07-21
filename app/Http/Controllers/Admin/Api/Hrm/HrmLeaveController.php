<?php

namespace App\Http\Controllers\Admin\Api\Hrm;

use App\Models\HrmEmployee;
use App\Models\HrmLeaveRequest;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrmLeaveController
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $admin = $request->user('admin');
        $canViewTeam = $admin->hasPermission('hrm.leave.team.view') || $admin->hasPermission('hrm.leave.approve');
        $employee = HrmEmployee::query()->where('admin_id', $admin->id)->first();
        $query = HrmLeaveRequest::query()->with(['employee.department', 'reviewer:id,name'])->latest('id');

        if (! $canViewTeam) {
            $query->where('employee_id', $employee?->id ?? 0);
        }

        return response()->json(['data' => [
            'items' => $query->get(),
            'current_employee' => $employee,
            'employees' => $canViewTeam
                ? HrmEmployee::query()->whereIn('employment_status', ['active', 'probation', 'leave'])->orderBy('full_name')->get(['id', 'employee_code', 'full_name'])
                : collect(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $admin = $request->user('admin');
        $canCreateForOthers = $admin->hasPermission('hrm.leave.team.view') || $admin->hasPermission('hrm.leave.approve');
        $ownEmployee = HrmEmployee::query()->where('admin_id', $admin->id)->first();
        $data = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:hrm_employees,id'],
            'leave_type' => ['required', Rule::in(['annual', 'sick', 'unpaid', 'maternity', 'paternity', 'other'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'days' => ['required', 'numeric', 'min:0.5', 'max:366'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $employeeId = $canCreateForOthers && ! empty($data['employee_id']) ? (int) $data['employee_id'] : $ownEmployee?->id;
        abort_if(! $employeeId, 422, 'Tài khoản chưa được liên kết với hồ sơ nhân sự.');
        $data['employee_id'] = $employeeId;
        $data['status'] = 'pending';
        $leave = HrmLeaveRequest::query()->create($data);
        $this->auditLogger->record('hrm.leave.requested', $leave, null, $leave->toArray(), moduleKey: 'hrm');

        return response()->json(['message' => 'Đã gửi đơn nghỉ phép.', 'data' => $leave->load('employee')], 201);
    }

    public function review(Request $request, HrmLeaveRequest $leaveRequest): JsonResponse
    {
        abort_if($leaveRequest->status !== 'pending', 422, 'Chỉ có thể duyệt đơn đang chờ xử lý.');
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $before = $leaveRequest->toArray();
        $leaveRequest->update($data + [
            'reviewed_by_admin_id' => $request->user('admin')->id,
            'reviewed_at' => now(),
        ]);
        $this->auditLogger->record('hrm.leave.reviewed', $leaveRequest, $before, $leaveRequest->fresh()->toArray(), moduleKey: 'hrm');

        return response()->json(['message' => 'Đã cập nhật kết quả duyệt đơn.', 'data' => $leaveRequest->fresh(['employee', 'reviewer'])]);
    }
}
