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

class Ec900ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ec900_is_registered_as_an_ecommerce_landing_theme(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'EC900');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertNotNull(data_get($theme, 'preview.thumbnail'));
        $this->assertNotNull(data_get($theme, 'preview.cover'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('EC900'));
        $this->assertSame([
            'hero_slider',
            'ec900_featured_categories',
            'ec900_best_sellers',
            'ec900_need_mosaic',
            'ec900_campaign_mosaic',
            'ec900_exclusive_products',
            'ec900_brand_banner',
            'ec900_advice_posts',
        ], collect($builder->availableBlocks('EC900'))->pluck('block_type')->all());

        $categories = collect($builder->availableBlocks('EC900'))->firstWhere('block_type', 'ec900_featured_categories');
        $products = collect($builder->availableBlocks('EC900'))->firstWhere('block_type', 'ec900_exclusive_products');
        $this->assertSame('catalog_categories', data_get($categories, 'settings_schema.source.options.0.value'));
        $this->assertSame('cms_products', data_get($products, 'settings_schema.source.options.0.value'));
        $this->assertArrayHasKey('category_id', data_get($products, 'settings_schema'));
    }

    public function test_ec900_demo_and_storefront_pages_render_successfully(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('EC900');
        $this->assertNotNull($provider);
        $result = $provider->generate('ec900-smart-home');

        $this->assertSame(8, data_get($result, 'counts.categories'));
        $this->assertSame(12, data_get($result, 'counts.products'));
        $this->assertDatabaseHas('site_banners', ['theme_key' => 'EC900', 'placement' => 'ec900-hero-slider']);
        $this->assertDatabaseHas('landing_pages', ['theme_key' => 'EC900', 'slug' => 'home', 'is_home' => true]);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('data-ec9-category-rail', false)
            ->assertSee('data-block-type="ec900_featured_categories"', false)
            ->assertSee('data-block-type="ec900_exclusive_products"', false)
            ->assertSee('Danh mục sản phẩm')
            ->assertSee('ECOMAX');

        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();
        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk()->assertSee($category->name);
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk()->assertSee($product->name);
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'Máy']))->assertOk()->assertSee('Kết quả tìm kiếm');
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk()->assertSee('Giỏ hàng');
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk()->assertSee('Tin tức');
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk()->assertSee($post->title);
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Liên hệ Ecomax');

        $page = LandingPage::query()->where('theme_key', 'EC900')->where('is_home', true)->firstOrFail();
        $this->assertCount(8, $page->blocks);
    }

    public function test_ec900_demo_preserves_an_existing_custom_logo(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Website',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'SHOP601',
            'branding' => ['logo_url' => '/storage/branding/custom-logo.svg'],
        ]);

        app(ThemeDemoContentProviderRegistry::class)->forTheme('EC900')?->generate('ec900-smart-home');

        $this->assertSame('/storage/branding/custom-logo.svg', data_get(SiteProfile::query()->firstOrFail()->branding, 'logo_url'));
    }
}
