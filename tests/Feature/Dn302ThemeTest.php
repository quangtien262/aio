<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Core\Themes\ThemeRegistry;
use App\Models\Admin;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\LandingPage;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dn302ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dn302_is_registered_with_complete_builder_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'DN302');

        $this->assertNotNull($theme);
        $this->assertSame('service', $theme['website_type']);

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('DN302'));
        $this->assertSame([
            'hero_slider', 'about_experience', 'featured_services', 'featured_categories',
            'project_gallery', 'content_showcase', 'newsletter_signup', 'testimonials',
            'latest_posts', 'landing_contact', 'partner_logos',
        ], collect($builder->availableBlocks('DN302'))->pluck('block_type')->all());

        foreach (['featured_services', 'project_gallery', 'latest_posts'] as $type) {
            $block = collect($builder->availableBlocks('DN302'))->firstWhere('block_type', $type);
            $this->assertArrayHasKey('search', data_get($block, 'settings_schema'));
            $this->assertArrayHasKey('category_id', data_get($block, 'settings_schema'));
        }
    }

    public function test_dn302_demo_and_storefront_routes_render(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('DN302', 'construction-materials');

        $siteProfile = SiteProfile::query()->firstOrFail();
        $siteProfile->forceFill([
            'branding' => array_merge((array) $siteProfile->branding, [
                'company_name' => 'Build Mart Custom',
                'logo_url' => 'https://cdn.example.com/branding/build-mart-custom.svg',
            ]),
        ])->save();

        $this->assertGreaterThan(0, CatalogProduct::query()->count());
        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('https://cdn.example.com/branding/build-mart-custom.svg', false)
            ->assertSee('Build Mart Custom - Trang chủ')
            ->assertDontSee('Janelas - Trang chủ')
            ->assertSee('data-block-type="featured_services"', false)
            ->assertSee('data-block-type="newsletter_signup"', false)
            ->assertSee('data-dn-reveal', false)
            ->assertSee('data-dn-auth-open="login"', false)
            ->assertSee('data-dn-auth-open="register"', false)
            ->assertSee('data-dn-auth-modal', false)
            ->assertSee('data-dn-auth-panel="login"', false)
            ->assertSee('data-dn-auth-panel="register"', false)
            ->assertSee('name="two_factor_code"', false)
            ->assertSee('data-dn-consult-open', false)
            ->assertSee('data-dn-consult-modal', false)
            ->assertSee('data-dn-consult-form', false)
            ->assertSee('Gửi yêu cầu tư vấn')
            ->assertSee('name="source" value="contact"', false)
            ->assertDontSee('name="source" value="dn302-landing"', false);

        $this->assertDatabaseHas('landing_pages', ['theme_key' => 'DN302', 'slug' => 'home', 'is_home' => true]);
        $landing = LandingPage::query()->where('theme_key', 'DN302')->where('is_home', true)->firstOrFail();
        $this->assertCount(11, $landing->blocks);

        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk();
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk();
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Đăng ký tư vấn');
    }

    public function test_dn302_storefront_admin_mode_renders_landing_block_editor(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('DN302', 'construction-materials');
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $this->get(route('site.home', ['locale' => 'vi', 'mod' => 'admin']))
            ->assertOk()
            ->assertSee('data-xd-edit-block', false)
            ->assertSee('data-xd-editor', false)
            ->assertSee('Sửa khối');
    }

    public function test_dn302_hero_uses_saved_database_slides_when_dynamic_source_is_empty(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('DN302', 'construction-materials');
        $landing = LandingPage::query()->where('theme_key', 'DN302')->where('is_home', true)->first()
            ?? app(LandingPageBuilder::class)->seedHome('website-main', 'DN302');
        $hero = $landing->blocks()->where('block_type', 'hero_slider')->firstOrFail();
        $heroData = $hero->data()->where('locale', 'vi')->firstOrFail();

        $heroData->update([
            'content' => json_encode([
                'slides' => [[
                    'title' => 'Ảnh hero đã cập nhật',
                    'summary' => 'Dữ liệu được lưu từ form quản trị.',
                    'image' => '/files/dn302-hero-updated.jpg',
                ]],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('/files/dn302-hero-updated.jpg', false)
            ->assertSee('Ảnh hero đã cập nhật');
    }

    public function test_dn302_hero_copy_uses_saved_block_title_and_description(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('DN302', 'construction-materials');
        $landing = LandingPage::query()->where('theme_key', 'DN302')->where('is_home', true)->first()
            ?? app(LandingPageBuilder::class)->seedHome('website-main', 'DN302');
        $hero = $landing->blocks()->where('block_type', 'hero_slider')->firstOrFail();
        $heroData = $hero->data()->where('locale', 'vi')->firstOrFail();
        $heroData->update([
            'title' => 'Tiêu đề hero lấy từ DB',
            'description' => 'Mô tả hero lấy từ form sửa khối.',
        ]);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('Tiêu đề hero lấy từ DB')
            ->assertSee('Mô tả hero lấy từ form sửa khối.');
    }
}
