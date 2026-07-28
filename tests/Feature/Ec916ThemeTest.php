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

class Ec916ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ec916_is_registered_with_nine_ordered_blocks_and_scroll_reveal(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'EC916');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('EC916'));
        $this->assertSame([
            'hero_slider',
            'ec916_categories',
            'ec916_featured_deals',
            'ec916_promo_pair',
            'ec916_beauty_deals',
            'ec916_campaign_mosaic',
            'ec916_brands',
            'ec916_newsletter',
            'ec916_footer',
        ], collect($builder->availableBlocks('EC916'))->pluck('block_type')->all());

        $styles = file_get_contents(base_path('themes/EC916/views/partials/styles.blade.php'));
        $scripts = file_get_contents(base_path('themes/EC916/views/partials/scripts.blade.php'));
        $this->assertStringContainsString('font-family:"EC916 Sans"', $styles);
        $this->assertStringContainsString('ec16-motion-ready', $styles);
        $this->assertStringContainsString('IntersectionObserver', $scripts);
        $this->assertStringContainsString('prefers-reduced-motion', $scripts);
        $this->assertStringContainsString('data-ec16-reveal', file_get_contents(base_path('themes/EC916/views/home.blade.php')));
        $this->assertFileExists(public_path('theme-demo/ec916/hero-mega-sale.webp'));
    }

    public function test_ec916_demo_preserves_branding_and_storefront_renders(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Siêu thị của tôi',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'TH0001',
            'branding' => [
                'logo_url' => '/storage/branding/custom-mart.svg',
                'support_hotline' => '0901 234 567',
                'support_email' => 'mart@example.test',
                'support_location' => '25 Phố Tiện Lợi',
            ],
        ]);

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('EC916');
        $this->assertNotNull($provider);
        $result = $provider->generate('ec916-bach-hoa-xanh-plus');

        $this->assertSame(8, data_get($result, 'counts.categories'));
        $this->assertSame(12, data_get($result, 'counts.products'));
        $this->assertSame(2, data_get($result, 'counts.banners'));
        $this->assertSame(3, data_get($result, 'counts.posts'));
        $branding = (array) SiteProfile::query()->firstOrFail()->branding;
        $this->assertSame('/storage/branding/custom-mart.svg', data_get($branding, 'logo_url'));
        $this->assertSame('0901 234 567', data_get($branding, 'support_hotline'));

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('/storage/branding/custom-mart.svg', false)
            ->assertSee('Danh mục nổi bật')
            ->assertSee('Sản phẩm nổi bật')
            ->assertSee('Sức khỏe &amp; Làm đẹp', false)
            ->assertSee('Chiến dịch mua sắm trong tuần')
            ->assertSee('data-block-type="ec916_featured_deals"', false)
            ->assertSee('data-block-type="ec916_campaign_mosaic"', false)
            ->assertSee('data-xd-auth-open="login"', false);

        $page = LandingPage::query()->where('theme_key', 'EC916')->where('is_home', true)->firstOrFail();
        $this->assertCount(9, $page->blocks);
    }

    public function test_ec916_catalog_and_content_pages_render_successfully(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('EC916')?->generate('ec916-bach-hoa-xanh-plus');
        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();

        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk();
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk();
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'combo']))->assertOk();
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk();
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk();
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk();
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Liên hệ');
    }

    public function test_ec916_templates_respect_uploaded_logo(): void
    {
        $header = file_get_contents(base_path('themes/EC916/views/partials/header.blade.php'));
        $this->assertStringContainsString("data_get(\$branding, 'logo_url'", $header);
        $this->assertStringNotContainsString('/theme-demo/ec916/logo', $header);
    }
}
