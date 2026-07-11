<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'type', 'status', 'source_warehouse_id', 'destination_warehouse_id', 'reference', 'note', 'created_by_admin_id', 'posted_at'])]
class InvStockDocument extends Model
{
    protected $table = 'inv_stock_documents';

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
        ];
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(InvWarehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(InvWarehouse::class, 'destination_warehouse_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvStockDocumentLine::class, 'document_id');
    }
}
