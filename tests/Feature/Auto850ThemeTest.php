<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPartner;
use App\Models\CmsPost;
use App\Models\CmsService;
use App\Models\CmsServiceCategory;
use App\Models\CmsServiceImage;
use App\Models\CmsTestimonial;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Auto850ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto850_is_registered_with_complete_block_library(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'AUTO850');
        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertFileExists(public_path('theme-previews/AUTO850/preview-auto850.png'));
        $this->assertFileExists(public_path('theme-previews/AUTO850/cover-auto850.png'));
        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('AUTO850'));
        $this->assertSame([
            'auto850_hero', 'auto850_brands', 'auto850_about', 'auto850_categories', 'auto850_deals', 'auto850_featured_products',
            'auto850_services', 'auto850_process', 'auto850_testimonials', 'auto850_news', 'auto850_faq', 'auto850_booking',
        ], collect($builder->availableBlocks('AUTO850'))->pluck('block_type')->all());
    }

    public function test_auto850_renders_runtime_catalog_cms_and_branding(): void
    {
        SiteProfile::query()->create(['site_name' => 'Auto Sentinel', 'website_type' => 'ecommerce', 'active_theme_key' => 'AUTO850', 'branding' => ['company_name' => 'AUTO850 Sentinel Care', 'logo_url' => '/storage/branding/auto850-sentinel.svg', 'support_hotline' => '0888 850 850', 'support_email' => 'auto850@sentinel.test', 'support_location' => 'Garage Sentinel']]);
        $category = CatalogCategory::query()->create(['name' => 'Phụ kiện Sentinel', 'slug' => 'phu-kien-sentinel', 'is_active' => true]);
        CatalogProduct::query()->create(['catalog_category_id' => $category->id, 'name' => 'Camera AUTO850 Sentinel', 'slug' => 'camera-auto850-sentinel', 'sku' => 'A850-TEST', 'price' => 2750000, 'stock' => 9, 'image_url' => '/themes/AUTO850/images/accessory-2.png', 'is_active' => true, 'is_featured' => true, 'is_highlight' => true]);
        $serviceCategory = CmsServiceCategory::query()->create(['name' => 'Dịch vụ Sentinel', 'slug' => 'dich-vu-sentinel', 'is_active' => true]);
        $service = CmsService::query()->create(['cms_service_category_id' => $serviceCategory->id, 'title' => 'Bảo dưỡng AUTO850 Sentinel', 'slug' => 'bao-duong-auto850-sentinel', 'status' => 'published', 'summary' => 'Dịch vụ kiểm thử.', 'publish_at' => now(), 'is_featured' => true, 'is_highlight' => true]);
        CmsServiceImage::query()->create(['cms_service_id' => $service->id, 'image_url' => '/themes/AUTO850/images/service-1.png', 'is_featured' => true]);
        CmsPost::query()->create(['title' => 'Tin xe AUTO850 Sentinel', 'slug' => 'tin-xe-auto850-sentinel', 'status' => 'published', 'excerpt' => 'Tin kiểm thử.', 'body' => '<p>Nội dung.</p>', 'publish_at' => now(), 'is_highlight' => true]);
        CmsTestimonial::query()->create(['name' => 'Khách hàng Sentinel', 'role' => 'Chủ xe', 'quote' => 'Trải nghiệm AUTO850 rất tốt.', 'status' => 'published', 'publish_at' => now(), 'is_featured' => true]);
        CmsPartner::query()->create(['title' => 'AUTO PARTNER SENTINEL', 'slug' => 'auto-partner-sentinel', 'status' => 'published', 'publish_at' => now(), 'is_featured' => true]);
        app(LandingPageBuilder::class)->resolveHome('website-main', 'AUTO850', true);

        $this->get(route('site.home', ['locale' => 'vi']))->assertOk()
            ->assertSee('/storage/branding/auto850-sentinel.svg', false)->assertSee('0888 850 850')->assertSee('auto850@sentinel.test')->assertSee('Garage Sentinel')
            ->assertSee('Phụ kiện Sentinel')->assertSee('Camera AUTO850 Sentinel')->assertSee('Bảo dưỡng AUTO850 Sentinel')->assertSee('Tin xe AUTO850 Sentinel')
            ->assertSee('Khách hàng Sentinel')->assertSee('AUTO PARTNER SENTINEL')->assertSee('data-block-type="auto850_booking"', false)
            ->assertDontSee('support@htvietnam.vn')->assertDontSee('70 Lữ Gia');
    }

    public function test_auto850_demo_provider_is_registered_and_repeatable(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('AUTO850');
        $this->assertNotNull($provider);
        $this->assertSame('auto850-euro-care', $provider->defaultPreset());
        SiteProfile::query()->create(['site_name' => 'Existing site', 'website_type' => 'ecommerce', 'active_theme_key' => 'AUTO850', 'branding' => ['logo_url' => '/storage/branding/existing-auto850.svg']]);
        $first = $provider->generate($provider->defaultPreset());
        $second = $provider->generate($provider->defaultPreset());
        $this->assertSame(6, $first['counts']['categories']);
        $this->assertSame(10, $first['counts']['products']);
        $this->assertSame(4, $first['counts']['services']);
        $this->assertSame(3, $first['counts']['testimonials']);
        $this->assertSame(10, CatalogProduct::query()->where('sku', 'like', 'A850-%')->count());
        $this->assertGreaterThan(0, array_sum($second['purged']));
        $this->assertSame('/storage/branding/existing-auto850.svg', data_get(SiteProfile::query()->first()->branding, 'logo_url'));
    }
}
