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

class Ec904ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ec904_is_registered_as_an_ecommerce_landing_theme(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'EC904');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertNotNull(data_get($theme, 'preview.thumbnail'));
        $this->assertNotNull(data_get($theme, 'preview.cover'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('EC904'));
        $this->assertSame([
            'hero_slider',
            'ec904_category_carousel',
            'ec904_tabbed_sale',
            'ec904_technology_products',
            'ec904_fashion_products',
            'ec904_daily_suggestions',
            'ec904_latest_posts',
            'ec904_newsletter',
        ], collect($builder->availableBlocks('EC904'))->pluck('block_type')->all());

        $categories = collect($builder->availableBlocks('EC904'))->firstWhere('block_type', 'ec904_category_carousel');
        $products = collect($builder->availableBlocks('EC904'))->firstWhere('block_type', 'ec904_technology_products');
        $posts = collect($builder->availableBlocks('EC904'))->firstWhere('block_type', 'ec904_latest_posts');
        $this->assertSame('catalog_categories', data_get($categories, 'settings_schema.source.options.0.value'));
        $this->assertSame('cms_products', data_get($products, 'settings_schema.source.options.0.value'));
        $this->assertSame('cms_posts', data_get($posts, 'settings_schema.source.options.0.value'));
        $this->assertArrayHasKey('category_id', data_get($products, 'settings_schema'));
    }

    public function test_ec904_demo_and_storefront_pages_render_successfully(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('EC904');
        $this->assertNotNull($provider);
        $result = $provider->generate('ec904-pocomall');

        $this->assertSame(10, data_get($result, 'counts.categories'));
        $this->assertSame(25, data_get($result, 'counts.products'));
        $this->assertSame(2, data_get($result, 'counts.banners'));
        $this->assertDatabaseHas('site_banners', ['theme_key' => 'EC904', 'placement' => 'ec904-hero-slider']);
        $this->assertDatabaseHas('landing_pages', ['theme_key' => 'EC904', 'slug' => 'home', 'is_home' => true]);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('data-ec94-slider', false)
            ->assertSee('data-block-type="ec904_category_carousel"', false)
            ->assertSee('data-block-type="ec904_technology_products"', false)
            ->assertSee('data-block-type="ec904_latest_posts"', false)
            ->assertSee('POCOMALL');

        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();
        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk()->assertSee($category->name);
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk()->assertSee($product->name);
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'Nova']))->assertOk()->assertSee('Tìm kiếm sản phẩm');
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk()->assertSee('Giỏ hàng');
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk()->assertSee('Tin tức mới nhất');
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk()->assertSee($post->title);
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Liên hệ PocoMall');

        $page = LandingPage::query()->where('theme_key', 'EC904')->where('is_home', true)->firstOrFail();
        $this->assertCount(8, $page->blocks);
    }

    public function test_ec904_demo_preserves_an_existing_custom_logo(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Website',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'SHOP601',
            'branding' => ['logo_url' => '/storage/branding/custom-logo.svg'],
        ]);
        app(ThemeDemoContentProviderRegistry::class)->forTheme('EC904')?->generate('ec904-pocomall');
        $this->assertSame('/storage/branding/custom-logo.svg', data_get(SiteProfile::query()->firstOrFail()->branding, 'logo_url'));
        $response = $this->get(route('site.home', ['locale' => 'vi']))->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), '<img src="/storage/branding/custom-logo.svg"'));
    }
}
