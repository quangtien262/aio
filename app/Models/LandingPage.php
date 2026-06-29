<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['website_key', 'theme_key', 'page_type', 'slug', 'status', 'template', 'is_home', 'sort_order', 'settings', 'media', 'published_at'])]
class LandingPage extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_home' => 'boolean',
            'sort_order' => 'integer',
            'settings' => 'array',
            'media' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function data(): HasMany
    {
        return $this->hasMany(LandingPageData::class);
    }

    public function localizedData(): HasOne
    {
        return $this->hasOne(LandingPageData::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(LandingPageBlock::class)->orderBy('sort_order')->orderBy('id');
    }
}
