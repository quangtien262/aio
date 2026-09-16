<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPartner;
use App\Models\CmsPost;
use App\Models\CmsTestimonial;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Tool750ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_tool750_is_registered_with_complete_block_library(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'TOOL750');

        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertFileExists(public_path('theme-previews/TOOL750/preview-tool750.png'));
        $this->assertFileExists(public_path('theme-previews/TOOL750/cover-tool750.png'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('TOOL750'));
        $this->assertSame([
            'tool750_hero',
            'tool750_promo_categories',
            'tool750_sale_products',
            'tool750_category_grid',
            'tool750_weekly_products',
            'tool750_reasons',
            'tool750_compact_products',
            'tool750_promo_banner',
            'tool750_news',
            'tool750_testimonials',
            'tool750_partners',
        ], collect($builder->availableBlocks('TOOL750'))->pluck('block_type')->all());
    }

    public function test_tool750_renders_catalog_cms_and_runtime_branding(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Xưởng Máy Sentinel',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'TOOL750',
            'branding' => [
                'company_name' => 'Cơ Khí Sentinel',
                'logo_url' => '/storage/branding/tool750-sentinel.svg',
                'support_hotline' => '0888 750 750',
                'support_email' => 'kythuat@sentinel.test',
                'support_location' => 'Khu công nghiệp Sentinel',
                'copyright_text' => 'Bản quyền Cơ Khí Sentinel.',
            ],
        ]);
        $category = CatalogCategory::query()->create(['name' => 'Máy khoan Sentinel', 'slug' => 'may-khoan-sentinel', 'is_active' => true]);
        CatalogProduct::query()->create(['catalog_category_id' => $category->id, 'name' => 'Máy khoan công nghiệp Sentinel', 'slug' => 'may-khoan-cong-nghiep-sentinel', 'sku' => 'TOOL750-TEST', 'price' => 2750000, 'stock' => 9, 'image_url' => '/themes/TOOL750/images/product-drill.png', 'is_active' => true, 'is_featured' => true, 'is_highlight' => true]);
        CmsPost::query()->create(['title' => 'Bảo dưỡng máy Sentinel', 'slug' => 'bao-duong-may-sentinel', 'status' => 'published', 'excerpt' => 'Cẩm nang kỹ thuật.', 'body' => '<p>Nội dung.</p>', 'publish_at' => now(), 'is_highlight' => true]);
        CmsTestimonial::query()->create(['name' => 'Khách Sentinel', 'role' => 'Kỹ sư', 'quote' => 'Thiết bị vận hành rất ổn định.', 'status' => 'published', 'publish_at' => now(), 'is_featured' => true]);
        CmsPartner::query()->create(['title' => 'PARTNER SENTINEL', 'slug' => 'partner-sentinel', 'status' => 'published', 'publish_at' => now(), 'is_featured' => true]);
        app(LandingPageBuilder::class)->resolveHome('website-main', 'TOOL750', true);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('/storage/branding/tool750-sentinel.svg', false)
            ->assertSee('0888 750 750')
            ->assertSee('kythuat@sentinel.test')
            ->assertSee('Khu công nghiệp Sentinel')
            ->assertSee('Bản quyền Cơ Khí Sentinel.')
            ->assertSee('Máy khoan Sentinel')
            ->assertSee('Máy khoan công nghiệp Sentinel')
            ->assertSee('Bảo dưỡng máy Sentinel')
            ->assertSee('Khách Sentinel')
            ->assertSee('PARTNER SENTINEL')
            ->assertSee('data-block-type="tool750_weekly_products"', false)
            ->assertDontSee('support@htvietnam.vn')
            ->assertDontSee('1900 6750');
    }

    public function test_tool750_demo_provider_is_registered_and_repeatable(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('TOOL750');

        $this->assertNotNull($provider);
        $this->assertSame('tool750-industrial', $provider->defaultPreset());

        SiteProfile::query()->create([
            'site_name' => 'Existing site',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'TOOL750',
            'branding' => ['logo_url' => '/storage/branding/existing.svg'],
        ]);
        $first = $provider->generate($provider->defaultPreset());
        $second = $provider->generate($provider->defaultPreset());

        $this->assertSame(7, $first['counts']['categories']);
        $this->assertSame(12, $first['counts']['products']);
        $this->assertSame(12, CatalogProduct::query()->where('sku', 'like', 'T750-%')->count());
        $this->assertGreaterThan(0, array_sum($second['purged']));
        $this->assertSame('/storage/branding/existing.svg', data_get(SiteProfile::query()->first()->branding, 'logo_url'));
    }
}
