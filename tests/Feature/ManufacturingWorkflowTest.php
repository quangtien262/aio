<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Inventory\Services\InventoryProductionService;
use Modules\Manufacturing\Services\ManufacturingData as D;
use Modules\Purchasing\Services\PurchasingService;
use Tests\TestCase;

class ManufacturingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    private string $company;

    private string $factory;

    private string $workshop;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = Admin::factory()->create(['must_change_password' => false]);
        foreach (['merchandise', 'inventory', 'purchasing', 'manufacturing'] as $module) {
            app(ModuleManager::class)->install($module);
            app(ModuleManager::class)->enable($module);
        }
        $this->actingAs($this->owner->fresh(), 'admin')->withSession(['admin_auth_version' => $this->owner->fresh()->auth_version]);
        $this->factory = '';
        $this->company = $this->command('company.create', ['code' => 'GARMENT', 'name' => 'Garment Pilot'])['id'];
        $this->factory = $this->command('factory.create', ['company_id' => $this->company, 'code' => 'PLANT-A', 'name' => 'Plant A', 'timezone' => 'Asia/Ho_Chi_Minh'])['id'];
        $this->workshop = $this->command('workshop.create', ['code' => 'WORKSHOP-A', 'name' => 'Workshop A'])['id'];
        $this->command('sku.save', ['sku' => 'COTTON', 'name' => 'Cotton fabric', 'base_unit' => 'm', 'tracking_mode' => 'batch', 'quantity_scale' => 6]);
    }

    public function test_full_workflow_preserves_snapshots_reworks_without_duplicate_output_and_posts_inventory(): void
    {
        $style = $this->approvedStyle();
        $demand = $this->command('demand.save', ['code' => 'SO-1', 'style_id' => $style['id'], 'type' => 'order', 'customer_name' => 'Buyer Before Change', 'due_date' => '2026-11-20', 'lines' => $this->matrix()]);
        $order = $this->command('order.save', ['code' => 'WO-1', 'demand_id' => $demand['id'], 'style_id' => $style['id'], 'workshop_id' => $this->workshop, 'planned_start' => '2026-11-01', 'planned_end' => '2026-11-15', 'lines' => $this->matrix()]);
        $order = $this->command('order.release', ['expected_version' => $order['version']], $order['id']);
        $snapshot = DB::table('mfg_orders')->where('public_id', $order['id'])->value('released_snapshot');
        $roll = $this->acceptedRoll();
        $this->command('costing.record', ['order_id' => $order['id'], 'category' => 'labor', 'amount' => '10000', 'notes' => 'Approved labor allocation']);
        $this->command('costing.record', ['order_id' => $order['id'], 'category' => 'overhead', 'amount' => '5000', 'notes' => 'Approved overhead allocation']);
        $bundles = [];
        foreach ($this->matrix() as $index => $variant) {
            $payload = ['order_id' => $order['id'], 'workshop_id' => $this->workshop, 'code' => 'CUT-'.($index + 1), ...$variant,
                'materials' => [['material_id' => $roll['id'], 'consumed_quantity' => (string) ($variant['quantity'] * 2)]]];
            $key = 'workflow-cut-idempotent-'.($index + 1);
            $bundle = $this->command('cut.create', $payload, null, $key);
            $replay = $this->command('cut.create', $payload, null, $key);
            $this->assertSame($bundle['id'], $replay['id']);
            foreach (['SEW', 'PRESS'] as $operation) {
                $bundle = $this->command('operation.record', ['operation_code' => $operation, 'quantity' => $variant['quantity'], 'good_quantity' => $variant['quantity'], 'scrap_quantity' => 0, 'work_minutes' => 30, 'expected_version' => $bundle['version']], $bundle['id']);
            }
            $inspection = $this->command('quality.inspect', ['bundle_id' => $bundle['id'], 'quantity' => $variant['quantity'], 'good_quantity' => $variant['quantity'] - ($index === 0 ? 1 : 0), 'rework_quantity' => $index === 0 ? 1 : 0, 'scrap_quantity' => 0, 'defect_code' => $index === 0 ? 'SEAM' : null, 'expected_version' => $bundle['version']]);
            $this->assertNotEmpty($inspection['id']);
            $bundle = $this->resource('mfg_bundles', $bundle['id']);
            if ($index === 0) {
                $this->command('quality.rework', ['bundle_id' => $bundle['id'], 'quantity' => 1, 'good_quantity' => 1, 'scrap_quantity' => 0, 'expected_version' => $bundle['version']]);
                $bundle = $this->resource('mfg_bundles', $bundle['id']);
            }
            $this->assertEquals($variant['quantity'], $bundle['accepted_quantity']);
            $bundle = $this->command('bundle.finish', ['quantity' => $variant['quantity'], 'expected_version' => $bundle['version']], $bundle['id']);
            $bundles[] = ['bundle_id' => $bundle['id'], 'quantity' => $variant['quantity']];
        }
        $this->assertSame(2, DB::table('mfg_bundles')->count());
        $this->assertSame(2, DB::table('inv_production_issues')->where('order_ref', $order['id'])->count());
        $rollAfter = app(InventoryProductionService::class)->roll($roll['id']);
        $this->assertEquals(60, $rollAfter['quantity_on_hand']);
        $shipment = $this->command('shipment.pack', ['order_id' => $order['id'], 'code' => 'CARTON-1', 'items' => $bundles]);
        $dispatch = ['reference' => 'TRUCK-001', 'expected_version' => $shipment['version']];
        $shipped = $this->command('shipment.dispatch', $dispatch, $shipment['id'], 'workflow-shipment-once');
        $this->command('shipment.dispatch', $dispatch, $shipment['id'], 'workflow-shipment-once');
        $this->assertSame('shipped', $shipped['status']);
        $this->assertEquals(20, DB::table('mfg_order_lines')->sum('shipped_quantity'));
        $this->assertEquals(20, DB::table('mfg_demand_lines')->sum('fulfilled_quantity'));
        $this->assertSame('shipped', DB::table('mfg_orders')->where('public_id', $order['id'])->value('status'));
        $this->assertSame($snapshot, DB::table('mfg_orders')->where('public_id', $order['id'])->value('released_snapshot'));
        $this->assertStringContainsString('Buyer Before Change', DB::table('mfg_orders')->where('public_id', $order['id'])->value('buyer_snapshot'));
        $orderAfter = $this->resource('mfg_orders', $order['id']);
        $cost = $this->command('costing.close', ['expected_version' => $orderAfter['version']], $order['id']);
        $this->assertSame('final', $cost['status']);
        $this->assertEquals('55000.000000', $cost['total_cost']);
        $this->assertEquals('2750.000000', $cost['unit_cost']);
        $this->assertSame('closed', DB::table('mfg_orders')->where('public_id', $order['id'])->value('status'));
        $this->getJson('/admin/api/manufacturing/commands/workflow-shipment-once?factory_id='.$this->factory)->assertOk()->assertJsonPath('data.status', 'committed');
        $this->getJson('/admin/api/manufacturing/orders?factory_id='.$this->factory)->assertOk()->assertJsonPath('data.items.0.id', $order['id']);
    }

    public function test_release_requires_approved_pp_sample_and_old_revision_snapshot_is_immutable(): void
    {
        $style = $this->command('style.save', $this->styleInput());
        $this->postCommand('style.approve', ['expected_version' => $style['version']], $style['id'])->assertUnprocessable();
        $sample = $this->command('sample.save', ['style_id' => $style['id'], 'type' => 'pp']);
        $this->command('sample.approve', ['expected_version' => $sample['version']], $sample['id']);
        $style = $this->command('style.approve', ['expected_version' => $style['version']], $style['id']);
        $demand = $this->command('demand.save', ['code' => 'SO-REV', 'style_id' => $style['id'], 'type' => 'order', 'due_date' => '2026-11-20', 'lines' => $this->matrix()]);
        $order = $this->command('order.save', ['code' => 'WO-REV', 'demand_id' => $demand['id'], 'workshop_id' => $this->workshop, 'lines' => $this->matrix()]);
        $this->command('order.release', ['expected_version' => $order['version']], $order['id']);
        $before = DB::table('mfg_orders')->where('public_id', $order['id'])->value('released_snapshot');
        $changed = $this->styleInput();
        $changed['bom'][0]['quantity'] = '3';
        $changed['expected_version'] = $style['version'];
        $this->command('style.save', $changed, $style['id']);
        $this->assertSame($before, DB::table('mfg_orders')->where('public_id', $order['id'])->value('released_snapshot'));
        $this->assertSame(2, DB::table('mfg_style_revisions')->count());
    }

    public function test_stale_versions_and_changed_retry_are_rejected_without_new_revisions(): void
    {
        $style = $this->command('style.save', $this->styleInput(), null, 'workflow-style-once');
        $again = $this->command('style.save', $this->styleInput(), null, 'workflow-style-once');
        $this->assertSame($style['id'], $again['id']);
        $changed = $this->styleInput();
        $changed['name'] = 'Changed after uncertain result';
        $this->postCommand('style.save', $changed, null, 'workflow-style-once')->assertConflict();
        $changed['expected_version'] = 0;
        $this->postCommand('style.save', $changed, $style['id'])->assertConflict();
        $this->assertSame(1, DB::table('mfg_styles')->count());
        $this->assertSame(1, DB::table('mfg_style_revisions')->count());
        $other = $this->command('factory.create', ['company_id' => $this->company, 'code' => 'PLANT-B', 'name' => 'Plant B']);
        $this->assertNotSame($this->factory, $other['id']);
        $this->getJson('/admin/api/manufacturing/bootstrap?factory_id='.$other['id'])->assertOk()->assertJsonPath('data.current_factory_id', $other['id']);
    }

    public function test_real_purchase_order_uses_maker_checker_and_partial_roll_receipts_without_duplicates(): void
    {
        $order = $this->command('procurement.create', ['supplier_name' => 'Pilot fabric mill', 'lines' => [['material_code' => 'COTTON', 'quantity' => '100', 'unit_cost' => '1000']]]);
        $this->assertSame('draft', $order['status']);
        $this->postCommand('procurement.approve', ['expected_version' => $order['version']], $order['id'])->assertForbidden();
        $checker = Admin::factory()->create(['must_change_password' => false]);
        $role = Role::query()->create(['key' => 'mfg-workflow-checker', 'name' => 'Manufacturing checker', 'status' => 'active']);
        $role->permissions()->sync(Permission::query()->whereIn('module_key', ['manufacturing', 'purchasing', 'inventory', 'merchandise'])->pluck('id'));
        AdminRoleAssignment::query()->create(['admin_id' => $checker->id, 'role_id' => $role->id, 'scope_type' => 'global', 'scope_value' => null]);
        D::insert('mfg_memberships', ['company_id' => DB::table('mfg_companies')->where('public_id', $this->company)->value('id'),
            'factory_id' => DB::table('mfg_factories')->where('public_id', $this->factory)->value('id'), 'admin_id' => $checker->id, 'role' => 'manager', 'status' => 'active']);
        $this->actingAs($checker->fresh(), 'admin')->withSession(['admin_auth_version' => $checker->auth_version]);
        $approved = $this->command('procurement.approve', ['expected_version' => $order['version']], $order['id']);
        $this->actingAs($this->owner->fresh(), 'admin')->withSession(['admin_auth_version' => $this->owner->auth_version]);
        $line = $order['lines'][0]['id'];
        $publicLine = DB::table('purchase_order_lines')->where('id', $line)->value('public_id');
        $first = ['expected_version' => $approved['version'], 'lines' => [['order_line_id' => $publicLine, 'quantity' => '40', 'roll_code' => 'PO-ROLL-A', 'lot_code' => 'DYE-A', 'shade' => 'A', 'width' => '1.5']]];
        $received = $this->command('procurement.receive', $first, $order['id'], 'workflow-purchase-receipt-once');
        $this->command('procurement.receive', $first, $order['id'], 'workflow-purchase-receipt-once');
        $this->assertSame('partially_received', $received['status']);
        $this->assertSame(1, DB::table('inv_trace_units')->count());
        $this->assertEquals(40, DB::table('purchase_order_lines')->where('id', $line)->value('quantity_received'));
        $second = $this->command('procurement.receive', ['expected_version' => $received['version'], 'lines' => [['order_line_id' => $line, 'quantity' => '60', 'roll_code' => 'PO-ROLL-B', 'lot_code' => 'DYE-A', 'shade' => 'A', 'width' => '1.5']]], $order['id']);
        $this->assertSame('received', $second['status']);
        $this->assertSame('100000', $second['financials']['payable_minor']);
        $this->assertSame(2, DB::table('inv_trace_units')->count());
        $materials = $this->getJson('/admin/api/manufacturing/materials?factory_id='.$this->factory)->assertOk()->assertJsonPath('data.total', 2)->json('data');
        $receiptLine = collect($materials['purchase_orders'][0]['receipt_lines'])->firstWhere('roll_code', 'PO-ROLL-A');
        $this->assertTrue(Str::isUuid($receiptLine['id']));
        $returnPayload = ['receipt_line_id' => $receiptLine['id'], 'quantity' => 10, 'reason' => 'Rejected incoming fabric sent back to mill', 'expected_version' => $second['version']];
        $returned = $this->command('procurement.return', $returnPayload, $order['id'], 'workflow-native-vendor-return-once');
        $this->command('procurement.return', $returnPayload, $order['id'], 'workflow-native-vendor-return-once');
        $this->assertSame('90000', $returned['financials']['payable_minor']);
        $this->assertEquals(30, app(InventoryProductionService::class)->roll($receiptLine['roll_id'])['quantity_on_hand']);
    }

    public function test_forecast_netting_prevents_double_production_and_preserves_consumed_variants(): void
    {
        $style = $this->approvedStyle();
        $input = ['code' => 'FC-1', 'style_id' => $style['id'], 'type' => 'forecast', 'due_date' => '2026-11-20',
            'lines' => [['color' => 'Red', 'size' => 'S', 'quantity' => 100]]];
        $forecast = $this->command('demand.save', $input);
        $this->command('demand.save', ['code' => 'FIRM-1', 'style_id' => $style['id'], 'type' => 'order', 'due_date' => '2026-11-20',
            'forecast_id' => $forecast['id'], 'lines' => [['color' => 'Red', 'size' => 'S', 'quantity' => 60]]]);
        $order = ['code' => 'WO-FC', 'demand_id' => $forecast['id'], 'workshop_id' => $this->workshop,
            'lines' => [['color' => 'Red', 'size' => 'S', 'quantity' => 80]]];
        $this->postCommand('order.save', $order)->assertConflict();
        $input['lines'][0]['quantity'] = 59;
        $input['expected_version'] = $forecast['version'];
        $this->postCommand('demand.save', $input, $forecast['id'])->assertConflict();
        $order['lines'][0]['quantity'] = 40;
        $this->command('order.save', $order);
        $this->postCommand('demand.save', ['code' => 'FIRM-2', 'style_id' => $style['id'], 'type' => 'order', 'due_date' => '2026-11-20',
            'forecast_id' => $forecast['id'], 'lines' => [['color' => 'Red', 'size' => 'S', 'quantity' => 1]]])->assertUnprocessable();
        $this->assertSame(1, DB::table('mfg_orders')->count());
    }

    public function test_split_merge_qc_hold_and_cancelled_carton_preserve_stock_and_allow_repacking(): void
    {
        [$order, $bundle] = $this->cutSingleOrder(10);
        $child = $this->command('bundle.split', ['quantity' => 4, 'expected_version' => $bundle['version']], $bundle['id']);
        $bundle = $this->resource('mfg_bundles', $bundle['id']);
        $bundle = $this->command('bundle.merge', ['source_bundle_id' => $child['id'], 'source_expected_version' => $child['version'],
            'expected_version' => $bundle['version']], $bundle['id']);
        $this->assertEquals(10, $bundle['quantity']);
        $this->assertSame('merged', $this->resource('mfg_bundles', $child['id'])['status']);
        $this->assertEquals(20, DB::table('mfg_bundle_materials')->where('bundle_id', DB::table('mfg_bundles')->where('public_id', $bundle['id'])->value('id'))->sum('quantity'));
        foreach (['SEW', 'PRESS'] as $operation) {
            $bundle = $this->command('operation.record', ['operation_code' => $operation, 'quantity' => 10, 'expected_version' => $bundle['version']], $bundle['id']);
        }
        $this->command('quality.inspect', ['quantity' => 10, 'good_quantity' => 10, 'expected_version' => $bundle['version']], $bundle['id']);
        $bundle = $this->resource('mfg_bundles', $bundle['id']);
        $bundle = $this->command('bundle.finish', ['quantity' => 10, 'expected_version' => $bundle['version']], $bundle['id']);
        $shipment = $this->command('shipment.pack', ['code' => 'BOX-CANCEL', 'order_id' => $order['id'], 'items' => [['bundle_id' => $bundle['id'], 'quantity' => 10]]]);
        $bundle = $this->resource('mfg_bundles', $bundle['id']);
        $held = $this->command('quality.hold', ['reason' => 'Buyer requests final reinspection', 'expected_version' => $bundle['version']], $bundle['id']);
        $this->postCommand('shipment.dispatch', ['expected_version' => $shipment['version']], $shipment['id'])->assertConflict();
        $this->assertEquals(0, DB::table('mfg_order_lines')->sum('shipped_quantity'));
        $this->command('shipment.cancel', ['reason' => 'Replace carton after inspection', 'expected_version' => $shipment['version']], $shipment['id']);
        $held = $this->resource('mfg_bundles', $bundle['id']);
        $this->command('quality.release', ['reason' => 'Inspection accepted by quality manager', 'expected_version' => $held['version']], $bundle['id']);
        $shipment = $this->command('shipment.pack', ['code' => 'BOX-REPACK', 'order_id' => $order['id'], 'items' => [['bundle_id' => $bundle['id'], 'quantity' => 10]]]);
        $this->command('shipment.dispatch', ['expected_version' => $shipment['version']], $shipment['id']);
        $this->assertEquals(10, DB::table('mfg_order_lines')->sum('shipped_quantity'));
        $this->assertSame(1, DB::table('inv_production_postings')->count());
    }

    public function test_subcontract_fees_only_count_accepted_output_including_reworked_output_once(): void
    {
        [$order, $bundle] = $this->cutSingleOrder(10);
        $sub = $this->command('subcontract.dispatch', ['vendor_name' => 'External sewing workshop', 'process' => 'SEW', 'quantity' => 10,
            'unit_cost' => '100.000001', 'expected_version' => $bundle['version']], $bundle['id']);
        $this->command('subcontract.receive', ['quantity' => 10, 'good_quantity' => 7, 'rework_quantity' => 3, 'scrap_quantity' => 0,
            'expected_version' => $sub['version']], $sub['id']);
        $this->assertEquals('700.000007', DB::table('mfg_cost_entries')->sum('amount'));
        $bundle = $this->resource('mfg_bundles', $bundle['id']);
        $payload = ['quantity' => 3, 'good_quantity' => 2, 'scrap_quantity' => 1, 'expected_version' => $bundle['version']];
        $this->command('quality.rework', $payload, $bundle['id'], 'workflow-sub-rework-once');
        $this->command('quality.rework', $payload, $bundle['id'], 'workflow-sub-rework-once');
        $this->assertEquals('900.000009', DB::table('mfg_cost_entries')->sum('amount'));
        $this->assertEquals(1, DB::table('mfg_subcontracts')->value('scrap_quantity'));
        $this->assertEquals(9, DB::table('mfg_operations')->sum('accepted_quantity'));
        $bundle = $this->resource('mfg_bundles', $bundle['id']);
        $this->assertSame('PRESS', $bundle['operation_key']);
        $this->command('operation.record', ['quantity' => 9, 'good_quantity' => 9, 'expected_version' => $bundle['version']], $bundle['id']);
    }

    public function test_missing_bom_material_rolls_back_every_inventory_write(): void
    {
        $this->command('sku.save', ['sku' => 'THREAD', 'name' => 'Thread', 'base_unit' => 'm', 'tracking_mode' => 'batch']);
        $input = $this->styleInput();
        $input['bom'][] = ['material_code' => 'THREAD', 'uom' => 'm', 'quantity' => '1'];
        $style = $this->command('style.save', $input);
        $sample = $this->command('sample.save', ['style_id' => $style['id'], 'type' => 'pp']);
        $this->command('sample.approve', ['expected_version' => $sample['version']], $sample['id']);
        $style = $this->command('style.approve', ['expected_version' => $style['version']], $style['id']);
        $matrix = [['color' => 'Red', 'size' => 'S', 'quantity' => 10]];
        $demand = $this->command('demand.save', ['code' => 'SO-MISSING', 'style_id' => $style['id'], 'type' => 'order', 'due_date' => '2026-11-20', 'lines' => $matrix]);
        $order = $this->command('order.save', ['code' => 'WO-MISSING', 'demand_id' => $demand['id'], 'workshop_id' => $this->workshop, 'lines' => $matrix]);
        $this->command('order.release', ['expected_version' => $order['version']], $order['id']);
        $roll = $this->acceptedRoll();
        $this->postCommand('cut.create', ['order_id' => $order['id'], 'workshop_id' => $this->workshop, 'code' => 'CUT-MISSING',
            'color' => 'Red', 'size' => 'S', 'quantity' => 10, 'materials' => [['material_id' => $roll['id'], 'consumed_quantity' => 20]]])->assertUnprocessable();
        $this->assertSame(0, DB::table('inv_production_issues')->count());
        $this->assertSame(0, DB::table('mfg_bundles')->count());
        $this->assertEquals(100, app(InventoryProductionService::class)->roll($roll['id'])['quantity_on_hand']);
    }

    public function test_cross_factory_custody_can_finish_and_ship_against_original_order_without_duplicate_wip(): void
    {
        [$order, $bundle] = $this->cutSingleOrder(10);
        $sourceFactory = $this->factory;
        $target = $this->command('factory.create', ['company_id' => $this->company, 'code' => 'PLANT-B', 'name' => 'Finishing plant']);
        $this->factory = $target['id'];
        $workshop = $this->command('workshop.create', ['code' => 'FINISH', 'name' => 'Finishing']);
        $this->factory = $sourceFactory;
        $handover = $this->command('bundle.handover', ['to_workshop_id' => $workshop['id'], 'quantity' => 10, 'expected_version' => $bundle['version']], $bundle['id']);
        $this->postCommand('operation.record', ['quantity' => 10, 'expected_version' => $bundle['version'] + 1], $bundle['id'])->assertConflict();
        $this->factory = $target['id'];
        $this->command('handover.receive', ['quantity' => 10, 'expected_version' => $handover['version']], $handover['id']);
        $this->getJson('/admin/api/manufacturing/orders?factory_id='.$this->factory)->assertOk()->assertJsonPath('data.items.0.id', $order['id']);
        $bundle = $this->resource('mfg_bundles', $bundle['id']);
        foreach (['SEW', 'PRESS'] as $operation) {
            $bundle = $this->command('operation.record', ['operation_code' => $operation, 'quantity' => 10, 'expected_version' => $bundle['version']], $bundle['id']);
        }
        $this->command('quality.inspect', ['quantity' => 10, 'good_quantity' => 10, 'expected_version' => $bundle['version']], $bundle['id']);
        $bundle = $this->resource('mfg_bundles', $bundle['id']);
        $this->command('bundle.finish', ['quantity' => 10, 'expected_version' => $bundle['version']], $bundle['id']);
        $shipment = $this->command('shipment.pack', ['code' => 'BOX-PLANT-B', 'order_id' => $order['id'], 'items' => [['bundle_id' => $bundle['id'], 'quantity' => 10]]]);
        $this->command('shipment.dispatch', ['expected_version' => $shipment['version']], $shipment['id']);
        $this->assertEquals(10, DB::table('mfg_order_lines')->sum('shipped_quantity'));
        $this->factory = $sourceFactory;
        $order = $this->resource('mfg_orders', $order['id']);
        $cost = $this->command('costing.close', ['expected_version' => $order['version']], $order['id']);
        $this->assertSame('final', $cost['status']);
        $this->assertEquals('20000.000000', $cost['total_cost']);
    }

    public function test_fulfillment_operator_cannot_read_costs_in_the_mutation_result(): void
    {
        [$order, $bundle] = $this->cutSingleOrder(10);
        foreach (['SEW', 'PRESS'] as $operation) {
            $bundle = $this->command('operation.record', ['operation_code' => $operation, 'quantity' => 10, 'expected_version' => $bundle['version']], $bundle['id']);
        }
        $this->command('quality.inspect', ['quantity' => 10, 'good_quantity' => 10, 'expected_version' => $bundle['version']], $bundle['id']);
        $bundle = $this->resource('mfg_bundles', $bundle['id']);
        $operator = Admin::factory()->create(['must_change_password' => false]);
        $role = Role::where('key', 'manufacturing.operator')->firstOrFail();
        AdminRoleAssignment::create(['admin_id' => $operator->id, 'role_id' => $role->id, 'scope_type' => 'global', 'scope_value' => null]);
        D::insert('mfg_memberships', ['company_id' => DB::table('mfg_companies')->where('public_id', $this->company)->value('id'),
            'factory_id' => DB::table('mfg_factories')->where('public_id', $this->factory)->value('id'), 'admin_id' => $operator->id, 'role' => 'operator', 'status' => 'active']);
        $this->actingAs($operator->fresh(), 'admin')->withSession(['admin_auth_version' => $operator->auth_version]);
        $response = $this->postCommand('bundle.finish', ['quantity' => 10, 'expected_version' => $bundle['version']], $bundle['id'])->assertSuccessful();
        $this->assertArrayNotHasKey('cost', $response->json('data.metadata.finished_lots.0'));
        $this->assertEquals('20000.000000', DB::table('inv_production_postings')->value('material_cost'));
    }

    public function test_late_cost_adjustment_after_partial_shipment_keeps_posted_history_and_closes_exactly(): void
    {
        [$order, $bundle] = $this->cutSingleOrder(10);
        foreach (['SEW', 'PRESS'] as $operation) {
            $bundle = $this->command('operation.record', ['operation_code' => $operation, 'quantity' => 10, 'expected_version' => $bundle['version']], $bundle['id']);
        }
        $this->command('quality.inspect', ['quantity' => 10, 'good_quantity' => 10, 'expected_version' => $bundle['version']], $bundle['id']);
        $bundle = $this->resource('mfg_bundles', $bundle['id']);
        $this->command('bundle.finish', ['quantity' => 10, 'expected_version' => $bundle['version']], $bundle['id']);
        $postingBefore = (array) DB::table('inv_production_postings')->first();
        $first = $this->command('shipment.pack', ['code' => 'BOX-LATE-A', 'order_id' => $order['id'], 'items' => [['bundle_id' => $bundle['id'], 'quantity' => 4]]]);
        $this->command('shipment.dispatch', ['expected_version' => $first['version']], $first['id']);
        $originalShipmentCost = DB::table('inv_production_issues')->where('order_ref', 'shipment:'.$first['id'])->value('total_cost');
        $this->command('costing.record', ['order_id' => $order['id'], 'category' => 'labor', 'amount' => 1000, 'notes' => 'Approved labor received after partial shipment']);
        $order = $this->resource('mfg_orders', $order['id']);
        $provisional = $this->command('costing.close', ['expected_version' => $order['version']], $order['id']);
        $this->assertSame('needs_adjustment', $provisional['status']);
        $payload = ['expected_version' => $order['version'], 'reason' => 'Allocate approved labor to finished output'];
        $this->command('costing.adjust', $payload, $order['id'], 'workflow-cost-adjust-once');
        $this->command('costing.adjust', $payload, $order['id'], 'workflow-cost-adjust-once');
        $this->assertSame(1, DB::table('inv_valuation_events')->where('source_type', 'late_conversion')->count());
        $this->assertEquals('1000.000000', DB::table('inv_valuation_events')->where('source_type', 'late_conversion')->value('amount'));
        $this->assertEquals($postingBefore['material_cost'], DB::table('inv_production_postings')->value('material_cost'));
        $this->assertEquals($originalShipmentCost, DB::table('inv_production_issues')->where('order_ref', 'shipment:'.$first['id'])->value('total_cost'));
        $order = $this->resource('mfg_orders', $order['id']);
        $final = $this->command('costing.close', ['expected_version' => $order['version']], $order['id']);
        $this->assertSame('final', $final['status']);
        $this->assertEquals('21000.000000', $final['total_cost']);
        $this->assertEquals('2100.000000', $final['unit_cost']);
        $last = $this->command('shipment.pack', ['code' => 'BOX-LATE-B', 'order_id' => $order['id'], 'items' => [['bundle_id' => $bundle['id'], 'quantity' => 6]]]);
        $this->command('shipment.dispatch', ['expected_version' => $last['version']], $last['id']);
        $this->assertEquals('12600.000000', DB::table('inv_production_issues')->where('order_ref', 'shipment:'.$last['id'])->value('total_cost'));
        $this->assertSame('closed', DB::table('mfg_orders')->where('public_id', $order['id'])->value('status'));
    }

    public function test_cut_consumes_preissued_material_once_and_returns_only_unbound_remainder(): void
    {
        $style = $this->approvedStyle();
        $matrix = [['color' => 'Red', 'size' => 'S', 'quantity' => 10]];
        $demand = $this->command('demand.save', ['code' => 'SO-ISSUED', 'style_id' => $style['id'], 'type' => 'order', 'due_date' => '2026-11-20', 'lines' => $matrix]);
        $order = $this->command('order.save', ['code' => 'WO-ISSUED', 'demand_id' => $demand['id'], 'workshop_id' => $this->workshop, 'lines' => $matrix]);
        $order = $this->command('order.release', ['expected_version' => $order['version']], $order['id']);
        $roll = $this->acceptedRoll();
        $hold = $this->command('material.reserve', ['order_id' => $order['id'], 'quantity' => 25], $roll['id']);
        $issue = $this->command('material.issue', ['quantity' => 25, 'expected_version' => $hold['version']], $hold['id']);
        $this->command('cut.create', ['order_id' => $order['id'], 'workshop_id' => $this->workshop, 'code' => 'CUT-ISSUED', 'color' => 'Red', 'size' => 'S', 'quantity' => 10,
            'materials' => [['issue_id' => $issue['id'], 'consumed_quantity' => 20]]]);
        $this->assertSame(1, DB::table('inv_production_issues')->count());
        $this->assertEquals(25, DB::table('mfg_material_requirements')->value('issued_quantity'));
        $this->assertEquals(20, DB::table('mfg_material_requirements')->value('consumed_quantity'));
        $this->postCommand('material.return', ['quantity' => 6, 'expected_version' => $issue['version']], $issue['id'])->assertConflict();
        $this->command('material.return', ['quantity' => 5, 'expected_version' => $issue['version']], $issue['id']);
        $this->assertEquals(5, DB::table('inv_production_returns')->value('quantity'));
        $this->assertEquals(75, app(InventoryProductionService::class)->roll($roll['id'])['quantity_on_hand']);
        $this->assertEquals(5, DB::table('inv_trace_units')->where('source_type', 'production.material_return')->value('quantity_received'));
    }

    public function test_inline_rework_unblocks_operation_without_counting_finished_output(): void
    {
        [$order, $bundle] = $this->cutSingleOrder(10);
        $this->command('quality.inspect', ['checkpoint' => 'inline', 'quantity' => 3, 'good_quantity' => 2,
            'rework_quantity' => 1, 'expected_version' => $bundle['version']], $bundle['id']);
        $bundle = $this->resource('mfg_bundles', $bundle['id']);
        $this->assertSame('quality_hold', $bundle['status']);
        $this->assertEquals(0, $bundle['accepted_quantity']);
        $this->postCommand('operation.record', ['quantity' => 10, 'expected_version' => $bundle['version']], $bundle['id'])->assertConflict();
        $bundle = $this->command('quality.rework', ['quantity' => 1, 'good_quantity' => 1, 'expected_version' => $bundle['version']], $bundle['id']);
        $this->assertEquals(0, $bundle['accepted_quantity']);
        $this->assertSame('active', $bundle['status']);
        $bundle = $this->command('operation.record', ['quantity' => 10, 'expected_version' => $bundle['version']], $bundle['id']);
        $this->assertSame('PRESS', $bundle['operation_key']);
        $this->assertSame(0, DB::table('inv_production_postings')->count());
    }

    public function test_operation_scrap_split_preserves_input_cost_and_can_move_remaining_good_quantity(): void
    {
        [$order, $bundle] = $this->cutSingleOrder(10);
        $bundle = $this->command('operation.record', ['quantity' => 10, 'good_quantity' => 9, 'scrap_quantity' => 1,
            'expected_version' => $bundle['version']], $bundle['id']);
        $source = $this->factory;
        $other = $this->command('factory.create', ['company_id' => $this->company, 'code' => 'SCRAP-TARGET', 'name' => 'Second workshop']);
        $this->factory = $other['id'];
        $targetWorkshop = $this->command('workshop.create', ['code' => 'PRESS', 'name' => 'Press remaining good garments']);
        $this->factory = $source;
        $this->postCommand('bundle.handover', ['to_workshop_id' => $targetWorkshop['id'], 'quantity' => 10,
            'expected_version' => $bundle['version']], $bundle['id'])->assertUnprocessable();
        $handover = $this->command('bundle.handover', ['to_workshop_id' => $targetWorkshop['id'], 'quantity' => 9,
            'expected_version' => $bundle['version']], $bundle['id']);
        $original = $this->resource('mfg_bundles', $bundle['id']);
        $this->assertSame('scrapped', $original['status']);
        $this->assertEquals(0, $original['wip_quantity']);
        $this->assertEquals(1, $original['quantity']);
        $this->factory = $other['id'];
        $this->command('handover.receive', ['quantity' => 9, 'expected_version' => $handover['version']], $handover['id']);
        $child = D::resource(DB::table('mfg_bundles')->where('parent_id', DB::table('mfg_bundles')->where('public_id', $bundle['id'])->value('id'))->first());
        $this->assertEquals(9, $child['quantity']);
        $this->assertEquals(20, $child['metadata']['materials'][0]['quantity']);
        $child = $this->command('operation.record', ['quantity' => 9, 'expected_version' => $child['version']], $child['id']);
        $this->command('quality.inspect', ['quantity' => 9, 'good_quantity' => 9, 'expected_version' => $child['version']], $child['id']);
        $child = $this->resource('mfg_bundles', $child['id']);
        $this->command('bundle.finish', ['quantity' => 9, 'expected_version' => $child['version']], $child['id']);
        $this->assertEquals('20000.000000', DB::table('inv_production_postings')->value('material_cost'));
    }

    public function test_fractional_fabric_and_conversion_cost_keep_all_six_decimals(): void
    {
        $input = $this->styleInput();
        $input['bom'][0]['quantity'] = '0.125125';
        $style = $this->command('style.save', $input);
        $sample = $this->command('sample.save', ['style_id' => $style['id'], 'type' => 'pp']);
        $this->command('sample.approve', ['expected_version' => $sample['version']], $sample['id']);
        $style = $this->command('style.approve', ['expected_version' => $style['version']], $style['id']);
        $matrix = [['color' => 'Red', 'size' => 'S', 'quantity' => 1]];
        $demand = $this->command('demand.save', ['code' => 'SO-FRACTION', 'style_id' => $style['id'], 'type' => 'order', 'due_date' => '2026-11-20', 'lines' => $matrix]);
        $order = $this->command('order.save', ['code' => 'WO-FRACTION', 'demand_id' => $demand['id'], 'workshop_id' => $this->workshop, 'lines' => $matrix]);
        $order = $this->command('order.release', ['expected_version' => $order['version']], $order['id']);
        $roll = $this->command('material.receive', ['code' => 'COTTON', 'roll_code' => 'ROLL-FRACTION', 'quantity' => '1.125125', 'unit_cost' => '1.234567', 'reason' => 'Fractional fabric opening verified']);
        $roll = $this->command('material.inspect', ['status' => 'accepted', 'expected_version' => $roll['version']], $roll['id']);
        $this->command('costing.record', ['order_id' => $order['id'], 'category' => 'labor', 'amount' => '0.000007', 'notes' => 'Approved fractional conversion']);
        $bundle = $this->command('cut.create', ['order_id' => $order['id'], 'workshop_id' => $this->workshop, 'code' => 'CUT-FRACTION', 'color' => 'Red', 'size' => 'S', 'quantity' => 1,
            'materials' => [['material_id' => $roll['id'], 'consumed_quantity' => '0.125125']]]);
        foreach (['SEW', 'PRESS'] as $operation) {
            $bundle = $this->command('operation.record', ['operation_code' => $operation, 'quantity' => 1, 'expected_version' => $bundle['version']], $bundle['id']);
        }
        $this->command('quality.inspect', ['quantity' => 1, 'good_quantity' => 1, 'expected_version' => $bundle['version']], $bundle['id']);
        $bundle = $this->resource('mfg_bundles', $bundle['id']);
        $this->command('bundle.finish', ['quantity' => 1, 'expected_version' => $bundle['version']], $bundle['id']);
        $order = $this->resource('mfg_orders', $order['id']);
        $cost = $this->command('costing.close', ['expected_version' => $order['version']], $order['id']);
        $this->assertSame('final', $cost['status']);
        $this->assertSame('0.154475', $cost['material_cost']);
        $this->assertSame('0.000007', $cost['labor_cost']);
        $this->assertSame('0.154482', $cost['total_cost']);
    }

    public function test_partial_shipment_does_not_block_remaining_production_and_partial_output_cost_adjustment(): void
    {
        [$order, $bundle] = $this->cutSingleOrder(10, 5);
        $originalOrderId = $order['id'];
        for ($batch = 0; $batch < 2; $batch++) {
            if ($batch === 1) {
                $roll = app(InventoryProductionService::class)->roll(DB::table('inv_trace_units')->where('code', 'ROLL-1')->value('public_id'));
                $this->command('material.reserve', ['order_id' => $order['id'], 'quantity' => 10], $roll['public_id']);
                $hold = D::resource(DB::table('inv_reservations')->where('order_ref', $order['id'])->where('status', 'active')->first());
                $bundle = $this->command('cut.create', ['order_id' => $order['id'], 'workshop_id' => $this->workshop, 'code' => 'CUT-SECOND-BATCH', 'color' => 'Red', 'size' => 'S', 'quantity' => 5,
                    'materials' => [['reservation_id' => $hold['id'], 'consumed_quantity' => 10]]]);
                $this->assertSame('partially_shipped', DB::table('mfg_orders')->where('public_id', $order['id'])->value('status'));
            }
            foreach (['SEW', 'PRESS'] as $operation) {
                $bundle = $this->command('operation.record', ['operation_code' => $operation, 'quantity' => 5, 'expected_version' => $bundle['version']], $bundle['id']);
            }
            $this->command('quality.inspect', ['quantity' => 5, 'good_quantity' => 5, 'expected_version' => $bundle['version']], $bundle['id']);
            $bundle = $this->resource('mfg_bundles', $bundle['id']);
            $this->command('bundle.finish', ['quantity' => 5, 'expected_version' => $bundle['version']], $bundle['id']);
            $shipment = $this->command('shipment.pack', ['code' => 'BOX-BATCH-'.$batch, 'order_id' => $order['id'], 'items' => [['bundle_id' => $bundle['id'], 'quantity' => 5]]]);
            $this->command('shipment.dispatch', ['expected_version' => $shipment['version']], $shipment['id']);
            if ($batch === 0) {
                $this->command('costing.record', ['order_id' => $order['id'], 'category' => 'labor', 'amount' => 1000, 'notes' => 'Approved conversion for both batches']);
                $order = $this->resource('mfg_orders', $order['id']);
                $adjustment = $this->command('costing.adjust', ['expected_version' => $order['version'], 'reason' => 'Allocate conversion to first completed batch'], $order['id']);
                $this->assertEquals('500.000000', $adjustment['total_cost']);
            }
        }
        $order = $this->resource('mfg_orders', $originalOrderId);
        $cost = $this->command('costing.close', ['expected_version' => $order['version']], $order['id']);
        $this->assertSame('final', $cost['status']);
        $this->assertEquals('21000.000000', $cost['total_cost']);
        $this->assertEquals(10, DB::table('mfg_order_lines')->sum('shipped_quantity'));
    }

    public function test_purchase_receiver_without_cost_permission_never_receives_minor_financial_totals(): void
    {
        $order = $this->command('procurement.create', ['supplier_name' => 'Fabric mill', 'lines' => [['material_code' => 'COTTON', 'quantity' => '100', 'unit_cost' => '1000']]]);
        $checker = Admin::factory()->create(['must_change_password' => false]);
        $approveRole = Role::create(['key' => 'mfg-approve-only', 'name' => 'PO approver', 'status' => 'active']);
        $approveRole->permissions()->sync(Permission::where('key', 'purchasing.order.approve')->pluck('id'));
        AdminRoleAssignment::create(['admin_id' => $checker->id, 'role_id' => $approveRole->id, 'scope_type' => 'global', 'scope_value' => null]);
        $approved = app(PurchasingService::class)->approveOrder($order['id'], 1, 'workflow-approve-private-receipt', $checker->id);
        $receiver = Admin::factory()->create(['must_change_password' => false]);
        $role = Role::create(['key' => 'mfg-receiver-only', 'name' => 'Fabric receiver', 'status' => 'active']);
        $role->permissions()->sync(Permission::whereIn('key', ['manufacturing.view', 'manufacturing.plan', 'purchasing.receipt.manage', 'inventory.receipt.manage'])->pluck('id'));
        AdminRoleAssignment::create(['admin_id' => $receiver->id, 'role_id' => $role->id, 'scope_type' => 'global', 'scope_value' => null]);
        D::insert('mfg_memberships', ['company_id' => DB::table('mfg_companies')->where('public_id', $this->company)->value('id'),
            'factory_id' => DB::table('mfg_factories')->where('public_id', $this->factory)->value('id'), 'admin_id' => $receiver->id,
            'role' => 'reader', 'permissions' => D::encode(['manufacturing.view', 'manufacturing.plan']), 'status' => 'active']);
        $this->actingAs($receiver->fresh(), 'admin')->withSession(['admin_auth_version' => $receiver->auth_version]);
        $result = $this->command('procurement.receive', ['expected_version' => $approved['version'], 'lines' => [['order_line_id' => $order['lines'][0]['id'], 'quantity' => '40', 'roll_code' => 'RECEIPT-PRIVATE']]], $order['id']);
        $this->assertArrayNotHasKey('financials', $result);
        $this->assertArrayNotHasKey('amount_minor', $result['receipts'][0]['lines'][0] ?? []);
        $this->assertArrayNotHasKey('unit_cost', $result['received_rolls'][0]['roll'] ?? []);
    }

    public function test_same_external_command_key_is_independent_between_actors_at_shared_providers(): void
    {
        $input = ['code' => 'COTTON', 'roll_code' => 'ACTOR-A', 'quantity' => 10, 'unit_cost' => 1, 'reason' => 'Signed independent opening stock'];
        $this->command('material.receive', $input, null, 'workflow-actor-separated-key');
        $manager = Admin::factory()->create(['must_change_password' => false]);
        AdminRoleAssignment::create(['admin_id' => $manager->id, 'role_id' => Role::where('key', 'manufacturing.manager')->value('id'), 'scope_type' => 'global', 'scope_value' => null]);
        D::insert('mfg_memberships', ['company_id' => DB::table('mfg_companies')->where('public_id', $this->company)->value('id'),
            'factory_id' => DB::table('mfg_factories')->where('public_id', $this->factory)->value('id'), 'admin_id' => $manager->id, 'role' => 'manager', 'status' => 'active']);
        $this->actingAs($manager->fresh(), 'admin')->withSession(['admin_auth_version' => $manager->auth_version]);
        $input['roll_code'] = 'ACTOR-B';
        $this->command('material.receive', $input, null, 'workflow-actor-separated-key');
        $this->assertSame(2, DB::table('mfg_commands')->where('command_key', 'workflow-actor-separated-key')->count());
        $this->assertSame(2, DB::table('inv_trace_units')->count());
    }

    private function cutSingleOrder(int $quantity, ?int $cutQuantity = null): array
    {
        $style = $this->approvedStyle();
        $matrix = [['color' => 'Red', 'size' => 'S', 'quantity' => $quantity]];
        $demand = $this->command('demand.save', ['code' => 'SO-SINGLE', 'style_id' => $style['id'], 'type' => 'order', 'due_date' => '2026-11-20', 'lines' => $matrix]);
        $order = $this->command('order.save', ['code' => 'WO-SINGLE', 'demand_id' => $demand['id'], 'workshop_id' => $this->workshop, 'lines' => $matrix]);
        $order = $this->command('order.release', ['expected_version' => $order['version']], $order['id']);
        $roll = $this->acceptedRoll();
        $bundle = $this->command('cut.create', ['order_id' => $order['id'], 'workshop_id' => $this->workshop, 'code' => 'CUT-SINGLE',
            'color' => 'Red', 'size' => 'S', 'quantity' => $cutQuantity ?? $quantity, 'materials' => [['material_id' => $roll['id'], 'consumed_quantity' => ($cutQuantity ?? $quantity) * 2]]]);

        return [$order, $bundle];
    }

    private function approvedStyle(): array
    {
        $style = $this->command('style.save', $this->styleInput());
        $sample = $this->command('sample.save', ['style_id' => $style['id'], 'type' => 'pp']);
        $this->command('sample.approve', ['expected_version' => $sample['version']], $sample['id']);

        return $this->command('style.approve', ['expected_version' => $style['version']], $style['id']);
    }

    private function acceptedRoll(): array
    {
        $roll = $this->command('material.receive', ['code' => 'COTTON', 'name' => 'Cotton', 'roll_code' => 'ROLL-1', 'lot_code' => 'DYE-1', 'shade' => 'A', 'width' => '1.5', 'quantity' => '100', 'uom' => 'm', 'unit_cost' => '1000', 'reason' => 'Signed opening stock count']);

        return $this->command('material.inspect', ['status' => 'accepted', 'notes' => 'Incoming fabric inspection passed', 'expected_version' => $roll['version']], $roll['id']);
    }

    private function matrix(): array
    {
        return [['color' => 'Red', 'size' => 'S', 'quantity' => 12], ['color' => 'Blue', 'size' => 'M', 'quantity' => 8]];
    }

    private function styleInput(): array
    {
        return ['code' => 'SHIRT-01', 'name' => 'Pilot shirt', 'sizes' => ['S', 'M'], 'colors' => ['Red', 'Blue'], 'bom' => [['material_code' => 'COTTON', 'uom' => 'm', 'quantity' => '2']], 'routing' => [['code' => 'SEW', 'name' => 'Sewing', 'smv' => '5'], ['code' => 'PRESS', 'name' => 'Pressing', 'smv' => '1']]];
    }

    private function resource(string $table, string $id): array
    {
        return D::resource(DB::table($table)->where('public_id', $id)->first());
    }

    private function postCommand(string $operation, array $data, ?string $resource = null, ?string $key = null): TestResponse
    {
        return $this->withHeader('Idempotency-Key', $key ?? 'workflow-command-'.(++$this->sequence))->postJson('/admin/api/manufacturing/commands/'.$operation, ['factory_id' => $this->factory ?: null, 'resource' => $resource, 'data' => $data]);
    }

    private function command(string $operation, array $data, ?string $resource = null, ?string $key = null): array
    {
        return $this->postCommand($operation, $data, $resource, $key)->assertSuccessful()->json('data');
    }
}
