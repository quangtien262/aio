<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
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

    public function test_demo_seeds_catalog_categories_with_images_and_working_product_links(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('FOOT405', 'foot405-complete');
        $generator->generate('FOOT405', 'foot405-complete');

        $categories = CatalogCategory::orderBy('sort_order')->get();
        $this->assertCount(6, $categories);
        $this->assertSame(6, CatalogProduct::count());
        $response = $this->get('/vi')->assertOk()->assertDontSee('Sản phẩm và thiết bị');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $links = (new \DOMXPath($dom))->query('//div[@class="f405-categories"]/a');
        $this->assertSame(6, $links->length);
        $menuLinks = (new \DOMXPath($dom))->query('//details[@data-f405-category-menu]//ul/li/a');
        $this->assertSame(6, $menuLinks->length);
        $search = $this->get('/vi/tim-kiem')->assertOk()->assertSee('data-f405-category-menu', false);
        if ($path = getenv('FOOT405_MENU_PREVIEW')) {
            file_put_contents($path, $search->getContent());
        }
        foreach ($categories as $index => $category) {
            $this->assertFileExists(public_path($category->image_url));
            $this->assertSame(1, $category->products()->count());
            $this->assertStringContainsString($category->name, $links->item($index)->textContent);
            $this->assertStringContainsString($category->name, $menuLinks->item($index)->textContent);
            $this->assertSame($category->image_url, $links->item($index)->getElementsByTagName('img')->item(0)->getAttribute('src'));
            $url = $links->item($index)->getAttribute('href');
            $this->assertStringContainsString($category->slug, $url);
            $this->get($url)->assertOk()->assertSee($category->products()->first()->name);
        }
    }

    public function test_category_menu_handles_empty_catalog_and_nested_categories(): void
    {
        SiteProfile::create(['site_name' => 'Fresh Market', 'website_type' => 'ecommerce', 'active_theme_key' => 'FOOT405']);
        $this->get('/vi/tim-kiem')->assertOk()->assertSee('Danh mục đang được cập nhật.');
        $parent = CatalogCategory::create(['name' => 'Rau củ', 'slug' => 'rau-cu', 'is_active' => true]);
        CatalogCategory::create(['name' => 'Rau lá', 'slug' => 'rau-la', 'parent_id' => $parent->id, 'is_active' => true]);
        $response = $this->get('/vi/tim-kiem')->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $nested = (new \DOMXPath($dom))->query('//details[@data-f405-category-menu]//ul/li/ul/li/a');
        $this->assertSame(1, $nested->length);
        $this->assertSame('Rau lá', trim($nested->item(0)->textContent));
    }

    public function test_demo_reuses_existing_category_without_overwriting_manual_content(): void
    {
        $category = CatalogCategory::create([
            'name' => 'Rau của cửa hàng', 'slug' => 'foot405-rau-cu-tuoi',
            'description' => 'Nội dung do cửa hàng nhập', 'is_active' => true,
        ]);
        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('FOOT405', 'foot405-complete');
        $generator->generate('FOOT405', 'foot405-complete');

        $this->assertSame(6, CatalogCategory::count());
        $this->assertSame('Nội dung do cửa hàng nhập', $category->fresh()->description);
        $this->assertSame('Rau của cửa hàng', $category->fresh()->name);
        $this->assertSame(1, $category->products()->count());
    }

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
