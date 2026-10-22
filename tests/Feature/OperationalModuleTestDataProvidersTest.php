<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Core\Modules\TestData\ModuleTestDataContext;
use App\Core\Modules\TestData\ModuleTestDataProvider;
use App\Core\Modules\TestData\ModuleTestDataService;
use App\Models\AcctOrganization;
use App\Models\Admin;
use App\Support\AccountingTax\AccountingInventoryWarehouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Services\InventoryTestDataProvider;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Manufacturing\Services\ManufacturingTestDataProvider;
use Modules\Merchandise\Services\MerchandiseTestDataProvider;
use Modules\Purchasing\Services\PurchasingTestDataProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OperationalModuleTestDataProvidersTest extends TestCase
{
    use RefreshDatabase;

    public static function providers(): array
    {
        return [
            'merchandise' => [MerchandiseTestDataProvider::class, 'merchandise'],
            'inventory' => [InventoryTestDataProvider::class, 'inventory'],
            'purchasing' => [PurchasingTestDataProvider::class, 'purchasing'],
            'manufacturing' => [ManufacturingTestDataProvider::class, 'manufacturing'],
        ];
    }

    #[DataProvider('providers')]
    public function test_fixtures_are_exactly_owned_without_posting_or_changing_existing_data(string $providerClass, string $module): void
    {
        $actor = Admin::factory()->create(['must_change_password' => false]);
        $catalog = DB::table('merch_catalogs')->insertGetId([
            'public_id' => (string) Str::uuid(), 'code' => 'REAL-CATALOG', 'name' => 'Existing real catalog',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $realCatalog = (array) DB::table('merch_catalogs')->find($catalog);
        $assignments = DB::table('admin_role_assignments')->get()->map(fn ($row) => (array) $row)->all();
        $tables = [
            'merch_catalogs', 'merch_units', 'merch_skus', 'merch_packagings', 'merch_barcodes',
            'merch_variant_sets', 'merch_sku_variants', 'inv_warehouses', 'inv_locations', 'inv_items',
            'inv_stock_documents', 'inv_stock_document_lines', 'purchase_suppliers', 'purchase_orders',
            'purchase_order_lines', 'mfg_companies', 'mfg_factories', 'mfg_workshops', 'mfg_lines',
            'mfg_shifts', 'mfg_memberships', 'mfg_styles', 'mfg_style_revisions', 'mfg_samples',
        ];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->count();
        }
        /** @var ModuleTestDataProvider $provider */
        $provider = new $providerClass;
        $batch = (string) Str::uuid();
        DB::transaction(function () use ($provider, $module, $actor, $batch): void {
            DB::table('module_test_data_batches')->insert([
                'id' => $batch, 'website_key' => 'website-test-fixtures', 'module_key' => $module,
                'actor_admin_id' => $actor->id, 'request_id' => (string) Str::uuid(),
                'preview' => json_encode($provider->preview(), JSON_THROW_ON_ERROR),
                'soft_references' => json_encode($provider->references(), JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $provider->create(new ModuleTestDataContext($batch, $module, 'website-test-fixtures', $actor));
        });

        $owned = DB::table('module_test_data_records')->where('batch_id', $batch)->get();
        $this->assertGreaterThan(5, $owned->count());
        foreach ($tables as $table) {
            $this->assertSame(
                DB::table($table)->count() - $before[$table],
                $owned->where('table_name', $table)->count(),
                'Every new fixture row must be owned, including dependency rows in '.$table,
            );
        }
        foreach ($owned as $record) {
            $this->assertDatabaseHas($record->table_name, json_decode($record->record_key, true, flags: JSON_THROW_ON_ERROR));
        }
        $this->assertSame($realCatalog, (array) DB::table('merch_catalogs')->find($catalog));
        $this->assertSame($assignments, DB::table('admin_role_assignments')->get()->map(fn ($row) => (array) $row)->all());
        foreach (['inv_stock_balances', 'inv_stock_movements', 'inv_cost_layers', 'inv_production_postings', 'purchase_receipts', 'purchase_payments', 'mfg_orders'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame(0, DB::table('inv_stock_documents')->where('status', '!=', 'draft')->count());
        $this->assertSame(0, DB::table('purchase_orders')->where('status', '!=', 'draft')->count());
        if ($module === 'manufacturing') {
            $this->assertDatabaseHas('mfg_memberships', ['admin_id' => $actor->id, 'factory_id' => null, 'role' => 'manager']);
            $this->assertDatabaseHas('mfg_styles', ['status' => 'draft']);
            $this->assertDatabaseHas('mfg_style_revisions', ['status' => 'draft']);
            $this->assertDatabaseHas('mfg_samples', ['type' => 'pp', 'status' => 'draft']);
        }
    }

    #[DataProvider('providers')]
    public function test_real_provider_service_round_trip_preserves_unowned_data(string $providerClass, string $module): void
    {
        $actor = Admin::factory()->create(['must_change_password' => false]);
        $this->enable($module);
        $realCatalog = DB::table('merch_catalogs')->insertGetId([
            'public_id' => (string) Str::uuid(), 'code' => 'REAL-KEEP', 'name' => 'Existing catalog retained',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = app(ModuleTestDataService::class);
        $requestId = (string) Str::uuid();
        $batch = $service->create($module, 'website-main', $actor, $requestId);
        $this->assertSame($batch, $service->create($module, 'website-main', $actor, $requestId));
        $owned = DB::table('module_test_data_records')->where('batch_id', $batch['id'])->get();
        $removed = $service->remove('website-main', $actor, $module, $batch['id']);
        $this->assertSame(['batch_count' => 1, 'record_count' => $batch['record_count']], $removed);
        foreach ($owned as $record) {
            $this->assertDatabaseMissing($record->table_name, json_decode($record->record_key, true, flags: JSON_THROW_ON_ERROR));
        }
        $this->assertDatabaseHas('merch_catalogs', ['id' => $realCatalog, 'code' => 'REAL-KEEP']);
        $this->assertDatabaseHas('module_test_data_batches', ['id' => $batch['id'], 'status' => 'removed']);
    }

    public function test_unowned_json_catalog_reference_blocks_whole_batch_cleanup(): void
    {
        $actor = Admin::factory()->create(['must_change_password' => false]);
        $this->enable('manufacturing');
        $service = app(ModuleTestDataService::class);
        $batch = $service->create('merchandise', 'website-main', $actor, (string) Str::uuid());
        $record = DB::table('module_test_data_records')->where('batch_id', $batch['id'])->where('table_name', 'merch_catalogs')->first();
        $catalog = json_decode($record->record_key, true, flags: JSON_THROW_ON_ERROR)['id'];
        DB::table('mfg_companies')->insert([
            'public_id' => (string) Str::uuid(), 'code' => 'REAL-COMPANY', 'name' => 'Real company',
            'settings' => json_encode(['catalog_id' => $catalog], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            $service->remove('website-main', $actor, 'merchandise', $batch['id']);
            $this->fail('A real company reference must block fixture deletion.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertDatabaseHas('module_test_data_batches', ['id' => $batch['id'], 'status' => 'active']);
        $this->assertDatabaseHas('merch_catalogs', ['id' => $catalog]);
        $this->assertSame($batch['record_count'], DB::table('module_test_data_records')->where('batch_id', $batch['id'])->count());
    }

    public function test_warehouse_creation_rechecks_catalog_after_initial_validation(): void
    {
        Admin::factory()->create(['must_change_password' => false]);
        $this->enable('inventory');
        $catalog = DB::table('merch_catalogs')->insertGetId([
            'public_id' => (string) Str::uuid(), 'code' => 'REMOVED-CATALOG', 'name' => 'Catalog being removed',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $armed = true;
        DB::listen(function ($query) use (&$armed, $catalog): void {
            if ($armed && str_starts_with($query->sql, 'select count(') && str_contains($query->sql, '"merch_catalogs"')) {
                $armed = false;
                DB::table('merch_catalogs')->where('id', $catalog)->delete();
            }
        });
        try {
            app(InventoryV2Service::class)->createWarehouse([
                'code' => 'NO-DANGLING-CATALOG', 'name' => 'Rejected warehouse',
                'catalog_id' => $catalog, 'legal_entity_ref' => 'test-legal',
            ]);
            $this->fail('A removed catalog must not receive a soft warehouse reference.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertFalse($armed);
        $this->assertDatabaseMissing('inv_warehouses', ['code' => 'NO-DANGLING-CATALOG']);
    }

    public function test_accounting_mapping_rechecks_warehouse_inside_its_transaction(): void
    {
        $actor = Admin::factory()->create(['must_change_password' => false]);
        $this->enable('inventory');
        $organization = AcctOrganization::query()->create([
            'name' => 'Legal Entity Test', 'legal_name' => 'Legal Entity Test',
            'tax_code' => '0100000999', 'status' => 'active',
        ]);
        $warehouse = DB::table('inv_warehouses')->insertGetId([
            'code' => 'REMOVED-WAREHOUSE', 'name' => 'Warehouse being removed', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $armed = true;
        DB::listen(function ($query) use (&$armed, $warehouse): void {
            if ($armed && str_starts_with($query->sql, 'select * from "inv_warehouses"')) {
                $armed = false;
                DB::table('inv_warehouses')->where('id', $warehouse)->delete();
            }
        });
        try {
            app(AccountingInventoryWarehouseService::class)->map($organization->id, $warehouse, false, $actor->id);
            $this->fail('A warehouse removed after the initial read must not receive an accounting mapping.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('warehouse_id', $exception->errors());
        }
        $this->assertFalse($armed);
        $this->assertDatabaseMissing('acct_inventory_warehouse_mappings', ['inventory_warehouse_id' => $warehouse]);
    }

    private function enable(string $module): void
    {
        $dependencies = match ($module) {
            'merchandise' => ['merchandise'],
            'inventory' => ['merchandise', 'inventory'],
            'purchasing' => ['merchandise', 'inventory', 'purchasing'],
            'manufacturing' => ['merchandise', 'inventory', 'manufacturing'],
        };
        foreach ($dependencies as $dependency) {
            app(ModuleManager::class)->install($dependency);
            app(ModuleManager::class)->enable($dependency);
        }
    }
}
