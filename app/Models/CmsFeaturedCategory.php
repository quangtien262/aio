<?php

namespace App\Models;

use App\Models\Concerns\HasWebsiteScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsFeaturedCategory extends Model
{
    use HasFactory;
    use HasWebsiteScope;

    protected $table = 'cms_featured_categories';

    protected $fillable = [
        'name',
        'location',
        'items',
        'website_key',
        'owner_key',
        'tenant_key',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
        ];
    }
}
