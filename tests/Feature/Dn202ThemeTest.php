<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogProduct;
use App\Models\CmsPartner;
use App\Models\CmsProject;
use App\Models\CmsService;
use App\Models\LandingPage;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Dn202ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dn202_is_registered_with_expected_homepage_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'DN202');

        $this->assertNotNull($theme);
        $this->assertSame('service', $theme['website_type']);
        $this->assertSame('dn202-delta-arc-interior', data_get($theme, 'demo.default_preset'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('DN202'));
        $this->assertSame([
            'hero_slider', 'featured_services', 'project_gallery', 'featured_products', 'content_showcase', 'partner_logos',
        ], collect($builder->availableBlocks('DN202'))->pluck('block_type')->all());
    }

    public function test_dn202_demo_content_and_storefront_render(): void
    {
        $result = app(ThemeDemoContentGenerator::class)->generate('DN202', 'dn202-delta-arc-interior');

        $this->assertSame(6, data_get($result, 'counts.services'));
        $this->assertSame(4, data_get($result, 'counts.products'));
        $this->assertSame(4, data_get($result, 'counts.projects'));
        $this->assertSame(6, data_get($result, 'counts.partners'));
        $this->assertCount(6, CmsService::query()->get());
        $this->assertCount(4, CatalogProduct::query()->get());
        $this->assertCount(4, CmsProject::query()->get());
        $this->assertCount(6, CmsPartner::query()->get());

        $landing = LandingPage::query()->where('theme_key', 'DN202')->where('is_home', true)->firstOrFail();
        $this->assertCount(6, $landing->blocks);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('DN202 Arc')
            ->assertSee('data-block-type="featured_services"', false)
            ->assertSee('data-block-type="featured_products"', false)
            ->assertSee('data-block-type="partner_logos"', false)
            ->assertSee('name="login"', false)
            ->assertSee('Email khách hàng / Username admin');

        $product = CatalogProduct::query()->firstOrFail();
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk();
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk()->assertSee('Liên hệ với DN202 Arc');
    }
}
