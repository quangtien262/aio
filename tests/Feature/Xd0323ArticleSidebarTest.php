<?php

namespace Tests\Feature;

use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Models\CmsService;
use App\Models\CmsServiceImage;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd0323ArticleSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_lists_only_public_items_from_current_website_and_limits_rows(): void
    {
        $profile = SiteProfile::create(['site_name' => 'Euro Farm', 'website_type' => 'corporate', 'active_theme_key' => 'XD0323']);
        CmsPost::create(['title' => 'Article', 'slug' => 'sidebar-article', 'status' => 'published', 'body' => '<p>Article body</p>']);
        for ($i = 0; $i < 12; $i++) {
            CatalogProduct::forceCreate(['sku' => 'SIDEBAR-'.$i, 'name' => 'Product '.$i, 'slug' => 'sidebar-product-'.$i, 'price' => 45000, 'is_active' => true, 'image_url' => '/theme-demo/dn351/category-vegetables.jpg', 'created_at' => now()->subDays(12 - $i)]);
            $service = CmsService::create(['title' => 'Service '.$i, 'slug' => 'sidebar-service-'.$i, 'status' => 'published', 'publish_at' => now()->subDays(12 - $i)]);
            CmsServiceImage::create(['cms_service_id' => $service->id, 'image_url' => '/theme-demo/dn351/category-vegetables.jpg']);
        }
        CatalogProduct::create(['sku' => 'INACTIVE', 'name' => 'Hidden inactive', 'slug' => 'inactive', 'price' => 1, 'is_active' => false]);
        CatalogProduct::create(['sku' => 'FOREIGN', 'website_key' => 'other-site', 'name' => 'Hidden foreign product', 'slug' => 'foreign-product', 'price' => 1, 'is_active' => true]);
        foreach (['draft', 'future', 'foreign'] as $kind) {
            CmsService::create(['website_key' => $kind === 'foreign' ? 'other-site' : 'website-main', 'title' => 'Hidden '.$kind.' service', 'slug' => 'hidden-'.$kind, 'status' => $kind === 'draft' ? 'draft' : 'published', 'publish_at' => $kind === 'future' ? now()->addDay() : now()]);
        }
        $response = $this->get('/vi/n/sidebar-article')->assertOk();
        $products = $response->viewData('articleLatestProducts');
        $services = $response->viewData('articleServices');
        $this->assertCount(5, $products);
        $this->assertCount(10, $services);
        $this->assertSame('Product 11', $products->first()['title']);
        $this->assertSame('Service 11', $services->first()['title']);
        $this->assertStringNotContainsString('Hidden', json_encode([$products, $services]));
        $response->assertSee('Sản phẩm mới nhất')->assertSee('Danh sách dịch vụ');
        foreach ([$products->first(), $services->first()] as $item) {
            $response->assertSee($item['url'], false);
            $this->get($item['url'])->assertOk();
        }

        $profile->update(['active_theme_key' => 'XD0320']);
        $this->get('/vi/n/sidebar-article')->assertOk()->assertDontSee('data-article-sidebar="products"', false)->assertDontSee('data-article-sidebar="services"', false);
    }

    public function test_each_empty_group_is_hidden_independently(): void
    {
        SiteProfile::create(['site_name' => 'Euro Farm', 'website_type' => 'corporate', 'active_theme_key' => 'XD0323']);
        CmsPost::create(['title' => 'Article', 'slug' => 'empty-sidebar', 'status' => 'published']);
        $this->get('/vi/n/empty-sidebar')->assertOk()->assertDontSee('Sản phẩm mới nhất')->assertDontSee('Danh sách dịch vụ');
        CatalogProduct::create(['sku' => 'ONLY', 'name' => 'Only product', 'slug' => 'only-product', 'price' => 1, 'is_active' => true]);
        $this->get('/vi/n/empty-sidebar')->assertOk()->assertSee('Sản phẩm mới nhất')->assertDontSee('Danh sách dịch vụ');
        CatalogProduct::query()->update(['is_active' => false]);
        CmsService::create(['title' => 'Only service', 'slug' => 'only-service', 'status' => 'published']);
        $this->get('/vi/n/empty-sidebar')->assertOk()->assertDontSee('Sản phẩm mới nhất')->assertSee('Danh sách dịch vụ');
    }
}
