<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Models\LandingPageData;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Shop602ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop602_is_registered_with_configurable_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'SHOP602');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('SHOP602'));
        $this->assertSame([
            'hero_slider', 'shop602_quality_slider', 'shop602_flash_sale', 'shop602_dual_ads', 'shop602_new_arrivals',
            'shop602_product_explorer', 'shop602_accessories', 'shop602_single_ad', 'shop602_latest_news', 'shop602_contact_form',
        ], collect($builder->availableBlocks('SHOP602'))->pluck('block_type')->all());
        foreach (['shop602_flash_sale', 'shop602_new_arrivals', 'shop602_product_explorer', 'shop602_accessories'] as $type) {
            $block = collect($builder->availableBlocks('SHOP602'))->firstWhere('block_type', $type);
            $this->assertSame('cms_products', data_get($block, 'settings_schema.source.options.0.value'));
            $this->assertArrayHasKey('category_id', data_get($block, 'settings_schema'));
        }
    }

    public function test_shop602_demo_and_storefront_render_successfully(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('SHOP602');
        $this->assertNotNull($provider);
        $result = $provider->generate('shop602-wolf-yoga');
        $this->assertSame(10, data_get($result, 'counts.products'));
        $this->assertDatabaseHas('site_banners', ['theme_key' => 'SHOP602', 'placement' => 'shop602-hero-slider']);
        $this->assertDatabaseHas('landing_pages', ['theme_key' => 'SHOP602', 'slug' => 'home', 'is_home' => true]);
        $this->get(route('site.home', ['locale' => 'vi']))->assertOk()->assertSee('data-block-type="shop602_product_explorer"', false)->assertSee('data-block-type="shop602_contact_form"', false)->assertSee('WOLF YOGA');
        $category = CatalogCategory::query()->firstOrFail(); $product = CatalogProduct::query()->firstOrFail(); $post = CmsPost::query()->firstOrFail();
        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk()->assertSee($category->name);
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk()->assertSee($product->name);
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'Yoga']))->assertOk()->assertSee('Kết quả tìm kiếm');
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk()->assertSee('Giỏ hàng');
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk()->assertSee('Tin tức');
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk()->assertSee($post->title);
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Liên hệ Wolf Yoga');
        $page = LandingPage::query()->where('theme_key', 'SHOP602')->where('is_home', true)->firstOrFail();
        $this->assertCount(10, $page->blocks);
    }

    public function test_shop602_uses_site_landing_show_route(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('SHOP602')?->generate('shop602-wolf-yoga');
        $page = LandingPage::query()->create(['website_key' => 'website-main', 'theme_key' => 'SHOP602', 'page_type' => 'landing', 'slug' => 'yoga-campaign', 'status' => 'published', 'template' => 'home', 'is_home' => false, 'sort_order' => 1, 'settings' => [], 'published_at' => now()]);
        LandingPageData::query()->create(['landing_page_id' => $page->id, 'locale' => 'vi', 'title' => 'Yoga Campaign']);
        $block = LandingPageBlock::query()->create(['landing_page_id' => $page->id, 'theme_key' => 'SHOP602', 'block_type' => 'shop602_quality_slider', 'sort_order' => 0, 'is_visible' => true, 'anchor_id' => 'cam-ket', 'settings' => [], 'media' => []]);
        LandingPageBlockData::query()->create(['landing_page_block_id' => $block->id, 'locale' => 'vi', 'title' => 'Cam kết', 'content' => json_encode(['items' => [['title' => 'Đồng hành tận tâm', 'summary' => 'Luôn lắng nghe bạn']]], JSON_UNESCAPED_UNICODE)]);
        $outputBufferLevel = ob_get_level();
        $this->get(route('site.landing.show', ['locale' => 'vi', 'slug' => 'yoga-campaign']))->assertOk()->assertSee('data-block-type="shop602_quality_slider"', false);
        while (ob_get_level() > $outputBufferLevel) ob_end_clean();
        $this->assertSame($outputBufferLevel, ob_get_level());
    }
}
