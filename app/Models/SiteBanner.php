<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['theme_key', 'placement', 'title', 'subtitle', 'image_url', 'link_url', 'badge', 'metadata', 'sort_order', 'is_active', 'website_key', 'owner_key', 'tenant_key'])]
class SiteBanner extends Model
{
    use HasFactory;

    protected $table = 'site_banners';

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
