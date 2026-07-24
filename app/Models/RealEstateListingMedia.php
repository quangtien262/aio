<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['real_estate_listing_id', 'cms_media_id', 'media_type', 'media_url', 'alt_text', 'caption', 'is_featured', 'sort_order'])]
class RealEstateListingMedia extends Model
{
    protected $table = 'real_estate_listing_media';

    protected function casts(): array
    {
        return ['is_featured' => 'boolean', 'sort_order' => 'integer'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(RealEstateListing::class, 'real_estate_listing_id');
    }

    public function cmsMedia(): BelongsTo
    {
        return $this->belongsTo(CmsMedia::class, 'cms_media_id');
    }
}
