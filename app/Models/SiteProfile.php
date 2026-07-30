<?php

namespace App\Models;

use App\Models\Concerns\HasWebsiteScope;
use App\Support\Localization\WebsiteLocaleManager;
use App\Support\SiteContext;
use App\Support\ThemeBrandingResolver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['website_key', 'site_name', 'description', 'website_type', 'active_theme_key', 'is_setup_completed', 'completed_steps', 'branding', 'theme_palettes', 'setup_completed_at'])]
class SiteProfile extends Model
{
    use HasFactory;
    use HasWebsiteScope;

    protected function casts(): array
    {
        return [
            'is_setup_completed' => 'boolean',
            'completed_steps' => 'array',
            'branding' => 'array',
            'theme_palettes' => 'array',
            'setup_completed_at' => 'datetime',
        ];
    }

    /**
     * Return the public branding for the current theme while preserving global
     * module namespaces stored in the legacy SiteProfile branding document.
     *
     * @return array<string, mixed>
     */
    public function getBrandingAttribute(mixed $value): array
    {
        $legacyBranding = is_array($value)
            ? $value
            : (json_decode((string) $value, true) ?: []);
        $themeKey = $this->attributes['active_theme_key'] ?? null;

        if (app()->bound(SiteContext::class)) {
            $themeKey = app(SiteContext::class)->site()?->theme_key ?: $themeKey;
        }

        return app(ThemeBrandingResolver::class)->resolve(
            (string) ($this->attributes['website_key'] ?? SiteContext::DEFAULT_WEBSITE_KEY),
            is_string($themeKey) ? $themeKey : null,
            $legacyBranding,
        );
    }

    /**
     * Raw global branding without theme resolution.
     *
     * @return array<string, mixed>
     */
    public function globalBranding(): array
    {
        $value = $this->getRawOriginal('branding');

        return is_array($value) ? $value : (json_decode((string) $value, true) ?: []);
    }

    protected static function booted(): void
    {
        static::created(function (SiteProfile $profile): void {
            app(WebsiteLocaleManager::class)->provisionWebsite($profile->website_key);
        });
    }
}
