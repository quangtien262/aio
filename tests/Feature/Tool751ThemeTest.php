<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPartner;
use App\Models\CmsPost;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Tool751ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_tool751_is_registered_with_complete_block_library(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'TOOL751');

        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertFileExists(public_path('theme-previews/TOOL751/preview-tool751.png'));
        $this->assertFileExists(public_path('theme-previews/TOOL751/cover-tool751.png'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('TOOL751'));
        $this->assertSame([
            'tool751_hero',
            'tool751_category_icons',
            'tool751_sale_products',
            'tool751_compact_products',
            'tool751_category_products',
            'tool751_news',
            'tool751_partners',
        ], collect($builder->availableBlocks('TOOL751'))->pluck('block_type')->all());
    }

    public function test_tool751_renders_catalog_cms_and_runtime_branding(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Bee Sentinel',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'TOOL751',
            'branding' => [
                'company_name' => 'Bee Sentinel Tools',
                'logo_url' => '/storage/branding/tool751-sentinel.svg',
                'support_hotline' => '0888 751 751',
                'support_email' => 'tool751@sentinel.test',
                'support_location' => 'Kho thiết bị Sentinel',
                'copyright_text' => 'Bản quyền Bee Sentinel Tools.',
            ],
        ]);
        $category = CatalogCategory::query()->create(['name' => 'Dụng cụ Sentinel', 'slug' => 'dung-cu-sentinel', 'is_active' => true]);
        CatalogProduct::query()->create(['catalog_category_id' => $category->id, 'name' => 'Máy khoan TOOL751 Sentinel', 'slug' => 'may-khoan-tool751-sentinel', 'sku' => 'T751-TEST', 'price' => 2750000, 'stock' => 9, 'image_url' => '/themes/TOOL751/images/product-drill.png', 'is_active' => true, 'is_featured' => true, 'is_highlight' => true]);
        CmsPost::query()->create(['title' => 'Tin kỹ thuật TOOL751 Sentinel', 'slug' => 'tin-ky-thuat-tool751-sentinel', 'status' => 'published', 'excerpt' => 'Cẩm nang kỹ thuật.', 'body' => '<p>Nội dung.</p>', 'publish_at' => now(), 'is_highlight' => true]);
        CmsPartner::query()->create(['title' => 'BEE PARTNER SENTINEL', 'slug' => 'bee-partner-sentinel', 'status' => 'published', 'publish_at' => now(), 'is_featured' => true]);
        app(LandingPageBuilder::class)->resolveHome('website-main', 'TOOL751', true);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('/storage/branding/tool751-sentinel.svg', false)
            ->assertSee('0888 751 751')
            ->assertSee('tool751@sentinel.test')
            ->assertSee('Kho thiết bị Sentinel')
            ->assertSee('Bản quyền Bee Sentinel Tools.')
            ->assertSee('Dụng cụ Sentinel')
            ->assertSee('Máy khoan TOOL751 Sentinel')
            ->assertSee('Tin kỹ thuật TOOL751 Sentinel')
            ->assertSee('BEE PARTNER SENTINEL')
            ->assertSee('data-block-type="tool751_category_products"', false)
            ->assertDontSee('support@htvietnam.vn')
            ->assertDontSee('266 Đội Cấn');
    }

    public function test_tool751_demo_provider_is_registered_and_repeatable(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('TOOL751');

        $this->assertNotNull($provider);
        $this->assertSame('tool751-bee-store', $provider->defaultPreset());

        SiteProfile::query()->create([
            'site_name' => 'Existing site',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'TOOL751',
            'branding' => ['logo_url' => '/storage/branding/existing-tool751.svg'],
        ]);
        $first = $provider->generate($provider->defaultPreset());
        $second = $provider->generate($provider->defaultPreset());

        $this->assertSame(7, $first['counts']['categories']);
        $this->assertSame(10, $first['counts']['products']);
        $this->assertSame(10, CatalogProduct::query()->where('sku', 'like', 'T751-%')->count());
        $this->assertGreaterThan(0, array_sum($second['purged']));
        $this->assertSame('/storage/branding/existing-tool751.svg', data_get(SiteProfile::query()->first()->branding, 'logo_url'));
    }
}
