<?php

namespace App\Models;

use App\Models\Concerns\HasWebsiteScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['website_key', 'cms_post_id', 'customer_id', 'parent_id', 'body', 'status'])]
class CmsPostComment extends Model
{
    use HasWebsiteScope;

    protected $table = 'cms_post_comments';

    public function post(): BelongsTo
    {
        return $this->belongsTo(CmsPost::class, 'cms_post_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }
}
