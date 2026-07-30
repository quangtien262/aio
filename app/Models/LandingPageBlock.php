<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['landing_page_id', 'theme_key', 'block_type', 'schema_version', 'sort_order', 'is_visible', 'anchor_id', 'settings', 'media'])]
class LandingPageBlock extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'schema_version' => 'integer',
            'settings' => 'array',
            'media' => 'array',
        ];
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }

    public function data(): HasMany
    {
        return $this->hasMany(LandingPageBlockData::class);
    }

    public function localizedData(): HasOne
    {
        return $this->hasOne(LandingPageBlockData::class);
    }
}
