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

class Shop603ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop603_is_registered_with_required_configurable_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'SHOP603');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('SHOP603'));
        $this->assertSame([
            'hero_slider', 'shop603_quality_slider', 'shop603_hot_products', 'shop603_new_arrivals', 'shop603_single_ad',
            'shop603_sale_slider', 'shop603_newsletter', 'latest_posts', 'partner_logos',
        ], collect($builder->availableBlocks('SHOP603'))->pluck('block_type')->all());

        foreach (['shop603_hot_products', 'shop603_new_arrivals', 'shop603_sale_slider'] as $type) {
            $block = collect($builder->availableBlocks('SHOP603'))->firstWhere('block_type', $type);
            $this->assertSame('cms_products', data_get($block, 'settings_schema.source.options.0.value'));
            $this->assertArrayHasKey('category_id', data_get($block, 'settings_schema'));
            $this->assertArrayHasKey('search', data_get($block, 'settings_schema'));
        }
        $news = collect($builder->availableBlocks('SHOP603'))->firstWhere('block_type', 'latest_posts');
        $this->assertSame('cms_posts', data_get($news, 'settings_schema.source.options.0.value'));
        $this->assertArrayHasKey('search', data_get($news, 'settings_schema'));
    }

    public function test_shop603_demo_data_and_storefront_routes_render(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('SHOP603');
        $this->assertNotNull($provider);
        $result = $provider->generate('shop603-alena-fashion');
        $this->assertSame(12, data_get($result, 'counts.products'));
        $this->assertSame(6, data_get($result, 'counts.partners'));
        $this->assertDatabaseHas('site_banners', ['theme_key' => 'SHOP603', 'placement' => 'shop603-hero-slider']);
        $this->assertDatabaseHas('landing_pages', ['theme_key' => 'SHOP603', 'slug' => 'home', 'is_home' => true]);

        $this->get(route('site.home', ['locale' => 'vi']))->assertOk()
            ->assertSee('data-block-type="shop603_new_arrivals"', false)
            ->assertSee('data-block-type="shop603_newsletter"', false)
            ->assertSee('Alena');

        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();
        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk()->assertSee($category->name);
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk()->assertSee($product->name);
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'Alena']))->assertOk()->assertSee('Kết quả tìm kiếm');
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk()->assertSee('Giỏ hàng');
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk()->assertSee('Tin tức thời trang');
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk()->assertSee($post->title);
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Liên hệ Alena');
        $landing = LandingPage::query()->where('theme_key', 'SHOP603')->where('is_home', true)->firstOrFail();
        $this->assertCount(9, $landing->blocks);

        $filteredProducts = app(LandingPageBuilder::class)->previewDynamicItems(
            $landing->blocks->firstWhere('block_type', 'shop603_new_arrivals'),
            'vi',
            ['search' => 'Áo khoác'],
        );
        $this->assertCount(1, $filteredProducts);
        $this->assertSame('Áo khoác nữ pastel', $filteredProducts[0]['title']);
    }
}
