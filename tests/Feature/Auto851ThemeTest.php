<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\CmsTestimonial;
use App\Models\LandingPageBlock;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Auto851ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto851_is_registered_with_complete_block_library(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'AUTO851');
        $this->assertNotNull($theme); $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertFileExists(public_path('theme-previews/AUTO851/preview-auto851.png')); $this->assertFileExists(public_path('theme-previews/AUTO851/cover-auto851.png'));
        $builder = app(LandingPageBuilder::class); $this->assertTrue($builder->supportsTheme('AUTO851'));
        $this->assertSame(['auto851_hero', 'auto851_model_rail', 'auto851_buy_sell', 'auto851_featured_cars', 'auto851_accessories', 'auto851_testimonials', 'auto851_faq', 'auto851_news', 'auto851_newsletter'], collect($builder->availableBlocks('AUTO851'))->pluck('block_type')->all());
    }

    public function test_auto851_renders_runtime_catalog_cms_and_branding(): void
    {
        SiteProfile::query()->create(['site_name' => 'Auto Sentinel', 'website_type' => 'ecommerce', 'active_theme_key' => 'AUTO851', 'branding' => ['company_name' => 'AUTO851 Sentinel Cars', 'logo_url' => '/storage/branding/auto851-sentinel.svg', 'support_hotline' => '0888 851 851', 'support_email' => 'auto851@sentinel.test', 'support_location' => 'Showroom Sentinel']]);
        $category = CatalogCategory::query()->create(['name' => 'Xe Sentinel', 'slug' => 'xe-sentinel-auto851', 'is_active' => true]);
        CatalogProduct::query()->create(['catalog_category_id' => $category->id, 'name' => 'SUV AUTO851 Sentinel', 'slug' => 'suv-auto851-sentinel', 'sku' => 'A851-TEST', 'price' => 1250000000, 'stock' => 1, 'image_url' => '/themes/AUTO851/images/car-1.png', 'is_active' => true, 'is_featured' => true, 'is_highlight' => true]);
        CmsPost::query()->create(['title' => 'Blog AUTO851 Sentinel', 'slug' => 'blog-auto851-sentinel', 'status' => 'published', 'excerpt' => 'Tin kiểm thử.', 'body' => '<p>Nội dung.</p>', 'publish_at' => now(), 'is_highlight' => true]);
        CmsTestimonial::query()->create(['name' => 'Khách AUTO851 Sentinel', 'role' => 'Chủ xe', 'quote' => 'Trải nghiệm AUTO851 rất tốt.', 'status' => 'published', 'publish_at' => now(), 'is_featured' => true]);
        app(LandingPageBuilder::class)->resolveHome('website-main', 'AUTO851', true);

        $this->get(route('site.home', ['locale' => 'vi']))->assertOk()->assertSee('/storage/branding/auto851-sentinel.svg', false)->assertSee('0888 851 851')->assertSee('auto851@sentinel.test')->assertSee('Showroom Sentinel')->assertSee('SUV AUTO851 Sentinel')->assertSee('Blog AUTO851 Sentinel')->assertSee('Khách AUTO851 Sentinel')->assertSee('data-block-type="auto851_newsletter"', false)->assertDontSee('support@htvietnam.vn')->assertDontSee('266 Đội Cấn');
    }

    public function test_auto851_demo_provider_is_registered_repeatable_and_filters_product_groups(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('AUTO851'); $this->assertNotNull($provider); $this->assertSame('auto851-ohcar-marketplace', $provider->defaultPreset());
        SiteProfile::query()->create(['site_name' => 'Existing site', 'website_type' => 'ecommerce', 'active_theme_key' => 'AUTO851', 'branding' => ['logo_url' => '/storage/branding/existing-auto851.svg']]);
        $first = $provider->generate($provider->defaultPreset()); $second = $provider->generate($provider->defaultPreset());
        $this->assertSame(2, $first['counts']['categories']); $this->assertSame(13, $first['counts']['products']); $this->assertSame(3, $first['counts']['posts']); $this->assertSame(3, $first['counts']['testimonials']);
        $this->assertSame(8, CatalogProduct::query()->where('sku', 'like', 'A851-CAR-%')->count()); $this->assertSame(5, CatalogProduct::query()->where('sku', 'like', 'A851-ACC-%')->count());
        $this->assertNotEquals(LandingPageBlock::query()->where('block_type', 'auto851_featured_cars')->value('settings'), LandingPageBlock::query()->where('block_type', 'auto851_accessories')->value('settings'));
        $this->assertGreaterThan(0, array_sum($second['purged'])); $this->assertSame('/storage/branding/existing-auto851.svg', data_get(SiteProfile::query()->first()->branding, 'logo_url'));
    }
}
