<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SiteProfile;
use App\Models\SiteThemeProfile;
use App\Support\SiteContext;
use App\Support\ThemeBrandingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeBrandingIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_themes_on_the_same_website_resolve_independent_branding(): void
    {
        $profile = SiteProfile::query()->create([
            'website_key' => 'website-main',
            'site_name' => 'Main',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'DN202',
            'branding' => [
                'company_name' => 'Legacy company',
                'cms' => ['menu_locations' => ['primary']],
            ],
        ]);

        SiteThemeProfile::query()->create([
            'website_key' => 'website-main',
            'theme_key' => 'DN202',
            'branding' => [
                'logo_url' => 'https://example.test/dn202.png',
                'support_hotline' => '111',
            ],
        ]);
        SiteThemeProfile::query()->create([
            'website_key' => 'website-main',
            'theme_key' => 'EC912',
            'branding' => [
                'logo_url' => 'https://example.test/ec912.png',
                'support_hotline' => '222',
            ],
        ]);

        $this->assertSame('111', $profile->branding['support_hotline']);
        $this->assertSame(['primary'], data_get($profile->branding, 'cms.menu_locations'));

        $profile->forceFill(['active_theme_key' => 'EC912'])->save();
        $profile->refresh();

        $this->assertSame('222', $profile->branding['support_hotline']);
        $this->assertSame('https://example.test/ec912.png', $profile->branding['logo_url']);
        $this->assertSame(['primary'], data_get($profile->branding, 'cms.menu_locations'));
    }

    public function test_same_theme_is_isolated_between_websites(): void
    {
        SiteThemeProfile::query()->create([
            'website_key' => 'website-main',
            'theme_key' => 'EC912',
            'branding' => ['support_email' => 'main@example.test'],
        ]);
        SiteThemeProfile::query()->withoutGlobalScope('current_website')->create([
            'website_key' => 'website-secondary',
            'theme_key' => 'EC912',
            'branding' => ['support_email' => 'secondary@example.test'],
        ]);

        $resolver = app(ThemeBrandingResolver::class);

        $this->assertSame(
            'main@example.test',
            $resolver->resolve('website-main', 'EC912')['support_email'],
        );
        $this->assertSame(
            'secondary@example.test',
            $resolver->resolve('website-secondary', 'EC912')['support_email'],
        );
    }

    public function test_legacy_or_demo_branding_writes_cannot_override_theme_company_contacts(): void
    {
        $profile = SiteProfile::query()->create([
            'website_key' => 'website-main',
            'site_name' => 'Main',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'DN202',
            'branding' => [],
        ]);
        SiteThemeProfile::query()->create([
            'website_key' => 'website-main',
            'theme_key' => 'DN202',
            'branding' => ThemeBrandingResolver::COMPANY_DEFAULTS,
        ]);

        $profile->forceFill([
            'branding' => [
                'logo_url' => 'https://demo.test/logo.png',
                'support_hotline' => '1900 demo',
                'support_email' => 'demo@example.test',
                'support_location' => 'Demo address',
            ],
        ])->save();
        $profile->refresh();

        $this->assertSame(ThemeBrandingResolver::COMPANY_DEFAULTS, array_intersect_key(
            $profile->branding,
            ThemeBrandingResolver::COMPANY_DEFAULTS,
        ));
    }

    public function test_admin_can_update_settings_for_a_specific_theme_only(): void
    {
        $admin = Admin::factory()->create(['id' => Admin::SYSTEM_OWNER_ID, 'is_system_owner' => true]);
        SiteProfile::query()->create([
            'website_key' => SiteContext::DEFAULT_WEBSITE_KEY,
            'site_name' => 'Main',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'DN202',
            'branding' => [],
        ]);

        $this->actingAs($admin, 'admin')
            ->withHeader('X-Website-Key', SiteContext::DEFAULT_WEBSITE_KEY)
            ->putJson('/admin/api/themes/EC912/settings', [
                'support_hotline' => '0909123456',
                'support_email' => 'ec912@example.test',
            ])
            ->assertOk()
            ->assertJsonPath('data.theme_key', 'EC912')
            ->assertJsonPath('data.branding.support_hotline', '0909123456');

        $this->assertDatabaseHas('site_theme_profiles', [
            'website_key' => SiteContext::DEFAULT_WEBSITE_KEY,
            'theme_key' => 'EC912',
        ]);
        $this->assertSame(
            '0909123456',
            data_get(
                SiteThemeProfile::query()->where('theme_key', 'EC912')->firstOrFail()->branding,
                'support_hotline',
            ),
        );
        $this->assertDatabaseMissing('site_theme_profiles', [
            'website_key' => SiteContext::DEFAULT_WEBSITE_KEY,
            'theme_key' => 'DN202',
            'branding' => json_encode(['support_hotline' => '0909123456']),
        ]);
    }
}
