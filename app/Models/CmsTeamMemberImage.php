<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cms_team_member_id', 'cms_media_id', 'image_url', 'alt_text', 'caption', 'is_featured', 'sort_order'])]
class CmsTeamMemberImage extends Model
{
    use HasFactory;

    protected $table = 'cms_team_member_images';

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(CmsTeamMember::class, 'cms_team_member_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(CmsMedia::class, 'cms_media_id');
    }
}
