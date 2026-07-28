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

class Ec913ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ec913_is_registered_with_eight_ordered_home_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'EC913');

        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('EC913'));
        $this->assertSame([
            'hero_slider',
            'ec913_benefits',
            'ec913_category_grid',
            'ec913_promotion_banners',
            'ec913_best_sellers',
            'ec913_laptop_showcase',
            'ec913_technology_news',
            'ec913_footer',
        ], collect($builder->availableBlocks('EC913'))->pluck('block_type')->all());
    }

    public function test_ec913_demo_preserves_database_branding_and_storefront_renders(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Cửa hàng của tôi',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'TH0001',
            'branding' => [
                'logo_url' => '/storage/branding/custom-novatech.svg',
                'support_hotline' => '0909 123 456',
                'support_email' => 'hello@example.test',
                'support_location' => '123 Đường thử nghiệm',
            ],
        ]);

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('EC913');
        $this->assertNotNull($provider);
        $result = $provider->generate('ec913-novatech-mall');

        $this->assertSame(10, data_get($result, 'counts.categories'));
        $this->assertSame(10, data_get($result, 'counts.products'));
        $this->assertSame(2, data_get($result, 'counts.banners'));
        $this->assertSame(3, data_get($result, 'counts.posts'));

        $branding = (array) SiteProfile::query()->firstOrFail()->branding;
        $this->assertSame('/storage/branding/custom-novatech.svg', data_get($branding, 'logo_url'));
        $this->assertSame('0909 123 456', data_get($branding, 'support_hotline'));
        $this->assertSame('hello@example.test', data_get($branding, 'support_email'));

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('/storage/branding/custom-novatech.svg', false)
            ->assertSee('0909 123 456')
            ->assertSee('hello@example.test')
            ->assertSee('Danh mục nổi bật')
            ->assertSee('Sản phẩm bán chạy')
            ->assertSee('Laptop &amp; Thiết bị tin học', false)
            ->assertSee('Nova X Pro 256GB')
            ->assertSee('data-block-type="ec913_category_grid"', false)
            ->assertSee('data-block-type="ec913_best_sellers"', false)
            ->assertSee('data-block-type="ec913_laptop_showcase"', false)
            ->assertSee('data-xd-auth-open="login"', false);

        $page = LandingPage::query()
            ->where('theme_key', 'EC913')
            ->where('is_home', true)
            ->firstOrFail();
        $this->assertCount(8, $page->blocks);
    }

    public function test_ec913_catalog_and_content_pages_render_successfully(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('EC913')?->generate('ec913-novatech-mall');

        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();

        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk();
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk();
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'Nova']))->assertOk();
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk();
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk();
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk();
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Liên hệ');
    }

    public function test_ec913_templates_do_not_hardcode_a_demo_logo(): void
    {
        $header = file_get_contents(base_path('themes/EC913/views/partials/header.blade.php'));
        $footer = file_get_contents(base_path('themes/EC913/views/partials/footer.blade.php'));

        $this->assertStringContainsString("data_get(\$branding, 'logo_url'", $header);
        $this->assertStringContainsString("data_get(\$branding, 'logo_url'", $footer);
        $this->assertStringNotContainsString('/theme-demo/ec913/logo', $header.$footer);
    }
}
