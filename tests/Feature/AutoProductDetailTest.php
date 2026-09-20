<?php

namespace Tests\Feature;

use App\Models\{SiteProfile, CatalogProduct, CatalogCategory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AutoProductDetailTest extends TestCase
{
    use RefreshDatabase;

    public static function themes(): array { return [['AUTO850'], ['AUTO851']]; }

    #[DataProvider('themes')]
    public function test_product_details_gallery_recommendations_and_stock(string $theme): void
    {
        SiteProfile::create(['site_name' => 'Auto store', 'website_type' => 'ecommerce', 'active_theme_key' => $theme]);
        $category = CatalogCategory::create(['name' => 'Phụ kiện', 'slug' => 'phu-kien', 'is_active' => true]);
        $product = CatalogProduct::create(['name' => 'Sản phẩm kiểm thử', 'slug' => 'product-test', 'sku' => 'AUTO-TEST', 'catalog_category_id' => $category->id, 'price' => 250000, 'original_price' => 300000, 'stock' => 3, 'image_url' => '/themes/'.$theme.'/images/accessory-1.png', 'is_active' => true, 'short_description' => 'Mô tả ngắn.', 'detail_content' => '<p>Thông tin chi tiết.</p>']);
        $product->images()->create(['image_url' => '/themes/'.$theme.'/images/accessory-2.png', 'sort_order' => 1]);
        $other = CatalogProduct::create(['name' => 'Gợi ý cùng danh mục', 'slug' => 'related', 'sku' => 'AUTO-RELATED', 'catalog_category_id' => $category->id, 'price' => 150000, 'stock' => 2, 'image_url' => $product->image_url, 'is_active' => true]);
        CatalogProduct::create(['name' => 'Không công khai', 'slug' => 'hidden', 'sku' => 'AUTO-HIDDEN', 'price' => 1, 'is_active' => false]);
        $url = route('site.catalog.product', ['locale' => 'vi', 'slug' => $product->slug]);
        $response = $this->get($url)->assertOk()->assertSee('250.000đ')->assertSee('300.000đ')->assertSee('Thông tin chi tiết.')
            ->assertSee('Sản phẩm liên quan')->assertSee('Sản phẩm mới nhất')->assertDontSee('Không công khai')
            ->assertViewHas('latestProducts', fn ($items) => count($items) === 1 && $items[0]['title'] === $other->name);
        $prefix = strtolower(str_replace('AUTO', 'a', $theme));
        $response->assertSee('data-'.$prefix.'-photo', false)->assertDontSee('<section class="'.$prefix.'-inner-hero">', false);
        $product->update(['stock' => 0, 'price' => 0]);
        $this->get($url)->assertOk()->assertSee('Hết hàng')->assertSee('Liên hệ')->assertSee('disabled', false);
    }
}
