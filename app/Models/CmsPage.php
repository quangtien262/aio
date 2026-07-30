<?php

namespace App\Models;

use App\Models\Concerns\HasWebsiteScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

#[Fillable(['title', 'slug', 'status', 'excerpt', 'body', 'meta_title', 'meta_description', 'meta_keywords', 'template', 'featured_media_id', 'publish_at', 'website_key'])]
class CmsPage extends Model
{
    use HasFactory;
    use HasWebsiteScope;

    protected $table = 'cms_pages';

    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (CmsPage $page): void {
            if (
                ! Schema::hasTable('cms_page_translations')
                || (! $page->wasRecentlyCreated
                    && ! $page->wasChanged([
                        'title',
                        'slug',
                        'status',
                        'excerpt',
                        'body',
                        'meta_title',
                        'meta_description',
                        'meta_keywords',
                        'publish_at',
                    ]))
            ) {
                return;
            }

            app(\App\Support\Localization\CmsPageLocalization::class)
                ->syncLegacySource($page);
        });
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(CmsMedia::class, 'featured_media_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CmsPageTranslation::class, 'cms_page_id');
    }
}
