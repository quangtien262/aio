<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\Admin;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\LandingPage;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dn351ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dn351_is_registered_with_eleven_ordered_home_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'DN351');

        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertSame('dn351-meatlers-market', data_get($theme, 'demo.default_preset'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('DN351'));
        $this->assertSame([
            'hero_slider',
            'about_experience',
            'dn351_promo_mosaic',
            'dn351_category_rail',
            'dn351_featured_split',
            'dn351_product_grid',
            'testimonials',
            'latest_posts',
            'partner_logos',
            'newsletter_signup',
            'footer_contact',
        ], collect($builder->availableBlocks('DN351'))->pluck('block_type')->all());

        foreach (['dn351_category_rail', 'dn351_featured_split', 'dn351_product_grid', 'latest_posts'] as $type) {
            $block = collect($builder->availableBlocks('DN351'))->firstWhere('block_type', $type);
            $this->assertArrayHasKey('source', data_get($block, 'settings_schema'));
            $this->assertArrayHasKey('limit', data_get($block, 'settings_schema'));
        }
    }

    public function test_dn351_demo_preserves_branding_and_renders_complete_home(): void
    {
        SiteProfile::query()->create([
            'website_key' => 'website-main',
            'site_name' => 'Cửa hàng của tôi',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'SHOP601',
            'branding' => [
                'logo_url' => '/storage/branding/custom-meatlers.svg',
                'support_hotline' => '0909 351 351',
                'support_email' => 'fresh@example.test',
            ],
        ]);

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('DN351');
        $this->assertNotNull($provider);
        $result = $provider->generate('dn351-meatlers-market');

        $this->assertSame(4, data_get($result, 'counts.categories'));
        $this->assertSame(8, data_get($result, 'counts.products'));
        $this->assertSame(2, data_get($result, 'counts.banners'));
        $this->assertSame(3, data_get($result, 'counts.posts'));

        $branding = (array) SiteProfile::query()->firstOrFail()->branding;
        $this->assertSame('/storage/branding/custom-meatlers.svg', data_get($branding, 'logo_url'));
        $this->assertSame('0909 351 351', data_get($branding, 'support_hotline'));
        $this->assertSame('fresh@example.test', data_get($branding, 'support_email'));

        $response = $this->get(route('site.home', ['locale' => 'vi']))->assertOk();
        $html = $response->getContent();
        $response
            ->assertSee('/storage/branding/custom-meatlers.svg', false)
            ->assertSee('Nhà cung cấp trái cây tươi tốt nhất thị trường')
            ->assertSee('Mua sắm theo danh mục')
            ->assertSee('Sản phẩm của chúng tôi')
            ->assertSee('data-xd-auth-open="login"', false)
            ->assertSee('data-dn351-auth-modal', false)
            ->assertSee('name="two_factor_code"', false)
            ->assertSee('data-dn351-reveal', false)
            ->assertDontSee('TÃ')
            ->assertDontSee('Ä‘')
            ->assertDontSee('áº')
            ->assertDontSee('á»');

        $types = ['hero_slider', 'about_experience', 'dn351_promo_mosaic', 'dn351_category_rail', 'dn351_featured_split', 'dn351_product_grid', 'testimonials', 'latest_posts', 'partner_logos', 'newsletter_signup'];
        $positions = collect($types)->map(fn (string $type) => strpos($html, 'data-block-type="'.$type.'"'))->all();
        $this->assertNotContains(false, $positions);
        $this->assertSame($positions, collect($positions)->sort()->values()->all());

        $page = LandingPage::query()->where('theme_key', 'DN351')->where('is_home', true)->firstOrFail();
        $this->assertCount(11, $page->blocks);
    }

    public function test_dn351_catalog_content_and_account_shells_render(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('DN351')?->generate('dn351-meatlers-market');
        $category = CatalogCategory::query()->firstOrFail();
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();

        $urls = [
            route('site.home', ['locale' => 'vi']),
            route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug]),
            route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]),
            route('site.catalog.search', ['locale' => 'vi', 'q' => 'DN351']),
            route('site.cart.index', ['locale' => 'vi']),
            route('site.blog.index', ['locale' => 'vi']),
            route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]),
            route('site.contact', ['locale' => 'vi']),
        ];

        foreach ($urls as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('dn351-header', false)
                ->assertSee('dn351-footer', false)
                ->assertSee('data-dn351-auth-modal', false);
        }

        $this->get(route('site.checkout.index', ['locale' => 'vi']))->assertRedirect();
    }

    public function test_dn351_admin_mode_exposes_every_editable_home_block(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('DN351')?->generate('dn351-meatlers-market');
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $response = $this->get(route('site.home', ['locale' => 'vi', 'mod' => 'admin']))
            ->assertOk()
            ->assertSee('data-xd-editor', false)
            ->assertSee('Sửa khối');

        $landing = LandingPage::query()->where('theme_key', 'DN351')->where('is_home', true)->firstOrFail();
        foreach ($landing->blocks->where('block_type', '!=', 'footer_contact') as $block) {
            $response->assertSee('data-xd-edit-block="'.$block->id.'"', false);
        }
    }

    public function test_dn351_assets_motion_and_branding_contract_exist(): void
    {
        $styles = file_get_contents(base_path('themes/DN351/views/partials/styles.blade.php'));
        $scripts = file_get_contents(base_path('themes/DN351/views/partials/shell-scripts.blade.php'));
        $header = file_get_contents(base_path('themes/DN351/views/partials/header.blade.php'));

        $this->assertStringContainsString('prefers-reduced-motion', $styles);
        $this->assertStringContainsString('IntersectionObserver', $scripts);
        $this->assertStringContainsString('data-dn351-reveal', file_get_contents(base_path('themes/DN351/views/home.blade.php')));
        $this->assertStringContainsString("\$branding['logo_url']", $header);
        $this->assertFileExists(public_path('theme-demo/dn351/hero-market.jpg'));
        $this->assertFileExists(public_path('theme-previews/DN351/preview-dn351.png'));
        $this->assertFileExists(storage_path('app/public/theme-demo/dn351/blog-fish-soup.jpg'));
    }
}
