<?php

namespace Tests\Feature;

use App\Core\Cms\CmsMenuLocalization;
use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Models\CmsMenu;
use App\Models\CmsService;
use App\Models\CmsTestimonial;
use App\Models\ContentTranslation;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Models\LandingPageData;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\Localization\WebsiteLocaleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageBuilderSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_foot403_seeds_complete_restaurant_homepage(): void
    {
        $builder = app(LandingPageBuilder::class);
        $page = $builder->seedHome('foot403-builder-test', 'FOOT403');
        $blocks = $page->blocks()->orderBy('sort_order')->get();

        $this->assertTrue($builder->supportsTheme('FOOT403'));
        $this->assertSame(9, $blocks->count());
        $this->assertSame(
            ['top', 'gioi-thieu', 'danh-muc', 'thuc-don', 'mon-noi-bat', 'con-so', 'tin-tuc', 'cam-nhan', 'lien-he'],
            $blocks->pluck('anchor_id')->all(),
        );
        $this->assertSame('landing_contact', $blocks->firstWhere('anchor_id', 'lien-he')->block_type);
    }

    public function test_foot401_homepage_can_render_shortened_service_and_product_summaries(): void
    {
        $html = view('theme-foot401::home', [
            'landingPage' => ['title' => 'FOOT401'],
            'landingBlocks' => [
                [
                    'id' => 1,
                    'block_type' => 'content_mosaic',
                    'anchor_id' => 'dich-vu',
                    'data' => ['title' => 'Dịch vụ', 'content' => ['items' => [[
                        'title' => 'Tiệc riêng',
                        'summary' => 'Mô tả dịch vụ nhà hàng cần được rút gọn.',
                    ]]]],
                ],
                [
                    'id' => 2,
                    'block_type' => 'featured_products',
                    'anchor_id' => 'thuc-don',
                    'data' => ['title' => 'Thực đơn', 'content' => ['items' => [[
                        'title' => 'Món theo mùa',
                        'summary' => 'Mô tả món ăn cần được rút gọn.',
                        'price' => 120000,
                    ]]]],
                ],
            ],
        ])->render();

        $this->assertStringContainsString('Mô tả dịch vụ nhà hàng', $html);
        $this->assertStringContainsString('Mô tả món ăn cần được rút gọn.', $html);
        $this->assertStringContainsString('120.000đ', $html);
    }

    public function test_landing_page_menu_uses_site_landing_show_route(): void
    {
        $page = LandingPage::query()->create([
            'website_key' => 'landing-route-test',
            'theme_key' => 'XD0302',
            'page_type' => 'landing',
            'slug' => 'summer-sale',
            'status' => 'published',
            'template' => 'home',
            'is_home' => false,
            'settings' => ['menu_display_type' => 'landingpage'],
            'published_at' => now(),
        ]);
        LandingPageData::query()->create([
            'landing_page_id' => $page->id,
            'locale' => 'vi',
            'title' => 'Summer sale',
        ]);
        $block = LandingPageBlock::query()->create([
            'landing_page_id' => $page->id,
            'theme_key' => 'XD0302',
            'block_type' => 'featured_products',
            'sort_order' => 10,
            'is_visible' => true,
            'anchor_id' => 'san-pham-hot',
            'settings' => ['source' => 'cms_products', 'limit' => 4],
        ]);
        LandingPageBlockData::query()->create([
            'landing_page_block_id' => $block->id,
            'locale' => 'vi',
            'title' => 'Sản phẩm hot',
            'subtitle' => 'Ưu đãi',
            'content' => json_encode([], JSON_UNESCAPED_UNICODE),
        ]);

        $viewData = app(LandingPageBuilder::class)->viewData($page->load(['data', 'blocks.data']), 'vi');

        $this->assertSame(
            route('site.landing.show', ['locale' => 'vi', 'slug' => 'summer-sale']).'#san-pham-hot',
            $viewData['landingMenuItems'][0]['url'],
        );
    }

    public function test_xd0302_featured_service_list_can_use_menu_items_as_its_source(): void
    {
        app(WebsiteLocaleManager::class)->updateLocale(
            'landing-source-test',
            'en',
            ['is_published' => true],
        );
        $menu = CmsMenu::query()->create([
            'website_key' => 'landing-source-test',
            'name' => 'Landing menu',
            'location' => 'landing-source-test',
            'items' => [[
                'label' => 'Dịch vụ lắp đặt',
                'url' => '/vi/ser/lap-dat',
                'children' => [
                    ['label' => 'Khảo sát công trình'],
                ],
            ]],
        ]);
        $translatedItems = $menu->items;
        $translatedItems[0]['label'] = 'Installation service';
        $translatedItems[0]['children'][0]['label'] = 'Site survey';
        ContentTranslation::query()->create([
            'website_key' => 'landing-source-test',
            'resource_type' => 'cms_menu',
            'resource_id' => (string) $menu->id,
            'locale' => 'en',
            'payload' => app(CmsMenuLocalization::class)->storagePayload(
                $menu->items,
                ['items' => $translatedItems],
            ),
            'translation_status' => 'published',
            'translation_published_at' => now(),
        ]);

        $builder = app(LandingPageBuilder::class);
        $page = $builder->seedHome('landing-source-test', 'XD0302');
        $block = $page->blocks()->where('block_type', 'featured_service_list')->firstOrFail();

        $block->update([
            'settings' => [
                'source' => 'cms_menus',
                'menu_location' => 'landing-source-test',
                'limit' => 3,
            ],
        ]);

        $landingBlocks = $builder->viewData($page->fresh(['data', 'blocks.data']), 'en')['landingBlocks'];
        $items = collect($landingBlocks)->firstWhere('block_type', 'featured_service_list')['dynamic_items'];

        $this->assertSame('Installation service', $items[0]['title']);
        $this->assertSame('Site survey', $items[0]['summary']);
        // There is no published EN service canonical route in this fixture.
        // Keep the visitor in EN instead of creating an EN URL with a VI slug.
        $this->assertSame('/en', $items[0]['url']);
    }

    public function test_xd0302_completed_projects_list_defaults_to_services(): void
    {
        $builder = app(LandingPageBuilder::class);
        $page = $builder->seedHome('completed-projects-source-test', 'XD0302');
        $block = $page->blocks()->where('block_type', 'completed_projects_list')->firstOrFail();

        $this->assertSame('cms_services', $block->settings['source']);
        $this->assertSame(5, $block->settings['limit']);
        $this->assertSame('primary-navigation', $block->settings['menu_location']);
    }

    public function test_xd0302_about_tabs_are_seeded_as_editable_sub_data(): void
    {
        $builder = app(LandingPageBuilder::class);
        $page = $builder->seedHome('xd0302-about-tabs-test', 'XD0302');
        $block = $page->blocks()->where('block_type', 'about_experience')->firstOrFail();
        $content = json_decode($block->data()->where('locale', 'vi')->firstOrFail()->content, true, flags: JSON_THROW_ON_ERROR);
        $tabs = $content['tabs'];

        $this->assertCount(3, $tabs);
        $this->assertSame(['label', 'description'], array_keys($tabs[0]));
        $this->assertSame('Về chúng tôi', $tabs[0]['label']);
        $this->assertNotSame($tabs[0]['description'], $tabs[1]['description']);
    }

    public function test_footer_is_not_created_as_a_landing_page_block(): void
    {
        $builder = app(LandingPageBuilder::class);
        $page = $builder->seedHome('fixed-footer-test', 'XD0302');

        $this->assertFalse($page->blocks()->where('block_type', 'footer_contact')->exists());
        $this->assertNotContains('footer_contact', collect($builder->availableBlocks('XD0302'))->pluck('block_type')->all());
    }

    public function test_xd0302_testimonial_showcase_uses_customer_testimonials_only(): void
    {
        CmsTestimonial::query()->create([
            'name' => 'Customer feedback',
            'quote' => 'The delivery was clear and well coordinated.',
            'status' => 'published',
            'is_featured' => true,
        ]);

        $builder = app(LandingPageBuilder::class);
        $page = $builder->seedHome('testimonial-showcase-test', 'XD0302');
        $block = $page->blocks()->where('block_type', 'testimonial_showcase')->firstOrFail();
        $items = collect($builder->viewData($page->fresh(['data', 'blocks.data']), 'vi')['landingBlocks'])
            ->firstWhere('block_type', 'testimonial_showcase')['dynamic_items'];

        $this->assertSame('Customer feedback', $items[0]['name']);
        $this->assertSame('The delivery was clear and well coordinated.', $items[0]['quote']);
        $this->assertSame('cms_testimonials', $block->settings['source']);
    }

    public function test_xd0304_creates_logistics_blocks_with_flexible_content_sources(): void
    {
        $builder = app(LandingPageBuilder::class);
        $page = $builder->seedHome('xd0304-logistics-test', 'XD0304');
        $blocks = $page->blocks()->get()->keyBy('block_type');

        $this->assertTrue($blocks->has('hero_slider'));
        $this->assertTrue($blocks->has('service_category_slider'));
        $this->assertTrue($blocks->has('solutions_split_list'));
        $this->assertTrue($blocks->has('logistics_feature_panel'));
        $this->assertTrue($blocks->has('collection_gallery'));
        $this->assertTrue($blocks->has('partner_logos'));
        $this->assertFalse($blocks->has('footer_contact'));
        $this->assertSame('cms_services', $blocks['solutions_split_list']->settings['source']);
        $this->assertSame('cms_services', $blocks['collection_gallery']->settings['source']);

        $available = collect($builder->availableBlocks('XD0304'))->keyBy('block_type');
        $sourceOptions = $available['solutions_split_list']['settings_schema']['source']['options'];

        $this->assertContains('custom', collect($sourceOptions)->pluck('value')->all());
        $this->assertContains('cms_posts', collect($sourceOptions)->pluck('value')->all());
        $this->assertContains('cms_products', collect($sourceOptions)->pluck('value')->all());
        $this->assertContains('cms_projects', collect($sourceOptions)->pluck('value')->all());
    }

    public function test_xd0304_registers_its_default_demo_content_preset(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('XD0304');

        $this->assertNotNull($provider);
        $this->assertSame('xd0304-logistics', $provider->defaultPreset());
        $this->assertSame('Logistics và vận tải', $provider->preset()['label']);
    }

    public function test_xd0304_demo_preset_creates_logistics_content(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('XD0304');
        $result = $provider->generate($provider->defaultPreset());

        $this->assertSame(4, $result['counts']['services']);
        $this->assertSame(6, $result['counts']['partners']);
        $this->assertSame(2, $result['counts']['banners']);
        $this->assertSame(4, CmsService::query()->count());
    }

    public function test_xd0304_homepage_renders_with_its_default_demo_content(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('XD0304');
        $provider->generate($provider->defaultPreset());

        $this->get('/vi')
            ->assertOk()
            ->assertSee('Logistics Việt')
            ->assertSee('Giải pháp logistics');
    }

    public function test_xd0305_business_homepage_and_demo_preset_render(): void
    {
        $builder = app(LandingPageBuilder::class);
        $page = $builder->seedHome('xd0305-business-test', 'XD0305');
        $this->assertTrue($page->blocks()->where('block_type', 'business_service_grid')->exists());
        $this->assertTrue($page->blocks()->where('block_type', 'bizmax_contact')->exists());
        $this->assertFalse($page->blocks()->where('block_type', 'footer_contact')->exists());

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('XD0305');
        $this->assertSame('xd0305-business-consulting', $provider->defaultPreset());
        $provider->generate($provider->defaultPreset());

        $this->get('/vi')->assertOk()->assertSee('Tư vấn doanh nghiệp');
    }

    public function test_xd0306_digital_agency_homepage_and_demo_preset_render(): void
    {
        $builder = app(LandingPageBuilder::class);
        $page = $builder->seedHome('xd0306-agency-test', 'XD0306');

        $this->assertTrue($page->blocks()->where('block_type', 'collection_gallery')->exists());
        $this->assertTrue($page->blocks()->where('block_type', 'faq_showcase')->exists());

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('XD0306');
        $this->assertSame('xd0306-digital-agency', $provider->defaultPreset());
        $provider->generate($provider->defaultPreset());

        $this->get('/vi')
            ->assertOk()
            ->assertSee('Biến ý tưởng thành thương hiệu có sức ảnh hưởng')
            ->assertSee('Chiến lược thương hiệu');
    }

    public function test_xd0308_study_abroad_homepage_and_demo_preset_render(): void
    {
        $builder = app(LandingPageBuilder::class);
        $page = $builder->seedHome('xd0308-study-test', 'XD0308');

        $this->assertTrue($page->blocks()->where('block_type', 'process_steps')->exists());
        $this->assertTrue($page->blocks()->where('block_type', 'testimonials')->exists());

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('XD0308');
        $this->assertSame('xd0308-study-abroad', $provider->defaultPreset());
        $provider->generate($provider->defaultPreset());

        $this->get('/vi')->assertOk();
    }

    public function test_xd0307_cleaning_homepage_and_demo_preset_render(): void
    {
        $builder = app(LandingPageBuilder::class);
        $page = $builder->seedHome('xd0307-cleaning-test', 'XD0307');

        $this->assertTrue($page->blocks()->where('block_type', 'business_service_grid')->exists());
        $this->assertTrue($page->blocks()->where('block_type', 'bizmax_testimonial_carousel')->exists());

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('XD0307');
        $this->assertSame('xd0307-cleaning-services', $provider->defaultPreset());
        $provider->generate($provider->defaultPreset());

        $this->get('/vi')->assertOk();
    }

    public function test_xd0309_industrial_safety_homepage_and_demo_preset_render(): void
    {
        $builder = app(LandingPageBuilder::class);
        $page = $builder->seedHome('xd0309-safety-test', 'XD0309');

        $this->assertTrue($page->blocks()->where('block_type', 'business_service_grid')->exists());
        $this->assertTrue($page->blocks()->where('block_type', 'bizmax_contact')->exists());
        $this->assertSame(
            'Dịch vụ của chúng tôi',
            $page->blocks()
                ->where('block_type', 'business_service_grid')
                ->firstOrFail()
                ->data()
                ->where('locale', 'vi')
                ->firstOrFail()
                ->title
        );

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('XD0309');
        $this->assertSame('xd0309-industrial-safety', $provider->defaultPreset());
        $provider->generate($provider->defaultPreset());

        $response = $this->get('/vi')
            ->assertOk()
            ->assertSee('Logistics Việt')
            ->assertSee('Vận tải nội địa')
            ->assertSee('Liên hệ chúng tôi');

        foreach (['TÃƒ', 'ÃƒÆ', 'Ãƒâ', 'Ã„', 'Ã¡', 'Ã¢â', 'Ã†', 'Â'] as $marker) {
            $response->assertDontSee($marker, false);
        }
    }

    public function test_xd0310_garden_homepage_and_demo_preset_render(): void
    {
        $builder = app(LandingPageBuilder::class);
        $page = $builder->seedHome('xd0310-garden-test', 'XD0310');

        $this->assertTrue($page->blocks()->where('block_type', 'project_gallery')->exists());
        $this->assertTrue($page->blocks()->where('block_type', 'partner_logos')->exists());

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('XD0310');
        $this->assertSame('xd0310-garden-landscape', $provider->defaultPreset());
        $result = $provider->generate($provider->defaultPreset());

        $this->assertSame(4, $result['counts']['projects']);
        $this->get('/vi')->assertOk();
    }

    public function test_xd0312_logistics_homepage_and_demo_preset_render(): void
    {
        $builder = app(LandingPageBuilder::class);
        $page = $builder->seedHome('xd0312-logistics-test', 'XD0312');

        $this->assertTrue($page->blocks()->where('block_type', 'process_steps')->exists());
        $this->assertTrue($page->blocks()->where('block_type', 'team_members')->exists());
        $processBlock = $page->blocks()->where('block_type', 'process_steps')->firstOrFail();
        $this->assertDatabaseHas('landing_page_block_data', [
            'landing_page_block_id' => $processBlock->id,
            'locale' => 'vi',
            'title' => 'Quy trình làm việc',
        ]);

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('XD0312');
        $this->assertSame('xd0312-logistics-bizgrow', $provider->defaultPreset());
        $result = $provider->generate($provider->defaultPreset());

        $this->assertSame(4, $result['counts']['services']);
        $this->assertSame(3, $result['counts']['team_members']);
        $this->get('/vi')
            ->assertOk()
            ->assertSee('Logistics thông minh cho chuỗi cung ứng hiện đại')
            ->assertSee('Quy trình làm việc')
            ->assertSee('Kho bãi và lưu trữ');
    }

    public function test_xd0313_visa_homepage_uses_accented_content_and_safe_about_layout(): void
    {
        $builder = app(LandingPageBuilder::class);
        $page = $builder->seedHome('xd0313-visa-test', 'XD0313');
        $aboutBlock = $page->blocks()->where('block_type', 'about_experience')->firstOrFail();

        $this->assertDatabaseHas('landing_page_block_data', [
            'landing_page_block_id' => $aboutBlock->id,
            'locale' => 'vi',
            'title' => 'Nơi niềm đam mê chạm đến những điểm đến trong mơ',
        ]);

        $html = view('theme-xd0313::home', [
            ...$builder->viewData($page->load(['data', 'blocks.data']), 'vi'),
            'themeShellData' => [],
            'siteProfile' => [],
            'themeHomeData' => [],
            'menus' => [],
        ])->render();

        $this->assertStringContainsString('Visa dễ dàng, giấc mơ thành hiện thực', $html);
        $this->assertStringContainsString('Nơi niềm đam mê chạm đến những điểm đến trong mơ', $html);
        $this->assertStringContainsString('rx13-motion-ready', $html);
        $this->assertStringNotContainsString('Noi Niem Dam Me Nhung Diem Den Trong Mo', $html);
    }

    public function test_xd0318_logistics_homepage_uses_accented_content_and_scroll_motion(): void
    {
        $builder = app(LandingPageBuilder::class);
        $page = $builder->seedHome('xd0318-logistics-test', 'XD0318');

        $this->assertDatabaseHas('landing_page_block_data', [
            'landing_page_block_id' => $page->blocks()->where('block_type', 'hero_slider')->firstOrFail()->id,
            'locale' => 'vi',
            'title' => 'Vận chuyển mọi lúc mọi nơi',
        ]);

        $html = view('theme-xd0318::home', [
            ...$builder->viewData($page->load(['data', 'blocks.data']), 'vi'),
            'themeShellData' => [],
            'siteProfile' => [],
            'themeHomeData' => [],
            'menus' => [],
        ])->render();

        $this->assertStringContainsString('Giải pháp logistics toàn cầu tốt nhất', $html);
        $this->assertStringContainsString('Dịch vụ của chúng tôi', $html);
        $this->assertStringContainsString('fg18-motion-ready', $html);
        $this->assertStringNotContainsString('Giai phap logistics toan cau tot nhat', $html);
    }

    public function test_xd0320_industrial_homepage_uses_custom_content_complete_layout_and_scroll_motion(): void
    {
        $builder = app(LandingPageBuilder::class);
        $page = $builder->seedHome('xd0320-industrial-test', 'XD0320');

        $this->assertCount(7, $page->blocks);
        $this->assertSame('custom', $page->blocks->firstWhere('block_type', 'featured_categories')->settings['source']);
        $this->assertSame('custom', $page->blocks->firstWhere('block_type', 'content_mosaic')->settings['source']);
        $this->assertSame('custom', $page->blocks->firstWhere('block_type', 'team_members')->settings['source']);

        $html = view('theme-xd0320::home', [
            ...$builder->viewData($page->load(['data', 'blocks.data']), 'vi'),
            'themeShellData' => [],
            'siteProfile' => [],
            'themeHomeData' => [],
            'menus' => [],
        ])->render();

        $this->assertStringContainsString('Giải pháp công nghiệp cho vận hành bền vững', $html);
        $this->assertStringContainsString('Nhà máy sản xuất tự động', $html);
        $this->assertStringContainsString('Hài lòng 100%', $html);
        $this->assertStringContainsString('foot-header__masthead', $html);
        $this->assertStringContainsString('xd20-motion-ready', $html);
        $this->assertStringNotContainsString('Giai phap cong nghiep', $html);
    }

    public function test_xd0322_construction_homepage_uses_accented_custom_content_and_scroll_motion(): void
    {
        $builder = app(LandingPageBuilder::class);
        $page = $builder->seedHome('xd0322-construction-test', 'XD0322');

        $this->assertCount(11, $page->blocks);
        $this->assertSame('custom', $page->blocks->firstWhere('block_type', 'content_mosaic')->settings['source']);
        $this->assertSame('custom', $page->blocks->firstWhere('block_type', 'project_gallery')->settings['source']);
        $this->assertSame('custom', $page->blocks->firstWhere('block_type', 'team_members')->settings['source']);

        $html = view('theme-xd0322::home', [
            ...$builder->viewData($page->load(['data', 'blocks.data']), 'vi'),
            'themeShellData' => [],
            'siteProfile' => [],
            'themeHomeData' => [],
            'menus' => [],
        ])->render();

        $this->assertStringContainsString('Cung cấp giải pháp xây dựng tốt nhất', $html);
        $this->assertStringContainsString('Chúng tôi dẫn đầu trong lĩnh vực xây dựng', $html);
        $this->assertStringContainsString('Biệt thự hiện đại ven đô', $html);
        $this->assertStringContainsString('c322-motion-ready', $html);
        $this->assertStringContainsString('width:100px', $html);
        $this->assertStringNotContainsString('Cung cap giai phap xay dung tot nhat', $html);
    }

    public function test_xd0323_uses_vietnamese_typography_and_scroll_motion(): void
    {
        $builder = app(LandingPageBuilder::class);
        $page = $builder->seedHome('xd0323-typography-test', 'XD0323');

        $html = view('theme-xd0323::home', [
            ...$builder->viewData($page->load(['data', 'blocks.data']), 'vi'),
            'themeShellData' => [],
            'siteProfile' => [],
            'themeHomeData' => [],
            'menus' => [],
        ])->render();

        $this->assertStringContainsString('Thực phẩm hữu cơ tươi chất lượng cao', $html);
        $this->assertStringContainsString('Be Vietnam Pro', $html);
        $this->assertStringContainsString('Lora', $html);
        $this->assertStringContainsString('xd323-motion-ready', $html);
        $this->assertStringContainsString('IntersectionObserver', $html);
    }

    public function test_xd0311_accounting_homepage_and_demo_preset_render(): void
    {
        $builder = app(LandingPageBuilder::class);
        $page = $builder->seedHome('xd0311-accounting-test', 'XD0311');

        $this->assertTrue($page->blocks()->where('block_type', 'business_service_grid')->exists());
        $this->assertTrue($page->blocks()->where('block_type', 'process_steps')->exists());
        $processBlock = $page->blocks()->where('block_type', 'process_steps')->firstOrFail();
        $this->assertDatabaseHas('landing_page_block_data', [
            'landing_page_block_id' => $processBlock->id,
            'locale' => 'vi',
            'title' => 'Cách chúng tôi hoạt động',
        ]);

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('XD0311');
        $this->assertSame('xd0311-accounting-advisory', $provider->defaultPreset());
        $provider->generate($provider->defaultPreset());

        $this->get('/vi')
            ->assertOk()
            ->assertSee('Đăng nhập')
            ->assertSee('Kế toán và thuế vững vàng cho doanh nghiệp')
            ->assertSee('Cách chúng tôi hoạt động');
    }

    public function test_th0050_wellness_homepage_and_demo_preset_render(): void
    {
        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('TH0050'));
        $page = $builder->seedHome('th0050-wellness-test', 'TH0050');

        $this->assertCount(9, $page->blocks);
        $this->assertSame('custom', $page->blocks->firstWhere('block_type', 'content_showcase')->settings['source']);
        $this->assertSame('catalog_products', $page->blocks->firstWhere('block_type', 'featured_products')->settings['source']);
        $this->assertTrue($page->blocks()->where('block_type', 'landing_contact')->exists());

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('TH0050');
        $this->assertSame('th0050-premium-wellness', $provider->defaultPreset());
        $result = $provider->generate($provider->defaultPreset());

        $this->assertSame(8, $result['counts']['products']);
        $this->get('/vi')
            ->assertOk()
            ->assertSee('Tinh hoa chăm sóc sức khỏe')
            ->assertSee('Cần một món quà thật sự ý nghĩa?');
    }
}
