<?php

namespace Tests\Feature;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd0320CatalogLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_links_preserve_filters_and_render_search_category_and_empty_states(): void
    {
        SiteProfile::create(['site_name' => 'Industrial Solutions', 'website_type' => 'ecommerce', 'active_theme_key' => 'XD0320']);
        $category = CatalogCategory::create(['name' => 'Thiết bị công nghiệp', 'slug' => 'industrial', 'is_active' => true]);
        for ($i = 1; $i <= 25; $i++) {
            CatalogProduct::create(['catalog_category_id' => $category->id, 'name' => 'Máy khoan chuyên dụng '.$i, 'slug' => 'drill-'.$i, 'sku' => 'DRILL-'.$i, 'price' => $i * 100000, 'stock' => 10, 'is_active' => true, 'image_url' => '/theme-demo/xd-shared/travel-2.jpg']);
        }
        $search = $this->get('/vi/tim-kiem?q=Máy&category=industrial&sort=price_asc')->assertOk();
        $search->assertSee('xd-catalog-page', false)->assertSee('Máy khoan chuyên dụng 1')->assertDontSee('<select', false);
        $this->assertEquals(100000, $search->viewData('products')[0]['price']);
        $search->assertSee('q=M%C3%A1y&amp;category=industrial&amp;sort=price_desc', false)->assertSee('page=2', false);
        $categoryResponse = $this->get(route('site.catalog.category', ['locale' => 'vi', 'slug' => 'industrial', 'sort' => 'price_desc']))->assertOk()->assertDontSee('<select', false);
        $this->assertEquals(2500000, $categoryResponse->viewData('products')[0]['price']);
        $empty = $this->get('/vi/tim-kiem?q=no-match')->assertOk()->assertSee('Chưa tìm thấy sản phẩm phù hợp');
        if ($folder = getenv('XD_CATALOG_PREVIEW')) {
            if (! is_dir($folder)) {
                mkdir($folder, 0777, true);
            }
            foreach (['search' => $search, 'category' => $categoryResponse, 'empty' => $empty] as $name => $response) {
                file_put_contents($folder.'/'.$name.'.html', $response->getContent());
            }
        }
    }
}
