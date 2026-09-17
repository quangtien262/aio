<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CatalogProduct;
use App\Models\CmsMenu;
use App\Support\FrontendRouteUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CmsMenuLinkOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_without_public_slugs_do_not_break_menu_options(): void
    {
        $this->actingAs(Admin::factory()->create(['id' => Admin::SYSTEM_OWNER_ID]), 'admin');
        foreach ([null, '', '   ', 'valid-product'] as $index => $slug) {
            $product = CatalogProduct::query()->create([
                'name' => 'Product '.$index, 'sku' => 'MENU-'.$index,
                'slug' => $slug, 'price' => 10, 'website_key' => 'website-main',
            ]);
        }
        CatalogProduct::query()->create([
            'name' => 'Other website', 'sku' => 'MENU-OTHER',
            'slug' => 'other-product', 'price' => 10, 'website_key' => 'website-other',
        ]);
        CmsMenu::query()->create([
            'name' => 'Main menu', 'location' => 'primary-navigation', 'items' => [],
        ]);

        $this->getJson('/admin/api/cms/menus')->assertOk()
            ->assertJsonPath('data.items.0.name', 'Main menu')
            ->assertJsonCount(1, 'data.linkOptions.products')
            ->assertJsonPath('data.linkOptions.products.0.value', (string) $product->id)
            ->assertJsonPath('data.linkOptions.products.0.url', FrontendRouteUrl::productPath('valid-product'));
        $this->assertDatabaseHas('catalog_products', ['sku' => 'MENU-0', 'slug' => null]);
    }

    public function test_menus_remain_available_without_optional_catalog_tables(): void
    {
        $this->actingAs(Admin::factory()->create(['id' => Admin::SYSTEM_OWNER_ID]), 'admin');
        Schema::disableForeignKeyConstraints();
        try {
            Schema::drop('catalog_product_images');
            Schema::drop('catalog_products');
            Schema::drop('catalog_categories');
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->getJson('/admin/api/cms/menus')->assertOk()
            ->assertJsonPath('data.linkOptions.products', [])
            ->assertJsonPath('data.linkOptions.productCategories', []);
    }
}
