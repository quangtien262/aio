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

class Ec915ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ec915_is_registered_with_eleven_ordered_blocks_and_scroll_reveal(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'EC915');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('EC915'));
        $this->assertSame([
            'hero_slider',
            'ec915_about',
            'ec915_room_categories',
            'ec915_best_sellers',
            'ec915_contact_banner',
            'ec915_process',
            'ec915_reasons',
            'ec915_faq',
            'ec915_testimonials',
            'ec915_latest_posts',
            'ec915_footer',
        ], collect($builder->availableBlocks('EC915'))->pluck('block_type')->all());

        $styles = file_get_contents(base_path('themes/EC915/views/partials/styles.blade.php'));
        $scripts = file_get_contents(base_path('themes/EC915/views/partials/scripts.blade.php'));
        $layout = file_get_contents(base_path('themes/EC915/views/layout.blade.php'));
        $this->assertStringContainsString('font-family:"EC915 Sans"', $styles);
        $this->assertStringContainsString('ec15-motion-ready', $styles);
        $this->assertStringContainsString('IntersectionObserver', $scripts);
        $this->assertStringContainsString('prefers-reduced-motion', $scripts);
        $this->assertStringContainsString('data-ec15-reveal', file_get_contents(base_path('themes/EC915/views/home.blade.php')));
        $this->assertStringNotContainsString('fonts.googleapis.com', $layout);
        $this->assertFileExists(public_path('theme-demo/ec915/fonts/BeVietnamPro-Regular.ttf'));
    }

    public function test_ec915_demo_preserves_existing_branding_and_storefront_renders(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Nội thất của tôi',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'SHOP601',
            'branding' => [
                'logo_url' => '/storage/branding/custom-interior.svg',
                'support_hotline' => '0909 888 777',
                'support_email' => 'interior@example.test',
                'support_location' => '18 Phố Thiết Kế',
            ],
        ]);

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('EC915');
        $this->assertNotNull($provider);
        $result = $provider->generate('ec915-nd-interior');

        $this->assertSame(5, data_get($result, 'counts.categories'));
        $this->assertSame(8, data_get($result, 'counts.products'));
        $this->assertSame(2, data_get($result, 'counts.banners'));
        $this->assertSame(3, data_get($result, 'counts.posts'));
        $branding = (array) SiteProfile::query()->firstOrFail()->branding;
        $this->assertSame('/storage/branding/custom-interior.svg', data_get($branding, 'logo_url'));
        $this->assertSame('0909 888 777', data_get($branding, 'support_hotline'));

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('/storage/branding/custom-interior.svg', false)
            ->assertSee('Giải pháp nội thất hoàn hảo')
            ->assertSee('Sản phẩm bán chạy')
            ->assertSee('Cam kết chất lượng từ ND Interior')
            ->assertSee('Tin tức và xu hướng nội thất')
            ->assertSee('data-block-type="ec915_best_sellers"', false)
            ->assertSee('data-block-type="ec915_faq"', false)
            ->assertSee('data-xd-auth-open="login"', false);

        $page = LandingPage::query()->where('theme_key', 'EC915')->where('is_home', true)->firstOrFail();
        $this->assertCount(11, $page->blocks);
    }

    public function test_ec915_catalog_and_content_pages_render_successfully(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('EC915')?->generate('ec915-nd-interior');
        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();

        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk();
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk();
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'sofa']))->assertOk();
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk();
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk();
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk();
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Liên hệ');
    }

    public function test_ec915_templates_respect_uploaded_logo(): void
    {
        $header = file_get_contents(base_path('themes/EC915/views/partials/header.blade.php'));
        $footer = file_get_contents(base_path('themes/EC915/views/partials/footer.blade.php'));
        $this->assertStringContainsString("data_get(\$branding, 'logo_url'", $header);
        $this->assertStringContainsString("data_get(\$branding, 'logo_url'", $footer);
        $this->assertStringNotContainsString('/theme-demo/ec915/logo', $header.$footer);
    }
}
