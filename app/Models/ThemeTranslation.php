<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['theme_key', 'locale', 'group', 'translation_key', 'value'])]
class ThemeTranslation extends Model
{
    use HasFactory;
}
