<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CatalogProduct;
use App\Models\CmsService;
use App\Models\CmsTestimonial;
use App\Models\SiteProfile;
use App\Support\SiteContext;
use App\Support\ThemeBrandingResolver;
use Database\Seeders\Xd321HomepageShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd321ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_xd321_renders_polished_shell_accented_copy_and_scroll_reveal(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('XD321', 'xd321-cargo-logistics');

        $profile = SiteProfile::query()->where('website_key', 'website-main')->firstOrFail();
        $profile->forceFill([
            'branding' => array_merge((array) $profile->branding, [
                'company_name' => 'XD321 Cargo Việt Nam',
                'logo_url' => '/storage/branding/xd321-logo.svg',
            ]),
        ])->save();

        $response = $this->get(route('site.home', ['locale' => 'vi']));

        $response->assertOk()
            ->assertSee('Cargo Logistics', false)
            ->assertSee('Chia sẻ từ khách hàng', false)
            ->assertSee('Giới thiệu', false)
            ->assertSee('Dịch vụ', false)
            ->assertSee('data-x321-reveal', false)
            ->assertSee('IntersectionObserver', false)
            ->assertSee('foot-header__masthead-inner', false)
            ->assertSee('foot-footer__top', false)
            ->assertSee('x321-process__steps', false)
            ->assertDontSee('Nhanh chong, an toan cung XD321 Cargo', false)
            ->assertDontSee('Gioi thieu', false);

        $this->assertSame(2, substr_count($response->getContent(), 'src="/storage/branding/xd321-logo.svg"'));
    }

    public function test_xd321_shell_styles_are_self_contained_and_responsive(): void
    {
        $styles = file_get_contents(base_path('themes/XD321/views/partials/styles.blade.php'));

        $this->assertStringContainsString('.foot-header__masthead-inner{', $styles);
        $this->assertStringContainsString('.foot-footer__top{display:grid', $styles);
        $this->assertStringContainsString('.x321-js [data-x321-reveal]', $styles);
        $this->assertStringContainsString('@media(max-width:800px)', $styles);
        $this->assertStringContainsString('@media(prefers-reduced-motion:reduce)', $styles);
    }

    public function test_showcase_seeder_is_additive_repeatable_and_scoped_to_xd321_websites(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('XD321', 'xd321-cargo-logistics');
        $context = app(SiteContext::class);
        $context->set(null, 'other-website');
        $otherService = CmsService::query()->create(['title' => 'Dịch vụ website khác', 'slug' => 'xd321-demo-air-freight', 'status' => 'published']);
        $context->set(null, 'website-main');
        $service = CmsService::query()->where('slug', 'xd321-demo-air-freight')->firstOrFail();
        $service->update(['summary' => 'Nội dung Sếp đã biên tập']);
        $before = [CmsService::count(), CatalogProduct::count(), CmsTestimonial::count()];

        $context->set(null, 'other-website');
        $result = app(Xd321HomepageShowcaseSeeder::class)->seedForWebsite('website-main');
        $this->assertSame(['services' => 0, 'products' => 0, 'testimonials' => 0, 'partners' => 0], $result);
        $this->assertSame('other-website', $context->websiteKey());
        $this->assertSame('Dịch vụ website khác', $otherService->fresh()->title);
        $context->set(null, 'website-main');
        $this->assertSame($before, [CmsService::count(), CatalogProduct::count(), CmsTestimonial::count()]);
        $this->assertSame('Nội dung Sếp đã biên tập', $service->fresh()->summary);
        $this->assertSame(6, CmsService::count());
        $this->assertSame(7, CatalogProduct::count());
        $this->assertGreaterThanOrEqual(7, CmsTestimonial::count());
    }

    public function test_homepage_renders_all_three_collections_with_cms_items_and_formatted_prices(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('XD321', 'xd321-cargo-logistics');
        $response = $this->get(route('site.home', ['locale' => 'vi']));
        $response->assertOk()
            ->assertSee('Vận tải hàng không')
            ->assertSee('Khai báo hải quan')
            ->assertSee('Màng PE quấn pallet')
            ->assertSee('185.000 ₫')
            ->assertSee('Chị Mai')
            ->assertSee('Chị Hương')
            ->assertSee('Northline Logistics')
            ->assertSee('data-carousel-pause', false)
            ->assertSee('scroll-snap-type:x mandatory', false);
        $this->assertSame(3, substr_count($response->getContent(), 'data-x321-carousel role='));
        $this->assertSame(7, substr_count($response->getContent(), 'class="x321-product-card"'));
        $this->assertGreaterThanOrEqual(7, substr_count($response->getContent(), 'class="x321-quote-card"'));
    }

    public function test_footer_only_shows_configured_valid_social_urls_from_theme_branding(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('XD321', 'xd321-cargo-logistics');
        app(ThemeBrandingResolver::class)->update('website-main', 'XD321', [
            'facebook_url' => ' https://www.facebook.com/xd321-demo ',
            'x_url' => '',
            'youtube_url' => 'https://www.youtube.com/@xd321-demo',
        ]);
        $this->get(route('site.home', ['locale' => 'vi']))->assertOk()
            ->assertSee('href="https://www.facebook.com/xd321-demo"', false)
            ->assertSee('href="https://www.youtube.com/@xd321-demo"', false)
            ->assertSee('fa-brands fa-facebook-f', false)
            ->assertSee('fa-brands fa-youtube', false)
            ->assertDontSee('fa-brands fa-x-twitter', false)
            ->assertDontSee('href="#footer">f</a>', false);

        app(ThemeBrandingResolver::class)->update('website-main', 'XD321', [
            'facebook_url' => '', 'x_url' => 'javascript:alert(1)', 'youtube_url' => '#footer',
        ]);
        $this->get(route('site.home', ['locale' => 'vi']))->assertOk()
            ->assertDontSee('<section class="foot-footer__social">', false)
            ->assertSee('foot-footer__top--without-social', false);
    }

    public function test_service_category_counts_the_displayed_records_instead_of_paginator_properties(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('XD321', 'xd321-cargo-logistics');

        $response = $this->get(route('site.services.category', ['locale' => 'vi', 'slug' => 'xd321-dich-vu']));
        $response->assertOk()->assertSee('<strong>4</strong>', false);
        $this->assertSame(4, substr_count($response->getContent(), '<article class="xd-service-card">'));
    }
}
