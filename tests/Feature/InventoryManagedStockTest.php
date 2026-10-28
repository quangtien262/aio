<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\InvBatch;
use App\Models\InvLocation;
use App\Models\InvWarehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Contracts\ManagedStockPolicy;
use Modules\Inventory\Observers\ManagedInventoryModelObserver;
use Modules\Inventory\Services\InventoryManagedStockService;
use Modules\Inventory\Services\InventoryProductionService;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Merchandise\Services\MerchandiseService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InventoryManagedStockTest extends TestCase
{
    use RefreshDatabase;

    private InventoryManagedStockService $managed;

    private InventoryV2Service $stock;

    private int $actor;

    private int $warehouse;

    private int $item;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['merchandise' => '0.2.2', 'inventory' => '0.5.0', 'pharmacy' => '0.1.0-dev.1'] as $key => $version) {
            DB::table('module_installations')->insert(['key' => $key, 'name' => $key, 'version' => $version, 'status' => 'enabled', 'website_types' => '[]', 'dependencies' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('merch_runtime_states')->update(['operational_state' => 'active', 'resume_state' => 'active']);
        DB::table('inv_runtime_states')->update(['operational_state' => 'active', 'resume_state' => 'active']);
        config(['inventory.managed_policies.pharmacy' => ManagedStockTestPolicy::class]);
        ManagedStockTestPolicy::$allowed = true;
        $this->actor = Admin::factory()->create(['must_change_password' => false])->id;
        $merch = app(MerchandiseService::class);
        $catalog = $merch->createCatalog(['code' => 'PH', 'name' => 'Pharmacy'])['id'];
        $sku = $merch->saveSku($catalog, ['sku' => 'MEDICINE', 'name' => 'Medicine', 'base_unit' => 'tablet', 'tracking_mode' => 'batch', 'requires_expiry' => true]);
        $this->stock = app(InventoryV2Service::class);
        $this->managed = app(InventoryManagedStockService::class);
        $this->item = $this->stock->ensureItem($sku['id']);
        $this->warehouse = $this->stock->createWarehouse(['code' => 'PH', 'name' => 'Pharmacy', 'catalog_id' => $catalog, 'legal_entity_ref' => 'LEGAL-1', 'timezone' => 'Asia/Ho_Chi_Minh'])['id'];
        $this->owner(fn () => $this->managed->tagWarehouse($this->warehouse, 'pharmacy', 'store-one', 'tag-managed-owner', $this->actor));
    }

    private function owner(callable $callback): mixed
    {
        return $this->managed->withinOwner('pharmacy', 'store-one', $this->actor, 'test.action', $callback, ['source_type' => 'dispensing', 'source_id' => 'DISP-1', 'source_line_id' => 'LINE-1']);
    }

    private function receipt(string $key = 'receipt-one', string $quantity = '10', string $cost = '25'): array
    {
        return $this->owner(fn () => $this->stock->receive(['warehouse_id' => $this->warehouse, 'item_id' => $this->item, 'quantity' => $quantity, 'unit_cost' => $cost, 'batch_code' => 'LOT-1', 'expires_at' => '2030-01-01'], $key, $this->actor));
    }

    private function releaseLot(int $batch): array
    {
        return $this->owner(fn () => $this->managed->changeDisposition(['warehouse_id' => $this->warehouse, 'item_id' => $this->item, 'batch_id' => $batch, 'disposition' => 'saleable', 'reason' => 'Incoming inspection approved'], 'release-lot', $this->actor));
    }

    private function fails(int $status, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected managed stock rejection');
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
    }

    public function test_owner_context_is_mandatory_receipt_is_quarantined_and_trace_separates_cost(): void
    {
        $this->fails(403, fn () => $this->stock->receive(['warehouse_id' => $this->warehouse, 'item_id' => $this->item, 'quantity' => '1'], 'raw-writer', $this->actor));
        $receipt = $this->receipt();
        $this->assertSame('quarantine', $receipt['disposition']);
        $this->assertSame('0.000000', $this->stock->availability($this->warehouse, $this->item));
        $layer = DB::table('inv_cost_layers')->first();
        $this->releaseLot($receipt['batch_id']);
        $this->assertSame('10.000000', $this->stock->availability($this->warehouse, $this->item));
        $this->assertEquals($layer->id, DB::table('inv_cost_layers')->first()->id);
        $hold = $this->owner(fn () => $this->stock->reserve($this->warehouse, $this->item, '2', 'dispense:1', 'reserve-two', $this->actor, $receipt['batch_id']));
        $issue = $this->owner(fn () => $this->stock->commitReservation($hold['public_id'], 'commit-two', $this->actor));
        $this->assertSame('50.000000', $issue['total_cost']);
        $trace = $this->owner(fn () => $this->managed->trace($this->warehouse, $this->item, $receipt['batch_id'], $this->actor));
        $this->assertCount(2, $trace);
        $this->assertSame('LINE-1', $trace[0]['source_line_id']);
        $this->assertArrayNotHasKey('unit_cost', $trace[0]);
        $this->assertNull($this->managed->context());
    }

    public function test_multiple_holds_survive_individual_release_and_block_commit_after_reservation(): void
    {
        $receipt = $this->receipt();
        $this->releaseLot($receipt['batch_id']);
        $reservation = $this->owner(fn () => $this->stock->reserve($this->warehouse, $this->item, '1', 'dispense:2', 'reserve-one', $this->actor, $receipt['batch_id']));
        $input = ['warehouse_id' => $this->warehouse, 'item_id' => $this->item, 'batch_id' => $receipt['batch_id'], 'reason_code' => 'recall', 'reason' => 'Lot recalled'];
        $a = $this->owner(fn () => $this->managed->placeHold($input, 'recall-hold', $this->actor));
        $b = $this->owner(fn () => $this->managed->placeHold(array_replace($input, ['reason_code' => 'temperature']), 'temperature-hold', $this->actor));
        $this->assertSame('0.000000', $this->stock->availability($this->warehouse, $this->item));
        $this->owner(fn () => $this->managed->releaseHold($a['public_id'], 1, 'Recall resolved', 'release-recall', $this->actor));
        $this->fails(409, fn () => $this->owner(fn () => $this->stock->commitReservation($reservation['public_id'], 'commit-blocked', $this->actor)));
        $this->assertEquals(10, DB::table('inv_stock_balances')->sum('quantity_on_hand'));
        $this->owner(fn () => $this->managed->releaseHold($b['public_id'], 1, 'Temperature investigated', 'release-temperature', $this->actor));
        $this->owner(fn () => $this->stock->commitReservation($reservation['public_id'], 'commit-after-release', $this->actor));
        $this->assertEquals(9, DB::table('inv_stock_balances')->sum('quantity_on_hand'));
    }

    public function test_cached_generic_and_managed_replay_reauthorize_and_disabled_owner_stays_closed(): void
    {
        $this->receipt();
        $this->fails(403, fn () => $this->stock->receive(['warehouse_id' => $this->warehouse, 'item_id' => $this->item, 'quantity' => '10', 'unit_cost' => '25', 'batch_code' => 'LOT-1', 'expires_at' => '2030-01-01'], 'receipt-one', $this->actor));
        ManagedStockTestPolicy::$allowed = false;
        $this->fails(403, fn () => $this->receipt());
        ManagedStockTestPolicy::$allowed = true;
        DB::table('module_installations')->where('key', 'pharmacy')->update(['status' => 'disabled']);
        $this->fails(503, fn () => $this->receipt());
        $this->assertEquals(10, DB::table('inv_stock_balances')->sum('quantity_on_hand'));
    }

    public function test_restricted_expired_return_uses_original_receipt_cost_and_caps_quantity(): void
    {
        $a = $this->receipt('first-receipt', '4', '25');
        $this->receipt('second-receipt', '6', '40');
        DB::table('inv_batches')->where('id', $a['batch_id'])->update(['expires_at' => '2020-01-01', 'is_active' => false]);
        $input = ['warehouse_id' => $this->warehouse, 'item_id' => $this->item, 'batch_id' => $a['batch_id'], 'quantity' => '3', 'purpose' => 'supplier_return', 'reason' => 'Return expired receipt goods', 'source_movement_id' => $a['movement_id']];
        $return = $this->owner(fn () => $this->managed->withdrawRestricted($input, 'return-expired', $this->actor));
        $this->assertSame('75.000000', $return['total_cost']);
        $this->assertTrue($this->owner(fn () => $this->managed->withdrawRestricted($input, 'return-expired', $this->actor))['replayed']);
        $this->fails(409, fn () => $this->owner(fn () => $this->managed->withdrawRestricted(array_replace($input, ['quantity' => '2']), 'return-too-many', $this->actor)));
        $this->assertEquals(7, DB::table('inv_stock_balances')->sum('quantity_on_hand'));
        $this->assertCount(1, DB::table('inv_restricted_withdrawals')->get());
    }

    public function test_cross_scope_and_reservation_mutators_cannot_bypass_owner(): void
    {
        $r = $this->receipt();
        $this->releaseLot($r['batch_id']);
        $reservation = $this->owner(fn () => $this->stock->reserve($this->warehouse, $this->item, '1', 'dispense:3', 'reserve-three', $this->actor, $r['batch_id']));
        $this->fails(403, fn () => $this->stock->releaseReservation($reservation['public_id'], 'raw-release', $this->actor));
        $this->fails(403, fn () => $this->stock->protectReservation($reservation['public_id'], true));
        $this->fails(403, fn () => $this->managed->withinOwner('pharmacy', 'other-store', $this->actor, 'test.action', fn () => $this->stock->commitReservation($reservation['public_id'], 'cross-scope', $this->actor)));
        $this->assertEquals(1, DB::table('inv_stock_balances')->sum('quantity_reserved'));
        $this->travel(1)->hours();
        $this->assertSame(0, $this->stock->expireReservations());
    }

    public function test_legacy_metadata_editors_cannot_release_managed_stock_or_remove_ownership(): void
    {
        InvWarehouse::observe(ManagedInventoryModelObserver::class);
        InvLocation::observe(ManagedInventoryModelObserver::class);
        InvBatch::observe(ManagedInventoryModelObserver::class);
        $receipt = $this->receipt();
        $location = InvLocation::query()->where('warehouse_id', $this->warehouse)->where('type', 'quarantine')->firstOrFail();
        $this->fails(403, fn () => $location->update(['type' => 'storage']));
        $this->fails(409, fn () => $this->owner(fn () => $location->update(['type' => 'storage'])));
        $warehouse = InvWarehouse::query()->findOrFail($this->warehouse);
        $this->fails(409, fn () => $warehouse->forceFill(['managed_owner_module' => null])->save());
        $lot = InvBatch::query()->findOrFail($receipt['batch_id']);
        $this->fails(403, fn () => $lot->update(['expires_at' => '2040-01-01']));
        $this->assertSame('0.000000', $this->stock->availability($this->warehouse, $this->item));
    }

    public function test_forward_schema_resume_preserves_stock_and_original_layer_identity(): void
    {
        $receipt = $this->receipt();
        $before = (array) DB::table('inv_cost_layers')->first();
        (require base_path('modules/Inventory/database/migrations/v2/2026_10_22_000001_add_managed_stock_contract.php'))->up();
        $this->assertSame($before, (array) DB::table('inv_cost_layers')->first());
        $this->assertEquals(10, DB::table('inv_stock_balances')->sum('quantity_on_hand'));
        $this->assertSame('pharmacy', DB::table('inv_warehouses')->where('id', $this->warehouse)->value('managed_owner_module'));
        $this->assertEquals($receipt['batch_id'], DB::table('inv_batches')->value('id'));
    }

    public function test_trusted_transfer_context_authorizes_both_owners_and_receipt_defaults_quarantine(): void
    {
        $receipt = $this->receipt();
        $this->releaseLot($receipt['batch_id']);
        $catalog = DB::table('inv_warehouses')->where('id', $this->warehouse)->value('catalog_id');
        $destination = $this->stock->createWarehouse(['code' => 'PH-TWO', 'name' => 'Other pharmacy', 'catalog_id' => $catalog, 'legal_entity_ref' => 'LEGAL-1'])['id'];
        $this->managed->withinOwner('pharmacy', 'store-two', $this->actor, 'test.action', fn () => $this->managed->tagWarehouse($destination, 'pharmacy', 'store-two', 'tag-other-owner', $this->actor));
        $input = ['source_warehouse_id' => $this->warehouse, 'destination_warehouse_id' => $destination, 'lines' => [['item_id' => $this->item, 'batch_id' => $receipt['batch_id'], 'quantity' => '2']]];
        $this->fails(403, fn () => $this->owner(fn () => $this->stock->dispatchTransfer($input, 'unauthorized-destination', $this->actor)));
        $transfer = $this->managed->withinOwner('pharmacy', 'store-one', $this->actor, 'test.action', fn () => $this->stock->dispatchTransfer($input, 'authorized-destination', $this->actor), ['authorized_scopes' => ['store-one', 'store-two']]);
        $checker = Admin::factory()->create(['must_change_password' => false])->id;
        $line = $transfer['lines'][0];
        $received = $this->managed->withinOwner('pharmacy', 'store-two', $checker, 'test.action', fn () => $this->stock->receiveTransfer($transfer['public_id'], ['expected_version' => 1, 'lines' => [['line_id' => $line['id'], 'quantity' => '2']]], 'receive-other-store', $checker));
        $this->assertSame('received', $received['status']);
        $this->assertSame('0.000000', $this->stock->availability($destination, $this->item));
        $this->assertEquals(2, DB::table('inv_stock_balances')->where('warehouse_id', $destination)->sum('quantity_on_hand'));
    }

    public function test_production_contract_cannot_take_over_a_managed_owner_warehouse(): void
    {
        $this->fails(409, fn () => app(InventoryProductionService::class)->enableWarehouse(['warehouse_id' => $this->warehouse], 'production-takeover', $this->actor));
        $this->assertFalse((bool) DB::table('inv_warehouses')->where('id', $this->warehouse)->value('production_managed'));
    }

    public function test_minimum_shelf_life_and_missing_required_expiry_are_rejected(): void
    {
        $receipt = $this->receipt();
        DB::table('inv_batches')->where('id', $receipt['batch_id'])->update(['expires_at' => now()->addDays(5)->toDateString()]);
        $this->fails(409, fn () => $this->owner(fn () => $this->managed->assertEligible($this->warehouse, $this->item, $receipt['batch_id'], $this->actor, 10)));
        DB::table('inv_batches')->where('id', $receipt['batch_id'])->update(['expires_at' => null]);
        $this->fails(409, fn () => $this->owner(fn () => $this->managed->assertEligible($this->warehouse, $this->item, $receipt['batch_id'], $this->actor)));
    }

    public function test_disposition_requires_unreserved_stock_and_commit_rechecks_location(): void
    {
        $r = $this->receipt();
        $this->releaseLot($r['batch_id']);
        $reservation = $this->owner(fn () => $this->stock->reserve($this->warehouse, $this->item, '1', 'dispense:4', 'reserve-four', $this->actor, $r['batch_id']));
        $this->fails(409, fn () => $this->owner(fn () => $this->managed->changeDisposition(['warehouse_id' => $this->warehouse, 'item_id' => $this->item, 'batch_id' => $r['batch_id'], 'disposition' => 'quarantine', 'reason' => 'Reinspection'], 'quarantine-reserved', $this->actor)));
        DB::table('inv_batches')->where('id', $r['batch_id'])->update(['is_active' => false]);
        $this->fails(409, fn () => $this->owner(fn () => $this->stock->commitReservation($reservation['public_id'], 'inactive-lot', $this->actor)));
        $this->assertEquals(10, DB::table('inv_stock_balances')->sum('quantity_on_hand'));
    }
}

class ManagedStockTestPolicy implements ManagedStockPolicy
{
    public static bool $allowed = true;

    public function authorize(int $actorId, object $warehouse, string $operation, array $context): void
    {
        abort_unless(self::$allowed && $context['action'] === 'test.action', 403);
    }
}
