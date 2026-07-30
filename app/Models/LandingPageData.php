<?php

namespace App\Models;

use App\Models\Concerns\HasTranslationWorkflow;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'landing_page_id',
    'locale',
    'slug',
    'title',
    'excerpt',
    'meta_title',
    'meta_description',
    'translation_status',
    'source_revision',
    'translation_revision',
    'is_machine_translated',
    'translation_meta',
    'translated_at',
    'reviewed_at',
    'translation_published_at',
])]
class LandingPageData extends Model
{
    use HasFactory;
    use HasTranslationWorkflow;

    protected $table = 'landing_page_data';

    protected static function booted(): void
    {
        static::created(function (LandingPageData $translation): void {
            if (data_get($translation->translation_meta, 'editor') === 'landing.pages') {
                return;
            }

            app(\App\Support\Localization\LandingPageLocalization::class)
                ->syncLegacyPageTranslation($translation);
        });
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }
}
