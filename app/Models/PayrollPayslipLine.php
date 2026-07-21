<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payslip_id', 'type', 'code', 'label', 'amount', 'sort_order'])]
class PayrollPayslipLine extends Model
{
    use HasFactory;

    protected $table = 'payroll_payslip_lines';

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(PayrollPayslip::class, 'payslip_id');
    }
}
