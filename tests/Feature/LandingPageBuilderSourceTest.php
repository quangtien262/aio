<?php

namespace Tests\Feature;

use App\Models\CmsMenu;
use App\Models\CmsTestimonial;
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
}
