<?php

namespace App\Models;

use App\Models\Concerns\HasWebsiteScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['website_key', 'site_name', 'description', 'website_type', 'active_theme_key', 'is_setup_completed', 'completed_steps', 'branding', 'theme_palettes', 'setup_completed_at'])]
class SiteProfile extends Model
{
    use HasFactory;
    use HasWebsiteScope;

    protected function casts(): array
    {
        return [
            'is_setup_completed' => 'boolean',
            'completed_steps' => 'array',
            'branding' => 'array',
            'theme_palettes' => 'array',
            'setup_completed_at' => 'datetime',
        ];
    }
}
