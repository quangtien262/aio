<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\LandingPage;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ec909ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ec909_is_registered_with_thirteen_editable_home_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'EC909');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('EC909'));
        $this->assertSame([
            'hero_slider',
            'ec909_about',
            'ec909_category_cards',
            'ec909_headphone_showcase',
            'ec909_headphone_products',
            'ec909_microphone_feature',
            'ec909_earphone_products',
            'ec909_stereo_feature',
            'ec909_recommendations',
            'ec909_brand_strip',
            'ec909_latest_posts',
            'ec909_benefits',
            'ec909_footer',
        ], collect($builder->availableBlocks('EC909'))->pluck('block_type')->all());

        $blocks = collect($builder->availableBlocks('EC909'));
        $this->assertSame('catalog_categories', data_get($blocks->firstWhere('block_type', 'ec909_category_cards'), 'settings_schema.source.options.0.value'));
        $this->assertSame('cms_products', data_get($blocks->firstWhere('block_type', 'ec909_headphone_showcase'), 'settings_schema.source.options.0.value'));
        $this->assertSame('cms_posts', data_get($blocks->firstWhere('block_type', 'ec909_latest_posts'), 'settings_schema.source.options.0.value'));
    }

    public function test_ec909_demo_and_storefront_pages_render_successfully(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('EC909');
        $this->assertNotNull($provider);
        $result = $provider->generate('ec909-euro-sound');
        $this->assertSame(4, data_get($result, 'counts.categories'));
        $this->assertSame(10, data_get($result, 'counts.products'));
        $this->assertSame(2, data_get($result, 'counts.banners'));
        $this->assertDatabaseHas('site_banners', ['theme_key' => 'EC909', 'placement' => 'ec909-hero-slider']);
        $this->assertDatabaseHas('landing_pages', ['theme_key' => 'EC909', 'slug' => 'home', 'is_home' => true]);

        $this->get(route('site.home', ['locale' => 'vi']))->assertOk()
            ->assertSee('data-block-type="ec909_category_cards"', false)
            ->assertSee('data-block-type="ec909_headphone_showcase"', false)
            ->assertSee('data-block-type="ec909_earphone_products"', false)
            ->assertSee('data-block-type="ec909_latest_posts"', false)
            ->assertSee('data-xd-auth-open="login"', false)
            ->assertSee('Euro Sound');

        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();
        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk()->assertSee($category->name);
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk()->assertSee($product->name);
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'Cloud']))->assertOk();
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk();
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk();
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk()->assertSee($post->title);
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Liên hệ Euro Sound');
        $page = LandingPage::query()->where('theme_key', 'EC909')->where('is_home', true)->firstOrFail();
        $this->assertCount(13, $page->blocks);
    }

    public function test_ec909_demo_preserves_an_existing_custom_logo(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Website',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'TH0001',
            'branding' => ['logo_url' => '/storage/branding/custom-logo.svg'],
        ]);

        app(ThemeDemoContentProviderRegistry::class)->forTheme('EC909')?->generate('ec909-euro-sound');
        $this->assertSame('/storage/branding/custom-logo.svg', data_get(SiteProfile::query()->firstOrFail()->branding, 'logo_url'));
    }
}
