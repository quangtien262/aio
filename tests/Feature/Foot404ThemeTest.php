<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\SiteProfile;
use App\Support\LandingPages\LandingPageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Foot404ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_foot404_is_registered_with_the_expected_homepage_blocks(): void
    {
        $theme = app(ThemeRegistry::class)->all()->firstWhere('key', 'FOOT404');

        $this->assertNotNull($theme);
        $this->assertSame('ecommerce', $theme['website_type']);
        $this->assertFileExists(public_path('theme-previews/FOOT404/preview-foot404.svg'));
        $this->assertFileExists(public_path('theme-previews/FOOT404/cover-foot404.svg'));

        $builder = app(LandingPageBuilder::class);
        $this->assertTrue($builder->supportsTheme('FOOT404'));
        $this->assertSame([
            'hero_slider',
            'foot404_categories',
            'foot404_promo_trio',
            'foot404_deals',
            'foot404_new_products',
            'foot404_coupon',
            'foot404_best_sellers',
            'foot404_promo_duo',
        ], collect($builder->availableBlocks('FOOT404'))->pluck('block_type')->all());

        $this->assertStringContainsString('IntersectionObserver', file_get_contents(base_path('themes/FOOT404/views/partials/scripts.blade.php')));
        $this->assertStringContainsString('prefers-reduced-motion', file_get_contents(base_path('themes/FOOT404/views/partials/styles.blade.php')));
    }

    public function test_foot404_renders_catalog_data_and_database_contact_information(): void
    {
        SiteProfile::query()->create([
            'site_name' => 'Cửa hàng của tôi',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'FOOT404',
            'branding' => [
                'logo_url' => '/storage/branding/customer-logo.svg',
                'support_hotline' => '0909 123 456',
                'support_email' => 'hello@example.test',
                'support_location' => '12 Đường Bình An, Hà Nội',
            ],
        ]);
        $category = CatalogCategory::query()->create(['name' => 'Quà tặng', 'slug' => 'qua-tang', 'is_active' => true]);
        CatalogProduct::query()->create([
            'catalog_category_id' => $category->id,
            'name' => 'Hộp quà an lành',
            'slug' => 'hop-qua-an-lanh',
            'sku' => 'FOOT404-001',
            'price' => 490000,
            'stock' => 10,
            'image_url' => '/theme-demo/ec903/food-dessert.webp',
            'is_featured' => true,
            'is_active' => true,
        ]);
        app(LandingPageBuilder::class)->resolveHome('website-main', 'FOOT404', true);

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertSee('/storage/branding/customer-logo.svg', false)
            ->assertSee('0909 123 456')
            ->assertSee('hello@example.test')
            ->assertSee('12 Đường Bình An, Hà Nội')
            ->assertSee('Hộp quà an lành')
            ->assertSee('Quà tặng')
            ->assertSee('data-block-type="foot404_deals"', false)
            ->assertSee('data-xd-auth-open="login"', false)
            ->assertDontSee('1900 9477')
            ->assertDontSee('admin@demo037173.web30s.vn');
    }
}
