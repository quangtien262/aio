<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['site_name', 'website_type', 'active_theme_key', 'is_setup_completed', 'completed_steps', 'branding', 'setup_completed_at'])]
class SiteProfile extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_setup_completed' => 'boolean',
            'completed_steps' => 'array',
            'branding' => 'array',
            'setup_completed_at' => 'datetime',
        ];
    }
}
