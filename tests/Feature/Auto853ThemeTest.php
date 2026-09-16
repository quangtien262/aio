<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Auto853ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto853_is_registered_with_complete_block_library_and_assets(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'AUTO853');

        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertFileExists(public_path('theme-previews/AUTO853/preview-auto853.png'));
        $this->assertFileExists(public_path('theme-previews/AUTO853/cover-auto853.png'));

        foreach (['hero-mountain', 'bike-1', 'bike-12', 'story-1', 'story-6', 'promo-accessories', 'promo-trail'] as $asset) {
            $this->assertFileExists(public_path('themes/AUTO853/images/'.$asset.'.png'));
        }

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('AUTO853'));
        $this->assertSame([
            'auto853_hero',
            'auto853_categories',
            'auto853_flash_sale',
            'auto853_promotions',
            'auto853_bikepacking',
            'auto853_feature',
            'auto853_sale_banner',
            'auto853_collection',
            'auto853_videos',
            'auto853_news',
        ], collect($builder->availableBlocks('AUTO853'))->pluck('block_type')->all());
    }

    public function test_auto853_renders_runtime_catalog_cms_and_branding(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Cycle Sentinel',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'AUTO853',
            'branding' => [
                'company_name' => 'AUTO853 Sentinel Cycle',
                'logo_url' => '/storage/branding/auto853-sentinel.svg',
                'support_hotline' => '0888 853 853',
                'support_email' => 'auto853@sentinel.test',
                'support_location' => 'Cycle Sentinel Studio',
            ],
        ]);
        $category = CatalogCategory::query()->create(['name' => 'Xe Sentinel', 'slug' => 'xe-sentinel-auto853', 'is_active' => true]);
        CatalogProduct::query()->create([
            'catalog_category_id' => $category->id,
            'name' => 'Summit AUTO853 Sentinel',
            'slug' => 'summit-auto853-sentinel',
            'sku' => 'A853-TEST',
            'price' => 32590000,
            'stock' => 9,
            'image_url' => '/themes/AUTO853/images/bike-1.png',
            'is_active' => true,
            'is_featured' => true,
            'is_highlight' => true,
        ]);
        CmsPost::query()->create([
            'title' => 'Hành trình AUTO853 Sentinel',
            'slug' => 'hanh-trinh-auto853-sentinel',
            'status' => 'published',
            'excerpt' => 'Tin kiểm thử.',
            'body' => '<p>Nội dung.</p>',
            'publish_at' => now(),
            'is_highlight' => true,
        ]);
        app(LandingPageBuilder::class)->resolveHome('website-main', 'AUTO853', true);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('/storage/branding/auto853-sentinel.svg', false)
            ->assertSee('0888 853 853')
            ->assertSee('auto853@sentinel.test')
            ->assertSee('Cycle Sentinel Studio')
            ->assertSee('Summit AUTO853 Sentinel')
            ->assertSee('Hành trình AUTO853 Sentinel')
            ->assertSee('data-block-type="auto853_bikepacking"', false)
            ->assertDontSee('support@htvietnam.vn')
            ->assertDontSee('70 Lữ Gia');
    }

    public function test_auto853_demo_provider_is_registered_and_repeatable(): void
    {
        $provider = app(ThemeDemoContentProviderRegistry::class)->forTheme('AUTO853');
        $this->assertNotNull($provider);
        $this->assertSame('auto853-summit-cycle', $provider->defaultPreset());
        SiteProfile::query()->create([
            'site_name' => 'Existing cycle site',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'AUTO853',
            'branding' => ['logo_url' => '/storage/branding/existing-auto853.svg'],
        ]);

        $first = $provider->generate($provider->defaultPreset());
        $second = $provider->generate($provider->defaultPreset());

        $this->assertSame(6, $first['counts']['categories']);
        $this->assertSame(12, $first['counts']['products']);
        $this->assertSame(4, $first['counts']['posts']);
        $this->assertSame(12, CatalogProduct::query()->where('sku', 'like', 'A853-BIKE-%')->count());
        $this->assertSame(4, CmsPost::query()->where('slug', 'like', 'auto853-%')->count());
        $this->assertGreaterThan(0, array_sum($second['purged']));
        $this->assertSame('/storage/branding/existing-auto853.svg', data_get(SiteProfile::query()->first()->branding, 'logo_url'));
    }
}
