<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cms_project_id', 'cms_media_id', 'image_url', 'alt_text', 'caption', 'is_featured', 'sort_order'])]
class CmsProjectImage extends Model
{
    use HasFactory;

    protected $table = 'cms_project_images';

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(CmsProject::class, 'cms_project_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(CmsMedia::class, 'cms_media_id');
    }
}
