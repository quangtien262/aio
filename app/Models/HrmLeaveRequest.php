<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_id', 'leave_type', 'start_date', 'end_date', 'days', 'reason', 'status', 'reviewed_by_admin_id', 'reviewed_at', 'review_note'])]
class HrmLeaveRequest extends Model
{
    use HasFactory;

    protected $table = 'hrm_leave_requests';

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'days' => 'decimal:2', 'reviewed_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }
}
