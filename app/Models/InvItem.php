<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['catalog_product_id', 'sku', 'barcode', 'name', 'unit', 'costing_method', 'track_batch', 'track_serial', 'sale_price', 'reorder_min', 'reorder_max', 'preferred_supplier', 'image_url', 'is_active', 'last_synced_at', 'sync_snapshot'])]
class InvItem extends Model
{
    protected $table = 'inv_items';

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'track_batch' => 'boolean',
            'track_serial' => 'boolean',
            'reorder_min' => 'decimal:3',
            'reorder_max' => 'decimal:3',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
            'sync_snapshot' => 'array',
        ];
    }

    public function catalogProduct(): BelongsTo
    {
        return $this->belongsTo(CatalogProduct::class, 'catalog_product_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InvStockBalance::class, 'item_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InvBatch::class, 'item_id');
    }
}
