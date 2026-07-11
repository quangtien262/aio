<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['warehouse_id', 'location_id', 'location_key', 'item_id', 'batch_id', 'batch_key', 'quantity_on_hand', 'quantity_reserved', 'last_movement_at'])]
class InvStockBalance extends Model
{
    protected $table = 'inv_stock_balances';

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:3',
            'quantity_reserved' => 'decimal:3',
            'last_movement_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(InvWarehouse::class, 'warehouse_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InvLocation::class, 'location_id');
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
