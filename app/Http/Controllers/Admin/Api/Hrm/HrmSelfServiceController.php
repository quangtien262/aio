<?php

namespace App\Http\Controllers\Admin\Api\Hrm;

use App\Models\HrmEmployee;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrmSelfServiceController
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function show(Request $request): JsonResponse
    {
        $employee = HrmEmployee::query()
            ->where('admin_id', $request->user('admin')->id)
            ->with(['department', 'position', 'manager:id,employee_code,full_name', 'contracts' => fn ($query) => $query->latest('start_date')])
            ->first();

        abort_if(! $employee, 404, 'Tài khoản chưa được liên kết với hồ sơ nhân sự.');

        return response()->json(['data' => $employee]);
    }

    public function update(Request $request): JsonResponse
    {
        $employee = HrmEmployee::query()->where('admin_id', $request->user('admin')->id)->firstOrFail();
        $data = $request->validate([
            'personal_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);
        $before = $employee->only(array_keys($data));
        $employee->update($data);
        $this->auditLogger->record('hrm.profile.self.updated', $employee, $before, $data, moduleKey: 'hrm');

        return response()->json(['message' => 'Đã cập nhật thông tin cá nhân.', 'data' => $employee->fresh(['department', 'position'])]);
    }
}
