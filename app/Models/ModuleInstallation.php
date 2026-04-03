<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'name', 'version', 'status', 'website_types', 'dependencies', 'installed_at', 'enabled_at'])]
class ModuleInstallation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'website_types' => 'array',
            'dependencies' => 'array',
            'installed_at' => 'datetime',
            'enabled_at' => 'datetime',
        ];
    }
}
