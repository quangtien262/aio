<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_id', 'contract_number', 'contract_type', 'start_date', 'end_date', 'base_salary', 'status', 'note'])]
class HrmContract extends Model
{
    use HasFactory;

    protected $table = 'hrm_contracts';

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'base_salary' => 'decimal:2'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}
