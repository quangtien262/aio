<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\Admin;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsMedia;
use App\Models\CmsPost;
use App\Models\LandingPage;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
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
        if ($path = getenv('DN351_HEADER_PREVIEW')) {
            file_put_contents($path, $html);
        }
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
        $this->assertFileExists(public_path('theme-previews/DN351/dn351-optimized.webp'));
        foreach (['blog-fish-soup.jpg', 'blog-squid-grilled.jpg', 'blog-stuffed-calamari.jpg'] as $image) {
            $this->assertFileExists(public_path('theme-demo/dn351/'.$image));
            $this->assertNotFalse(getimagesize(public_path('theme-demo/dn351/'.$image)));
        }
    }

    public function test_every_demo_news_cover_uses_a_bundled_public_image_across_storefront_pages(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('DN351')->generate('dn351-meatlers-market');
        config(['filesystems.disks.public.url' => '/files']);
        $home = $this->get(route('site.home', ['locale' => 'vi']))->assertOk();
        $listing = $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk();
        $posts = CmsPost::with('featuredMedia')->get();
        $this->assertCount(3, $posts);
        foreach ($posts as $post) {
            $media = $post->featuredMedia;
            $this->assertSame('', $media->getRawOriginal('file_path'));
            $this->assertStringStartsWith('/theme-demo/dn351/blog-', $media->file_url);
            $this->assertNotFalse(getimagesize(public_path($media->file_url)));
            $home->assertSee('src="'.$media->file_url.'"', false);
            $listing->assertSee('src="'.$media->file_url.'"', false);
            $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))
                ->assertOk()->assertSee('src="'.$media->file_url.'"', false);
        }
    }

    public function test_media_repair_preserves_post_identity_and_manual_uploads_and_is_repeatable(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('DN351')->generate('dn351-meatlers-market');
        $posts = CmsPost::with('featuredMedia')->get();
        $identities = $posts->map(fn ($post) => [$post->id, $post->slug, $post->body, $post->featured_media_id])->all();
        foreach ($posts as $post) {
            $post->featuredMedia->update(['file_path' => ltrim($post->featuredMedia->file_url, '/'), 'file_url' => '/storage'.$post->featuredMedia->file_url]);
        }
        $manual = CmsMedia::create(['title' => 'Manual upload', 'file_path' => 'uploads/my-cover.jpg', 'file_url' => '/storage/uploads/my-cover.jpg']);
        ThemeDemoRecord::create(['theme_key' => 'DN351', 'preset_key' => 'dn351-meatlers-market', 'model_type' => CmsMedia::class, 'model_id' => $manual->id]);
        $unmarked = CmsMedia::create(['title' => 'Unmarked media', 'file_path' => 'theme-demo/dn351/blog-fish-soup.jpg', 'file_url' => '/storage/theme-demo/dn351/blog-fish-soup.jpg']);
        $otherWebsite = CmsMedia::create(['website_key' => 'other-website', 'title' => 'Other DN351 demo', 'file_path' => 'theme-demo/dn351/blog-fish-soup.jpg', 'file_url' => '/files/theme-demo/dn351/blog-fish-soup.jpg']);
        ThemeDemoRecord::create(['website_key' => 'other-website', 'theme_key' => 'DN351', 'preset_key' => 'dn351-meatlers-market', 'model_type' => CmsMedia::class, 'model_id' => $otherWebsite->id]);
        $otherTheme = CmsMedia::create(['website_key' => 'other-website', 'title' => 'Other theme demo', 'file_path' => 'theme-demo/dn351/blog-fish-soup.jpg', 'file_url' => '/files/theme-demo/dn351/blog-fish-soup.jpg']);
        ThemeDemoRecord::create(['website_key' => 'other-website', 'theme_key' => 'DN350', 'preset_key' => 'dn350-demo', 'model_type' => CmsMedia::class, 'model_id' => $otherTheme->id]);
        $migration = require database_path('migrations/2026_10_10_000001_repair_dn351_demo_news_media_urls.php');
        $migration->up();
        $migration->up();
        $this->assertSame($identities, CmsPost::all()->map(fn ($post) => [$post->id, $post->slug, $post->body, $post->featured_media_id])->all());
        foreach ($posts as $post) {
            $this->assertSame('', $post->featuredMedia->fresh()->getRawOriginal('file_path'));
            $this->assertStringStartsWith('/theme-demo/dn351/', $post->featuredMedia->fresh()->file_url);
        }
        $this->assertSame('uploads/my-cover.jpg', $manual->fresh()->getRawOriginal('file_path'));
        $this->assertSame('theme-demo/dn351/blog-fish-soup.jpg', $unmarked->fresh()->getRawOriginal('file_path'));
        $this->assertSame('', $otherWebsite->fresh()->getRawOriginal('file_path'));
        $this->assertSame('/theme-demo/dn351/blog-fish-soup.jpg', $otherWebsite->fresh()->file_url);
        $this->assertSame('theme-demo/dn351/blog-fish-soup.jpg', $otherTheme->fresh()->getRawOriginal('file_path'));
    }
}
