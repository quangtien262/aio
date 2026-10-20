<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Merchandise\Services\MerchandiseService;
use Modules\Purchasing\Services\PurchasingService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PurchasingModuleTest extends TestCase
{
    use RefreshDatabase;

    private function setupOrder(string $quantity = '10', string $cost = '1000', int $scale = 0): array
    {
        foreach (['merchandise', 'inventory', 'purchasing'] as $key) {
            app(ModuleManager::class)->install($key);
            app(ModuleManager::class)->enable($key);
        }
        $role = Role::query()->create(['key' => 'purchase-test', 'name' => 'Purchase test', 'status' => 'active']);
        $role->permissions()->sync(Permission::query()->whereIn('module_key', ['purchasing', 'inventory'])->pluck('id')->all());
        foreach ([7, 8, 9, 10] as $id) {
            Admin::factory()->create(['id' => $id]);
            AdminRoleAssignment::query()->create(['admin_id' => $id, 'role_id' => $role->id, 'scope_type' => 'global', 'scope_value' => null]);
        }
        $merch = app(MerchandiseService::class);
        $inventory = app(InventoryV2Service::class);
        $purchase = app(PurchasingService::class);
        $catalog = $merch->createCatalog(['code' => 'C', 'name' => 'Chuỗi']);
        $sku = $merch->saveSku($catalog['id'], ['sku' => 'RICE', 'name' => 'Hàng', 'base_unit' => $scale ? 'kg' : 'pcs', 'quantity_scale' => $scale]);
        $warehouse = $inventory->createWarehouse(['code' => 'WH', 'name' => 'Kho', 'catalog_id' => $catalog['id'], 'legal_entity_ref' => 'LEGAL-01']);
        $supplier = $purchase->createSupplier(['code' => 'SUP', 'name' => 'Nhà cung cấp']);
        $order = $purchase->createOrder(['catalog_id' => $catalog['id'], 'warehouse_id' => $warehouse['id'], 'supplier_id' => $supplier['id'], 'lines' => [['sku_id' => $sku['id'], 'quantity' => $quantity, 'unit_cost' => $cost]]], 'order-create-001', 7);

        return [$purchase, $inventory, $order, $warehouse];
    }

    public function test_partial_receipts_and_retry_post_inventory_once_and_track_payable(): void
    {
        [$p, $inv, $o, $w] = $this->setupOrder();
        $o = $p->approveOrder($o['public_id'], 1, 'order-approve-01', 8);
        $body = ['expected_version' => 2, 'lines' => [['order_line_id' => $o['lines'][0]['id'], 'quantity' => '3']]];
        $first = $p->receiveOrder($o['public_id'], $body, 'order-receive-01', 9);
        $replay = $p->receiveOrder($o['public_id'], $body, 'order-receive-01', 9);
        $this->assertSame($first['version'], $replay['version']);
        $this->assertSame('partially_received', $first['status']);
        $this->assertSame('3000', $first['financials']['payable_minor']);
        $this->assertSame(1, DB::table('purchase_receipts')->count());
        $this->assertSame('3.000000', $inv->availability($w['id'], $o['lines'][0]['inventory_item_id']));
        $o = $p->receiveOrder($o['public_id'], ['expected_version' => 3, 'lines' => [['order_line_id' => $o['lines'][0]['id'], 'quantity' => '7']]], 'order-receive-02', 9);
        $this->assertSame('received', $o['status']);
        $this->assertSame('10000', $o['financials']['payable_minor']);
    }

    public function test_maker_cannot_approve_own_purchase_order(): void
    {
        [$p, , $o] = $this->setupOrder();
        $this->expectException(HttpException::class);
        try {
            $p->approveOrder($o['public_id'], 1, 'order-approve-01', 7);
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            throw $e;
        }
    }

    public function test_supplier_and_financial_command_replay_stays_bound_to_actor_and_intent(): void
    {
        [$p, , $o] = $this->setupOrder();
        $a = $p->createSupplier(['code' => 'REPLAY', 'name' => 'Replay'], 'supplier-stable-key', 7);
        $b = $p->createSupplier(['code' => 'REPLAY', 'name' => 'Replay'], 'supplier-stable-key', 7);
        $this->assertSame($a['public_id'], $b['public_id']);
        $this->assertSame('committed', $p->commandStatus('supplier-stable-key', 7)['status']);
        $this->assertSame('unknown', $p->commandStatus('supplier-stable-key', 8)['status']);
        $p->approveOrder($o['public_id'], 1, 'approval-stable-key', 8);
        $this->expectException(HttpException::class);
        try {
            $p->approveOrder($o['public_id'], 1, 'approval-stable-key', 9);
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            throw $e;
        }
    }

    public function test_over_receipt_rolls_back_preceding_stock_and_receipt_lines(): void
    {
        [$p, $inv, $o, $w] = $this->setupOrder();
        $o = $p->approveOrder($o['public_id'], 1, 'order-approve-01', 8);
        try {
            $p->receiveOrder($o['public_id'], ['expected_version' => 2, 'lines' => [['order_line_id' => $o['lines'][0]['id'], 'quantity' => '8'], ['order_line_id' => $o['lines'][0]['id'], 'quantity' => '8']]], 'order-receive-01', 9);
            $this->fail('Over receipt expected.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame(0, DB::table('purchase_receipts')->count());
        $this->assertSame('0.000000', $inv->availability($w['id'], $o['lines'][0]['inventory_item_id']));
        $this->assertSame('approved', $p->order($o['public_id'])['status']);
    }

    public function test_supplier_returns_payments_and_credit_keep_original_lineage(): void
    {
        [$p, $inv, $o, $w] = $this->setupOrder();
        $o = $p->approveOrder($o['public_id'], 1, 'order-approve-01', 8);
        $o = $p->receiveOrder($o['public_id'], ['expected_version' => 2, 'lines' => [['order_line_id' => $o['lines'][0]['id'], 'quantity' => '10']]], 'order-receive-01', 9);
        $o = $p->payOrder($o['public_id'], ['expected_version' => 3, 'amount_minor' => '8000', 'method' => 'cash'], 'order-payment-01', 10);
        $o = $p->returnReceiptLine($o['public_id'], ['expected_version' => 4, 'receipt_line_id' => $o['receipts'][0]['lines'][0]['id'], 'quantity' => '3', 'reason' => 'Hàng lỗi'], 'order-return-001', 9);
        $this->assertSame('0', $o['financials']['payable_minor']);
        $this->assertSame('1000', $o['financials']['supplier_credit_minor']);
        $this->assertSame('7.000000', $inv->availability($w['id'], $o['lines'][0]['inventory_item_id']));
    }

    public function test_fractional_returns_allocate_rounding_remainder_cumulatively(): void
    {
        [$p, , $o] = $this->setupOrder('0.6', '1', 3);
        $o = $p->approveOrder($o['public_id'], 1, 'order-approve-01', 8);
        $o = $p->receiveOrder($o['public_id'], ['expected_version' => 2, 'lines' => [['order_line_id' => $o['lines'][0]['id'], 'quantity' => '0.6']]], 'order-receive-01', 9);
        $this->assertSame('1', $o['financials']['payable_minor']);
        $receiptLine = $o['receipts'][0]['lines'][0]['id'];
        $o = $p->returnReceiptLine($o['public_id'], ['expected_version' => 3, 'receipt_line_id' => $receiptLine, 'quantity' => '0.3', 'reason' => 'Trả'], 'order-return-001', 9);
        $o = $p->returnReceiptLine($o['public_id'], ['expected_version' => 4, 'receipt_line_id' => $receiptLine, 'quantity' => '0.3', 'reason' => 'Trả'], 'order-return-002', 9);
        $this->assertSame('1', $o['financials']['returned_minor']);
        $this->assertSame('0', $o['financials']['payable_minor']);
    }

    public function test_reused_key_different_actor_and_warehouse_owner_drift_are_rejected(): void
    {
        [$p, , $o, $w] = $this->setupOrder();
        $o = $p->approveOrder($o['public_id'], 1, 'order-approve-01', 8);
        DB::table('inv_warehouses')->where('id', $w['id'])->update(['legal_entity_ref' => 'OTHER']);
        $this->expectException(HttpException::class);
        try {
            $p->receiveOrder($o['public_id'], ['expected_version' => 2, 'lines' => [['order_line_id' => $o['lines'][0]['id'], 'quantity' => '1']]], 'order-receive-01', 9);
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            throw $e;
        }
    }
}
