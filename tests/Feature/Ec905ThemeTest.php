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

class Ec905ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ec905_is_registered_as_an_ecommerce_landing_theme(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'EC905');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertNotNull(data_get($theme, 'preview.thumbnail'));
        $this->assertNotNull(data_get($theme, 'preview.cover'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('EC905'));
        $this->assertSame([
            'hero_slider',
            'ec905_benefits',
            'ec905_paint_products',
            'ec905_tile_products',
            'ec905_projects',
            'ec905_news',
            'ec905_newsletter',
        ], collect($builder->availableBlocks('EC905'))->pluck('block_type')->all());

        $paint = collect($builder->availableBlocks('EC905'))->firstWhere('block_type', 'ec905_paint_products');
        $projects = collect($builder->availableBlocks('EC905'))->firstWhere('block_type', 'ec905_projects');
        $this->assertSame('cms_products', data_get($paint, 'settings_schema.source.options.0.value'));
        $this->assertSame('cms_posts', data_get($projects, 'settings_schema.source.options.0.value'));
        $this->assertArrayHasKey('category_id', data_get($paint, 'settings_schema'));
        $this->assertArrayHasKey('category_id', data_get($projects, 'settings_schema'));
    }

    public function test_ec905_demo_and_storefront_pages_render_successfully(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('EC905');
        $this->assertNotNull($provider);
        $result = $provider->generate('ec905-egohome');

        $this->assertSame(10, data_get($result, 'counts.categories'));
        $this->assertSame(22, data_get($result, 'counts.products'));
        $this->assertSame(1, data_get($result, 'counts.banners'));
        $this->assertSame(10, data_get($result, 'counts.posts'));
        $this->assertDatabaseHas('site_banners', ['theme_key' => 'EC905', 'placement' => 'ec905-hero-slider']);
        $this->assertDatabaseHas('landing_pages', ['theme_key' => 'EC905', 'slug' => 'home', 'is_home' => true]);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('data-ec95-slider', false)
            ->assertSee('data-block-type="ec905_paint_products"', false)
            ->assertSee('data-block-type="ec905_tile_products"', false)
            ->assertSee('data-block-type="ec905_projects"', false)
            ->assertSee('Công ty cổ phần Ego Home');

        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();
        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk()->assertSee($category->name);
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk()->assertSee($product->name);
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'Sơn']))->assertOk()->assertSee('Tìm kiếm sản phẩm');
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk()->assertSee('Giỏ hàng');
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk()->assertSee('Tin tức nhà đẹp');
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk()->assertSee($post->title);
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Liên hệ Ego Home');

        $page = LandingPage::query()->where('theme_key', 'EC905')->where('is_home', true)->firstOrFail();
        $this->assertCount(7, $page->blocks);
    }

    public function test_ec905_demo_preserves_an_existing_custom_logo(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Website',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'TH0001',
            'branding' => ['logo_url' => '/storage/branding/custom-logo.svg'],
        ]);
        app(ThemeDemoContentProviderRegistry::class)->forTheme('EC905')?->generate('ec905-egohome');
        $this->assertSame('/storage/branding/custom-logo.svg', data_get(SiteProfile::query()->firstOrFail()->branding, 'logo_url'));
        $response = $this->get(route('site.home', ['locale' => 'vi']))->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), '<img src="/storage/branding/custom-logo.svg"'));
    }
}
