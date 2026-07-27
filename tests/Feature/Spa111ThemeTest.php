<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\LandingPage;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Spa111ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_spa111_is_registered_with_fourteen_editable_home_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'SPA111');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('SPA111'));
        $this->assertSame([
            'hero_slider',
            'spa111_service_highlights',
            'spa111_about',
            'spa111_services',
            'spa111_stats',
            'spa111_featured_products',
            'spa111_why_choose',
            'spa111_testimonials',
            'spa111_faq',
            'spa111_team',
            'spa111_latest_posts',
            'spa111_partners',
            'spa111_booking',
            'spa111_footer',
        ], collect($builder->availableBlocks('SPA111'))->pluck('block_type')->all());
    }

    public function test_spa111_demo_and_homepage_render_successfully(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('SPA111');
        $this->assertNotNull($provider);
        $result = $provider->generate('spa111-bean-spa');
        $this->assertSame(8, data_get($result, 'counts.products'));
        $this->assertSame(3, data_get($result, 'counts.services'));
        $this->assertSame(4, data_get($result, 'counts.team_members'));
        $this->assertSame(3, data_get($result, 'counts.testimonials'));
        $this->assertSame(9, data_get($result, 'counts.partners'));
        $this->assertDatabaseHas('site_banners', ['theme_key' => 'SPA111', 'placement' => 'spa111-hero-slider']);

        $response = $this->get(route('site.home', ['locale' => 'vi']));
        $response->assertOk()
            ->assertSee('data-block-type="spa111_services"', false)
            ->assertSee('data-block-type="spa111_featured_products"', false)
            ->assertSee('data-block-type="spa111_testimonials"', false)
            ->assertSee('data-block-type="spa111_team"', false)
            ->assertSee('data-block-type="spa111_booking"', false)
            ->assertSee('Bean Spa');

        $this->assertCount(14, LandingPage::query()->where('theme_key', 'SPA111')->where('is_home', true)->firstOrFail()->blocks);
        $product = CatalogProduct::query()->firstOrFail();
        $post = CmsPost::query()->firstOrFail();
        $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]))->assertOk();
        $this->get(route('site.blog.show', ['locale' => 'vi', 'slug' => $post->slug]))->assertOk();
        $this->get(route('site.contact', ['locale' => 'vi']))->assertOk();

        Mail::fake();
        $this->post(route('site.contact.submit', ['locale' => 'vi']), [
            'source' => 'contact',
            'name' => 'Khách đặt lịch SPA111',
            'phone' => '0901234567',
            'email' => 'spa111@example.test',
            'subject' => 'Massage thư giãn',
            'route_summary' => '2026-08-01',
            'message' => 'Tôi muốn được tư vấn và đặt lịch massage thư giãn.',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('contact_inquiries', [
            'email' => 'spa111@example.test',
            'subject' => 'Massage thư giãn',
            'route_summary' => '2026-08-01',
        ]);
    }

    public function test_spa111_demo_preserves_an_existing_custom_logo(): void
    {
        SiteProfile::query()->create(['site_name' => 'Website', 'website_type' => 'ecommerce', 'active_theme_key' => 'TH0001', 'branding' => ['logo_url' => '/storage/branding/custom-logo.svg']]);
        app(ThemeDemoContentProviderRegistry::class)->forTheme('SPA111')?->generate('spa111-bean-spa');
        $this->assertSame('/storage/branding/custom-logo.svg', data_get(SiteProfile::query()->firstOrFail()->branding, 'logo_url'));
    }
}
