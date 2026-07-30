<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\Admin;
use App\Models\CmsPost;
use App\Models\CmsService;
use App\Models\LandingPage;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ser103ThemeTest extends TestCase
{
    use RefreshDatabase;

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

        $this->assertSame(5, data_get($result, 'counts.services'));
        $this->assertSame(5, data_get($result, 'counts.service_images'));
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
