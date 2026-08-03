<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\LandingPage;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd0325ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_xd0325_is_registered_with_complete_builder_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'XD0325');
        $this->assertNotNull($theme);
        $this->assertSame('service', $theme['website_type']);

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('XD0325'));
        $this->assertSame([
            'hero_slider', 'about_experience', 'project_gallery', 'featured_services', 'featured_products',
            'featured_categories', 'testimonials', 'team_members', 'faq_showcase', 'process_steps',
            'latest_posts', 'landing_contact', 'partner_logos',
        ], collect($builder->availableBlocks('XD0325'))->pluck('block_type')->all());

        foreach (['project_gallery', 'featured_services', 'featured_products', 'latest_posts'] as $type) {
            $block = collect($builder->availableBlocks('XD0325'))->firstWhere('block_type', $type);
            $this->assertArrayHasKey('search', data_get($block, 'settings_schema'));
            $this->assertArrayHasKey('category_id', data_get($block, 'settings_schema'));
        }
    }

    public function test_xd0325_demo_and_storefront_render(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('XD0325', 'construction-materials');
        $this->assertGreaterThan(0, CatalogProduct::query()->count());
        $response = $this->get(route('site.home', ['locale' => 'vi']));
        $response->assertOk()->assertSee('Bean Construction')
            ->assertSee('data-block-type="featured_products"', false)
            ->assertSee('data-block-type="faq_showcase"', false)
            ->assertSee('data-block-type="landing_contact"', false);

        $this->assertDatabaseHas('landing_pages', ['theme_key' => 'XD0325', 'slug' => 'home', 'is_home' => true]);

        $landing = LandingPage::query()->where('theme_key', 'XD0325')->where('is_home', true)->firstOrFail();
        $this->assertCount(13, $landing->blocks);
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk();
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk();
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Liên hệ Bean Construction');
    }

    public function test_xd0325_renders_accented_vietnamese_scroll_reveal_and_database_logo_in_footer(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('XD0325', 'construction-materials');

        $profile = SiteProfile::query()->where('website_key', 'website-main')->firstOrFail();
        $profile->forceFill([
            'branding' => array_merge((array) $profile->branding, [
                'company_name' => 'Bean Construction Việt Nam',
                'logo_url' => '/storage/branding/xd0325-custom-logo.svg',
            ]),
        ])->save();

        $response = $this->get(route('site.home', ['locale' => 'vi']));

        $response->assertOk()
            ->assertSee('Kiến Tạo Tương Lai', false)
            ->assertSee('Chúng tôi là ai?', false)
            ->assertSee('Đối Tác Lâu Năm Của Chúng Tôi', false)
            ->assertSee('data-x325-reveal', false)
            ->assertSee('IntersectionObserver', false)
            ->assertSee('src="/storage/branding/xd0325-custom-logo.svg"', false)
            ->assertDontSee('Kien Tao Tuong Lai', false)
            ->assertDontSee('Chung toi la ai?', false);

        $this->assertSame(
            2,
            substr_count($response->getContent(), 'src="/storage/branding/xd0325-custom-logo.svg"'),
            'Logo từ database phải xuất hiện ở cả header và footer XD0325.',
        );
    }
}
