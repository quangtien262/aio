<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['catalog_product_id', 'image_url', 'alt_text', 'sort_order'])]
class CatalogProductImage extends Model
{
    use HasFactory;

    protected $table = 'catalog_product_images';

    public function product(): BelongsTo
    {
        return $this->belongsTo(CatalogProduct::class, 'catalog_product_id');
    }
}
