<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Foot409ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_foot409_is_registered_with_all_homepage_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'FOOT409');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertFileExists(public_path('theme-previews/FOOT409/preview-foot409.svg'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('FOOT409'));
        $this->assertSame([
            'hero_slider',
            'foot409_categories',
            'foot409_promo_banner',
            'foot409_featured_products',
            'foot409_dual_promos',
            'foot409_recommendations',
            'foot409_triple_promos',
            'foot409_blog_posts',
            'foot409_suppliers',
            'foot409_benefits',
        ], collect($builder->availableBlocks('FOOT409'))->pluck('block_type')->all());
    }

    public function test_demo_provider_generates_content_and_preserves_configured_logo(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Bếp Nhanh Việt',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'FOOT408',
            'branding' => [
                'logo_url' => '/storage/branding/bep-nhanh-viet.svg',
                'support_hotline' => '0888 409 409',
                'support_email' => 'hello@bepnhanh.test',
                'support_location' => '409 Nguyễn Trãi, Hà Nội',
            ],
        ]);

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('FOOT409');
        $this->assertNotNull($provider);
        $result = $provider->generate('foot409-fast-food');
        $this->assertSame(6, data_get($result, 'counts.categories'));
        $this->assertSame(12, data_get($result, 'counts.products'));
        $this->assertDatabaseHas('site_banners', ['theme_key' => 'FOOT409', 'placement' => 'foot409-hero-slider']);

        // Regenerating demo content must never replace branding uploaded by the customer.
        $provider->generate('foot409-fast-food');
        $this->assertSame('/storage/branding/bep-nhanh-viet.svg', data_get(SiteProfile::query()->firstOrFail()->branding, 'logo_url'));

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('/storage/branding/bep-nhanh-viet.svg', false)
            ->assertSee('0888 409 409')
            ->assertSee('hello@bepnhanh.test')
            ->assertSee('409 Nguyễn Trãi, Hà Nội')
            ->assertSee('Gà rán giòn cay (4 miếng)')
            ->assertSee('Bảng tin khuyến mãi')
            ->assertSee('data-block-type="foot409_recommendations"', false)
            ->assertDontSee('src="/theme-demo/foot409/logo', false);
    }
}
