<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\LandingPage;
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

        $this->assertGreaterThan(0, CatalogProduct::query()->count());
        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('janelas')
            ->assertSee('data-block-type="featured_services"', false)
            ->assertSee('data-block-type="newsletter_signup"', false)
            ->assertSee('data-dn-reveal', false)
            ->assertSee('data-dn-auth-open="login"', false)
            ->assertSee('data-dn-auth-open="register"', false)
            ->assertSee('data-dn-auth-modal', false)
            ->assertSee('data-dn-auth-panel="login"', false)
            ->assertSee('data-dn-auth-panel="register"', false)
            ->assertSee('name="two_factor_code"', false);

        $this->assertDatabaseHas('landing_pages', ['theme_key' => 'DN302', 'slug' => 'home', 'is_home' => true]);
        $landing = LandingPage::query()->where('theme_key', 'DN302')->where('is_home', true)->firstOrFail();
        $this->assertCount(11, $landing->blocks);

        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk();
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk();
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Đăng ký tư vấn');
    }
}
