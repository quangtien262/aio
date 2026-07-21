<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['payroll_period_id', 'employee_id', 'base_salary', 'allowances', 'deductions', 'net_salary', 'status', 'snapshot', 'note'])]
class PayrollPayslip extends Model
{
    use HasFactory;

    protected $table = 'payroll_payslips';

    protected function casts(): array
    {
        return ['base_salary' => 'decimal:2', 'allowances' => 'decimal:2', 'deductions' => 'decimal:2', 'net_salary' => 'decimal:2', 'snapshot' => 'array'];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollPayslipLine::class, 'payslip_id')->orderBy('sort_order');
    }
}
