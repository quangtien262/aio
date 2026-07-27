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

class Ec901ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ec901_is_registered_as_an_ecommerce_landing_theme(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'EC901');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertNotNull(data_get($theme, 'preview.thumbnail'));
        $this->assertNotNull(data_get($theme, 'preview.cover'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('EC901'));
        $this->assertSame([
            'hero_slider',
            'ec901_flash_deals',
            'ec901_featured_categories',
            'ec901_best_sellers',
            'ec901_gender_banners',
            'ec901_promotion_mosaic',
            'ec901_product_grid',
            'ec901_mini_promotions',
            'ec901_luxury_collection',
            'ec901_testimonials',
            'ec901_featured_brands',
            'ec901_latest_posts',
            'ec901_benefits',
        ], collect($builder->availableBlocks('EC901'))->pluck('block_type')->all());

        $categories = collect($builder->availableBlocks('EC901'))->firstWhere('block_type', 'ec901_featured_categories');
        $products = collect($builder->availableBlocks('EC901'))->firstWhere('block_type', 'ec901_product_grid');
        $this->assertSame('catalog_categories', data_get($categories, 'settings_schema.source.options.0.value'));
        $this->assertSame('cms_products', data_get($products, 'settings_schema.source.options.0.value'));
        $this->assertArrayHasKey('category_id', data_get($products, 'settings_schema'));
    }

    public function test_ec901_demo_and_storefront_pages_render_successfully(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('EC901');
        $this->assertNotNull($provider);
        $result = $provider->generate('ec901-tempo-watch');

        $this->assertSame(5, data_get($result, 'counts.categories'));
        $this->assertSame(12, data_get($result, 'counts.products'));
        $this->assertDatabaseHas('site_banners', ['theme_key' => 'EC901', 'placement' => 'ec901-hero-slider']);
        $this->assertDatabaseHas('landing_pages', ['theme_key' => 'EC901', 'slug' => 'home', 'is_home' => true]);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('data-ec91-slider', false)
            ->assertSee('data-block-type="ec901_flash_deals"', false)
            ->assertSee('data-block-type="ec901_product_grid"', false)
            ->assertSee('TEMPO');

        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();
        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk()->assertSee($category->name);
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk()->assertSee($product->name);
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'Tempo']))->assertOk()->assertSee('Tìm kiếm sản phẩm');
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk()->assertSee('Giỏ hàng');
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk()->assertSee('Tạp chí đồng hồ');
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk()->assertSee($post->title);
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Liên hệ Tempo');

        $page = LandingPage::query()->where('theme_key', 'EC901')->where('is_home', true)->firstOrFail();
        $this->assertCount(13, $page->blocks);
    }

    public function test_ec901_demo_preserves_an_existing_custom_logo(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Website',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'TH0001',
            'branding' => ['logo_url' => '/storage/branding/custom-logo.svg'],
        ]);

        app(ThemeDemoContentProviderRegistry::class)->forTheme('EC901')?->generate('ec901-tempo-watch');

        $this->assertSame('/storage/branding/custom-logo.svg', data_get(SiteProfile::query()->firstOrFail()->branding, 'logo_url'));
    }
}
