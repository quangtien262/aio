<?php

namespace App\Http\Controllers\Admin\Api\Hrm;

use App\Models\HrmAttendanceRecord;
use App\Models\HrmEmployee;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HrmAttendanceController
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $admin = $request->user('admin');
        $canViewAll = $admin->hasPermission('hrm.attendance.view');
        $employee = HrmEmployee::query()->where('admin_id', $admin->id)->first();
        $query = HrmAttendanceRecord::query()->with('employee.department')->latest('work_date');
        if (! $canViewAll) {
            $query->where('employee_id', $employee?->id ?? 0);
        }
        if ($request->filled('month')) {
            $query->where('work_date', 'like', $request->string('month').'%');
        }

        return response()->json(['data' => [
            'items' => $query->get(),
            'employees' => $canViewAll ? HrmEmployee::query()->whereIn('employment_status', ['active', 'probation', 'leave'])->orderBy('full_name')->get(['id', 'employee_code', 'full_name']) : collect(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hrm_employees,id'],
            'work_date' => ['required', 'date'],
            'check_in_at' => ['nullable', 'date_format:H:i'],
            'check_out_at' => ['nullable', 'date_format:H:i', 'after:check_in_at'],
            'worked_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'status' => ['required', Rule::in(['present', 'late', 'remote', 'leave', 'absent', 'holiday'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        if (! empty($data['check_in_at']) && ! empty($data['check_out_at'])) {
            $data['worked_hours'] = round(
                Carbon::createFromFormat('H:i', $data['check_in_at'])
                    ->diffInMinutes(Carbon::createFromFormat('H:i', $data['check_out_at'])) / 60,
                2,
            );
        } elseif (in_array($data['status'], ['leave', 'absent', 'holiday'], true)) {
            $data['worked_hours'] = 0;
        }
        if (! array_key_exists('worked_hours', $data) || $data['worked_hours'] === null) {
            throw ValidationException::withMessages([
                'worked_hours' => ['Vui lòng chọn giờ vào và giờ ra để tính số giờ làm.'],
            ]);
        }
        $record = HrmAttendanceRecord::query()->updateOrCreate(
            ['employee_id' => $data['employee_id'], 'work_date' => $data['work_date']],
            $data + ['source' => 'manual', 'updated_by_admin_id' => $request->user('admin')->id],
        );
        $this->auditLogger->record('hrm.attendance.saved', $record, null, $record->toArray(), moduleKey: 'hrm');

        return response()->json(['message' => 'Đã lưu dữ liệu chấm công.', 'data' => $record->load('employee')], 201);
    }
}
