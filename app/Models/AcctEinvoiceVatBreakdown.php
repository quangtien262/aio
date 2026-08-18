<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'external_invoice_id',
    'vat_rate',
    'taxable_amount',
    'vat_amount',
    'payload_snapshot',
])]
class AcctEinvoiceVatBreakdown extends Model
{
    use HasFactory;

    protected $table = 'acct_external_invoice_vat_breakdowns';

    protected function casts(): array
    {
        return [
            'taxable_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'payload_snapshot' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(AcctEinvoiceInbound::class, 'external_invoice_id');
    }
}
