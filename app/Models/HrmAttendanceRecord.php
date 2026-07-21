<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_id', 'work_date', 'check_in_at', 'check_out_at', 'worked_hours', 'status', 'source', 'note', 'updated_by_admin_id'])]
class HrmAttendanceRecord extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['work_date' => 'date', 'worked_hours' => 'decimal:2'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }
}
