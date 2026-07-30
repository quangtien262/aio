<?php

namespace App\Models;

use App\Models\Concerns\HasTranslationWorkflow;
use App\Models\Concerns\HasWebsiteScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cms_page_id',
    'website_key',
    'locale',
    'title',
    'slug',
    'excerpt',
    'body',
    'meta_title',
    'meta_description',
    'meta_keywords',
    'translation_status',
    'source_revision',
    'translation_revision',
    'is_machine_translated',
    'translation_meta',
    'translated_at',
    'reviewed_at',
    'translation_published_at',
])]
class CmsPageTranslation extends Model
{
    use HasFactory;
    use HasTranslationWorkflow;
    use HasWebsiteScope;

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'cms_page_id');
    }
}
