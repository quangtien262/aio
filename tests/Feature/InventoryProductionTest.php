<?php

namespace Tests\Feature;

use App\Models\Admin;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Inventory\Services\InventoryProductionService;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Inventory\Services\InventoryValuationService;
use Modules\Merchandise\Services\MerchandiseService;
use Modules\Merchandise\Services\MerchandiseVariantService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InventoryProductionTest extends TestCase
{
    use RefreshDatabase;

    private InventoryProductionService $production;

    private InventoryV2Service $stock;

    private int $actor;

    private int $checker;

    private int $warehouse;

    private int $material;

    private int $finished;

    private int $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['merchandise' => '0.2.0', 'inventory' => '0.4.0'] as $key => $version) {
            DB::table('module_installations')->insert(['key' => $key, 'name' => $key, 'version' => $version, 'status' => 'enabled', 'website_types' => '[]', 'dependencies' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('merch_runtime_states')->where('module_key', 'merchandise')->update(['operational_state' => 'active', 'resume_state' => 'active']);
        DB::table('inv_runtime_states')->where('module_key', 'inventory')->update(['operational_state' => 'active', 'resume_state' => 'active']);
        $this->actor = Admin::factory()->create(['must_change_password' => false])->id;
        $this->checker = Admin::factory()->create(['must_change_password' => false])->id;
        $merch = app(MerchandiseService::class);
        $this->catalog = $merch->createCatalog(['code' => 'MFG', 'name' => 'Production'])['id'];
        $raw = $merch->saveSku($this->catalog, ['sku' => 'FABRIC', 'name' => 'Fabric', 'base_unit' => 'm', 'tracking_mode' => 'batch']);
        $fg = $merch->saveSku($this->catalog, ['sku' => 'SHIRT-M', 'name' => 'Shirt', 'base_unit' => 'pcs', 'tracking_mode' => 'batch']);
        $this->stock = app(InventoryV2Service::class);
        $this->production = app(InventoryProductionService::class);
        $this->material = $this->stock->ensureItem($raw['id']);
        $this->finished = $this->stock->ensureItem($fg['id']);
        $this->warehouse = $this->stock->createWarehouse(['code' => 'MFG', 'name' => 'Factory', 'catalog_id' => $this->catalog, 'legal_entity_ref' => 'company-one'])['id'];
        $this->production->enableWarehouse(['warehouse_id' => $this->warehouse], 'enable-production', $this->actor);
    }

    private function roll(): array
    {
        return $this->production->createRoll(['warehouse_id' => $this->warehouse, 'item_id' => $this->material, 'code' => 'ROLL-1', 'quantity' => '100', 'secondary_quantity' => '50', 'secondary_unit' => 'kg', 'unit_cost' => '50000'], 'receive-roll', $this->actor);
    }

    private function accepted(): array
    {
        $r = $this->roll();

        return $this->production->inspectRoll(['roll_id' => $r['public_id'], 'expected_version' => 1, 'disposition' => 'saleable', 'reason' => 'Incoming QC passed'], 'accept-roll', $this->checker);
    }

    public function test_quarantine_and_native_writer_barrier_keep_production_stock_safe(): void
    {
        $r = $this->roll();
        $this->assertSame('0.000000', $this->stock->availability($this->warehouse, $this->material));
        $this->assertSame('100.000000', $r['quantity_on_hand']);
        try {
            $this->stock->receive(['warehouse_id' => $this->warehouse, 'item_id' => $this->material, 'quantity' => '1', 'unit_cost' => '1', 'batch_code' => 'BYPASS'], 'direct-writer', $this->actor);
            $this->fail('Direct writer must fail closed');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $r = $this->production->inspectRoll(['roll_id' => $r['public_id'], 'expected_version' => 1, 'disposition' => 'saleable', 'reason' => 'Passed'], 'qc-pass', $this->checker);
        $this->assertSame(2, $r['version']);
        $this->assertSame('100.000000', $this->stock->availability($this->warehouse, $this->material));
    }

    public function test_partial_issue_return_and_two_output_completions_allocate_cost_once(): void
    {
        $r = $this->accepted();
        $hold = $this->production->reserve(['warehouse_id' => $this->warehouse, 'item_id' => $this->material, 'roll_id' => $r['public_id'], 'quantity' => '80', 'order_ref' => 'WO-1'], 'hold-order', $this->actor);
        $this->travel(30)->days();
        $this->assertSame(0, $this->stock->expireReservations());
        $issue = $this->production->issue(['reservation_id' => $hold['public_id'], 'quantity' => '80', 'expected_version' => 1], 'issue-order', $this->actor);
        $this->assertSame('4000000.000000', $issue['total_cost']);
        $this->assertSame('10.000000', (string) $this->production->roll($r['public_id'])['secondary_on_hand']);
        $return = $this->production->returnMaterial(['issue_id' => $issue['public_id'], 'quantity' => '5', 'expected_version' => 1], 'return-five', $this->actor);
        $this->assertSame('250000.000000', $return['total_cost']);
        $this->assertEquals(25, DB::table('inv_stock_balances')->where('warehouse_id', $this->warehouse)->sum('quantity_on_hand'));
        $input = ['warehouse_id' => $this->warehouse, 'order_ref' => 'WO-1', 'materials' => [['issue_id' => $issue['public_id'], 'quantity' => '25']], 'outputs' => [['item_id' => $this->finished, 'quantity' => '10']], 'conversion_cost' => '100000'];
        $first = $this->production->postOutput($input, 'output-first', $this->actor);
        $this->assertSame('1350000.000000', $first['total_cost']);
        $this->assertTrue($this->production->postOutput($input, 'output-first', $this->actor)['replayed']);
        $input['materials'][0]['quantity'] = '50';
        $input['outputs'][0]['quantity'] = '20';
        $second = $this->production->postOutput($input, 'output-second', $this->actor);
        $this->assertSame('2600000.000000', $second['total_cost']);
        $this->assertEquals(75, DB::table('inv_production_issues')->where('id', $issue['id'])->value('allocated_quantity'));
        $this->assertSame('0.000000', $this->stock->availability($this->warehouse, $this->finished));
        $this->expectException(HttpException::class);
        $this->production->postOutput($input, 'output-double-allocation', $this->actor);
    }

    public function test_stocktake_preserves_long_holds_and_rejects_counts_below_reserved(): void
    {
        $r = $this->accepted();
        $this->production->reserve(['warehouse_id' => $this->warehouse, 'item_id' => $this->material, 'quantity' => '80', 'order_ref' => 'WO-COUNT'], 'hold-count', $this->actor);
        $count = $this->production->stocktake(['warehouse_id' => $this->warehouse], 'begin-count', $this->actor);
        $counts = array_map(fn ($line) => ['balance_id' => $line->balance_id ?? $line['balance_id'], 'quantity' => (string) ($line->expected_quantity ?? $line['expected_quantity']), 'secondary_quantity' => (float) ($line->expected_quantity ?? $line['expected_quantity']) > 0 ? '50' : '0'], $count['lines']);
        foreach ($counts as &$line) {
            if ((float) $line['quantity'] > 0) {
                $line['quantity'] = '79';
            }
        }
        try {
            $this->production->stocktake(['warehouse_id' => $this->warehouse, 'stocktake_id' => $count['public_id'], 'counts' => $counts], 'count-below-hold', $this->checker);
            $this->fail('Count below reservation must conflict');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertEquals(80, DB::table('inv_stock_balances')->where('batch_id', $r['batch_id'])->sum('quantity_reserved'));
    }

    public function test_variants_reject_duplicate_combinations_and_rollback_stock_with_caller(): void
    {
        $variants = app(MerchandiseVariantService::class);
        $set = $variants->createSet($this->catalog, ['code' => 'STYLE-1', 'name' => 'Style', 'axes' => ['color' => ['red', 'blue'], 'size' => ['S', 'M']]]);
        $sku = $variants->saveVariant($set['id'], ['sku' => 'RED-S', 'name' => 'Red S', 'attributes' => ['size' => 'S', 'color' => 'red']]);
        $this->assertSame(['color' => 'red', 'size' => 'S'], $sku['attributes']);
        $extended = $variants->extendSet($set['id'], ['axes' => ['color' => ['red', 'blue', 'black'], 'size' => ['S', 'M', 'L']]], 1);
        $this->assertSame(2, $extended['version']);
        $this->assertSame(['color' => 'red', 'size' => 'S'], $extended['variants'][0]['attributes']);
        try {
            $variants->saveVariant($set['id'], ['sku' => 'RED-S-AGAIN', 'name' => 'Duplicate', 'attributes' => ['color' => 'red', 'size' => 'S']]);
            $this->fail('Duplicate variant must fail');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        DB::beginTransaction();
        $this->roll();
        DB::rollBack();
        $this->assertSame(0, DB::table('inv_trace_units')->count());
        $this->assertSame(0, DB::table('inv_stock_movements')->count());
    }

    public function test_forward_migration_can_resume_without_rewriting_existing_stock_or_holds(): void
    {
        $legacy = $this->stock->createWarehouse(['code' => 'EXISTING', 'name' => 'Existing', 'catalog_id' => $this->catalog, 'legal_entity_ref' => 'company-one']);
        $this->stock->receive(['warehouse_id' => $legacy['id'], 'item_id' => $this->material, 'quantity' => '10', 'unit_cost' => '12', 'batch_code' => 'LEGACY'], 'legacy-receipt', $this->actor);
        $hold = $this->stock->reserve($legacy['id'], $this->material, '2', 'legacy-sale', 'legacy-reservation', $this->actor);
        $before = DB::table('inv_stock_balances')->where('warehouse_id', $legacy['id'])->first();
        $layers = DB::table('inv_cost_layers')->where('warehouse_id', $legacy['id'])->get()->toArray();
        // Simulate a partial additive DDL application, then resume the same migration.
        Schema::table('inv_reservations', fn ($t) => $t->dropColumn('version'));
        $migration = require base_path('modules/Inventory/database/migrations/v2/2026_10_21_000001_add_inventory_production_contract.php');
        $migration->up();
        $migration->up();
        $after = DB::table('inv_stock_balances')->where('id', $before->id)->first();
        $this->assertEquals($before, $after);
        $this->assertEquals($layers, DB::table('inv_cost_layers')->where('warehouse_id', $legacy['id'])->get()->toArray());
        $this->assertSame($hold['public_id'], DB::table('inv_reservations')->where('id', $hold['id'])->value('public_id'));
        $this->assertEquals(1, DB::table('inv_reservations')->where('id', $hold['id'])->value('version'));
    }

    public function test_output_failure_rolls_back_partial_material_attribution_and_stock(): void
    {
        $r = $this->accepted();
        $hold = $this->production->reserve(['warehouse_id' => $this->warehouse, 'item_id' => $this->material, 'quantity' => '20', 'order_ref' => 'WO-FAIL'], 'failure-hold', $this->actor);
        $issue = $this->production->issue(['reservation_id' => $hold['public_id'], 'expected_version' => 1, 'quantity' => '20'], 'failure-issue', $this->actor);
        try {
            $this->production->postOutput(['warehouse_id' => $this->warehouse, 'order_ref' => 'WO-FAIL', 'materials' => [['issue_id' => $issue['public_id'], 'quantity' => '10']], 'outputs' => [['item_id' => $this->finished, 'quantity' => '0.0000001']]], 'failure-output', $this->actor);
            $this->fail('Quantity beyond six decimals must fail');
        } catch (HttpException $error) {
            $this->assertSame(422, $error->getStatusCode());
        }
        $this->assertEquals(0, DB::table('inv_production_issues')->where('id', $issue['id'])->value('allocated_quantity'));
        $this->assertSame(0, DB::table('inv_production_postings')->count());
        $this->assertSame(1, DB::table('inv_trace_units')->count());
    }

    public function test_split_and_two_step_roll_transfer_preserve_measured_units_and_identity(): void
    {
        $r = $this->accepted();
        $split = $this->production->splitRoll(['roll_id' => $r['public_id'], 'expected_version' => 2, 'quantity' => '30', 'secondary_quantity' => '14', 'code' => 'CHILD-ROLL'], 'split-measured-roll', $this->actor);
        $this->assertSame('70.000000', $split['parent']['quantity_on_hand']);
        $this->assertSame('36.000000', $split['parent']['secondary_on_hand']);
        $this->assertSame('14.000000', $split['child']['secondary_on_hand']);
        $target = $this->stock->createWarehouse(['code' => 'OTHER', 'name' => 'Other factory', 'catalog_id' => $this->catalog, 'legal_entity_ref' => 'company-one']);
        $this->production->enableWarehouse(['warehouse_id' => $target['id']], 'enable-other-factory', $this->actor);
        $dispatch = $this->production->dispatchRoll(['roll_id' => $split['child']['public_id'], 'expected_version' => 1, 'destination_warehouse_id' => $target['id']], 'dispatch-physical-roll', $this->actor);
        $this->assertSame('dispatched', $dispatch['status']);
        $this->assertSame('0.000000', $this->stock->availability($target['id'], $this->material));
        $received = $this->production->receiveRoll(['roll_id' => $split['child']['public_id'], 'expected_version' => 2], 'receive-physical-roll', $this->checker);
        $this->assertSame('received', $received['status']);
        $this->assertSame($split['child']['public_id'], $received['roll']['public_id']);
        $this->assertSame('30.000000', $received['roll']['quantity_on_hand']);
        $this->assertSame('quarantine', $received['roll']['disposition']);
        $this->assertSame('14.000000', $received['roll']['secondary_on_hand']);
        $this->assertEquals(5000000, DB::table('inv_cost_layers')->selectRaw('SUM(quantity_remaining * unit_cost) AS valuation')->first()->valuation);
    }

    public function test_supplier_returns_can_withdraw_expired_quarantine_without_making_it_available(): void
    {
        $roll = $this->production->createRoll(['warehouse_id' => $this->warehouse, 'item_id' => $this->material, 'code' => 'EXPIRED', 'quantity' => '10', 'unit_cost' => '3', 'expires_at' => now()->subDay()->toDateString(), 'source_type' => 'purchasing.receipt'], 'receive-expired-quarantine', $this->actor);
        $returned = $this->production->returnReceipt(['roll_id' => $roll['public_id'], 'expected_version' => 1, 'quantity' => '5'], 'return-expired-quarantine', $this->actor);
        $this->assertSame('15.000000', $returned['total_cost']);
        $this->assertSame('5.000000', $this->production->roll($roll['public_id'])['quantity_on_hand']);
        $this->assertSame('0.000000', $this->stock->availability($this->warehouse, $this->material));
    }

    private function finishedOutput(string $quantity = '100'): array
    {
        $this->accepted();
        $hold = $this->production->reserve(['warehouse_id' => $this->warehouse, 'item_id' => $this->material, 'quantity' => '100', 'order_ref' => 'WO-LATE-COST'], 'cost-material-hold', $this->actor);
        $issue = $this->production->issue(['reservation_id' => $hold['public_id'], 'quantity' => '100', 'expected_version' => 1], 'cost-material-issue', $this->actor);
        $posting = $this->production->postOutput(['warehouse_id' => $this->warehouse, 'order_ref' => 'WO-LATE-COST', 'materials' => [['issue_id' => $issue['public_id'], 'quantity' => '100']], 'outputs' => [['item_id' => $this->finished, 'quantity' => $quantity]]], 'cost-output', $this->actor);
        $this->production->inspectRoll(['roll_id' => $posting['outputs'][0]['roll_id'], 'expected_version' => 1, 'disposition' => 'saleable', 'reason' => 'Finished QC passed'], 'cost-finished-qc', $this->checker);

        return $posting;
    }

    public function test_late_conversion_cost_splits_remaining_and_realized_and_returns_current_value(): void
    {
        $posting = $this->finishedOutput();
        $rollId = $posting['outputs'][0]['roll_id'];
        $hold = $this->production->reserve(['warehouse_id' => $this->warehouse, 'item_id' => $this->finished, 'roll_id' => $rollId, 'quantity' => '30', 'order_ref' => 'shipment:one'], 'shipment-first-hold', $this->actor);
        $shipped = $this->production->issue(['reservation_id' => $hold['public_id'], 'quantity' => '30', 'expected_version' => 1], 'shipment-first-issue', $this->actor);
        $this->assertSame('1500000.000000', $shipped['total_cost']);
        $input = ['posting_id' => $posting['public_id'], 'expected_version' => 1, 'amount' => '1000000', 'reason' => 'Approved late conversion cost', 'source_id' => 'approved-cost-entry-1'];
        $adjustment = $this->production->adjustOutputCost($input, 'adjust-late-conversion', $this->actor);
        $this->assertSame('700000.000000', $adjustment['remaining_value']);
        $this->assertSame('300000.000000', $adjustment['realized_value']);
        $this->assertSame('4200000.000000', app(InventoryValuationService::class)->warehouse($this->warehouse)['valuation']);
        $this->assertTrue($this->production->adjustOutputCost($input, 'adjust-same-source-new-key', $this->actor)['source_replayed']);
        $this->assertSame(1, DB::table('inv_valuation_events')->where('source_type', 'late_conversion')->count());
        $hold = $this->production->reserve(['warehouse_id' => $this->warehouse, 'item_id' => $this->finished, 'quantity' => '10', 'order_ref' => 'shipment:two'], 'shipment-next-hold', $this->actor);
        $next = $this->production->issue(['reservation_id' => $hold['public_id'], 'quantity' => '10', 'expected_version' => 1], 'shipment-next-issue', $this->actor);
        $this->assertSame('600000.000000', $next['total_cost']);
        $returned = $this->production->returnMaterial(['issue_id' => $shipped['public_id'], 'quantity' => '5', 'expected_version' => 1], 'shipment-return-current-value', $this->actor);
        $this->assertSame('300000.000000', $returned['total_cost']);
        $fresh = app(InventoryValuationService::class)->event($adjustment['id']);
        $this->assertSame('650000.000000', $fresh['remaining_value']);
        $this->assertSame('350000.000000', $fresh['realized_value']);
        $this->assertSame('3900000.000000', app(InventoryValuationService::class)->warehouse($this->warehouse)['valuation']);
        $this->assertEquals(1500000, DB::table('inv_production_issues')->where('id', $shipped['id'])->value('total_cost'));
        $this->assertEquals(50000, DB::table('inv_cost_layers')->where('id', $shipped['layers'][0]['cost_layer_id'])->value('unit_cost'));
    }

    public function test_late_cost_follows_split_and_transfer_without_double_capitalizing(): void
    {
        $posting = $this->finishedOutput();
        $split = $this->production->splitRoll(['roll_id' => $posting['outputs'][0]['roll_id'], 'expected_version' => 2, 'quantity' => '20', 'code' => 'FINISHED-SPLIT'], 'finished-split', $this->actor);
        $event = $this->production->adjustOutputCost(['posting_id' => $posting['public_id'], 'amount' => '1000000', 'reason' => 'Late cost after split', 'source_id' => 'cost-after-split'], 'adjust-split-output', $this->actor);
        $this->assertSame('1000000.000000', $event['remaining_value']);
        $this->assertSame('0.000000', $event['realized_value']);
        $target = $this->stock->createWarehouse(['code' => 'VALUATION-TARGET', 'name' => 'Other factory', 'catalog_id' => $this->catalog, 'legal_entity_ref' => 'company-one']);
        $this->production->enableWarehouse(['warehouse_id' => $target['id']], 'enable-valuation-target', $this->actor);
        $this->production->dispatchRoll(['roll_id' => $split['child']['public_id'], 'expected_version' => 1, 'destination_warehouse_id' => $target['id']], 'transfer-adjusted-output', $this->actor);
        $this->production->receiveRoll(['roll_id' => $split['child']['public_id'], 'expected_version' => 2], 'receive-adjusted-output', $this->checker);
        $this->assertSame('4800000.000000', app(InventoryValuationService::class)->warehouse($this->warehouse)['valuation']);
        $this->assertSame('1200000.000000', app(InventoryValuationService::class)->warehouse($target['id'])['valuation']);
        $this->assertSame('1000000.000000', app(InventoryValuationService::class)->event($event['id'])['remaining_value']);
        $this->assertSame('0.000000', app(InventoryValuationService::class)->event($event['id'])['realized_value']);
    }

    public function test_output_rounding_residual_is_consumed_on_last_issue_without_losing_value(): void
    {
        $posting = $this->finishedOutput('3');
        $this->assertSame('5000000.000000', app(InventoryValuationService::class)->warehouse($this->warehouse)['valuation']);
        $total = BigDecimal::zero();
        foreach ([1, 2] as $index => $quantity) {
            $hold = $this->production->reserve(['warehouse_id' => $this->warehouse, 'item_id' => $this->finished, 'quantity' => (string) $quantity, 'order_ref' => 'shipment:rounding'], 'rounding-hold-'.$index, $this->actor);
            $issue = $this->production->issue(['reservation_id' => $hold['public_id'], 'quantity' => (string) $quantity, 'expected_version' => 1], 'rounding-issue-'.$index, $this->actor);
            $total = $total->plus($issue['total_cost']);
        }
        $this->assertSame('5000000.000000', (string) $total->toScale(6));
        $this->assertSame('0.000000', app(InventoryValuationService::class)->warehouse($this->warehouse)['valuation']);
    }

    public function test_fractional_fabric_hold_issue_and_release_keep_all_six_decimal_places(): void
    {
        $r = $this->production->createRoll(['warehouse_id' => $this->warehouse, 'item_id' => $this->material, 'code' => 'FRACTIONAL', 'quantity' => '100.125235', 'secondary_unit' => 'kg', 'secondary_quantity' => '50.678912', 'unit_cost' => '3.125235'], 'fractional-receipt', $this->actor);
        $this->production->inspectRoll(['roll_id' => $r['public_id'], 'expected_version' => 1, 'disposition' => 'saleable', 'reason' => 'Passed'], 'fractional-inspect', $this->checker);
        $hold = $this->production->reserve(['warehouse_id' => $this->warehouse, 'item_id' => $this->material, 'quantity' => '0.375125', 'order_ref' => 'WO-FRACTION'], 'fractional-hold', $this->actor);
        $issue = $this->production->issue(['reservation_id' => $hold['public_id'], 'quantity' => '0.125125', 'expected_version' => 1], 'fractional-issue', $this->actor);
        $expected = BigDecimal::of('0.125125')->multipliedBy('3.125235')->toScale(6, RoundingMode::HalfUp);
        $this->assertSame((string) $expected, $issue['total_cost']);
        $this->production->release(['reservation_id' => $hold['public_id'], 'quantity' => '0.25', 'expected_version' => 2], 'fractional-release', $this->actor);
        $this->assertSame('100.000110', $this->stock->availability($this->warehouse, $this->material));
        $this->assertEquals(0, DB::table('inv_stock_balances')->where('warehouse_id', $this->warehouse)->sum('quantity_reserved'));
    }

    public function test_negative_adjustment_cannot_make_one_sold_output_negative_even_when_posting_total_is_positive(): void
    {
        $this->accepted();
        $hold = $this->production->reserve(['warehouse_id' => $this->warehouse, 'item_id' => $this->material, 'quantity' => '100', 'order_ref' => 'WO-NEGATIVE'], 'negative-material-hold', $this->actor);
        $issue = $this->production->issue(['reservation_id' => $hold['public_id'], 'quantity' => '100', 'expected_version' => 1], 'negative-material-issue', $this->actor);
        $posting = $this->production->postOutput(['warehouse_id' => $this->warehouse, 'order_ref' => 'WO-NEGATIVE', 'issue_ids' => [$issue['public_id']],
            'outputs' => [['item_id' => $this->finished, 'quantity' => '1', 'code' => 'NEGATIVE-SMALL', 'cost_weight' => '1'],
                ['item_id' => $this->finished, 'quantity' => '99', 'code' => 'NEGATIVE-LARGE', 'cost_weight' => '99']]], 'negative-output', $this->actor);
        $small = $posting['outputs'][0]['roll_id'];
        $this->production->inspectRoll(['roll_id' => $small, 'expected_version' => 1, 'disposition' => 'saleable', 'reason' => 'Passed'], 'negative-inspect', $this->checker);
        $hold = $this->production->reserve(['warehouse_id' => $this->warehouse, 'item_id' => $this->finished, 'roll_id' => $small, 'quantity' => '1', 'order_ref' => 'shipment:small'], 'negative-shipment-hold', $this->actor);
        $this->production->issue(['reservation_id' => $hold['public_id'], 'quantity' => '1', 'expected_version' => 1], 'negative-shipment-issue', $this->actor);
        try {
            $this->production->adjustOutputCost(['posting_id' => $posting['public_id'], 'amount' => '-100000', 'reason' => 'Approved reduction with invalid allocation', 'source_id' => 'negative-cost-source',
                'output_weights' => [$small => '99', $posting['outputs'][1]['roll_id'] => '1']], 'negative-cost-adjust', $this->actor);
            $this->fail('A sold output cannot receive a negative full cost');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame(0, DB::table('inv_valuation_events')->where('source_type', 'late_conversion')->count());
        $this->assertSame('4950000.000000', app(InventoryValuationService::class)->warehouse($this->warehouse)['valuation']);
    }
}
