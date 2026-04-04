<?php

namespace App\Models;

use App\Models\CatalogProduct;
use App\Models\Order;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'catalog_product_id',
    'product_name',
    'product_slug',
    'sku',
    'unit_price',
    'original_price',
    'quantity',
    'line_total',
    'image_url',
])]
class OrderItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'original_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(CatalogProduct::class, 'catalog_product_id');
    }
}
