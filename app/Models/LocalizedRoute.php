<?php

namespace App\Models;

use App\Models\Concerns\HasWebsiteScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'website_key',
    'locale',
    'resource_type',
    'resource_id',
    'path',
    'route_name',
    'is_canonical',
    'is_published',
    'redirect_to',
    'metadata',
    'published_at',
])]
class LocalizedRoute extends Model
{
    use HasFactory;
    use HasWebsiteScope;

    protected function casts(): array
    {
        return [
            'is_canonical' => 'boolean',
            'is_published' => 'boolean',
            'metadata' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
