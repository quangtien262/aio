<?php

namespace App\Http\Controllers\Admin\Api\Hrm;

use App\Models\HrmDepartment;
use App\Models\HrmEmployee;
use App\Models\HrmLeaveRequest;
use Illuminate\Http\JsonResponse;

class HrmDashboardController
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => [
            'total_employees' => HrmEmployee::query()->count(),
            'active_employees' => HrmEmployee::query()->where('employment_status', 'active')->count(),
            'probation_employees' => HrmEmployee::query()->where('employment_status', 'probation')->count(),
            'active_departments' => HrmDepartment::query()->where('is_active', true)->count(),
            'pending_leave_requests' => HrmLeaveRequest::query()->where('status', 'pending')->count(),
            'recent_employees' => HrmEmployee::query()->with(['department', 'position'])->latest()->take(5)->get(),
        ]]);
    }
}
