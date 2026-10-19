<?php

namespace Tests\Feature;

use App\Models\CatalogProduct;
use App\Models\CmsPage;
use App\Models\CmsService;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CmsArticleCatalogSidebarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SiteProfile::create(['site_name' => 'Sidebar test', 'website_type' => 'corporate', 'active_theme_key' => 'corporate-starter']);
        CmsPage::create(['title' => 'Sidebar page', 'slug' => 'sidebar-page', 'status' => 'published', 'body' => '<p>Published content.</p>']);
        CmsService::create(['title' => 'Published CMS service', 'slug' => 'sidebar-service', 'status' => 'published']);
    }

    public function test_installed_catalog_keeps_public_product_cards_and_cms_services(): void
    {
        $product = CatalogProduct::create(['name' => 'Public product', 'slug' => 'public-product', 'sku' => 'PUBLIC', 'is_active' => true]);
        $product->images()->create(['image_url' => '/files/sidebar-product.jpg']);
        CatalogProduct::create(['name' => 'Inactive product', 'slug' => 'inactive-product', 'sku' => 'INACTIVE', 'is_active' => false]);
        CatalogProduct::create(['website_key' => 'other-website', 'name' => 'Other website product', 'slug' => 'other-product', 'sku' => 'OTHER', 'is_active' => true]);

        $response = $this->get('/vi/p/sidebar-page')->assertOk();
        $cards = $response->viewData('articleLatestProducts');
        $this->assertSame(['Public product'], $cards->pluck('title')->all());
        $this->assertSame('/files/sidebar-product.jpg', $cards->first()['image']);
        $this->assertStringContainsString('/public-product', $cards->first()['url']);
        $this->assertSame(['Published CMS service'], $response->viewData('articleServices')->pluck('title')->all());
    }

    public function test_incomplete_optional_catalog_schema_does_not_hide_cms_services_or_crash_content(): void
    {
        CatalogProduct::create(['name' => 'Product without image schema', 'slug' => 'incomplete-product', 'sku' => 'INCOMPLETE', 'is_active' => true]);
        Schema::drop('catalog_product_images');

        $response = $this->get('/vi/p/sidebar-page')->assertOk();
        $this->assertCount(0, $response->viewData('articleLatestProducts'));
        $this->assertSame(['Published CMS service'], $response->viewData('articleServices')->pluck('title')->all());
    }
}
