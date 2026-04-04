<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'slug', 'status', 'excerpt', 'body', 'meta_title', 'meta_description', 'template', 'featured_media_id', 'publish_at', 'website_key', 'owner_key', 'tenant_key'])]
class CmsPage extends Model
{
    use HasFactory;

    protected $table = 'cms_pages';

    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime',
        ];
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(CmsMedia::class, 'featured_media_id');
    }
}
