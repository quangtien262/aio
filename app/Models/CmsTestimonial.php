<?php

namespace App\Models;

use App\Models\Concerns\HasWebsiteScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'role', 'company', 'quote', 'image_url', 'image_alt', 'link_url', 'status', 'publish_at', 'is_featured', 'sort_order', 'website_key', 'owner_key', 'tenant_key'])]
class CmsTestimonial extends Model
{
    use HasFactory;
    use HasWebsiteScope;

    protected $table = 'cms_testimonials';

    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
