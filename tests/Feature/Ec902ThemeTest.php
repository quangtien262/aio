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

class Ec902ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ec902_is_registered_as_an_ecommerce_landing_theme(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'EC902');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertNotNull(data_get($theme, 'preview.thumbnail'));
        $this->assertNotNull(data_get($theme, 'preview.cover'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('EC902'));
        $this->assertSame([
            'hero_slider',
            'ec902_benefits',
            'ec902_featured_categories',
            'ec902_product_tabs',
            'ec902_featured_deals',
            'ec902_phone_collection',
            'ec902_tablet_collection',
            'ec902_accessory_categories',
            'ec902_accessory_products',
            'ec902_wide_banner',
            'ec902_latest_posts',
            'ec902_video_reviews',
            'ec902_testimonials',
            'ec902_support_strip',
        ], collect($builder->availableBlocks('EC902'))->pluck('block_type')->all());

        $categories = collect($builder->availableBlocks('EC902'))->firstWhere('block_type', 'ec902_featured_categories');
        $products = collect($builder->availableBlocks('EC902'))->firstWhere('block_type', 'ec902_product_tabs');
        $posts = collect($builder->availableBlocks('EC902'))->firstWhere('block_type', 'ec902_latest_posts');
        $this->assertSame('catalog_categories', data_get($categories, 'settings_schema.source.options.0.value'));
        $this->assertSame('cms_products', data_get($products, 'settings_schema.source.options.0.value'));
        $this->assertSame('cms_posts', data_get($posts, 'settings_schema.source.options.0.value'));
        $this->assertArrayHasKey('category_id', data_get($products, 'settings_schema'));
    }

    public function test_ec902_demo_and_storefront_pages_render_successfully(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('EC902');
        $this->assertNotNull($provider);
        $result = $provider->generate('ec902-novaphone');

        $this->assertSame(5, data_get($result, 'counts.categories'));
        $this->assertSame(12, data_get($result, 'counts.products'));
        $this->assertSame(2, data_get($result, 'counts.banners'));
        $this->assertDatabaseHas('site_banners', ['theme_key' => 'EC902', 'placement' => 'ec902-hero-slider']);
        $this->assertDatabaseHas('landing_pages', ['theme_key' => 'EC902', 'slug' => 'home', 'is_home' => true]);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('data-ec92-slider', false)
            ->assertSee('data-block-type="ec902_featured_deals"', false)
            ->assertSee('data-block-type="ec902_accessory_products"', false)
            ->assertSee('NOVAPHONE');

        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();
        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk()->assertSee($category->name);
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk()->assertSee($product->name);
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'Nova']))->assertOk()->assertSee('Tìm kiếm sản phẩm');
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk()->assertSee('Giỏ hàng');
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk()->assertSee('Tin công nghệ');
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk()->assertSee($post->title);
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Liên hệ NovaPhone');

        $page = LandingPage::query()->where('theme_key', 'EC902')->where('is_home', true)->firstOrFail();
        $this->assertCount(14, $page->blocks);
    }

    public function test_ec902_demo_preserves_an_existing_custom_logo(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Website',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'TH0001',
            'branding' => ['logo_url' => '/storage/branding/custom-logo.svg'],
        ]);

        app(ThemeDemoContentProviderRegistry::class)->forTheme('EC902')?->generate('ec902-novaphone');

        $this->assertSame('/storage/branding/custom-logo.svg', data_get(SiteProfile::query()->firstOrFail()->branding, 'logo_url'));
    }
}
