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

class Ec914ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ec914_is_registered_with_twelve_ordered_blocks_and_private_typography(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'EC914');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('EC914'));
        $this->assertSame([
            'hero_slider', 'ec914_category_rail', 'ec914_craft_sale', 'ec914_featured_products',
            'ec914_collection_gallery', 'ec914_basket_showcase', 'ec914_lamp_showcase',
            'ec914_artisan_story', 'ec914_testimonials', 'ec914_partners',
            'ec914_latest_posts', 'ec914_footer',
        ], collect($builder->availableBlocks('EC914'))->pluck('block_type')->all());

        $styles = file_get_contents(base_path('themes/EC914/views/partials/styles.blade.php'));
        $layout = file_get_contents(base_path('themes/EC914/views/layout.blade.php'));
        $this->assertStringContainsString('font-family:"EC914 Sans"', $styles);
        $this->assertStringContainsString('font-family:"EC914 Hand"', $styles);
        $this->assertStringNotContainsString('fonts.googleapis.com', $layout);
        $this->assertFileExists(base_path('themes/EC914/assets/fonts/PlaywriteVN-Variable.woff2'));
        $this->assertFileExists(base_path('themes/EC914/assets/fonts/BeVietnamPro-Regular.ttf'));
    }

    public function test_ec914_demo_preserves_branding_and_storefront_renders(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Góc thủ công của tôi', 'website_type' => 'ecommerce', 'active_theme_key' => 'SHOP601',
            'branding' => ['logo_url' => '/storage/branding/custom-craft.svg', 'support_hotline' => '0908 456 789', 'support_email' => 'craft@example.test', 'support_location' => '12 Làng nghề'],
        ]);

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('EC914');
        $this->assertNotNull($provider);
        $result = $provider->generate('ec914-moc-nhien-craft');

        $this->assertSame(8, data_get($result, 'counts.categories'));
        $this->assertSame(14, data_get($result, 'counts.products'));
        $this->assertSame(2, data_get($result, 'counts.banners'));
        $this->assertSame(3, data_get($result, 'counts.posts'));
        $branding = (array) SiteProfile::query()->firstOrFail()->branding;
        $this->assertSame('/storage/branding/custom-craft.svg', data_get($branding, 'logo_url'));
        $this->assertSame('0908 456 789', data_get($branding, 'support_hotline'));

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('/storage/branding/custom-craft.svg', false)
            ->assertSee('Year End Craft Sale')
            ->assertSee('Các sản phẩm nổi bật')
            ->assertSee('Bộ sưu tập mới nhất')
            ->assertSee('Câu chuyện từ những đôi tay')
            ->assertSee('data-block-type="ec914_craft_sale"', false)
            ->assertSee('data-block-type="ec914_artisan_story"', false)
            ->assertSee('data-xd-auth-open="login"', false);

        $page = LandingPage::query()->where('theme_key', 'EC914')->where('is_home', true)->firstOrFail();
        $this->assertCount(12, $page->blocks);
    }

    public function test_ec914_catalog_and_content_pages_render_successfully(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('EC914')?->generate('ec914-moc-nhien-craft');
        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();

        $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]))->assertOk();
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk();
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'mây']))->assertOk();
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertOk();
        $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk();
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk();
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Liên hệ');
    }

    public function test_ec914_templates_respect_uploaded_logo(): void
    {
        $header = file_get_contents(base_path('themes/EC914/views/partials/header.blade.php'));
        $footer = file_get_contents(base_path('themes/EC914/views/partials/footer.blade.php'));
        $this->assertStringContainsString("data_get(\$branding, 'logo_url'", $header);
        $this->assertStringContainsString("data_get(\$branding, 'logo_url'", $footer);
        $this->assertStringNotContainsString('/theme-demo/ec914/logo', $header.$footer);
    }
}
