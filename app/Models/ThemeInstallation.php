<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'name', 'version', 'website_type', 'status', 'is_active', 'blocks', 'installed_at', 'activated_at'])]
class ThemeInstallation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'blocks' => 'array',
            'installed_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }
}
