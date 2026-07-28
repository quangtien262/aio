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

class Ec912ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ec912_is_registered_with_nine_ordered_home_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'EC912');

        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('EC912'));
        $this->assertSame([
            'hero_slider',
            'ec912_benefits',
            'ec912_hot_sale',
            'ec912_featured_categories',
            'ec912_promotion_banners',
            'ec912_iphone_products',
            'ec912_technology_news',
            'ec912_customer_gallery',
            'ec912_footer',
        ], collect($builder->availableBlocks('EC912'))->pluck('block_type')->all());
    }

    public function test_ec912_demo_preserves_database_branding_and_storefront_renders(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Cửa hàng của tôi',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'TH0001',
            'branding' => [
                'logo_url' => '/storage/branding/custom-sudes.svg',
                'support_hotline' => '0909 123 456',
                'support_email' => 'hello@example.test',
                'support_location' => '123 Đường thử nghiệm',
            ],
        ]);

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('EC912');
        $this->assertNotNull($provider);
        $result = $provider->generate('ec912-sudes-phone');

        $this->assertSame(6, data_get($result, 'counts.categories'));
        $this->assertSame(8, data_get($result, 'counts.products'));
        $this->assertSame(2, data_get($result, 'counts.banners'));

        $branding = (array) SiteProfile::query()->firstOrFail()->branding;
        $this->assertSame('/storage/branding/custom-sudes.svg', data_get($branding, 'logo_url'));
        $this->assertSame('0909 123 456', data_get($branding, 'support_hotline'));
        $this->assertSame('hello@example.test', data_get($branding, 'support_email'));

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('/storage/branding/custom-sudes.svg', false)
            ->assertSee('0909 123 456')
            ->assertSee('hello@example.test')
            ->assertSee('HOT SALE CUỐI TUẦN')
            ->assertSee('DANH MỤC NỔI BẬT')
            ->assertSee('TIN TỨC')
            ->assertSee('iPhone 14 Pro Max 512GB')
            ->assertSee('data-block-type="ec912_hot_sale"', false)
            ->assertSee('data-block-type="ec912_iphone_products"', false)
            ->assertSee('data-block-type="ec912_customer_gallery"', false)
            ->assertSee('data-xd-auth-open="login"', false);

        $page = LandingPage::query()
            ->where('theme_key', 'EC912')
            ->where('is_home', true)
            ->firstOrFail();
        $this->assertCount(9, $page->blocks);
    }

    public function test_ec912_catalog_and_content_pages_render_successfully(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('EC912')?->generate('ec912-sudes-phone');

        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();

        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk();
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk();
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'iPhone']))->assertOk();
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk();
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk();
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk();
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Liên hệ');
    }

    public function test_ec912_templates_do_not_hardcode_a_demo_logo(): void
    {
        $header = file_get_contents(base_path('themes/EC912/views/partials/header.blade.php'));
        $footer = file_get_contents(base_path('themes/EC912/views/partials/footer.blade.php'));

        $this->assertStringContainsString("data_get(\$branding, 'logo_url'", $header);
        $this->assertStringContainsString("data_get(\$branding, 'logo_url'", $footer);
        $this->assertStringNotContainsString('/theme-demo/ec912/logo', $header.$footer);
    }
}
