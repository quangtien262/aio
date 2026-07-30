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

class Ec917ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ec917_is_registered_with_eight_ordered_blocks_and_motion_support(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'EC917');

        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('EC917'));
        $this->assertSame([
            'hero_slider',
            'ec917_categories',
            'ec917_summer_sale',
            'ec917_promo_banner',
            'ec917_collections',
            'ec917_inspiration',
            'ec917_benefits',
            'ec917_footer',
        ], collect($builder->availableBlocks('EC917'))->pluck('block_type')->all());

        $styles = file_get_contents(base_path('themes/EC917/views/partials/styles.blade.php'));
        $scripts = file_get_contents(base_path('themes/EC917/views/partials/scripts.blade.php'));
        $home = file_get_contents(base_path('themes/EC917/views/home.blade.php'));

        $this->assertStringContainsString('ec17-motion-ready', $styles);
        $this->assertStringContainsString('IntersectionObserver', $scripts);
        $this->assertStringContainsString('prefers-reduced-motion', $scripts);
        $this->assertStringContainsString('data-ec17-reveal', $home);
        $this->assertFileExists(public_path('theme-demo/ec917/hero-interior.webp'));
        $this->assertFileExists(public_path('theme-previews/EC917/cover-ec917.png'));
    }

    public function test_ec917_demo_preserves_uploaded_branding_and_storefront_renders(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Cửa hàng nội thất của tôi',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'SHOP601',
            'branding' => [
                'logo_url' => '/storage/branding/custom-furniture.svg',
                'support_hotline' => '0901 234 567',
                'support_email' => 'furniture@example.test',
                'support_location' => '25 Phố Nội Thất',
            ],
        ]);

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('EC917');
        $this->assertNotNull($provider);
        $result = $provider->generate('ec917-ega-furniture');

        $this->assertSame(6, data_get($result, 'counts.categories'));
        $this->assertSame(8, data_get($result, 'counts.products'));
        $this->assertSame(2, data_get($result, 'counts.banners'));
        $this->assertSame(4, data_get($result, 'counts.posts'));

        $branding = (array) SiteProfile::query()->firstOrFail()->branding;
        $this->assertSame('/storage/branding/custom-furniture.svg', data_get($branding, 'logo_url'));
        $this->assertSame('0901 234 567', data_get($branding, 'support_hotline'));
        $this->assertSame('furniture@example.test', data_get($branding, 'support_email'));

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('/storage/branding/custom-furniture.svg', false)
            ->assertSee('DANH MỤC SẢN PHẨM')
            ->assertSee('HAPPY SUMMER - GIẢM ĐẾN 50%')
            ->assertSee('BST NỘI THẤT DÀNH CHO BẠN')
            ->assertSee('GÓC CẢM HỨNG')
            ->assertSee('0901 234 567')
            ->assertSee('furniture@example.test')
            ->assertSee('data-block-type="ec917_summer_sale"', false)
            ->assertSee('data-block-type="ec917_collections"', false)
            ->assertSee('data-xd-auth-open="login"', false);

        $page = LandingPage::query()
            ->where('theme_key', 'EC917')
            ->where('is_home', true)
            ->firstOrFail();
        $this->assertCount(8, $page->blocks);
    }

    public function test_ec917_catalog_and_content_pages_render_successfully(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('EC917')?->generate('ec917-ega-furniture');
        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();

        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk();
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk();
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'sofa']))->assertOk();
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk();
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk();
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk();
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk();
    }

    public function test_ec917_templates_use_database_branding_instead_of_a_hardcoded_logo(): void
    {
        $header = file_get_contents(base_path('themes/EC917/views/partials/header.blade.php'));
        $footer = file_get_contents(base_path('themes/EC917/views/partials/footer.blade.php'));

        $this->assertStringContainsString("data_get(\$branding, 'logo_url'", $header);
        $this->assertStringContainsString("data_get(\$branding, 'support_hotline'", $footer);
        $this->assertStringContainsString("data_get(\$branding, 'support_email'", $footer);
        $this->assertStringNotContainsString('/theme-demo/ec917/logo', $header);
    }
}
