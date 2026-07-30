<?php

namespace App\Models;

use App\Models\Concerns\HasWebsiteScope;
use App\Support\Localization\LocaleContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'website_key',
    'locale',
    'is_default',
    'is_enabled_for_editing',
    'is_published',
    'fallback_locale',
    'sort_order',
    'domain',
    'path_prefix',
    'currency_code',
    'timezone',
    'date_format',
    'number_format',
])]
class WebsiteLocale extends Model
{
    use HasFactory;
    use HasWebsiteScope;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_enabled_for_editing' => 'boolean',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
            'number_format' => 'array',
        ];
    }

    protected static function booted(): void
    {
        $flush = static function (WebsiteLocale $locale): void {
            if (app()->bound(LocaleContext::class)) {
                app(LocaleContext::class)->flush($locale->website_key);
            }
        };

        static::saved($flush);
        static::deleted($flush);
    }

    public function systemLocale(): BelongsTo
    {
        return $this->belongsTo(SystemLocale::class, 'locale', 'code');
    }
}
