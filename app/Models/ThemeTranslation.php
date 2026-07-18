<?php

namespace App\Models;

use App\Models\Concerns\HasWebsiteScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['theme_key', 'locale', 'group', 'translation_key', 'value', 'website_key'])]
class ThemeTranslation extends Model
{
    use HasFactory;
    use HasWebsiteScope;
}
