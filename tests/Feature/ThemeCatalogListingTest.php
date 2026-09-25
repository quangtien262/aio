<?php

namespace Tests\Feature;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeCatalogListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_theme_uses_the_same_catalog_fallback(): void
    {
        SiteProfile::create(['site_name' => 'Starter', 'website_type' => 'corporate', 'active_theme_key' => 'corporate-starter']);
        $category = CatalogCategory::create(['name' => 'Catalog', 'slug' => 'starter-catalog', 'is_active' => true]);
        foreach (['/vi/tim-kiem', route('site.catalog.category', ['locale' => 'vi', 'slug' => $category->slug])] as $url) {
            $this->get($url)->assertOk()->assertSee('class="xd-catalog-page"', false)->assertSee('Chưa tìm thấy sản phẩm phù hợp');
        }
    }

    public function test_every_theme_renders_filtered_catalog_and_search(): void
    {
        $profile = SiteProfile::create(['site_name' => 'Catalog Demo', 'website_type' => 'ecommerce', 'active_theme_key' => 'XD0320']);
        $category = CatalogCategory::create(['name' => 'Thiết bị chuyên dụng', 'slug' => 'equipment', 'is_active' => true]);
        for ($i = 1; $i <= 25; $i++) {
            CatalogProduct::create(['catalog_category_id' => $category->id, 'name' => 'Sản phẩm chuyên dụng '.$i, 'slug' => 'equipment-'.$i, 'sku' => 'EQ-'.$i, 'price' => $i * 100000, 'stock' => 10, 'is_active' => true, 'image_url' => '/theme-demo/xd-shared/travel-2.jpg']);
        }
        $failures = [];
        foreach (glob(base_path('themes/*/views/search.blade.php')) as $file) {
            $theme = basename(dirname($file, 2));
            $profile->update(['active_theme_key' => $theme]);
            foreach (['search' => '/vi/tim-kiem?q=EQ&category=equipment&sort=price_asc', 'category' => route('site.catalog.category', ['locale' => 'vi', 'slug' => 'equipment', 'sort' => 'price_desc']), 'empty' => '/vi/tim-kiem?q=none-found'] as $mode => $url) {
                $response = $this->get($url);
                if ($response->status() !== 200) {
                    $failures[] = $theme.'/'.$mode.' HTTP '.$response->status();

                    continue;
                }
                $html = $response->getContent();
                $this->assertStringContainsString('class="xd-catalog-page"', $html, $theme.'/'.$mode);
                preg_match('/<main class="xd-catalog-page"[\s\S]*?<\/main>/', $html, $main);
                $this->assertStringNotContainsString('<select', $main[0] ?? '', $theme.'/'.$mode);
                $this->assertStringNotContainsString('catalog-listing.', $main[0] ?? '', $theme.'/'.$mode);
                if ($mode === 'search') {
                    $this->assertEquals(100000, $response->viewData('products')[0]['price'], $theme);
                    $response->assertSee('q=EQ&amp;category=equipment&amp;sort=price_desc', false)->assertSee('page=2', false);
                } elseif ($mode === 'category') {
                    $this->assertEquals(2500000, $response->viewData('products')[0]['price'], $theme);
                    $response->assertSee('sort=price_desc&amp;page=2', false);
                    $this->assertEquals(25, $response->viewData('resultCount'));
                } else {
                    $response->assertSee('Chưa tìm thấy sản phẩm phù hợp');
                }
                if ($folder = getenv('CATALOG_PREVIEW')) {
                    if (! is_dir($folder)) {
                        mkdir($folder, 0777, true);
                    }file_put_contents($folder.'/'.$theme.'-'.$mode.'.html', $html);
                }
            }
        }
        $this->assertSame([], $failures);
        $pageTwo = $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => 'equipment', 'sort' => 'price_desc', 'page' => 2]))->assertOk();
        $this->assertCount(1, $pageTwo->viewData('products'));
        $this->assertEquals(100000, $pageTwo->viewData('products')[0]['price']);
        app()->setLocale('en');
        $english = view('themes.common.catalog-listing', ['catalogMode' => 'search', 'products' => [], 'activeTheme' => ['key' => 'BOOK920']])->render();
        $this->assertStringContainsString('No matching products', $english);
        $this->assertStringNotContainsString('catalog-listing.', $english);
    }
}
