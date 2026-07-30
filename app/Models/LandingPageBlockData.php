<?php

namespace App\Models;

use App\Models\Concerns\HasTranslationWorkflow;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'landing_page_block_id',
    'locale',
    'schema_version',
    'title',
    'subtitle',
    'description',
    'button_label',
    'content',
    'translation_status',
    'source_revision',
    'translation_revision',
    'is_machine_translated',
    'translation_meta',
    'translated_at',
    'reviewed_at',
    'translation_published_at',
])]
class LandingPageBlockData extends Model
{
    use HasFactory;
    use HasTranslationWorkflow;

    protected $table = 'landing_page_block_data';

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'schema_version' => 'integer',
        ]);
    }

    public function landingPageBlock(): BelongsTo
    {
        return $this->belongsTo(LandingPageBlock::class);
    }
}
