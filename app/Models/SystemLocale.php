<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'native_name', 'is_default', 'is_active', 'is_published', 'sort_order'])]
class SystemLocale extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'is_published' => 'boolean',
        ];
    }
}
