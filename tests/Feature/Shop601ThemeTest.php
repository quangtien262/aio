<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Models\LandingPageData;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Shop601ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop601_is_registered_with_all_builder_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'SHOP601');

        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('SHOP601'));
        $this->assertSame([
            'hero_slider', 'shop601_benefits', 'shop601_collection_cards', 'shop601_flash_sale', 'shop601_ads',
            'shop601_product_grid', 'shop601_feature_collection', 'shop601_product_carousel', 'testimonials',
            'shop601_tiktok', 'shop601_latest_content',
        ], collect($builder->availableBlocks('SHOP601'))->pluck('block_type')->all());

        $collection = collect($builder->availableBlocks('SHOP601'))->firstWhere('block_type', 'shop601_collection_cards');
        $this->assertSame(['custom', 'cms_products', 'cms_posts', 'catalog_categories', 'cms_categories'], collect(data_get($collection, 'settings_schema.source.options'))->pluck('value')->all());
    }

    public function test_shop601_demo_and_homepage_render_successfully(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('SHOP601');
        $this->assertNotNull($provider);

        $result = $provider->generate('shop601-bean-style');
        $this->assertSame(10, data_get($result, 'counts.products'));
        $this->assertDatabaseHas('site_banners', ['theme_key' => 'SHOP601', 'placement' => 'shop601-hero-slider']);
        $this->assertDatabaseHas('landing_pages', ['theme_key' => 'SHOP601', 'slug' => 'home', 'is_home' => true]);

        $outputBufferLevel = ob_get_level();
        $response = $this->get(route('site.home', ['locale' => 'vi']));
        $response->assertOk()->assertSee('data-block-type="shop601_product_grid"', false)->assertSee('BEAN Style');
        $this->assertSame($outputBufferLevel, ob_get_level(), 'Homepage left an output buffer open.');

        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();
        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk()->assertSee($category->name);
        $this->assertSame($outputBufferLevel, ob_get_level(), 'Category page left an output buffer open.');
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk()->assertSee($product->name);
        $this->assertSame($outputBufferLevel, ob_get_level(), 'Product page left an output buffer open.');
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'Đầm']))->assertOk()->assertSee('Kết quả tìm kiếm');
        $this->assertSame($outputBufferLevel, ob_get_level(), 'Search page left an output buffer open.');
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk()->assertSee('Giỏ hàng');
        $this->assertSame($outputBufferLevel, ob_get_level(), 'Cart page left an output buffer open.');
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk()->assertSee('Tin tức');
        $this->assertSame($outputBufferLevel, ob_get_level(), 'News page left an output buffer open.');
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk()->assertSee($post->title);
        $this->assertSame($outputBufferLevel, ob_get_level(), 'News detail left an output buffer open.');
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Liên hệ với chúng tôi');
        $this->assertSame($outputBufferLevel, ob_get_level(), 'Contact page left an output buffer open.');

        $page = LandingPage::query()->where('theme_key', 'SHOP601')->where('is_home', true)->firstOrFail();
        $this->assertCount(11, $page->blocks);
    }

    public function test_shop601_uses_the_named_landing_page_route(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('SHOP601')?->generate('shop601-bean-style');
        $page = LandingPage::query()->create(['website_key' => 'website-main', 'theme_key' => 'SHOP601', 'page_type' => 'landing', 'slug' => 'summer-lookbook', 'status' => 'published', 'template' => 'home', 'is_home' => false, 'sort_order' => 1, 'settings' => [], 'published_at' => now()]);
        LandingPageData::query()->create(['landing_page_id' => $page->id, 'locale' => 'vi', 'title' => 'Summer Lookbook', 'meta_title' => 'Summer Lookbook']);
        $block = LandingPageBlock::query()->create(['landing_page_id' => $page->id, 'theme_key' => 'SHOP601', 'block_type' => 'shop601_benefits', 'sort_order' => 0, 'is_visible' => true, 'anchor_id' => 'loi-ich', 'settings' => [], 'media' => []]);
        LandingPageBlockData::query()->create(['landing_page_block_id' => $block->id, 'locale' => 'vi', 'title' => 'Summer Lookbook', 'content' => json_encode(['items' => [['title' => 'Đổi trả dễ dàng', 'summary' => 'Trong vòng 15 ngày']]], JSON_UNESCAPED_UNICODE)]);

        $this->get(route('site.landing.show', ['locale' => 'vi', 'slug' => 'summer-lookbook']))
            ->assertOk()
            ->assertSee('data-block-type="shop601_benefits"', false);
    }
}
