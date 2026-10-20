<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\InvStockDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Services\InventoryV2Service;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InventoryV2Test extends TestCase
{
    use RefreshDatabase;

    private InventoryV2Service $inventory;

    private int $actor;

    private int $checker;

    private int $warehouse;

    private int $item;

    private int $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventory = app(InventoryV2Service::class);
        foreach (['merchandise' => '0.1.0', 'inventory' => '0.3.0'] as $key => $version) {
            DB::table('module_installations')->insert(['key' => $key, 'name' => $key, 'version' => $version, 'status' => 'enabled', 'website_types' => '[]', 'dependencies' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('inv_runtime_states')->where('module_key', 'inventory')->update(['operational_state' => 'active', 'resume_state' => 'active']);
        $this->actor = Admin::factory()->create()->id;
        $this->checker = Admin::factory()->create()->id;
        $this->catalog = DB::table('merch_catalogs')->insertGetId(['public_id' => (string) Str::uuid(), 'code' => 'TEST', 'name' => 'Test', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $sku = DB::table('merch_skus')->insertGetId(['public_id' => (string) Str::uuid(), 'catalog_id' => $this->catalog, 'sku' => 'RICE', 'name' => 'Gạo', 'base_unit' => 'kg', 'quantity_scale' => 6, 'tracking_mode' => 'none', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->item = $this->inventory->ensureItem($sku);
        $this->warehouse = $this->inventory->createWarehouse(['code' => 'RETAIL', 'name' => 'Retail', 'catalog_id' => $this->catalog, 'legal_entity_ref' => 'legal-one'])['id'];
    }

    private function receipt(string $quantity = '10', string $cost = '24000', string $key = 'receipt-1'): array
    {
        return $this->inventory->receive(['warehouse_id' => $this->warehouse, 'item_id' => $this->item, 'quantity' => $quantity, 'unit_cost' => $cost], $key, $this->actor);
    }

    public function test_decimal_reservation_cost_commit_and_replay_are_consistent(): void
    {
        $this->receipt();
        $hold = $this->inventory->reserve($this->warehouse, $this->item, '0.375', 'sale-1', 'hold-1', $this->actor);
        $this->assertSame('9.625000', $this->inventory->availability($this->warehouse, $this->item));
        $commit = $this->inventory->commitReservation($hold['public_id'], 'commit-1', $this->actor);
        $again = $this->inventory->commitReservation($hold['public_id'], 'commit-1', $this->actor);
        $this->assertSame('9000.000000', $commit['total_cost']);
        $this->assertTrue($again['replayed']);
        $this->assertSame($commit['document_id'], $again['document_id']);
        $this->assertSame('9.625000', $this->inventory->availability($this->warehouse, $this->item));
        $this->assertSame(2, DB::table('inv_stock_movements')->count());
    }

    public function test_last_item_cannot_be_reserved_twice_and_conflicting_key_is_rejected(): void
    {
        $this->receipt('1');
        $this->inventory->reserve($this->warehouse, $this->item, '1', 'first', 'last', $this->actor);
        try {
            $this->inventory->reserve($this->warehouse, $this->item, '1', 'second', 'other', $this->actor);
            $this->fail('Expected insufficient stock');
        } catch (ConflictHttpException) {
            $this->assertSame('0.000000', $this->inventory->availability($this->warehouse, $this->item));
        }
        $this->expectException(HttpException::class);
        $this->inventory->reserve($this->warehouse, $this->item, '0.5', 'first', 'last', $this->actor);
    }

    public function test_caller_rollback_rolls_back_stock_and_idempotency_together(): void
    {
        DB::beginTransaction();
        $this->receipt();
        $this->assertSame('10.000000', $this->inventory->availability($this->warehouse, $this->item));
        DB::rollBack();
        $this->assertSame('0.000000', $this->inventory->availability($this->warehouse, $this->item));
        $this->assertSame(0, DB::table('inv_v2_operations')->count());
    }

    public function test_two_step_transfer_does_not_credit_destination_before_receipt(): void
    {
        $this->receipt();
        $target = $this->inventory->createWarehouse(['code' => 'RETAIL2', 'name' => 'Retail 2', 'catalog_id' => $this->catalog, 'legal_entity_ref' => 'legal-one'])['id'];
        $transfer = $this->inventory->dispatchTransfer(['source_warehouse_id' => $this->warehouse, 'destination_warehouse_id' => $target, 'lines' => [['item_id' => $this->item, 'quantity' => '10']]], 'transfer-1', $this->actor);
        $this->assertSame('0.000000', $this->inventory->availability($target, $this->item));
        $received = $this->inventory->receiveTransfer($transfer['public_id'], ['expected_version' => 1, 'lines' => [['line_id' => $transfer['lines'][0]['id'], 'quantity' => '8']]], 'receive-transfer-1', $this->checker);
        $this->assertSame('partially_received', $received['status']);
        $this->assertSame('8.000000', $this->inventory->availability($target, $this->item));
        $this->assertSame('0.000000', $this->inventory->availability($this->warehouse, $this->item));
    }

    public function test_stocktake_freezes_scope_and_posts_an_approved_adjustment(): void
    {
        $this->receipt();
        $take = $this->inventory->beginStocktake($this->warehouse, 'count-1', $this->actor);
        try {
            $this->inventory->reserve($this->warehouse, $this->item, '1', 'sale-1', 'blocked', $this->actor);
            $this->fail('Stocktake must freeze the warehouse');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $result = $this->inventory->postStocktake($take['public_id'], [['balance_id' => $take['lines'][0]['balance_id'], 'quantity' => '9.75']], 'post-count-1', $this->checker);
        $this->assertSame('posted', $result['status']);
        $this->assertSame('9.750000', $this->inventory->availability($this->warehouse, $this->item));
        $this->assertSame(2, DB::table('inv_stock_movements')->count());
    }

    public function test_escrow_preserves_central_availability_until_consumption_is_ingested(): void
    {
        $this->receipt();
        $escrow = $this->inventory->grantEscrow(['warehouse_id' => $this->warehouse, 'item_id' => $this->item, 'quantity' => '7', 'gateway_id' => 'GATEWAY', 'epoch' => 1], 'quota-1', $this->actor);
        $this->assertSame('3.000000', $this->inventory->availability($this->warehouse, $this->item));
        $this->inventory->consumeEscrow($escrow['public_id'], 1, 1, '2', 'consume-1', $this->actor);
        $this->assertSame('3.000000', $this->inventory->availability($this->warehouse, $this->item));
        $this->inventory->consumeEscrow($escrow['public_id'], 1, 1, '2', 'consume-1', $this->actor);
        $this->assertSame(2, DB::table('inv_stock_movements')->count());
        $this->assertSame(1, DB::table('inv_escrows')->value('last_sequence'));
    }

    public function test_legacy_document_writer_cannot_bypass_retail_reservations(): void
    {
        $this->expectException(ValidationException::class);
        InvStockDocument::query()->create(['code' => 'BYPASS', 'type' => 'receipt', 'destination_warehouse_id' => $this->warehouse]);
    }

    public function test_item_catalog_ownership_is_checked(): void
    {
        DB::table('inv_warehouses')->where('id', $this->warehouse)->update(['catalog_id' => $this->catalog + 100]);
        $this->expectException(HttpException::class);
        $this->receipt();
    }

    public function test_quarantined_returns_keep_physical_stock_but_are_not_sellable(): void
    {
        $this->receipt('2', '24000');
        $this->inventory->receive(['warehouse_id' => $this->warehouse, 'item_id' => $this->item, 'quantity' => '3', 'unit_cost' => '9000', 'disposition' => 'quarantine'], 'quarantine-1', $this->actor);
        $this->assertSame('2.000000', $this->inventory->availability($this->warehouse, $this->item));
        $this->assertEquals(5, DB::table('inv_stock_balances')->sum('quantity_on_hand'));
        $hold = $this->inventory->reserve($this->warehouse, $this->item, '1', 'sale', 'sell-good', $this->actor);
        $issued = $this->inventory->commitReservation($hold['public_id'], 'commit-good', $this->actor);
        $this->assertSame('24000.000000', $issued['total_cost']);
        $this->assertSame('1.000000', $this->inventory->availability($this->warehouse, $this->item));
    }

    public function test_pending_payment_holds_are_not_expired_by_the_cleanup_job(): void
    {
        $this->receipt('1');
        $hold = $this->inventory->reserve($this->warehouse, $this->item, '1', 'payment-1', 'hold-pending', $this->actor);
        $this->inventory->protectReservation($hold['public_id'], true);
        $this->travel(20)->minutes();
        $this->assertSame(0, $this->inventory->expireReservations());
        $this->assertSame('0.000000', $this->inventory->availability($this->warehouse, $this->item));
        $this->inventory->protectReservation($hold['public_id'], false);
        $this->assertSame(1, $this->inventory->expireReservations());
        $this->assertSame('1.000000', $this->inventory->availability($this->warehouse, $this->item));
    }

    public function test_expired_batch_is_not_available_and_transfer_preserves_batch_identity(): void
    {
        DB::table('inv_items')->where('id', $this->item)->update(['track_batch' => true]);
        $input = ['warehouse_id' => $this->warehouse, 'item_id' => $this->item, 'quantity' => '2', 'unit_cost' => '24000', 'batch_code' => 'LOT-1', 'expires_at' => now()->addDay()->toDateString()];
        $receipt = $this->inventory->receive($input, 'lot-receipt', $this->actor);
        $target = $this->inventory->createWarehouse(['code' => 'TARGET', 'name' => 'Target', 'catalog_id' => $this->catalog, 'legal_entity_ref' => 'legal-one'])['id'];
        $transfer = $this->inventory->dispatchTransfer(['source_warehouse_id' => $this->warehouse, 'destination_warehouse_id' => $target, 'lines' => [['item_id' => $this->item, 'quantity' => '1']]], 'lot-transfer', $this->actor);
        $this->assertSame($receipt['batch_id'], $transfer['lines'][0]['batch_id']);
        $this->inventory->receiveTransfer($transfer['public_id'], ['expected_version' => 1, 'lines' => [['line_id' => $transfer['lines'][0]['id'], 'quantity' => '1']]], 'lot-transfer-receive', $this->checker);
        $this->travel(2)->days();
        $this->assertSame('0.000000', $this->inventory->availability($this->warehouse, $this->item));
        $this->assertSame('0.000000', $this->inventory->availability($target, $this->item));
    }

    public function test_lot_tracking_without_expiry_is_allowed_only_when_sku_policy_allows_it(): void
    {
        DB::table('inv_items')->where('id', $this->item)->update(['track_batch' => true]);
        $input = ['warehouse_id' => $this->warehouse, 'item_id' => $this->item, 'quantity' => '1', 'unit_cost' => '1', 'batch_code' => 'LOT-NO-EXPIRY'];
        $receipt = $this->inventory->receive($input, 'no-expiry', $this->actor);
        $this->assertNotNull($receipt['batch_id']);
        DB::table('merch_skus')->where('id', DB::table('inv_items')->where('id', $this->item)->value('merchandise_sku_id'))->update(['requires_expiry' => true]);
        $this->expectException(HttpException::class);
        $input['batch_code'] = 'LOT-REQUIRES-EXPIRY';
        $this->inventory->receive($input, 'requires-expiry', $this->actor);
    }

    public function test_disabled_dependency_is_not_bypassed_by_a_stale_service_instance(): void
    {
        $this->receipt();
        DB::table('module_installations')->where('key', 'inventory')->update(['status' => 'disabled']);
        $this->expectException(HttpException::class);
        $this->inventory->reserve($this->warehouse, $this->item, '1', 'disabled', 'disabled', $this->actor);
    }

    public function test_expiry_uses_warehouse_local_day_instead_of_server_utc_day(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 16:00:00', 'UTC'));
        DB::table('inv_warehouses')->where('id', $this->warehouse)->update(['timezone' => 'Asia/Ho_Chi_Minh']);
        $this->inventory->receive(['warehouse_id' => $this->warehouse, 'item_id' => $this->item, 'quantity' => '1', 'unit_cost' => '1', 'batch_code' => 'DAY-BOUNDARY', 'expires_at' => '2026-10-20'], 'boundary-receipt', $this->actor);
        $this->assertSame('1.000000', $this->inventory->availability($this->warehouse, $this->item));
        $this->travel(2)->hours();
        $this->assertSame('2026-10-20', now('UTC')->toDateString());
        $this->assertSame('0.000000', $this->inventory->availability($this->warehouse, $this->item));
    }

    public function test_decimal_overflow_is_rejected_before_storage_or_huge_exponent_allocation(): void
    {
        $this->expectException(ValidationException::class);
        $this->receipt('1e100000000');
    }
}
