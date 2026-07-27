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

class Ec906ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ec906_is_registered_with_editable_home_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'EC906');

        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertNotNull(data_get($theme, 'preview.thumbnail'));
        $this->assertNotNull(data_get($theme, 'preview.cover'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('EC906'));
        $this->assertSame([
            'hero_slider',
            'ec906_benefits',
            'ec906_flash_sale',
            'ec906_family_care',
            'ec906_category_promos',
            'ec906_kitchen_products',
            'ec906_latest_posts',
            'ec906_brand_strip',
            'ec906_newsletter',
        ], collect($builder->availableBlocks('EC906'))->pluck('block_type')->all());

        $products = collect($builder->availableBlocks('EC906'))->firstWhere('block_type', 'ec906_flash_sale');
        $posts = collect($builder->availableBlocks('EC906'))->firstWhere('block_type', 'ec906_latest_posts');
        $this->assertSame('cms_products', data_get($products, 'settings_schema.source.options.0.value'));
        $this->assertSame('cms_posts', data_get($posts, 'settings_schema.source.options.0.value'));
        $this->assertArrayHasKey('category_id', data_get($products, 'settings_schema'));
    }

    public function test_ec906_demo_and_storefront_pages_render_successfully(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('EC906');
        $this->assertNotNull($provider);
        $result = $provider->generate('ec906-ega-minimart');

        $this->assertSame(8, data_get($result, 'counts.categories'));
        $this->assertSame(20, data_get($result, 'counts.products'));
        $this->assertSame(2, data_get($result, 'counts.banners'));
        $this->assertDatabaseHas('site_banners', ['theme_key' => 'EC906', 'placement' => 'ec906-hero-slider']);
        $this->assertDatabaseHas('landing_pages', ['theme_key' => 'EC906', 'slug' => 'home', 'is_home' => true]);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('data-block-type="ec906_flash_sale"', false)
            ->assertSee('data-block-type="ec906_family_care"', false)
            ->assertSee('data-block-type="ec906_kitchen_products"', false)
            ->assertSee('data-block-type="ec906_latest_posts"', false)
            ->assertSee('data-xd-auth-open="login"', false)
            ->assertSee('EGA Mini Mart');

        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();
        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk()->assertSee($category->name);
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk()->assertSee($product->name);
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'Nước']))->assertOk()->assertSee('Tìm kiếm sản phẩm');
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk()->assertSee('Giỏ hàng');
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk()->assertSee('Tin tức mới nhất');
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk()->assertSee($post->title);
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Liên hệ EGA Mini Mart');

        $page = LandingPage::query()->where('theme_key', 'EC906')->where('is_home', true)->firstOrFail();
        $this->assertCount(9, $page->blocks);
    }

    public function test_ec906_demo_preserves_an_existing_custom_logo(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Website',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'TH0001',
            'branding' => ['logo_url' => '/storage/branding/custom-logo.svg'],
        ]);

        app(ThemeDemoContentProviderRegistry::class)->forTheme('EC906')?->generate('ec906-ega-minimart');

        $this->assertSame('/storage/branding/custom-logo.svg', data_get(SiteProfile::query()->firstOrFail()->branding, 'logo_url'));
    }
}
