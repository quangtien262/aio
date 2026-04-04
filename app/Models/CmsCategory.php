<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'meta_title', 'meta_description', 'parent_id', 'website_key', 'owner_key', 'tenant_key'])]
class CmsCategory extends Model
{
    use HasFactory;

    protected $table = 'cms_categories';

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function posts(): HasMany
    {
        /** @var HasMany<CmsPost, $this> $relation */
        $relation = $this->hasMany(CmsPost::class, 'category_id');

        return $relation;
    }
}
