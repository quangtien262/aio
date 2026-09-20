<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\CmsService;
use App\Models\CmsServiceCategory;
use App\Models\CmsServiceImage;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Auto852ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto852_is_registered_with_complete_block_library_and_assets(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'AUTO852');
        $this->assertNotNull($theme); $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertFileExists(public_path('theme-previews/AUTO852/preview-auto852.png')); $this->assertFileExists(public_path('theme-previews/AUTO852/cover-auto852.png'));
        foreach (['hero-detailing', 'product-banner', 'service-1', 'service-6', 'product-1', 'product-10'] as $asset) { $this->assertFileExists(public_path('themes/AUTO852/images/'.$asset.'.png')); }
        $builder = app(LandingPageBuilder::class); $this->assertTrue($builder->supportsTheme('AUTO852'));
        $this->assertSame(['auto852_hero', 'auto852_services', 'auto852_process', 'auto852_promotions', 'auto852_pricing', 'auto852_product_banner', 'auto852_products', 'auto852_news'], collect($builder->availableBlocks('AUTO852'))->pluck('block_type')->all());
    }

    public function test_auto852_renders_runtime_catalog_cms_and_branding(): void
    {
        SiteProfile::query()->create(['site_name' => 'Detail Sentinel', 'website_type' => 'ecommerce', 'active_theme_key' => 'AUTO852', 'branding' => ['company_name' => 'AUTO852 Sentinel Studio', 'logo_url' => '/storage/branding/auto852-sentinel.svg', 'support_hotline' => '0888 852 852', 'support_email' => 'auto852@sentinel.test', 'support_location' => 'Detailing Sentinel']]);
        $category = CatalogCategory::query()->create(['name' => 'Chăm xe Sentinel', 'slug' => 'cham-xe-sentinel-auto852', 'is_active' => true]);
        CatalogProduct::query()->create(['catalog_category_id' => $category->id, 'name' => 'Dung dịch AUTO852 Sentinel', 'slug' => 'dung-dich-auto852-sentinel', 'sku' => 'A852-TEST', 'price' => 285000, 'stock' => 9, 'image_url' => '/themes/AUTO852/images/product-1.png', 'is_active' => true, 'is_featured' => true, 'is_highlight' => true]);
        $serviceCategory = CmsServiceCategory::query()->create(['name' => 'Detail Sentinel', 'slug' => 'detail-sentinel-auto852', 'is_active' => true]);
        $service = CmsService::query()->create(['cms_service_category_id' => $serviceCategory->id, 'title' => 'Phủ bóng AUTO852 Sentinel', 'slug' => 'phu-bong-auto852-sentinel', 'status' => 'published', 'summary' => 'Dịch vụ kiểm thử.', 'publish_at' => now(), 'is_featured' => true, 'is_highlight' => true]);
        CmsServiceImage::query()->create(['cms_service_id' => $service->id, 'image_url' => '/themes/AUTO852/images/service-1.png', 'is_featured' => true]);
        CmsPost::query()->create(['title' => 'Kiến thức AUTO852 Sentinel', 'slug' => 'kien-thuc-auto852-sentinel', 'status' => 'published', 'excerpt' => 'Tin kiểm thử.', 'body' => '<p>Nội dung.</p>', 'publish_at' => now(), 'is_highlight' => true]);
        app(LandingPageBuilder::class)->resolveHome('website-main', 'AUTO852', true);

        $this->get(route('site.home', ['locale' => 'vi']))->assertOk()->assertSee('/storage/branding/auto852-sentinel.svg', false)->assertSee('0888 852 852')->assertSee('auto852@sentinel.test')->assertSee('Detailing Sentinel')->assertSee('Dung dịch AUTO852 Sentinel')->assertSee('Phủ bóng AUTO852 Sentinel')->assertSee('Kiến thức AUTO852 Sentinel')->assertSee('data-block-type="auto852_pricing"', false)->assertDontSee('support@htvietnam.vn')->assertDontSee('70 Lữ Gia');
    }

    public function test_auto852_product_detail_renders_product_data_and_contact_price(): void
    {
        SiteProfile::create(['site_name' => 'Detail product', 'website_type' => 'ecommerce', 'active_theme_key' => 'AUTO852']);
        $product = CatalogProduct::create(['name' => 'Dung dịch đánh bóng hoàn thiện', 'slug' => 'auto852-dung-dich-danh-bong-hoan-thien', 'sku' => 'A852-DETAIL', 'price' => 285000, 'stock' => 9, 'image_url' => '/themes/AUTO852/images/product-1.png', 'detail_content' => '<p>Thông tin dung dịch kiểm thử.</p>', 'is_active' => true]);
        $url = route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]);
        $this->get($url)->assertOk()->assertSee($product->name)->assertSee('285.000đ')
            ->assertSee('/themes/AUTO852/images/product-1.png', false)->assertSee('Thông tin dung dịch kiểm thử.')
            ->assertSee(route('site.cart.add', ['locale' => 'vi', 'slug' => $product->slug]), false);
        $product->update(['price' => 0, 'image_url' => null]);
        $this->get($url)->assertOk()->assertSee($product->name)->assertSee('Liên hệ');
    }

    public function test_auto852_demo_provider_is_registered_and_repeatable(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('AUTO852'); $this->assertNotNull($provider); $this->assertSame('auto852-onyx-detailing', $provider->defaultPreset());
        SiteProfile::query()->create(['site_name' => 'Existing site', 'website_type' => 'ecommerce', 'active_theme_key' => 'AUTO852', 'branding' => ['logo_url' => '/storage/branding/existing-auto852.svg']]);
        $first = $provider->generate($provider->defaultPreset()); $second = $provider->generate($provider->defaultPreset());
        $this->assertSame(6, $first['counts']['categories']); $this->assertSame(10, $first['counts']['products']); $this->assertSame(6, $first['counts']['services']); $this->assertSame(4, $first['counts']['posts']);
        $this->assertSame(10, CatalogProduct::query()->where('sku', 'like', 'A852-PROD-%')->count()); $this->assertSame(6, CmsService::query()->where('slug', 'like', 'auto852-%')->count());
        $this->assertGreaterThan(0, array_sum($second['purged'])); $this->assertSame('/storage/branding/existing-auto852.svg', data_get(SiteProfile::query()->first()->branding, 'logo_url'));
    }
}
