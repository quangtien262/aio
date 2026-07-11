<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['item_id', 'batch_id', 'serial_number_id', 'warehouse_id', 'location_id', 'document_id', 'document_line_id', 'type', 'quantity_delta', 'balance_after', 'unit_cost', 'reference', 'note', 'created_by_admin_id'])]
class InvStockMovement extends Model
{
    protected $table = 'inv_stock_movements';

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InvItem::class, 'item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(InvWarehouse::class, 'warehouse_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InvBatch::class, 'batch_id');
    }
}
