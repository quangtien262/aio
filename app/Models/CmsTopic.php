<?php

namespace App\Models;

use App\Models\Concerns\HasWebsiteScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CmsTopic extends Model
{
    use HasWebsiteScope;

    protected $fillable = ['website_key', 'name', 'slug', 'description', 'image_url', 'meta_title', 'meta_description', 'is_active'];

    public static function available(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('cms_topics')
            && \Illuminate\Support\Facades\Schema::hasTable('cms_post_topic');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(CmsPost::class, 'cms_post_topic');
    }
}
