<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\LandingPage;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Nt503ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_nt503_is_registered_with_expected_landing_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'NT503');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('NT503'));
        $this->assertSame([
            'hero_slider',
            'nt503_categories',
            'nt503_mattresses',
            'nt503_promo_banners',
            'nt503_flash_sale',
            'nt503_kids_collection',
            'nt503_season_promo',
            'nt503_advice',
            'nt503_footer',
        ], collect($builder->availableBlocks('NT503'))->pluck('block_type')->all());
    }

    public function test_nt503_demo_storefront_and_auth_modal_render(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('NT503');
        $this->assertNotNull($provider);
        $result = $provider->generate('nt503-wolfbed');

        $this->assertSame(12, data_get($result, 'counts.products'));
        $this->assertDatabaseHas('site_banners', ['theme_key' => 'NT503', 'placement' => 'nt503-hero-slider']);
        $this->assertDatabaseHas('landing_pages', ['theme_key' => 'NT503', 'slug' => 'home', 'is_home' => true]);

        SiteProfile::query()->firstOrFail()->forceFill(['active_theme_key' => 'NT503'])->save();
        $response = $this->get(route('site.home', ['locale' => 'vi']));

        $response->assertOk()
            ->assertSee('data-block-type="nt503_categories"', false)
            ->assertSee('data-block-type="nt503_flash_sale"', false)
            ->assertSee('data-block-type="nt503_advice"', false)
            ->assertSee('data-block-type="nt503_footer"', false)
            ->assertSee('data-xd-auth-open="login"', false)
            ->assertSee('data-xd-auth-open="register"', false)
            ->assertSee('WolfBed');

        $this->assertCount(9, LandingPage::query()->where('theme_key', 'NT503')->where('is_home', true)->firstOrFail()->blocks);
    }
}
