<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['employee_code', 'admin_id', 'department_id', 'position_id', 'manager_employee_id', 'full_name', 'work_email', 'personal_email', 'phone', 'date_of_birth', 'gender', 'identity_number', 'address', 'work_location', 'join_date', 'termination_date', 'employment_status', 'note'])]
class HrmEmployee extends Model
{
    use HasFactory;

    protected $table = 'hrm_employees';

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'join_date' => 'date', 'termination_date' => 'date'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrmDepartment::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(HrmPosition::class, 'position_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_employee_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(HrmContract::class, 'employee_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(HrmLeaveRequest::class, 'employee_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(HrmAttendanceRecord::class, 'employee_id');
    }
}
