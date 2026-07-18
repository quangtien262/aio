<?php

namespace App\Models;

use App\Models\Concerns\HasWebsiteScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['theme_key', 'preset_key', 'model_type', 'model_id', 'website_key'])]
class ThemeDemoRecord extends Model
{
    use HasFactory;
    use HasWebsiteScope;

    protected function casts(): array
    {
        return [
            'model_id' => 'integer',
        ];
    }
}
