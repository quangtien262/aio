<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\LandingPage;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Nt504ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_nt504_is_registered_with_expected_landing_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'NT504');

        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertSame('nt504.png', data_get($theme, 'preview.thumbnail'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('NT504'));
        $this->assertSame([
            'hero_slider',
            'nt504_spaces',
            'nt504_product_categories',
            'nt504_premium_promo',
            'nt504_category_rail',
            'nt504_sale_products',
            'nt504_service_promos',
            'nt504_latest_news',
            'nt504_footer',
        ], collect($builder->availableBlocks('NT504'))->pluck('block_type')->all());
    }

    public function test_nt504_demo_storefront_renders_all_home_sections_and_auth(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('NT504');

        $this->assertNotNull($provider);
        $result = $provider->generate('nt504-wolf-paint');

        $this->assertSame(12, data_get($result, 'counts.products'));
        $this->assertDatabaseHas('site_banners', ['theme_key' => 'NT504', 'placement' => 'nt504-hero-slider']);
        $this->assertDatabaseHas('landing_pages', ['theme_key' => 'NT504', 'slug' => 'home', 'is_home' => true]);

        SiteProfile::query()->firstOrFail()->forceFill(['active_theme_key' => 'NT504'])->save();
        $response = $this->get(route('site.home', ['locale' => 'vi']));

        $response->assertOk()
            ->assertSee('data-block-type="nt504_spaces"', false)
            ->assertSee('data-block-type="nt504_product_categories"', false)
            ->assertSee('data-block-type="nt504_premium_promo"', false)
            ->assertSee('data-block-type="nt504_category_rail"', false)
            ->assertSee('data-block-type="nt504_sale_products"', false)
            ->assertSee('data-block-type="nt504_service_promos"', false)
            ->assertSee('data-block-type="nt504_latest_news"', false)
            ->assertSee('data-block-type="nt504_footer"', false)
            ->assertSee('data-xd-auth-open="login"', false)
            ->assertSee('Wolf Paint');

        $this->assertCount(9, LandingPage::query()->where('theme_key', 'NT504')->where('is_home', true)->firstOrFail()->blocks);
    }

    public function test_nt504_demo_generation_preserves_and_renders_database_logo(): void
    {
        $logoUrl = 'https://example.test/uploads/customer-logo.png';
        SiteProfile::query()->create([
            'website_key' => 'website-main',
            'site_name' => 'Customer Paint',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'NT504',
            'branding' => [
                'company_name' => 'Customer Paint',
                'logo_url' => $logoUrl,
            ],
        ]);

        app(ThemeDemoContentProviderRegistry::class)
            ->forTheme('NT504')
            ->generate('nt504-wolf-paint');

        $profile = SiteProfile::query()->firstOrFail();
        $this->assertSame($logoUrl, data_get($profile->globalBranding(), 'logo_url'));

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('<img src="'.$logoUrl.'"', false);
    }
}
