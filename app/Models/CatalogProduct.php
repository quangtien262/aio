<?php

namespace App\Models;

use App\Models\Concerns\HasWebsiteScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['catalog_category_id', 'name', 'slug', 'sku', 'price', 'original_price', 'stock', 'short_description', 'detail_content', 'meta_title', 'meta_description', 'meta_keywords', 'highlights', 'usage_terms', 'usage_location', 'image_url', 'sold_count', 'deal_end_at', 'is_featured', 'is_highlight', 'sort_order', 'is_active', 'website_key'])]
class CatalogProduct extends Model
{
    use HasFactory;
    use HasWebsiteScope;

    protected $table = 'catalog_products';

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'original_price' => 'decimal:2',
            'deal_end_at' => 'datetime',
            'is_featured' => 'boolean',
            'is_highlight' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'catalog_category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(CatalogProductImage::class, 'catalog_product_id')->orderBy('sort_order')->orderBy('id');
    }
}
