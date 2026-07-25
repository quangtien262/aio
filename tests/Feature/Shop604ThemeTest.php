<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\LandingPage;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Shop604ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop604_is_registered_with_the_reference_homepage_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'SHOP604');

        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertSame(['vi', 'en'], data_get($theme, 'localization.supported_locales'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('SHOP604'));
        $this->assertSame([
            'hero_slider',
            'featured_categories',
            'shop604_flash_sale',
            'shop604_editorial_one',
            'shop604_new_arrivals',
            'shop604_editorial_two',
            'shop604_collection_tabs',
            'shop604_lookbook',
            'testimonials',
            'partner_logos',
            'latest_posts',
            'shop604_benefits',
            'shop604_gallery',
            'shop604_newsletter',
        ], collect($builder->availableBlocks('SHOP604'))->pluck('block_type')->all());

        foreach (['shop604_flash_sale', 'shop604_new_arrivals', 'shop604_collection_tabs'] as $type) {
            $block = collect($builder->availableBlocks('SHOP604'))->firstWhere('block_type', $type);
            $this->assertSame('cms_products', data_get($block, 'settings_schema.source.options.0.value'));
            $this->assertArrayHasKey('category_id', data_get($block, 'settings_schema'));
            $this->assertArrayHasKey('search', data_get($block, 'settings_schema'));
        }
    }

    public function test_shop604_demo_data_and_storefront_routes_render(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('SHOP604');

        $this->assertNotNull($provider);
        $result = $provider->generate('shop604-bean-lingerie');
        $this->assertSame(12, data_get($result, 'counts.products'));
        $this->assertSame(4, data_get($result, 'counts.posts'));
        $this->assertDatabaseHas('site_banners', ['theme_key' => 'SHOP604', 'placement' => 'shop604-hero-slider']);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('data-block-type="shop604_flash_sale"', false)
            ->assertSee('data-block-type="shop604_collection_tabs"', false)
            ->assertSee('data-block-type="shop604_gallery"', false)
            ->assertSee('data-block-type="shop604_newsletter"', false)
            ->assertSee('Bean Lingerie');

        $landing = LandingPage::query()->where('theme_key', 'SHOP604')->where('is_home', true)->firstOrFail();
        $this->assertCount(14, $landing->blocks);

        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();

        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk()->assertSee($category->name);
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk()->assertSee($product->name);
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'bikini']))->assertOk()->assertSee('Kết quả tìm kiếm');
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk()->assertSee('Giỏ hàng');
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk()->assertSee('Tin tức');
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk()->assertSee($post->title);

        $filtered = app(LandingPageBuilder::class)->previewDynamicItems(
            $landing->blocks->firstWhere('block_type', 'shop604_new_arrivals'),
            'vi',
            ['search' => 'multiway'],
        );
        $this->assertCount(1, $filtered);
        $this->assertSame('Áo ngực multiway bốn kiểu', $filtered[0]['title']);
    }
}
