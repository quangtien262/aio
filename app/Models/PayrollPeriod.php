<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'start_date', 'end_date', 'status', 'approved_by_admin_id', 'approved_at', 'published_at', 'locked_at'])]
class PayrollPeriod extends Model
{
    use HasFactory;

    protected $table = 'payroll_periods';

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'approved_at' => 'datetime', 'published_at' => 'datetime', 'locked_at' => 'datetime'];
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(PayrollPayslip::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }
}
