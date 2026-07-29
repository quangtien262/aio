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

class Book920ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_book920_is_registered_with_nine_ordered_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'BOOK920');

        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertSame('book920-bookle', data_get($theme, 'demo.default_preset'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('BOOK920'));
        $this->assertSame([
            'hero_slider',
            'book920_benefits',
            'book920_featured',
            'book920_sale',
            'book920_promo',
            'book920_hot',
            'book920_testimonials',
            'latest_posts',
            'book920_footer',
        ], collect($builder->availableBlocks('BOOK920'))->pluck('block_type')->all());

        foreach (['book920_featured', 'book920_sale', 'book920_hot', 'latest_posts'] as $type) {
            $block = collect($builder->availableBlocks('BOOK920'))->firstWhere('block_type', $type);
            $this->assertArrayHasKey('source', data_get($block, 'settings_schema'));
            $this->assertArrayHasKey('limit', data_get($block, 'settings_schema'));
        }
    }

    public function test_book920_demo_preserves_branding_and_home_renders(): void
    {
        SiteProfile::query()->create([
            'website_key' => 'website-main',
            'site_name' => 'Nhà sách của tôi',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'TH0001',
            'branding' => [
                'logo_url' => '/storage/branding/custom-bookle.svg',
                'support_hotline' => '0909 920 920',
                'support_email' => 'bookle@example.test',
            ],
        ]);

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('BOOK920');
        $this->assertNotNull($provider);
        $result = $provider->generate('book920-bookle');

        $this->assertSame(8, data_get($result, 'counts.categories'));
        $this->assertSame(12, data_get($result, 'counts.products'));
        $this->assertSame(2, data_get($result, 'counts.banners'));
        $this->assertSame(4, data_get($result, 'counts.posts'));

        $branding = (array) SiteProfile::query()->firstOrFail()->branding;
        $this->assertSame('/storage/branding/custom-bookle.svg', data_get($branding, 'logo_url'));
        $this->assertSame('0909 920 920', data_get($branding, 'support_hotline'));

        $response = $this->get(route('site.home', ['locale' => 'vi']))->assertOk();
        $html = $response->getContent();
        $response
            ->assertSee('/storage/branding/custom-bookle.svg', false)
            ->assertSee('Sách nổi bật')
            ->assertSee('Trăng Và Những Mùa Hoa')
            ->assertSee('Sách khuyến mãi')
            ->assertSee('Khách hàng của chúng tôi nói gì')
            ->assertSee('Tin tức mới nhất')
            ->assertSee('Tận dụng quỹ thời gian với hai giờ đầu tiên')
            ->assertSee('Chuyện kể từ Sài Gòn')
            ->assertSee('data-book20-reveal', false)
            ->assertSee('data-xd-auth-open="login"', false)
            ->assertDontSee('BÃ')
            ->assertDontSee('á»')
            ->assertDontSee('EC916');

        $positions = collect([
            'hero_slider',
            'book920_benefits',
            'book920_featured',
            'book920_sale',
            'book920_promo',
            'book920_hot',
            'book920_testimonials',
            'latest_posts',
            'book920_footer',
        ])->map(fn (string $type) => strpos($html, 'data-block-type="'.$type.'"'))->all();
        $this->assertNotContains(false, $positions);
        $this->assertSame($positions, collect($positions)->sort()->values()->all());

        $page = LandingPage::query()->where('theme_key', 'BOOK920')->where('is_home', true)->firstOrFail();
        $this->assertCount(9, $page->blocks);
    }

    public function test_book920_catalog_blog_cart_and_contact_pages_render(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('BOOK920')?->generate('book920-bookle');
        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();

        $urls = [
            route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]),
            route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]),
            route('site.catalog.search', ['locale' => 'vi', 'q' => 'sách']),
            route('site.cart.index', ['locale' => 'vi']),
            route('site.blog.index', ['locale' => 'vi']),
            route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]),
            route('site.contact', ['locale' => 'vi']),
        ];

        foreach ($urls as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('book20-header', false)
                ->assertSee('book20-footer', false);
        }
    }

    public function test_book920_assets_logo_and_motion_contract_are_present(): void
    {
        $header = file_get_contents(base_path('themes/BOOK920/views/partials/header.blade.php'));
        $footer = file_get_contents(base_path('themes/BOOK920/views/partials/footer.blade.php'));
        $styles = file_get_contents(base_path('themes/BOOK920/views/partials/styles.blade.php'));
        $scripts = file_get_contents(base_path('themes/BOOK920/views/partials/scripts.blade.php'));

        $this->assertStringContainsString("data_get(\$branding, 'logo_url'", $header);
        $this->assertStringContainsString("data_get(\$branding, 'logo_url'", $footer);
        $this->assertStringContainsString('prefers-reduced-motion', $styles);
        $this->assertStringContainsString('IntersectionObserver', $scripts);
        $this->assertFileExists(public_path('theme-demo/book920/hero-bookstore.png'));
        $this->assertFileExists(public_path('theme-previews/BOOK920/preview-book920.png'));
        $this->assertFileExists(public_path('theme-previews/BOOK920/cover-book920.webp'));
    }
}
