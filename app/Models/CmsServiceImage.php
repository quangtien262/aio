<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cms_service_id', 'cms_media_id', 'image_url', 'alt_text', 'caption', 'is_featured', 'sort_order'])]
class CmsServiceImage extends Model
{
    use HasFactory;

    protected $table = 'cms_service_images';

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(CmsService::class, 'cms_service_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(CmsMedia::class, 'cms_media_id');
    }
}
