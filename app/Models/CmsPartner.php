<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'slug', 'description', 'image_url', 'image_alt', 'link_url', 'status', 'publish_at', 'is_featured', 'sort_order', 'website_key', 'owner_key', 'tenant_key'])]
class CmsPartner extends Model
{
    use HasFactory;

    protected $table = 'cms_partners';

    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
