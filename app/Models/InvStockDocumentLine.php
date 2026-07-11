<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['document_id', 'item_id', 'source_location_id', 'destination_location_id', 'batch_id', 'batch_code', 'expires_at', 'serial_numbers', 'quantity', 'unit_cost', 'note'])]
class InvStockDocumentLine extends Model
{
    protected $table = 'inv_stock_document_lines';

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'expires_at' => 'date',
            'serial_numbers' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(InvStockDocument::class, 'document_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InvItem::class, 'item_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InvBatch::class, 'batch_id');
    }
}
