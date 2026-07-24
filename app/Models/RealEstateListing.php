<?php

namespace App\Models;

use App\Models\Concerns\HasWebsiteScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'property_type_id', 'cms_project_id', 'title', 'slug', 'code', 'publication_status',
    'availability_status', 'transaction_type', 'price', 'price_unit', 'currency', 'province',
    'district', 'ward', 'address', 'latitude', 'longitude', 'bedrooms', 'bathrooms', 'floors',
    'land_area', 'floor_area', 'direction', 'legal_status', 'furnishing_status', 'video_url',
    'virtual_tour_url', 'summary', 'content', 'meta_title', 'meta_description', 'meta_keywords',
    'is_featured', 'is_hot', 'sort_order', 'published_at', 'expires_at', 'website_key',
])]
class RealEstateListing extends Model
{
    use HasWebsiteScope;

    protected $table = 'real_estate_listings';

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'land_area' => 'decimal:2',
            'floor_area' => 'decimal:2',
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'floors' => 'integer',
            'is_featured' => 'boolean',
            'is_hot' => 'boolean',
            'sort_order' => 'integer',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(RealEstatePropertyType::class, 'property_type_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(CmsProject::class, 'cms_project_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(RealEstateListingMedia::class, 'real_estate_listing_id')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function featuredMedia(): HasOne
    {
        return $this->hasOne(RealEstateListingMedia::class, 'real_estate_listing_id')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
