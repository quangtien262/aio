<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'external_invoice_id',
    'provider_line_id',
    'provider_header_id',
    'line_no',
    'item_name',
    'unit',
    'quantity',
    'unit_price',
    'subtotal_ex_vat',
    'vat_rate',
    'vat_amount',
    'discount_amount',
    'discount_rate',
    'line_type',
    'payload_snapshot',
])]
class AcctEinvoiceInboundLine extends Model
{
    use HasFactory;

    protected $table = 'acct_external_invoice_lines';

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'subtotal_ex_vat' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'discount_rate' => 'decimal:4',
            'payload_snapshot' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(AcctEinvoiceInbound::class, 'external_invoice_id');
    }
}
