<?php

namespace App\Models;

use App\Models\Concerns\HasTranslationWorkflow;
use App\Models\Concerns\HasWebsiteScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'website_key',
    'resource_type',
    'resource_id',
    'locale',
    'slug',
    'payload',
    'translation_status',
    'source_revision',
    'translation_revision',
    'is_machine_translated',
    'translation_meta',
    'translated_at',
    'reviewed_at',
    'translation_published_at',
])]
class ContentTranslation extends Model
{
    use HasFactory;
    use HasTranslationWorkflow;
    use HasWebsiteScope;

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'payload' => 'array',
        ]);
    }
}
