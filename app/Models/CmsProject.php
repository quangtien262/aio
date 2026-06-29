<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['title', 'slug', 'status', 'summary', 'content', 'button_label', 'link_url', 'meta_title', 'meta_description', 'publish_at', 'is_featured', 'is_highlight', 'sort_order', 'website_key', 'owner_key', 'tenant_key'])]
class CmsProject extends Model
{
    use HasFactory;

    protected $table = 'cms_projects';

    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime',
            'is_featured' => 'boolean',
            'is_highlight' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(CmsProjectImage::class, 'cms_project_id')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function featuredImage(): HasOne
    {
        return $this->hasOne(CmsProjectImage::class, 'cms_project_id')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
