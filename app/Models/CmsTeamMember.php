<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'slug', 'role', 'department', 'summary', 'bio', 'email', 'phone', 'link_url', 'status', 'publish_at', 'is_featured', 'sort_order', 'website_key', 'owner_key', 'tenant_key'])]
class CmsTeamMember extends Model
{
    use HasFactory;

    protected $table = 'cms_team_members';

    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(CmsTeamMemberImage::class, 'cms_team_member_id')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function featuredImage(): HasOne
    {
        return $this->hasOne(CmsTeamMemberImage::class, 'cms_team_member_id')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
