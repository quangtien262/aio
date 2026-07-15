<?php

namespace Tests\Feature;

use App\Models\CmsMenu;
use App\Models\CmsService;
use App\Models\CmsTestimonial;
use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageBuilderSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_xd0302_featured_service_list_can_use_menu_items_as_its_source(): void
    {
        CmsMenu::query()->create([
            'name' => 'Landing menu',
            'location' => 'landing-source-test',
            'items' => [[
                'label' => 'Installation service',
                'url' => '/vi/dich-vu/lap-dat',
                'children' => [
                    ['label' => 'Site survey'],
                ],
            ]],
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

        $landingBlocks = $builder->viewData($page->fresh(['data', 'blocks.data']), 'vi')['landingBlocks'];
        $items = collect($landingBlocks)->firstWhere('block_type', 'featured_service_list')['dynamic_items'];

        $this->assertSame('Installation service', $items[0]['title']);
        $this->assertSame('Site survey', $items[0]['summary']);
        $this->assertSame('/vi/dich-vu/lap-dat', $items[0]['url']);
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
        $this->assertArrayNotHasKey('source', $block->settings);
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

        $this->get('/vi')->assertOk()->assertSee('Dịch vụ vận tải hàng không đường biển');
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

        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('XD0309');
        $this->assertSame('xd0309-industrial-safety', $provider->defaultPreset());
        $provider->generate($provider->defaultPreset());

        $this->get('/vi')->assertOk();
    }
}
