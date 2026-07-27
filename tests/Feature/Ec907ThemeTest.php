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

class Ec907ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ec907_is_registered_with_editable_home_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'EC907');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertNotNull(data_get($theme, 'preview.thumbnail'));
        $this->assertNotNull(data_get($theme, 'preview.cover'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('EC907'));
        $this->assertSame([
            'hero_slider', 'ec907_benefits', 'ec907_category_grid', 'ec907_best_sellers', 'ec907_campaign_cards',
            'ec907_audio_showcase', 'ec907_gaming_products', 'ec907_brand_strip', 'ec907_tech_news', 'ec907_newsletter',
        ], collect($builder->availableBlocks('EC907'))->pluck('block_type')->all());
        $categories = collect($builder->availableBlocks('EC907'))->firstWhere('block_type', 'ec907_category_grid');
        $products = collect($builder->availableBlocks('EC907'))->firstWhere('block_type', 'ec907_best_sellers');
        $posts = collect($builder->availableBlocks('EC907'))->firstWhere('block_type', 'ec907_tech_news');
        $this->assertSame('catalog_categories', data_get($categories, 'settings_schema.source.options.0.value'));
        $this->assertSame('cms_products', data_get($products, 'settings_schema.source.options.0.value'));
        $this->assertSame('cms_posts', data_get($posts, 'settings_schema.source.options.0.value'));
    }

    public function test_ec907_demo_and_storefront_pages_render_successfully(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('EC907');
        $this->assertNotNull($provider);
        $result = $provider->generate('ec907-ega-gear');
        $this->assertSame(16, data_get($result, 'counts.categories'));
        $this->assertSame(17, data_get($result, 'counts.products'));
        $this->assertSame(2, data_get($result, 'counts.banners'));
        $this->assertDatabaseHas('site_banners', ['theme_key' => 'EC907', 'placement' => 'ec907-hero-slider']);
        $this->assertDatabaseHas('landing_pages', ['theme_key' => 'EC907', 'slug' => 'home', 'is_home' => true]);

        $this->get(route('site.home', ['locale' => 'vi']))->assertOk()
            ->assertSee('data-block-type="ec907_category_grid"', false)
            ->assertSee('data-block-type="ec907_best_sellers"', false)
            ->assertSee('data-block-type="ec907_audio_showcase"', false)
            ->assertSee('data-block-type="ec907_tech_news"', false)
            ->assertSee('data-xd-auth-open="login"', false)->assertSee('EGA Gear');

        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();
        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk()->assertSee($category->name);
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk()->assertSee($product->name);
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'Bàn phím']))->assertOk()->assertSee('Tìm kiếm sản phẩm');
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk()->assertSee('Giỏ hàng');
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk()->assertSee('Tin tức mới nhất');
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk()->assertSee($post->title);
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Liên hệ EGA Gear');
        $page = LandingPage::query()->where('theme_key', 'EC907')->where('is_home', true)->firstOrFail();
        $this->assertCount(10, $page->blocks);
    }

    public function test_ec907_demo_preserves_an_existing_custom_logo(): void
    {
        SiteProfile::query()->create(['site_name' => 'Website', 'website_type' => 'ecommerce', 'active_theme_key' => 'TH0001', 'branding' => ['logo_url' => '/storage/branding/custom-logo.svg']]);
        app(ThemeDemoContentProviderRegistry::class)->forTheme('EC907')?->generate('ec907-ega-gear');
        $this->assertSame('/storage/branding/custom-logo.svg', data_get(SiteProfile::query()->firstOrFail()->branding, 'logo_url'));
    }
}
