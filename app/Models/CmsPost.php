<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'slug', 'status', 'excerpt', 'body', 'meta_title', 'meta_description', 'featured_media_id', 'category_id', 'publish_at', 'website_key', 'owner_key', 'tenant_key'])]
class CmsPost extends Model
{
    use HasFactory;

    protected $table = 'cms_posts';

    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        /** @var BelongsTo<CmsCategory, $this> $relation */
        $relation = $this->belongsTo(CmsCategory::class, 'category_id');

        return $relation;
    }

    public function featuredMedia(): BelongsTo
    {
        /** @var BelongsTo<CmsMedia, $this> $relation */
        $relation = $this->belongsTo(CmsMedia::class, 'featured_media_id');

        return $relation;
    }
}
