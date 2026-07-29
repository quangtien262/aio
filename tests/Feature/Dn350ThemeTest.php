<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Core\Themes\ThemeRegistry;
use App\Models\Admin;
use App\Models\CmsPost;
use App\Models\CmsService;
use App\Models\LandingPage;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dn350ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dn350_is_registered_with_nine_home_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'DN350');

        $this->assertNotNull($theme);
        $this->assertSame('service', $theme['website_type']);
        $this->assertSame('dn350-cleaning', data_get($theme, 'demo.default_preset'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('DN350'));
        $this->assertSame([
            'hero_slider',
            'about_experience',
            'featured_services',
            'featured_categories',
            'testimonials',
            'project_gallery',
            'latest_posts',
            'newsletter_signup',
            'footer_contact',
        ], collect($builder->availableBlocks('DN350'))->pluck('block_type')->all());

        foreach (['featured_services', 'project_gallery', 'latest_posts'] as $type) {
            $block = collect($builder->availableBlocks('DN350'))->firstWhere('block_type', $type);
            $this->assertArrayHasKey('source', data_get($block, 'settings_schema'));
            $this->assertArrayHasKey('limit', data_get($block, 'settings_schema'));
        }
    }

    public function test_dn350_demo_renders_complete_home_and_preserves_custom_logo(): void
    {
        SiteProfile::query()->create([
            'website_key' => 'website-main',
            'site_name' => 'Logo test',
            'website_type' => 'service',
            'branding' => ['logo_url' => 'https://cdn.example.com/dn350-custom-logo.svg'],
        ]);

        app(ThemeDemoContentGenerator::class)->generate('DN350', 'dn350-cleaning');

        $this->assertSame(6, CmsService::query()->count());
        $this->assertSame(3, CmsPost::query()->count());
        $this->assertDatabaseHas('landing_pages', ['theme_key' => 'DN350', 'slug' => 'home', 'is_home' => true]);

        $response = $this->get(route('site.home', ['locale' => 'vi']))->assertOk();
        $html = $response->getContent();

        $response
            ->assertSee('https://cdn.example.com/dn350-custom-logo.svg', false)
            ->assertSee('Chúng tôi là lựa chọn tốt nhất cho bạn')
            ->assertSee('Dịch vụ tốt nhất mà chúng tôi cung cấp')
            ->assertSee('Tin tức &amp; Blog mới nhất', false)
            ->assertSee('data-xd-auth-open="login"', false)
            ->assertSee('data-xd-auth-open="register"', false)
            ->assertSee('data-dn350-auth-modal', false)
            ->assertSee('name="two_factor_code"', false)
            ->assertSee('data-dn350-reveal', false)
            ->assertDontSee('TÃ')
            ->assertDontSee('Ä‘')
            ->assertDontSee('áº')
            ->assertDontSee('á»');

        $positions = [];
        foreach (['hero_slider', 'about_experience', 'featured_services', 'featured_categories', 'testimonials', 'project_gallery', 'latest_posts', 'newsletter_signup'] as $type) {
            $positions[] = strpos($html, 'data-block-type="'.$type.'"');
        }
        $this->assertNotContains(false, $positions);
        $this->assertSame($positions, collect($positions)->sort()->values()->all());
        $response->assertSee('dn350-footer', false);
    }

    public function test_dn350_all_storefront_pages_use_the_shared_shell(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('DN350', 'dn350-cleaning');

        $service = CmsService::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();

        $urls = [
            route('site.home', ['locale' => 'vi']),
            route('site.services.index', ['locale' => 'vi']),
            route('site.services.show', ['locale' => 'vi', 'slug' => $service->slug]),
            route('site.blog.index', ['locale' => 'vi']),
            route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]),
            route('site.contact', ['locale' => 'vi']),
            route('site.catalog.search', ['locale' => 'vi']),
            route('site.cart.index', ['locale' => 'vi']),
        ];

        foreach ($urls as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('dn350-header', false)
                ->assertSee('dn350-footer', false)
                ->assertSee('data-dn350-auth-modal', false);
        }
    }

    public function test_dn350_admin_mode_renders_real_block_edit_buttons(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('DN350', 'dn350-cleaning');
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $response = $this->get(route('site.home', ['locale' => 'vi', 'mod' => 'admin']))->assertOk();
        $response->assertSee('data-xd-edit-block', false)->assertSee('data-xd-editor', false)->assertSee('Sửa khối');

        $landing = LandingPage::query()->where('theme_key', 'DN350')->where('is_home', true)->firstOrFail();
        $this->assertSame(9, $landing->blocks()->count());
        foreach ($landing->blocks->where('block_type', '!=', 'footer_contact') as $block) {
            $response->assertSee('data-xd-edit-block="'.$block->id.'"', false);
        }
    }

    public function test_dn350_normal_mode_hides_landing_editor(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('DN350', 'dn350-cleaning');
        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertDontSee('data-xd-edit-block', false)
            ->assertDontSee('data-xd-editor', false);
    }
}
