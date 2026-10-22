<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Core\Modules\TestData\ModuleTestDataService;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\FnbService;
use Modules\RetailPos\Services\RetailService;
use Tests\TestCase;

final class PosModuleTestDataProvidersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_fnb_draft_fixtures_stay_out_of_live_menus_and_cleanup_preserves_real_data_and_access(): void
    {
        $actor = Admin::factory()->create(['must_change_password' => false]);
        $this->enable(['fnb-pos']);
        DB::table('fnb_site_settings')->insert([
            'website_key' => 'website-main', 'is_active' => true, 'operational_state' => 'active',
            'settings' => json_encode(['real_setting' => 'keep'], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $outlet = DB::table('fnb_outlets')->insertGetId([
            'website_key' => 'website-main', 'public_id' => (string) Str::uuid(),
            'code' => 'TEST-REAL-OUTLET', 'name' => 'Existing real outlet',
            'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $item = DB::table('fnb_menu_items')->insertGetId([
            'website_key' => 'website-main', 'public_id' => (string) Str::uuid(),
            'code' => 'TEST-REAL-ITEM', 'name' => 'Existing real coffee', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $variant = DB::table('fnb_item_variants')->insertGetId([
            'website_key' => 'website-main', 'item_id' => $item, 'code' => 'REAL-DEFAULT',
            'name' => 'Real size', 'base_price_minor' => 39000, 'is_default' => true,
            'default_slot' => 'default', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $sentinels = $this->snapshot(['fnb_site_settings', 'fnb_outlets', 'fnb_menu_items', 'fnb_item_variants']);
        $access = $this->accessSnapshot($actor);
        $service = app(ModuleTestDataService::class);
        $requestId = (string) Str::uuid();
        $batch = $service->create('fnb-pos', 'website-main', $actor, $requestId);
        $this->assertSame($batch, $service->create('fnb-pos', 'website-main', $actor, $requestId));
        $owned = $this->owned($batch['id']);
        $fixtureOutlet = $this->ownedIds($owned, 'fnb_outlets')[0];
        $fixtureItems = $this->ownedIds($owned, 'fnb_menu_items');
        $this->assertCount(3, $fixtureItems);
        $this->assertDatabaseHas('fnb_outlet_staff', ['outlet_id' => $fixtureOutlet, 'admin_id' => $actor->id, 'is_active' => true]);
        $this->assertSame(3, DB::table('fnb_menu_items')->whereIn('id', $fixtureItems)->where('status', 'inactive')->count());
        $this->assertSame(1, DB::table('fnb_menus')->where('status', 'draft')->count());

        $catalog = app(FnbService::class);
        $draft = $catalog->catalog(new FnbContext('website-main', $fixtureOutlet, $actor->id), ['draft' => true, 'include_inactive' => true]);
        $draftItems = collect($draft['resource']['items'])->keyBy('id');
        foreach ($fixtureItems as $fixtureItem) {
            $this->assertTrue($draftItems->has($fixtureItem), 'Fixture must be available to the draft menu editor.');
            $this->assertNotEmpty($draftItems[$fixtureItem]['variants']);
            $this->assertNotEmpty($draftItems[$fixtureItem]['station_routes']);
        }
        foreach ([$outlet, $fixtureOutlet] as $outletId) {
            $live = $catalog->catalog(new FnbContext('website-main', $outletId, $actor->id));
            $this->assertSame([$item], array_column($live['resource']['items'], 'id'), 'Draft fixtures must not enter an existing or test outlet live fallback menu.');
        }
        foreach (['fnb_business_days', 'fnb_shifts', 'fnb_service_sessions', 'fnb_orders', 'fnb_payments', 'fnb_menu_publications'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame($access, $this->accessSnapshot($actor));

        $this->assertSame(['batch_count' => 1, 'record_count' => $owned->count()], $service->remove('website-main', $actor, 'fnb-pos', $batch['id']));
        $this->assertRemoved($owned, $batch['id']);
        $this->assertSame($sentinels, $this->snapshot(array_keys($sentinels)));
        $this->assertDatabaseHas('fnb_item_variants', ['id' => $variant, 'base_price_minor' => 39000]);
        $this->assertSame($access, $this->accessSnapshot($actor));
        $this->assertSame(['batch_count' => 0, 'record_count' => 0], $service->remove('website-main', $actor, 'fnb-pos', $batch['id']));
    }

    public function test_fnb_unconfigured_site_rejects_creation_without_partial_batch_or_config_changes(): void
    {
        $actor = Admin::factory()->create(['must_change_password' => false]);
        $this->enable(['fnb-pos']);
        $access = $this->accessSnapshot($actor);
        try {
            app(ModuleTestDataService::class)->create('fnb-pos', 'website-not-onboarded', $actor, (string) Str::uuid());
            $this->fail('An unconfigured FNB website must require normal onboarding first.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('module', $exception->errors());
        }
        foreach (['module_test_data_batches', 'module_test_data_records', 'fnb_site_settings', 'fnb_outlets', 'fnb_menu_items'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame($access, $this->accessSnapshot($actor));
    }

    public function test_retail_fixtures_are_visible_with_zero_stock_and_round_trip_preserves_real_data_and_access(): void
    {
        $actor = Admin::factory()->create(['must_change_password' => false]);
        $this->enable(['merchandise', 'inventory', 'retail-pos']);
        $catalog = DB::table('merch_catalogs')->insertGetId([
            'public_id' => (string) Str::uuid(), 'code' => 'TEST-REAL-CATALOG', 'name' => 'Existing real catalog',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('merch_skus')->insert([
            'public_id' => (string) Str::uuid(), 'catalog_id' => $catalog,
            'sku' => 'TEST-REAL-SKU', 'name' => 'Existing real product', 'base_unit' => 'pcs',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $sentinels = $this->snapshot(['merch_catalogs', 'merch_skus', 'inv_warehouses', 'inv_items']);
        $access = $this->accessSnapshot($actor);
        $service = app(ModuleTestDataService::class);
        $batch = $service->create('retail-pos', 'website-main', $actor, (string) Str::uuid());
        $owned = $this->owned($batch['id']);
        $chain = $this->ownedIds($owned, 'retail_chains')[0];
        $this->assertDatabaseHas('retail_memberships', ['chain_id' => $chain, 'admin_id' => $actor->id, 'branch_id' => 0, 'preset' => 'hq', 'is_active' => true]);
        $retail = app(RetailService::class);
        $bootstrap = $retail->bootstrap($actor);
        $this->assertCount(1, $bootstrap['chains']);
        $this->assertCount(2, $bootstrap['branches']);
        $this->assertCount(2, $this->ownedIds($owned, 'retail_terminals'));
        $itemsBeforeRead = DB::table('inv_items')->count();
        $ownershipBeforeRead = DB::table('module_test_data_records')->where('batch_id', $batch['id'])->count();
        foreach ($bootstrap['branches'] as $branch) {
            $this->assertCount(1, $branch['terminals']);
            $products = $retail->products($actor, $branch['public_id']);
            $this->assertCount(3, $products);
            foreach ($products as $product) {
                $this->assertTrue($product['is_listed']);
                $this->assertGreaterThan(0, (int) $product['price_minor']);
                $this->assertSame(0.0, (float) $product['available']);
                $this->assertNotEmpty($product['barcodes']);
            }
            $this->assertCount(4, $retail->units($actor, $branch['public_id']));
        }
        $this->assertSame($itemsBeforeRead, DB::table('inv_items')->count(), 'Reading products must not create unowned inventory projections.');
        $this->assertSame($ownershipBeforeRead, DB::table('module_test_data_records')->where('batch_id', $batch['id'])->count());
        foreach (['retail_shifts', 'retail_sales', 'retail_tenders', 'retail_outbox', 'inv_stock_balances', 'inv_stock_movements', 'inv_v2_operations'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame($access, $this->accessSnapshot($actor));

        $this->assertSame(['batch_count' => 1, 'record_count' => $owned->count()], $service->remove('website-main', $actor, 'retail-pos', $batch['id']));
        $this->assertRemoved($owned, $batch['id']);
        $this->assertSame($sentinels, $this->snapshot(array_keys($sentinels)));
        $this->assertSame($access, $this->accessSnapshot($actor));
        $this->assertSame([], $retail->bootstrap($actor)['chains']);
    }

    private function enable(array $modules): void
    {
        foreach ($modules as $module) {
            app(ModuleManager::class)->install($module);
            app(ModuleManager::class)->enable($module);
        }
    }

    private function accessSnapshot(Admin $actor): array
    {
        return $this->snapshot(['roles', 'permissions', 'permission_role', 'admin_role_assignments'])
            + ['actor' => (array) DB::table('admins')->find($actor->id)];
    }

    private function snapshot(array $tables): array
    {
        $result = [];
        foreach ($tables as $table) {
            $result[$table] = DB::table($table)->get()->toJson();
        }

        return $result;
    }

    private function owned(string $batch): Collection
    {
        $records = DB::table('module_test_data_records')->where('batch_id', $batch)->orderBy('id')->get();
        $this->assertNotEmpty($records);

        return $records;
    }

    private function ownedIds(Collection $records, string $table): array
    {
        return $records->where('table_name', $table)->map(fn ($record) => (int) json_decode($record->record_key, true, flags: JSON_THROW_ON_ERROR)['id'])->values()->all();
    }

    private function assertRemoved(Collection $records, string $batch): void
    {
        foreach ($records as $record) {
            $this->assertDatabaseMissing($record->table_name, json_decode($record->record_key, true, flags: JSON_THROW_ON_ERROR));
        }
        $this->assertDatabaseMissing('module_test_data_records', ['batch_id' => $batch]);
        $this->assertDatabaseHas('module_test_data_batches', ['id' => $batch, 'status' => 'removed']);
    }
}
