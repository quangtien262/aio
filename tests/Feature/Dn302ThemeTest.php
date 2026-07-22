<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Core\Themes\ThemeRegistry;
use App\Models\Admin;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsService;
use App\Models\CmsServiceCategory;
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

        $serviceBlock = collect($builder->availableBlocks('DN302'))->firstWhere('block_type', 'featured_services');
        $this->assertSame([
            'cms_services',
            'catalog_categories',
            'cms_products',
            'cms_service_categories',
            'custom',
        ], collect(data_get($serviceBlock, 'settings_schema.source.options'))->pluck('value')->all());
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

        $headerTemplate = file_get_contents(base_path('themes/DN302/views/partials/header.blade.php'));
        $this->assertStringNotContainsString('fa-location-dot', $headerTemplate);
        $this->assertStringNotContainsString('$address', $headerTemplate);

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
        $landing = LandingPage::query()->where('theme_key', 'DN302')->where('is_home', true)->first()
            ?? app(LandingPageBuilder::class)->seedHome('website-main', 'DN302');
        $this->assertCount(11, $landing->blocks);

        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk();
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk();
        $this->get(route('site.contact', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('Thông tin liên hệ')
            ->assertSee('1900 6760')
            ->assertSee('hello@buildmart.demo')
            ->assertSee('QL1A, Thủ Đức, TP.HCM')
            ->assertSee('name="source" value="contact"', false)
            ->assertSee('Gửi yêu cầu tư vấn');
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

    public function test_dn302_post_detail_shows_ten_latest_related_posts(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('DN302', 'construction-materials');
        $currentPost = CmsPost::query()->firstOrFail();
        $websiteKey = (string) ($currentPost->website_key ?: 'website-main');

        foreach (range(1, 12) as $index) {
            CmsPost::query()->create([
                'website_key' => $websiteKey,
                'title' => sprintf('RELATED-LATEST-%02d', $index),
                'slug' => sprintf('related-latest-%02d', $index),
                'status' => 'published',
                'excerpt' => 'Bài viết kiểm thử cho khối tin liên quan.',
                'body' => '<p>Nội dung bài viết liên quan.</p>',
                'publish_at' => now()->subMinutes(12 - $index),
            ]);
        }

        $response = $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $currentPost->slug]))->assertOk();
        $html = $response->getContent();

        $response->assertSee('Tin liên quan');
        $response->assertSee('RELATED-LATEST-12');
        $response->assertSee('RELATED-LATEST-03');
        $response->assertDontSee('RELATED-LATEST-02');
        $this->assertSame(10, substr_count($html, 'data-related-post-card'));
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

    public function test_dn302_uses_primary_database_menu_with_nested_items(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('DN302', 'construction-materials');

        $menu = CmsMenu::query()->where('location', 'primary')->firstOrFail();
        $menu->update([
            'items' => [[
                'label' => 'Menu chính từ DB',
                'url' => '#menu-db',
                'target' => '_self',
                'children' => [[
                    'label' => 'Menu con từ DB',
                    'url' => '#menu-con-db',
                    'target' => '_self',
                ]],
            ]],
        ]);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('Menu chính từ DB')
            ->assertSee('Menu con từ DB')
            ->assertSee('href="#menu-db"', false)
            ->assertSee('href="#menu-con-db"', false)
            ->assertSee('Liên kết nhanh')
            ->assertSee('Thông tin liên hệ')
            ->assertSee('Cùng chúng tôi hiện thực hóa không gian của bạn')
            ->assertSee('data-dn-consult-open', false)
            ->assertDontSee('href="#"><i class="fa-brands', false);
    }

    public function test_dn302_contact_page_does_not_require_a_published_cms_page(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('DN302', 'construction-materials');
        CmsPage::query()->whereIn('slug', ['contact', 'lien-he'])->delete();

        $this->get(route('site.contact', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('Liên hệ')
            ->assertSee('Thông tin liên hệ')
            ->assertSee('name="source" value="contact"', false);
    }

    public function test_dn302_service_category_hero_and_entries_use_database_values(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('DN302', 'construction-materials');

        $category = CmsServiceCategory::query()->create([
            'name' => 'Thiết kế theo dữ liệu DB',
            'slug' => 'thiet-ke-db',
            'description' => 'Mô tả danh mục dịch vụ được quản trị trong database.',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $service = CmsService::query()->create([
            'cms_service_category_id' => $category->id,
            'title' => 'Dịch vụ lấy từ database',
            'slug' => 'dich-vu-db',
            'status' => 'published',
            'summary' => 'Tóm tắt dịch vụ trong database.',
            'content' => '<p>Nội dung dịch vụ trong database.</p>',
            'sort_order' => 1,
            'publish_at' => now(),
        ]);

        $this->get(route('site.services.category', ['locale' => 'vi', 'slug' => $category->slug]))
            ->assertOk()
            ->assertSee('Thiết kế theo dữ liệu DB')
            ->assertSee('Mô tả danh mục dịch vụ được quản trị trong database.')
            ->assertSee($service->title)
            ->assertSee(route('site.services.show', ['locale' => 'vi', 'slug' => $service->slug]), false)
            ->assertDontSee('Janelas Windows &amp; Doors', false)
            ->assertDontSee('Dịch vụ cửa nhôm kính');
    }

    public function test_dn302_cms_page_hero_and_body_use_the_current_database_entry(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('DN302', 'construction-materials');

        $page = CmsPage::query()->create([
            'title' => 'Giới thiệu lấy từ database',
            'slug' => 'dn302-page-db',
            'status' => 'published',
            'excerpt' => 'Mô tả page được lưu và quản lý trong CMS.',
            'body' => '<h2>Nội dung chi tiết của page trong database</h2><p>Thông tin giới thiệu đã được cập nhật.</p>',
            'meta_title' => 'SEO title của page',
            'publish_at' => now(),
        ]);

        $this->get('/vi/'.$page->slug)
            ->assertOk()
            ->assertSee('Giới thiệu lấy từ database')
            ->assertSee('Mô tả page được lưu và quản lý trong CMS.')
            ->assertSee('Nội dung chi tiết của page trong database')
            ->assertSee('Thông tin giới thiệu đã được cập nhật.')
            ->assertDontSee('Janelas Windows &amp; Doors', false);
    }

    public function test_dn302_hero_becomes_full_width_when_shared_copy_and_cta_are_empty(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('DN302', 'construction-materials');
        $landing = LandingPage::query()->where('theme_key', 'DN302')->where('is_home', true)->first()
            ?? app(LandingPageBuilder::class)->seedHome('website-main', 'DN302');
        $hero = $landing->blocks()->where('block_type', 'hero_slider')->firstOrFail();
        $hero->update(['settings' => array_merge($hero->settings ?? [], ['cta_url' => ''])]);
        $hero->data()->where('locale', 'vi')->firstOrFail()->update([
            'title' => '',
            'subtitle' => '',
            'description' => '',
            'button_label' => '',
        ]);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('dn-hero-stage--media-only', false)
            ->assertDontSee('<div class="dn-hero-copy"', false);
    }

    public function test_dn302_all_block_headings_media_and_custom_items_use_saved_database_values(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('DN302', 'construction-materials');
        $landing = LandingPage::query()->where('theme_key', 'DN302')->where('is_home', true)->first()
            ?? app(LandingPageBuilder::class)->seedHome('website-main', 'DN302');

        foreach ($landing->blocks as $index => $block) {
            $block->data()->where('locale', 'vi')->firstOrFail()->update([
                'title' => 'DN302 DB block '.($index + 1),
                'description' => 'DN302 DB description '.($index + 1),
            ]);
        }

        foreach (['about_experience', 'featured_categories', 'content_showcase', 'landing_contact'] as $blockType) {
            $block = $landing->blocks->firstWhere('block_type', $blockType);
            $block->update(['media' => ['image' => '/files/'.$blockType.'-db.jpg']]);
        }

        $partners = $landing->blocks->firstWhere('block_type', 'partner_logos');
        $partners->update(['settings' => ['source' => 'custom', 'limit' => 10]]);
        $partners->data()->where('locale', 'vi')->firstOrFail()->update([
            'content' => json_encode(['items' => [['title' => 'Đối tác nhập từ DB']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $response = $this->get(route('site.home', ['locale' => 'vi']))->assertOk();
        foreach (range(1, 11) as $index) {
            $response->assertSee('DN302 DB block '.$index);
            $response->assertSee('DN302 DB description '.$index);
        }
        foreach (['about_experience', 'featured_categories', 'content_showcase', 'landing_contact'] as $blockType) {
            $response->assertSee('/files/'.$blockType.'-db.jpg', false);
        }
        $response->assertSee('Đối tác nhập từ DB');
    }

    public function test_dn302_about_value_buttons_use_saved_names_and_links(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('DN302', 'construction-materials');
        $landing = LandingPage::query()->where('theme_key', 'DN302')->where('is_home', true)->first()
            ?? app(LandingPageBuilder::class)->seedHome('website-main', 'DN302');
        $about = $landing->blocks()->where('block_type', 'about_experience')->firstOrFail();
        $about->data()->where('locale', 'vi')->firstOrFail()->update([
            'content' => json_encode(['items' => [
                ['title' => 'Chất lượng từ DB', 'url' => '/vi/chat-luong'],
                ['title' => 'Tiến bộ từ DB', 'url' => '#du-an'],
                ['title' => 'Uy tín từ DB', 'url' => 'https://example.test/uy-tin'],
                ['title' => 'Chuyên nghiệp từ DB', 'url' => '/vi/lien-he'],
            ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $response = $this->get(route('site.home', ['locale' => 'vi']))->assertOk();
        $response->assertSee('<a class="dn-value" href="/vi/chat-luong"', false);
        $response->assertSee('Chất lượng từ DB');
        $response->assertSee('<a class="dn-value" href="#du-an"', false);
        $response->assertSee('https://example.test/uy-tin', false);
        $response->assertSee('Chuyên nghiệp từ DB');
    }

    public function test_dn302_editors_expose_value_button_name_and_link_fields(): void
    {
        $inlineEditor = file_get_contents(base_path('themes/XD0302/views/partials/scripts.blade.php'));
        $adminEditor = file_get_contents(resource_path('admin/src/modules/cms/components/LandingBlockManagerDrawer.jsx'));

        $this->assertStringContainsString("['title', 'Tên nút']", $inlineEditor);
        $this->assertStringContainsString("['url', 'Link khi click']", $inlineEditor);
        $this->assertStringContainsString('xdAboutUsesValueButtons', file_get_contents(base_path('themes/DN302/views/home.blade.php')));
        $this->assertStringContainsString("['title', 'Tên nút']", $adminEditor);
        $this->assertStringContainsString("['url', 'Link khi click']", $adminEditor);
    }

    public function test_dn302_inline_editor_uses_menu_select_and_shared_icon_picker(): void
    {
        $editor = file_get_contents(base_path('themes/XD0302/views/partials/inline-editor.blade.php'));
        $scripts = file_get_contents(base_path('themes/XD0302/views/partials/scripts.blade.php'));

        $this->assertStringContainsString('<select data-xd-setting-field="menu_location">', $editor);
        $this->assertStringContainsString('Chỉ hiển thị dữ liệu, không chọn menu', $editor);
        $this->assertStringContainsString("@include('partials.font-awesome-icon-picker')", $editor);
        $this->assertStringContainsString('window.AioFontAwesomeIconPicker?.open', $scripts);
        $this->assertStringContainsString("['icon', 'Biểu tượng']", $scripts);
        $this->assertStringContainsString("if (blockType === 'content_showcase')", $scripts);
        $this->assertStringContainsString("['about_experience', 'featured_categories', 'landing_contact'].includes(blockType)", $scripts);
        $this->assertStringContainsString('Ảnh nền khu vực liên hệ', $scripts);
        $this->assertStringContainsString('Ảnh khối danh mục nổi bật', $scripts);
    }

    public function test_dn302_content_showcase_uses_each_items_image_description_and_link(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('DN302', 'construction-materials');
        $landing = LandingPage::query()->where('theme_key', 'DN302')->where('is_home', true)->first()
            ?? app(LandingPageBuilder::class)->seedHome('website-main', 'DN302');
        $showcase = $landing->blocks()->where('block_type', 'content_showcase')->firstOrFail();
        $showcase->update(['settings' => ['source' => 'custom', 'limit' => 4]]);
        $showcase->data()->where('locale', 'vi')->firstOrFail()->update([
            'content' => json_encode(['items' => [[
                'title' => 'Cửa mở quay từ DB',
                'summary' => 'Mô tả phủ trên ảnh lấy đúng từ DB.',
                'image' => '/files/dn302-style-from-db.jpg',
                'url' => '/vi/lien-he-kieu-cua',
            ]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $response = $this->get(route('site.home', ['locale' => 'vi']))->assertOk();
        $response->assertSee('data-dn-style-showcase', false);
        $response->assertSee('data-dn-style-tab="0"', false);
        $response->assertSee('data-dn-style-panel="0"', false);
        $response->assertSee('/files/dn302-style-from-db.jpg', false);
        $response->assertSee('Mô tả phủ trên ảnh lấy đúng từ DB.');
        $response->assertSee('href="/vi/lien-he-kieu-cua"', false);
        $this->assertStringContainsString('data-dn-style-panel', file_get_contents(base_path('themes/DN302/views/partials/shell-scripts.blade.php')));
    }

    public function test_dn302_feature_items_render_saved_urls_as_clickable_links(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('DN302', 'construction-materials');
        $landing = LandingPage::query()->where('theme_key', 'DN302')->where('is_home', true)->first()
            ?? app(LandingPageBuilder::class)->seedHome('website-main', 'DN302');
        $features = $landing->blocks()->where('block_type', 'featured_categories')->firstOrFail();
        $features->update(['settings' => ['source' => 'custom', 'limit' => 6]]);
        $features->data()->where('locale', 'vi')->firstOrFail()->update([
            'content' => json_encode(['items' => [
                ['title' => 'Cách nhiệt có liên kết', 'icon' => 'fa-solid fa-temperature-half', 'url' => '/vi/cach-nhiet'],
                ['title' => 'Chịu lực không có liên kết', 'icon' => 'fa-regular fa-window-restore'],
            ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $response = $this->get(route('site.home', ['locale' => 'vi']))->assertOk();
        $response->assertSee('<a class="dn-feature" href="/vi/cach-nhiet"', false);
        $response->assertSee('<article class="dn-feature"', false);

        $template = file_get_contents(base_path('themes/DN302/views/home.blade.php'));
        $styles = file_get_contents(base_path('themes/DN302/views/partials/styles.blade.php'));

        $this->assertStringContainsString("\$featureUrl = data_get(\$item, 'url')", $template);
        $this->assertStringContainsString("?: data_get(\$item, 'link_url')", $template);
        $this->assertStringContainsString("?: data_get(\$item, 'link')", $template);
        $this->assertStringContainsString('<a class="dn-feature" href="{{ $featureUrl }}"', $template);
        $this->assertStringContainsString('<article class="dn-feature"', $template);
        $this->assertStringContainsString('.dn-feature[href]:hover', $styles);
    }

    public function test_dn302_partner_logos_render_as_a_single_row_autoplay_slider(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('DN302', 'construction-materials');

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('data-dn-partner-slider', false)
            ->assertSee('data-dn-partner-track', false)
            ->assertSee('data-dn-partner-prev', false)
            ->assertSee('data-dn-partner-next', false);

        $scripts = file_get_contents(base_path('themes/DN302/views/partials/shell-scripts.blade.php'));
        $styles = file_get_contents(base_path('themes/DN302/views/partials/styles.blade.php'));
        $this->assertStringContainsString("querySelectorAll('[data-dn-partner-slider]')", $scripts);
        $this->assertStringContainsString('window.setInterval(() => move(1)', $scripts);
        $this->assertStringContainsString('display:flex;gap:25px', $styles);
        $this->assertStringContainsString('scroll-snap-type:x mandatory', $styles);
    }

    public function test_dn302_services_render_four_per_row_in_a_horizontal_slider(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('DN302', 'construction-materials');

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('data-dn-service-slider', false)
            ->assertSee('data-dn-service-track', false)
            ->assertSee('data-dn-service-prev', false)
            ->assertSee('data-dn-service-next', false);

        $scripts = file_get_contents(base_path('themes/DN302/views/partials/shell-scripts.blade.php'));
        $styles = file_get_contents(base_path('themes/DN302/views/partials/styles.blade.php'));
        $this->assertStringContainsString("querySelectorAll('[data-dn-service-slider]')", $scripts);
        $this->assertStringContainsString('flex:0 0 calc((100% - 84px)/4)', $styles);
        $this->assertStringContainsString('.dn-service-image{display:block;', $styles);
        $this->assertStringContainsString('scroll-snap-type:x mandatory', $styles);
    }

    public function test_dn302_home_renders_blocks_by_saved_sort_order_and_visibility(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('DN302', 'construction-materials');
        $landing = LandingPage::query()->where('theme_key', 'DN302')->where('is_home', true)->first()
            ?? app(LandingPageBuilder::class)->seedHome('website-main', 'DN302');

        $hero = $landing->blocks()->where('block_type', 'hero_slider')->firstOrFail();
        $services = $landing->blocks()->where('block_type', 'featured_services')->firstOrFail();
        $partners = $landing->blocks()->where('block_type', 'partner_logos')->firstOrFail();
        $services->update(['sort_order' => 10]);
        $hero->update(['sort_order' => 30]);
        $partners->update(['is_visible' => false]);

        $html = $this->get(route('site.home', ['locale' => 'vi']))->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, 'data-block-type="hero_slider"'),
            strpos($html, 'data-block-type="featured_services"'),
        );
        $this->assertStringNotContainsString('data-block-type="partner_logos"', $html);
    }
}
