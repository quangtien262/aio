<?php

namespace App\Models;

use App\Models\Concerns\HasTranslationWorkflow;
use App\Models\Concerns\HasWebsiteScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'theme_key',
    'locale',
    'group',
    'translation_key',
    'value',
    'website_key',
    'translation_status',
    'source_revision',
    'translation_revision',
    'is_machine_translated',
    'translation_meta',
    'translated_at',
    'reviewed_at',
    'translation_published_at',
])]
class ThemeTranslation extends Model
{
    use HasFactory;
    use HasTranslationWorkflow;
    use HasWebsiteScope;
}
