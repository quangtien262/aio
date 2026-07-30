<?php

namespace App\Support;

use App\Models\SiteThemeProfile;
use Illuminate\Support\Facades\Schema;

class ThemeBrandingResolver
{
    public const COMPANY_DEFAULTS = [
        'logo_url' => 'https://htvietnam.vn/layouts/HTVietNam/images/logo.png',
        'support_hotline' => '0399162342',
        'support_email' => 'info@htvietnam.vn',
        'support_location' => 'Số 7 ngõ 68 đường Nguyễn Khuyến, Đại Mỗ, Hà Nội',
    ];

    /**
     * Fields which describe the public appearance of a theme. Module configuration
     * namespaces (for example cms.* and catalog.*) intentionally remain global.
     *
     * @var list<string>
     */
    public const PUBLIC_FIELDS = [
        'company_name',
        'company_description',
        'slogan',
        'primary_color',
        'primary_color_deep',
        'accent_color',
        'accent_soft_color',
        'background_color',
        'surface_color',
        'surface_tint_color',
        'logo_url',
        'favicon_url',
        'support_hotline',
        'support_email',
        'support_location',
        'boc_status',
        'boc_confirmation_url',
        'boc_footer_note',
    ];

    /**
     * @param array<string, mixed> $legacyBranding
     * @return array<string, mixed>
     */
    public function resolve(string $websiteKey, ?string $themeKey, array $legacyBranding = []): array
    {
        $resolved = array_merge(self::COMPANY_DEFAULTS, $legacyBranding);
        $themeKey = $this->normalizeThemeKey($themeKey);

        if ($themeKey === null || ! $this->tableExists()) {
            return $resolved;
        }

        $profile = SiteThemeProfile::query()
            ->forWebsite($websiteKey)
            ->where('theme_key', $themeKey)
            ->first();

        return array_merge($resolved, $profile?->branding ?? []);
    }

    /**
     * @param array<string, mixed> $legacyBranding
     */
    public function ensure(string $websiteKey, string $themeKey, array $legacyBranding = []): ?SiteThemeProfile
    {
        if (! $this->tableExists()) {
            return null;
        }

        $seed = array_merge(
            self::COMPANY_DEFAULTS,
            array_intersect_key($legacyBranding, array_flip(self::PUBLIC_FIELDS)),
        );

        return SiteThemeProfile::query()
            ->withoutGlobalScope('current_website')
            ->firstOrCreate(
                [
                    'website_key' => $websiteKey,
                    'theme_key' => strtoupper($themeKey),
                ],
                ['branding' => $seed],
            );
    }

    /**
     * @param array<string, mixed> $changes
     */
    public function update(string $websiteKey, string $themeKey, array $changes, array $legacyBranding = []): SiteThemeProfile
    {
        $profile = $this->ensure($websiteKey, $themeKey, $legacyBranding);

        abort_if($profile === null, 503, 'Theme settings storage is not available.');

        $profile->forceFill([
            'branding' => array_merge($profile->branding ?? [], $changes),
        ])->save();

        return $profile;
    }

    private function normalizeThemeKey(?string $themeKey): ?string
    {
        $themeKey = strtoupper(trim((string) $themeKey));

        return $themeKey !== '' ? $themeKey : null;
    }

    private function tableExists(): bool
    {
        return Schema::hasTable('site_theme_profiles');
    }
}
