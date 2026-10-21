<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use App\Models\CatalogProduct;
use App\Models\ModuleInstallation;
use App\Support\SiteContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryProductSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(SiteContext::class, new SiteContext);
        $actor = Admin::factory()->create(['id' => Admin::SYSTEM_OWNER_ID, 'must_change_password' => false]);
        $manager = app(ModuleManager::class);
        $manager->install('merchandise');
        $manager->enable('merchandise');
        $manager->install('inventory');
        $manager->enable('inventory');
        $this->actingAs($actor->fresh(), 'admin');
    }

    public function test_missing_optional_catalog_returns_guidance_without_querying_products_or_writing_a_sync_run(): void
    {
        ModuleInstallation::query()->where('key', 'catalog')->delete();
        $this->dropProducts();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void { $queries[] = $query->sql; });
        $this->postJson('/admin/api/inventory/items/sync-products')->assertUnprocessable()
            ->assertJsonPath('message', 'Chưa thể đồng bộ sản phẩm. Hãy mở App Store, cài đặt và bật ứng dụng Catalog để có nguồn sản phẩm đồng bộ sang kho.');
        $this->assertSame(0, DB::table('inv_sync_runs')->count());
        $this->assertSame(0, DB::table('inv_items')->count());
        $this->assertFalse(collect($queries)->contains(fn ($sql) => str_contains($sql, 'catalog_products')));
    }

    public function test_disabled_catalog_does_not_read_products_even_when_the_table_exists(): void
    {
        $manager = app(ModuleManager::class);
        $this->installCatalog();
        ModuleInstallation::query()->where('key', 'catalog')->update(['status' => 'disabled']);
        $this->postJson('/admin/api/inventory/items/sync-products')->assertUnprocessable()
            ->assertJsonPath('message', 'Chưa thể đồng bộ sản phẩm. Hãy mở App Store, cài đặt và bật ứng dụng Catalog để có nguồn sản phẩm đồng bộ sang kho.');
        $this->assertSame(0, DB::table('inv_sync_runs')->count());
    }

    public function test_enabled_catalog_with_missing_schema_returns_guidance_instead_of_a_sql_error(): void
    {
        $this->installCatalog();
        app(ModuleManager::class)->enable('catalog');
        $this->dropProducts();
        $this->postJson('/admin/api/inventory/items/sync-products')->assertUnprocessable()
            ->assertJsonPath('message', 'Ứng dụng Catalog chưa có bảng dữ liệu sản phẩm. Hãy kiểm tra hoặc nâng cấp Catalog trong App Store trước khi đồng bộ.');
        $this->assertSame(0, DB::table('inv_sync_runs')->count());
    }

    public function test_enabled_catalog_syncs_only_the_current_website_and_does_not_duplicate_items(): void
    {
        $this->installCatalog();
        app(ModuleManager::class)->enable('catalog');
        $existingCount = CatalogProduct::query()->forWebsite('website-main')->count();
        CatalogProduct::query()->create(['name' => 'Sync product', 'sku' => 'SYNC-1', 'slug' => 'sync-product', 'price' => 12500, 'stock' => 0, 'is_active' => true, 'website_key' => 'website-main']);
        CatalogProduct::query()->create(['name' => 'Other website', 'sku' => 'SYNC-OTHER', 'slug' => 'sync-other', 'price' => 10, 'stock' => 0, 'is_active' => true, 'website_key' => 'other-website']);
        $this->postJson('/admin/api/inventory/items/sync-products')->assertOk()
            ->assertJsonPath('data.created_count', $existingCount + 1)->assertJsonPath('data.failed_count', 0);
        $this->assertDatabaseHas('inv_items', ['sku' => 'SYNC-1', 'name' => 'Sync product']);
        $this->assertDatabaseMissing('inv_items', ['sku' => 'SYNC-OTHER']);
        $this->postJson('/admin/api/inventory/items/sync-products')->assertOk()
            ->assertJsonPath('data.created_count', 0)->assertJsonPath('data.skipped_count', $existingCount + 1);
        $this->assertSame(1, DB::table('inv_items')->where('sku', 'SYNC-1')->count());
    }

    private function installCatalog(): void
    {
        $manager = app(ModuleManager::class);
        $manager->install('cms');
        $manager->enable('cms');
        $manager->install('catalog');
    }

    private function dropProducts(): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            Schema::dropIfExists('catalog_product_images');
            Schema::dropIfExists('catalog_products');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
}
