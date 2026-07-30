<?php

namespace App\Models;

use App\Support\Localization\LocaleContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected static function booted(): void
    {
        $flush = static function (): void {
            if (app()->bound(LocaleContext::class)) {
                app(LocaleContext::class)->flushAll();
            }
        };

        static::saved($flush);
        static::deleted($flush);
    }

    public function websiteLocales(): HasMany
    {
        return $this->hasMany(WebsiteLocale::class, 'locale', 'code');
    }
}
