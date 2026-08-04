<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Foot405ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_foot405_is_registered_with_the_expected_homepage_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'FOOT405');

        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertFileExists(public_path('theme-previews/FOOT405/preview-foot405.svg'));
        $this->assertFileExists(public_path('theme-previews/FOOT405/cover-foot405.svg'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('FOOT405'));
        $this->assertSame([
            'hero_slider',
            'foot405_categories',
            'foot405_popular_products',
            'foot405_promo_trio',
            'foot405_best_sellers',
            'foot405_daily_deals',
            'foot405_product_columns',
            'foot405_newsletter',
            'foot405_benefits',
        ], collect($builder->availableBlocks('FOOT405'))->pluck('block_type')->all());

        $this->assertStringContainsString('IntersectionObserver', file_get_contents(base_path('themes/FOOT405/views/partials/scripts.blade.php')));
        $this->assertStringContainsString('translateY(-34px)', file_get_contents(base_path('themes/FOOT405/views/partials/styles.blade.php')));
        $this->assertStringContainsString('prefers-reduced-motion', file_get_contents(base_path('themes/FOOT405/views/partials/styles.blade.php')));
    }

    public function test_foot405_renders_catalog_data_and_database_contact_information(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Nông sản An Nhiên',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'FOOT405',
            'branding' => [
                'logo_url' => '/storage/branding/an-nhien.svg',
                'support_hotline' => '0888 456 789',
                'support_email' => 'chamsoc@annhien.test',
                'support_location' => '25 Phố Bình Minh, Hà Nội',
                'business_hours' => '08:00 - 20:30 mỗi ngày',
            ],
        ]);
        $category = CatalogCategory::query()->create(['name' => 'Rau củ hữu cơ', 'slug' => 'rau-cu-huu-co', 'is_active' => true]);
        CatalogProduct::query()->create([
            'catalog_category_id' => $category->id,
            'name' => 'Giỏ rau an lành',
            'slug' => 'gio-rau-an-lanh',
            'sku' => 'FOOT405-001',
            'price' => 185000,
            'stock' => 12,
            'image_url' => '/theme-demo/ec916/product-grocery.webp',
            'is_featured' => true,
            'is_active' => true,
        ]);
        app(LandingPageBuilder::class)->resolveHome('website-main', 'FOOT405', true);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('/storage/branding/an-nhien.svg', false)
            ->assertSee('0888 456 789')
            ->assertSee('chamsoc@annhien.test')
            ->assertSee('25 Phố Bình Minh, Hà Nội')
            ->assertSee('08:00 - 20:30 mỗi ngày')
            ->assertSee('Giỏ rau an lành')
            ->assertSee('Rau củ hữu cơ')
            ->assertSee('data-block-type="foot405_popular_products"', false)
            ->assertSee('data-xd-auth-open="login"', false)
            ->assertDontSee('1900 9477')
            ->assertDontSee('admin@demo037131.web30s.vn')
            ->assertDontSee('344 Huỳnh Tấn Phát');
    }
}
