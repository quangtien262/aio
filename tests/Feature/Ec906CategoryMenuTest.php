<?php

namespace Tests\Feature;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ec906CategoryMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_menu_uses_website_categories_on_home_and_inner_pages(): void
    {
        SiteProfile::create(['site_name' => 'Mini Mart', 'website_type' => 'ecommerce', 'active_theme_key' => 'EC906']);
        $parent = CatalogCategory::create(['name' => 'Đồ dùng gia đình', 'slug' => 'household', 'is_active' => true]);
        $child = CatalogCategory::create(['name' => 'Chăm sóc nhà cửa', 'slug' => 'home-care', 'parent_id' => $parent->id, 'is_active' => true]);
        CatalogProduct::create(['name' => 'Nước giặt', 'slug' => 'laundry', 'sku' => 'LAUNDRY', 'catalog_category_id' => $child->id, 'price' => 100000, 'stock' => 10, 'is_active' => true]);
        foreach (['home' => '/vi', 'search' => '/vi/tim-kiem'] as $name => $url) {
            $response = $this->get($url)->assertOk()->assertSee('aria-controls="ec96-category-panel"', false)->assertSee('data-ec96-categories hidden', false);
            preg_match('/<div class="ec96-category-panel"[\s\S]*?<\/header>/', $response->getContent(), $matches);
            $this->assertStringContainsString('Đồ dùng gia đình', $matches[0]);
            $this->assertStringContainsString('Chăm sóc nhà cửa', $matches[0]);
            $this->assertStringContainsString('/home-care', $matches[0]);
            if ($folder = getenv('EC906_PREVIEW')) {
                if (! is_dir($folder)) {
                    mkdir($folder, 0777, true);
                }file_put_contents($folder.'/'.$name.'.html', $response->getContent());
            }
        }
    }
}
