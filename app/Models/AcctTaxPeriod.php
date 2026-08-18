<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id', 'code', 'period_type', 'start_date', 'end_date', 'base_currency',
    'status', 'version', 'created_by', 'report_snapshot', 'snapshot_hash', 'locked_at', 'locked_by',
    'filed_at', 'filed_by', 'filing_reference', 'notes',
])]
class AcctTaxPeriod extends Model
{
    protected $table = 'acct_tax_periods';

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'report_snapshot' => 'array',
            'locked_at' => 'datetime',
            'filed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(AcctOrganization::class, 'organization_id');
    }
}
