<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleRegistry;
use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\Admin;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\RealEstate\Database\Seeders\RealEstateModuleSeeder;
use Tests\TestCase;

class Bds701ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_bds701_is_registered_as_a_real_estate_landing_theme(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'BDS701');

        $this->assertNotNull($theme);
        $this->assertSame('real_estate', $theme['website_type']);
        $this->assertTrue(app(LandingPageBuilder::class)->supportsTheme('BDS701'));
        $this->assertSame([
            'bds701_hero_search',
            'bds701_latest_listings',
            'bds701_property_types',
            'bds701_rental_listings',
            'bds701_market_news',
            'bds701_latest_news',
            'bds701_newsletter',
        ], collect(app(LandingPageBuilder::class)->availableBlocks('BDS701'))->pluck('block_type')->all());
    }

    public function test_real_estate_module_and_demo_provider_are_discoverable(): void
    {
        $module = app(ModuleRegistry::class)->find('real-estate');
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('BDS701');

        $this->assertNotNull($module);
        $this->assertContains('real_estate', $module['website_types']);
        $this->assertSame('BDS701', $provider?->themeKey());
        $this->assertSame('bds701-delta-platinum', $provider?->defaultPreset());
    }

    public function test_real_estate_public_routes_are_named_and_centralized(): void
    {
        $this->assertTrue(app('router')->has('site.real-estate.index'));
        $this->assertTrue(app('router')->has('site.real-estate.show'));
    }

    public function test_demo_provider_builds_and_renders_the_bds701_landing_homepage(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('BDS701');
        $result = $provider->generate($provider->defaultPreset());

        $this->assertSame(8, $result['counts']['listings']);
        $this->assertDatabaseCount('real_estate_property_types', 5);
        $this->assertDatabaseCount('real_estate_listings', 8);
        $this->assertDatabaseCount('real_estate_listing_media', 16);

        $response = $this->get('/vi');

        $response->assertOk();
        $response->assertSee('Tìm kiếm nhà đất mơ ước');
        $response->assertSee('Dự án mới nhất');
        $response->assertSee('bds701_latest_news');
    }

    public function test_demo_provider_reuses_module_seeded_property_types_and_can_run_repeatedly(): void
    {
        $this->seed(RealEstateModuleSeeder::class);
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('BDS701');

        $provider->generate($provider->defaultPreset());
        $provider->generate($provider->defaultPreset());

        $this->assertDatabaseCount('real_estate_property_types', 5);
        $this->assertDatabaseCount('real_estate_listings', 8);
        $this->assertDatabaseCount('real_estate_listing_media', 16);
        $this->assertDatabaseHas('real_estate_property_types', [
            'website_key' => 'website-main',
            'slug' => 'biet-thu',
        ]);
    }

    public function test_bds701_admin_mode_renders_the_landing_block_editor(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('BDS701');
        $provider->generate($provider->defaultPreset());
        $this->actingAs(Admin::factory()->create(), 'admin');

        $this->get(route('site.home', ['locale' => 'vi', 'mod' => 'admin']))
            ->assertOk()
            ->assertSee('data-xd-edit-block', false)
            ->assertSee('data-xd-editor', false)
            ->assertSee('.xd-editor,.xd-item-modal{position:fixed', false);
    }

    public function test_bds701_header_uses_the_logo_uploaded_in_website_branding(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('BDS701');
        $provider->generate($provider->defaultPreset());
        $profile = SiteProfile::query()->where('website_key', 'website-main')->firstOrFail();
        $profile->update([
            'branding' => array_merge((array) $profile->branding, [
                'logo_url' => 'https://cdn.example.com/branding/custom-logo.png',
            ]),
        ]);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('src="https://cdn.example.com/branding/custom-logo.png"', false)
            ->assertSee('alt="Delta Platinum"', false);
    }

    public function test_bds701_topbar_uses_branding_contact_and_customer_account_routes(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('BDS701');
        $provider->generate($provider->defaultPreset());

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('href="tel:19006750"', false)
            ->assertSee('href="mailto:hello@deltaplatinum.vn"', false)
            ->assertSee(route('customer.auth.login', ['locale' => 'vi']), false)
            ->assertSee(route('customer.auth.register', ['locale' => 'vi']), false);
    }
}
