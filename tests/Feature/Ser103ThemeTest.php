<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeDemoContentGenerator;
use App\Core\Themes\ThemeRegistry;
use App\Models\Admin;
use App\Models\CmsMedia;
use App\Models\CmsPost;
use App\Models\CmsService;
use App\Models\CmsServiceCategory;
use App\Models\CmsServiceImage;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\Site;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\SiteContext;
use Database\Seeders\Ser103ServicesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ser103ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_demo_news_covers_use_public_assets_across_storefront_pages(): void
    {
        config(['filesystems.disks.public.url' => '/files']);
        app(ThemeDemoContentGenerator::class)->generate('SER103', 'ser103-bohu-wedding');
        $posts = CmsPost::with('featuredMedia')->get();
        $this->assertCount(3, $posts);
        $home = $this->get('/vi')->assertOk();
        $listing = $this->get(route('site.blog.index', ['locale' => 'vi']))->assertOk();
        foreach ($posts as $post) {
            $media = $post->featuredMedia;
            $this->assertSame('', $media->getRawOriginal('file_path'));
            $this->assertStringStartsWith('/theme-demo/ser103/news-', $media->file_url);
            $this->assertNotFalse(getimagesize(public_path($media->file_url)));
            $home->assertSee('src="'.$media->file_url.'"', false);
            $listing->assertSee('src="'.$media->file_url.'"', false);
            $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))
                ->assertOk()->assertSee('src="'.$media->file_url.'"', false);
        }
    }

    public function test_news_media_repair_preserves_articles_and_manual_uploads_and_is_repeatable(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('SER103', 'ser103-bohu-wedding');
        $posts = CmsPost::with('featuredMedia')->get();
        $identities = $posts->map(fn ($post) => [$post->id, $post->slug, $post->body, $post->featured_media_id])->all();
        foreach ($posts as $post) {
            $post->featuredMedia->update(['file_path' => ltrim($post->featuredMedia->file_url, '/'), 'file_url' => '/storage'.$post->featuredMedia->file_url]);
        }
        $manual = CmsMedia::create(['title' => 'Manual upload', 'file_path' => 'uploads/my-cover.webp', 'file_url' => '/files/uploads/my-cover.webp']);
        ThemeDemoRecord::create(['theme_key' => 'SER103', 'preset_key' => 'ser103-bohu-wedding', 'model_type' => CmsMedia::class, 'model_id' => $manual->id]);
        $unmarked = CmsMedia::create(['title' => 'Unmarked media', 'file_path' => 'theme-demo/ser103/news-garden.webp', 'file_url' => '/files/theme-demo/ser103/news-garden.webp']);
        $otherWebsite = CmsMedia::create(['website_key' => 'other-website', 'title' => 'Other SER103 demo', 'file_path' => 'theme-demo/ser103/news-garden.webp', 'file_url' => '/files/theme-demo/ser103/news-garden.webp']);
        ThemeDemoRecord::create(['website_key' => 'other-website', 'theme_key' => 'SER103', 'preset_key' => 'ser103-bohu-wedding', 'model_type' => CmsMedia::class, 'model_id' => $otherWebsite->id]);
        $otherTheme = CmsMedia::create(['website_key' => 'other-website', 'title' => 'Other theme demo', 'file_path' => 'theme-demo/ser103/news-garden.webp', 'file_url' => '/files/theme-demo/ser103/news-garden.webp']);
        ThemeDemoRecord::create(['website_key' => 'other-website', 'theme_key' => 'DN350', 'preset_key' => 'dn350-cleaning', 'model_type' => CmsMedia::class, 'model_id' => $otherTheme->id]);
        $migration = require database_path('migrations/2026_10_10_000003_repair_ser103_demo_news_media_urls.php');
        $migration->up();
        $migration->up();
        $this->assertSame($identities, CmsPost::all()->map(fn ($post) => [$post->id, $post->slug, $post->body, $post->featured_media_id])->all());
        foreach ($posts as $post) {
            $this->assertSame('', $post->featuredMedia->fresh()->getRawOriginal('file_path'));
            $this->assertStringStartsWith('/theme-demo/ser103/', $post->featuredMedia->fresh()->file_url);
        }
        $this->assertSame('uploads/my-cover.webp', $manual->fresh()->getRawOriginal('file_path'));
        $this->assertSame('theme-demo/ser103/news-garden.webp', $unmarked->fresh()->getRawOriginal('file_path'));
        $this->assertSame('', $otherWebsite->fresh()->getRawOriginal('file_path'));
        $this->assertSame('/theme-demo/ser103/news-garden.webp', $otherWebsite->fresh()->file_url);
        $this->assertSame('theme-demo/ser103/news-garden.webp', $otherTheme->fresh()->getRawOriginal('file_path'));
    }

    public function test_services_seeder_populates_empty_listing_and_all_detail_pages(): void
    {
        config(['localized-content.rollout.legacy_fallback' => false]);
        SiteProfile::create(['site_name' => 'Bøhu', 'website_type' => 'service', 'active_theme_key' => 'SER103']);
        $existingCategories = CmsServiceCategory::count();
        $seeder = app(Ser103ServicesSeeder::class);
        $this->assertSame(['service_categories' => 1, 'services' => 6, 'service_images' => 6], $seeder->seedForWebsite('website-main'));
        $this->assertSame(['service_categories' => 0, 'services' => 0, 'service_images' => 0], $seeder->seedForWebsite('website-main'));
        $this->assertSame($existingCategories + 1, CmsServiceCategory::count());
        $this->assertSame(6, CmsService::count());
        $this->assertSame(6, CmsServiceImage::count());
        $listing = $this->get(route('site.services.index', ['locale' => 'vi']))->assertOk();
        $listing->assertDontSee('Chưa có dịch vụ để hiển thị.');
        foreach (CmsService::with('featuredImage')->get() as $service) {
            $image = $service->featuredImage->image_url;
            $this->assertNotFalse(getimagesize(public_path($image)));
            $this->assertDatabaseHas('content_translations', ['website_key' => 'website-main', 'resource_type' => 'cms_service', 'resource_id' => $service->id, 'locale' => 'vi', 'translation_status' => 'published']);
            $listing->assertSee($service->title)->assertSee('src="'.$image.'"', false);
            $this->get(route('site.services.show', ['locale' => 'vi', 'slug' => $service->slug]))
                ->assertOk()->assertSee($service->title)->assertSee('src="'.$image.'"', false)
                ->assertSee('Đội ngũ Bøhu đồng hành');
        }
    }

    public function test_services_seeder_preserves_edits_and_seeds_only_active_ser103_websites(): void
    {
        SiteProfile::create(['site_name' => 'Bøhu', 'website_type' => 'service', 'active_theme_key' => 'SER103', 'branding' => ['support_hotline' => '0909103103']]);
        $edited = CmsService::create(['title' => 'Dịch vụ đã biên tập', 'slug' => 'ser103-trang-diem-co-dau', 'summary' => 'Nội dung riêng', 'status' => 'draft']);
        Site::create(['domain' => 'ser103.example.test', 'website_key' => 'other-ser103', 'theme_key' => 'SER103', 'status' => 'active']);
        Site::create(['domain' => 'other.example.test', 'website_key' => 'other-theme', 'theme_key' => 'DN350', 'status' => 'active']);
        SiteProfile::create(['website_key' => 'other-theme', 'site_name' => 'Stale profile', 'active_theme_key' => 'SER103']);
        Site::create(['domain' => 'paused.example.test', 'website_key' => 'inactive-ser103', 'theme_key' => 'SER103', 'status' => 'inactive']);
        $other = CmsService::create(['website_key' => 'other-theme', 'title' => 'Website khác', 'slug' => 'ser103-chup-anh-cuoi', 'status' => 'published']);
        $context = app(SiteContext::class);
        $context->set(null, 'other-theme');
        app(Ser103ServicesSeeder::class)->run();
        app(Ser103ServicesSeeder::class)->run();
        $this->assertSame('other-theme', $context->websiteKey());
        $this->assertSame(6, CmsService::query()->forWebsite('website-main')->count());
        $this->assertSame(6, CmsService::query()->forWebsite('other-ser103')->count());
        $this->assertSame(1, CmsService::query()->forWebsite('other-theme')->count());
        $this->assertSame(0, CmsService::query()->forWebsite('inactive-ser103')->count());
        $context->set(null, 'website-main');
        $edited = $edited->fresh();
        $this->assertSame('Dịch vụ đã biên tập', $edited->title);
        $this->assertSame('Nội dung riêng', $edited->summary);
        $this->assertSame('draft', $edited->status);
        $this->assertSame(0, $edited->images()->count());
        $this->assertSame('Website khác', $other->newQuery()->withoutGlobalScopes()->findOrFail($other->id)->title);
        $this->assertSame('0909103103', data_get(SiteProfile::first()->branding, 'support_hotline'));
    }

    public function test_services_seeder_rejects_unrelated_website(): void
    {
        SiteProfile::create(['site_name' => 'DN350', 'active_theme_key' => 'DN350']);
        $this->expectException(\RuntimeException::class);
        app(Ser103ServicesSeeder::class)->seedForWebsite('website-main');
    }

    public function test_ser103_is_registered_with_six_ordered_home_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'SER103');

        $this->assertNotNull($theme);
        $this->assertSame('service', $theme['website_type']);
        $this->assertSame('ser103-bohu-wedding', data_get($theme, 'demo.default_preset'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('SER103'));
        $this->assertSame([
            'hero_slider',
            'ser103_about',
            'business_service_grid',
            'latest_posts',
            'landing_contact',
            'collection_gallery',
        ], collect($builder->availableBlocks('SER103'))->pluck('block_type')->all());

        foreach (['business_service_grid', 'latest_posts'] as $type) {
            $block = collect($builder->availableBlocks('SER103'))->firstWhere('block_type', $type);
            $this->assertArrayHasKey('source', data_get($block, 'settings_schema'));
            $this->assertArrayHasKey('limit', data_get($block, 'settings_schema'));
        }
    }

    public function test_ser103_demo_preserves_existing_branding_and_renders_home(): void
    {
        SiteProfile::query()->create([
            'website_key' => 'website-main',
            'site_name' => 'Studio của tôi',
            'website_type' => 'service',
            'active_theme_key' => 'SHOP601',
            'branding' => [
                'logo_url' => '/storage/branding/custom-bohu.svg',
                'support_hotline' => '0909 103 103',
                'support_email' => 'wedding@example.test',
            ],
        ]);

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('SER103');
        $this->assertNotNull($provider);
        $result = $provider->generate('ser103-bohu-wedding');

        $this->assertSame(6, data_get($result, 'counts.services'));
        $this->assertSame(6, data_get($result, 'counts.service_images'));
        $this->assertSame(3, data_get($result, 'counts.posts'));
        $this->assertSame(2, data_get($result, 'counts.banners'));

        $branding = (array) SiteProfile::query()->firstOrFail()->branding;
        $this->assertSame('/storage/branding/custom-bohu.svg', data_get($branding, 'logo_url'));
        $this->assertSame('0909 103 103', data_get($branding, 'support_hotline'));
        $this->assertSame('wedding@example.test', data_get($branding, 'support_email'));

        $response = $this->get(route('site.home', ['locale' => 'vi']))->assertOk();
        $html = $response->getContent();
        $response
            ->assertSee('/storage/branding/custom-bohu.svg', false)
            ->assertSee('Lập kế hoạch cho đám cưới của bạn')
            ->assertSee('Chúng tôi có thể làm gì')
            ->assertSee('Tin tức - Sự kiện mới nhất')
            ->assertSee('Liên hệ chúng tôi cho sự kiện của bạn')
            ->assertSee('data-ser103-booking', false)
            ->assertSee('data-ser103-reveal', false)
            ->assertDontSee('SER102');

        $positions = collect([
            'hero_slider',
            'ser103_about',
            'business_service_grid',
            'latest_posts',
            'landing_contact',
            'collection_gallery',
        ])->map(fn (string $type) => strpos($html, 'data-block-type="'.$type.'"'))->all();
        $this->assertNotContains(false, $positions);
        $this->assertSame($positions, collect($positions)->sort()->values()->all());

        $page = LandingPage::query()->where('theme_key', 'SER103')->where('is_home', true)->firstOrFail();
        $this->assertCount(6, $page->blocks);
    }

    public function test_ser103_service_blog_and_contact_pages_render(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('SER103')?->generate('ser103-bohu-wedding');
        $service = CmsService::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();

        $urls = [
            route('site.home', ['locale' => 'vi']),
            route('site.services.index', ['locale' => 'vi']),
            route('site.services.show', ['locale' => 'vi', 'slug' => $service->slug]),
            route('site.blog.index', ['locale' => 'vi']),
            route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]),
            route('site.contact', ['locale' => 'vi']),
        ];

        foreach ($urls as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('ser103-header', false)
                ->assertSee('ser103-footer', false);
        }
    }

    public function test_ser103_admin_mode_exposes_editable_blocks_and_motion_contract(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('SER103')?->generate('ser103-bohu-wedding');
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $response = $this->get(route('site.home', ['locale' => 'vi', 'mod' => 'admin']))
            ->assertOk()
            ->assertSee('data-xd-editor', false);

        $landing = LandingPage::query()->where('theme_key', 'SER103')->where('is_home', true)->firstOrFail();
        foreach ($landing->blocks as $block) {
            $response->assertSee('data-xd-edit-block="'.$block->id.'"', false);
        }

        $styles = file_get_contents(base_path('themes/SER103/views/partials/styles.blade.php'));
        $scripts = file_get_contents(base_path('themes/SER103/views/partials/scripts.blade.php'));
        $header = file_get_contents(base_path('themes/SER103/views/partials/header.blade.php'));
        $this->assertStringContainsString('prefers-reduced-motion', $styles);
        $this->assertStringContainsString('IntersectionObserver', $scripts);
        $this->assertStringContainsString('data-ser103-reveal', file_get_contents(base_path('themes/SER103/views/home.blade.php')));
        $this->assertStringContainsString("data_get(\$branding, 'logo_url'", $header);
        $this->assertFileExists(public_path('theme-demo/ser103/hero-wedding.webp'));
        $this->assertFileExists(public_path('theme-previews/SER103/cover-ser103.png'));
    }
}
