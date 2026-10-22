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
use Modules\Inventory\Services\InventoryProductionService;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Merchandise\Services\MerchandiseService;
use Modules\Purchasing\Services\PurchasingProductionService;
use Modules\Purchasing\Services\PurchasingService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PurchasingProductionTest extends TestCase
{
    use RefreshDatabase;

    private function setupProcurement(): array
    {
        foreach (['merchandise', 'inventory', 'purchasing'] as $key) {
            app(ModuleManager::class)->install($key);
            app(ModuleManager::class)->enable($key);
        }
        $role = Role::query()->create(['key' => 'production-purchaser', 'name' => 'Production purchaser', 'status' => 'active']);
        $role->permissions()->sync(Permission::query()->whereIn('module_key', ['purchasing', 'inventory'])->pluck('id'));
        foreach ([7, 8, 9] as $id) {
            Admin::factory()->create(['id' => $id, 'must_change_password' => false]);
            AdminRoleAssignment::query()->create(['admin_id' => $id, 'role_id' => $role->id, 'scope_type' => 'global', 'scope_value' => null]);
        }
        $merch = app(MerchandiseService::class);
        $catalog = $merch->createCatalog(['code' => 'PRODUCTION', 'name' => 'Production']);
        $sku = $merch->saveSku($catalog['id'], ['sku' => 'FABRIC', 'name' => 'Fabric', 'base_unit' => 'm', 'tracking_mode' => 'batch']);
        $w = app(InventoryV2Service::class)->createWarehouse(['code' => 'FABRIC', 'name' => 'Fabric warehouse', 'catalog_id' => $catalog['id'], 'legal_entity_ref' => 'company-one']);
        app(InventoryProductionService::class)->enableWarehouse(['warehouse_id' => $w['id']], 'enable-stock-contract', 7);
        $supplier = app(PurchasingService::class)->createSupplier(['code' => 'FABRIC-SUPPLIER', 'name' => 'Supplier']);
        $p = app(PurchasingProductionService::class);
        $r = $p->requisition(['warehouse_id' => $w['id'], 'catalog_id' => $catalog['id'], 'order_ref' => 'WO-1', 'source_revision' => '1', 'lines' => [['sku_id' => $sku['id'], 'quantity' => '100']]], 'production-requisition', 7);
        $o = $p->createOrder(['requisition_id' => $r['public_id'], 'supplier_id' => $supplier['id'], 'lines' => [['sku_id' => $sku['id'], 'quantity' => '100', 'unit_cost' => '1000']]], 'production-purchase-order', 7);
        $o = app(PurchasingService::class)->approveOrder($o['public_id'], 1, 'production-purchase-approved', 8);

        return [$p, $o, $w, $role, $r];
    }

    public function test_rejected_roll_return_preserves_qc_cost_and_received_provisional_liability(): void
    {
        [$p, $o, $w] = $this->setupProcurement();
        $input = ['order_id' => $o['public_id'], 'expected_version' => 2, 'lines' => [['order_line_id' => $o['lines'][0]['id'], 'rolls' => [['code' => 'ROLL-A', 'quantity' => '40', 'secondary_quantity' => '18', 'secondary_unit' => 'kg', 'unit_cost' => '1'], ['code' => 'ROLL-B', 'quantity' => '60', 'secondary_quantity' => '32', 'secondary_unit' => 'kg']]]]];
        $received = $p->receive($input, 'production-receive-rolls', 9);
        $this->assertTrue($p->receive($input, 'production-receive-rolls', 9)['replayed']);
        $this->assertSame(2, DB::table('inv_trace_units')->count());
        $this->assertSame('100000', $received['financials']['payable_minor']);
        $item = $o['lines'][0]['inventory_item_id'];
        $this->assertSame('0.000000', app(InventoryV2Service::class)->availability($w['id'], $item));
        $rejected = $received['received_rolls'][0];
        $accepted = $received['received_rolls'][1];
        $this->assertTrue(Str::isUuid($rejected['receipt_line_public_id']));
        $this->assertSame($rejected['receipt_line_public_id'], DB::table('purchase_receipt_lines')->where('id', $rejected['receipt_line_id'])->value('public_id'));
        $p->inspectReceipt(['receipt_line_id' => $rejected['receipt_line_id'], 'expected_version' => 1, 'disposition' => 'rejected', 'reason' => 'Shade mismatch'], 'production-qc-rejected', 8);
        $p->inspectReceipt(['receipt_line_id' => $accepted['receipt_line_id'], 'expected_version' => 1, 'disposition' => 'saleable', 'reason' => 'QC passed'], 'production-qc-accepted', 8);
        $this->assertSame('60.000000', app(InventoryV2Service::class)->availability($w['id'], $item));
        $returned = $p->returnRoll(['receipt_line_id' => $rejected['receipt_line_id'], 'expected_version' => 3, 'quantity' => '10', 'reason' => 'Return rejected fabric'], 'production-return-rejected', 9);
        $this->assertSame('90000', $returned['financials']['payable_minor']);
        $this->assertEquals(90, DB::table('inv_stock_balances')->where('warehouse_id', $w['id'])->sum('quantity_on_hand'));
        $this->assertSame('60.000000', app(InventoryV2Service::class)->availability($w['id'], $item));
        $roll = app(InventoryProductionService::class)->roll($rejected['roll']['public_id']);
        $this->assertSame('30.000000', $roll['quantity_on_hand']);
        $this->assertSame('rejected', $roll['disposition']);
        $this->assertEquals(13.5, $roll['secondary_on_hand']);
    }

    public function test_over_receipt_rolls_back_every_physical_roll_and_purchase_row(): void
    {
        [$p, $o] = $this->setupProcurement();
        try {
            $p->receive(['order_id' => $o['public_id'], 'expected_version' => 2, 'lines' => [['order_line_id' => $o['lines'][0]['id'], 'rolls' => [['code' => 'FIRST', 'quantity' => '60'], ['code' => 'OVER', 'quantity' => '60']]]]], 'over-receipt-atomic', 9);
            $this->fail('Over receipt should fail');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame(0, DB::table('purchase_receipts')->count());
        $this->assertSame(0, DB::table('inv_trace_units')->count());
        $this->assertSame(0, DB::table('inv_stock_movements')->count());
    }

    public function test_native_global_permissions_are_rechecked_before_replay(): void
    {
        [$p, $o, , $role] = $this->setupProcurement();
        $input = ['order_id' => $o['public_id'], 'expected_version' => 2, 'lines' => [['order_line_id' => $o['lines'][0]['id'], 'rolls' => [['code' => 'AUTH', 'quantity' => '1']]]]];
        $p->receive($input, 'permission-bound-receipt', 9);
        $role->permissions()->detach();
        $this->expectException(HttpException::class);
        $p->receive($input, 'permission-bound-receipt', 9);
    }

    public function test_http_production_requisition_keeps_native_global_authorization(): void
    {
        [, $order, $warehouse, $role] = $this->setupProcurement();
        $actor = Admin::query()->findOrFail(7);
        $body = ['warehouse_id' => $warehouse['id'], 'catalog_id' => $warehouse['catalog_id'], 'order_ref' => 'WO-HTTP', 'lines' => [['sku_id' => $order['lines'][0]['sku_id'], 'quantity' => '5']]];
        $this->actingAs($actor, 'admin')->withHeader('Idempotency-Key', 'http-production-requisition')->postJson('/admin/api/purchasing/production/requisitions', $body)->assertOk()->assertJsonPath('data.order_ref', 'WO-HTTP');
        $role->permissions()->detach();
        $this->actingAs($actor->fresh(), 'admin')->withHeader('Idempotency-Key', 'http-production-requisition')->postJson('/admin/api/purchasing/production/requisitions', $body)->assertForbidden();
    }

    public function test_receipt_public_id_forward_backfill_is_repeatable_and_preserves_numeric_links(): void
    {
        [$p, $o] = $this->setupProcurement();
        $received = $p->receive(['order_id' => $o['public_id'], 'expected_version' => 2, 'lines' => [['order_line_id' => $o['lines'][0]['id'], 'rolls' => [['code' => 'IDENTITY-A', 'quantity' => '40'], ['code' => 'IDENTITY-B', 'quantity' => '60']]]]], 'identity-receive', 9);
        $first = $received['received_rolls'][0];
        $second = $received['received_rolls'][1];
        $before = DB::table('purchase_receipt_lines')->where('id', $first['receipt_line_id'])->first();
        DB::table('purchase_receipt_lines')->where('id', $first['receipt_line_id'])->update(['public_id' => null]);
        $migration = require base_path('modules/Purchasing/database/migrations/2026_10_21_030004_add_purchase_receipt_line_public_ids.php');
        $migration->up();
        $backfilled = DB::table('purchase_receipt_lines')->where('id', $first['receipt_line_id'])->first();
        $this->assertTrue(Str::isUuid($backfilled->public_id));
        $this->assertEquals($before->receipt_id, $backfilled->receipt_id);
        $this->assertEquals($before->order_line_id, $backfilled->order_line_id);
        $this->assertEquals($before->trace_unit_id, $backfilled->trace_unit_id);
        $migration->up();
        $this->assertSame($backfilled->public_id, DB::table('purchase_receipt_lines')->where('id', $first['receipt_line_id'])->value('public_id'));
        $this->assertSame($second['receipt_line_public_id'], DB::table('purchase_receipt_lines')->where('id', $second['receipt_line_id'])->value('public_id'));
        $this->assertEquals(100, DB::table('inv_stock_balances')->sum('quantity_on_hand'));
        $this->assertSame('100000', app(PurchasingService::class)->order($o['public_id'])['financials']['payable_minor']);
    }

    public function test_production_receive_rejects_old_installed_identity_contract_before_writing(): void
    {
        [$p, $o] = $this->setupProcurement();
        DB::table('module_installations')->where('key', 'purchasing')->update(['version' => '0.2.0']);
        try {
            $p->receive(['order_id' => $o['public_id'], 'expected_version' => 2, 'lines' => [['order_line_id' => $o['lines'][0]['id'], 'rolls' => [['code' => 'NOT-UPGRADED', 'quantity' => '1']]]]], 'old-identity-contract', 9);
            $this->fail('Upgrade must precede new receipt writes');
        } catch (HttpException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
        $this->assertSame(0, DB::table('purchase_receipt_lines')->count());
        $this->assertSame(0, DB::table('inv_trace_units')->count());
        $this->assertEquals(0, DB::table('purchase_order_lines')->where('order_id', $o['id'])->sum('quantity_received'));
    }
}
