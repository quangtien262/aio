<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Pharmacy\Services\Purchasing\PharmacyPurchasingService;
use Modules\Pharmacy\Services\Setup\PharmacySetupService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PharmacyPurchasingTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    private object $pharmacy;

    private object $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = Admin::query()->findOrFail(1);
        $this->owner->update(['must_change_password' => false]);
        foreach (['merchandise', 'inventory', 'purchasing', 'pharmacy'] as $module) {
            app(ModuleManager::class)->install($module);
            app(ModuleManager::class)->enable($module);
        }
        $setup = app(PharmacySetupService::class)->dispatch($this->owner, 'setup', ['chain_name' => 'Synthetic Purchasing chain', 'pharmacy_name' => 'Synthetic pharmacy', 'code' => 'PH-PUR'], $this->key());
        $this->pharmacy = DB::table('ph_pharmacies')->where('public_id', $setup['id'])->first();
        $product = app(PharmacySetupService::class)->dispatch($this->owner, 'product.save', ['pharmacy_id' => $this->pharmacy->public_id, 'name' => 'Synthetic medicine', 'code' => 'PH-MED', 'base_unit' => 'tablet', 'registration_number' => 'TEST-REG', 'price_minor' => '100'], $this->key());
        $this->product = DB::table('ph_products')->where('public_id', $product['id'])->first();
    }

    private function key(): string
    {
        return 'ph-test:'.Str::uuid();
    }

    private function command(string $action, array $input, ?Admin $actor = null, ?string $key = null): array
    {
        return app(PharmacyPurchasingService::class)->dispatch($actor ?? $this->owner, $action, ['pharmacy_id' => $this->pharmacy->public_id] + $input, $key ?? $this->key());
    }

    private function fails(int $status, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected purchasing rejection');
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
    }

    private function supplier(): array
    {
        return $this->command('supplier.save', ['code' => 'SYNTHETIC-NCC', 'name' => 'Synthetic supplier', 'license_number' => 'TEST-NOT-A-LICENSE', 'valid_until' => now()->addYear()->toDateTimeString()]);
    }

    private function checker(): Admin
    {
        $actor = Admin::factory()->create(['must_change_password' => false]);
        AdminRoleAssignment::query()->create(['admin_id' => $actor->id, 'role_id' => Role::query()->where('key', Role::SUPER_ADMIN_KEY)->value('id'), 'scope_type' => 'global']);

        return $actor->fresh();
    }

    public function test_po_maker_checker_partial_quarantined_receipt_and_expired_supplier_return_keep_one_ledger(): void
    {
        $supplier = $this->supplier();
        $order = $this->command('purchasing.create', ['supplier_id' => $supplier['supplier']['public_id'], 'lines' => [['product_id' => $this->product->public_id, 'quantity' => '10', 'unit_cost' => '20']]]);
        $this->fails(403, fn () => $this->command('purchasing.approve', ['order_id' => $order['id'], 'expected_version' => 1]));
        $approved = $this->command('purchasing.approve', ['order_id' => $order['id'], 'expected_version' => 1], $this->checker());
        $line = $approved['order']['lines'][0];
        $received = $this->command('purchasing.receive', ['order_id' => $order['id'], 'expected_version' => 2, 'lines' => [['order_line_id' => $line['public_id'], 'quantity' => '4', 'batch_code' => 'PH-LOT', 'expires_at' => now()->addYear()->toDateString()]]]);
        $this->assertSame('partially_received', $received['status']);
        $this->assertCount(1, $received['receipts']);
        $receipt = $received['receipts'][0];
        $this->assertSame('quarantine', $receipt['status']);
        $this->assertNotNull($receipt['source_movement_id']);
        $this->assertSame('0.000000', app(InventoryV2Service::class)->availability($this->pharmacy->warehouse_id, $this->product->inventory_item_id));
        DB::table('inv_batches')->where('id', $receipt['batch_id'])->update(['expires_at' => '2020-01-01', 'is_active' => false]);
        $input = ['order_id' => $order['id'], 'expected_version' => 3, 'receipt_id' => $receipt['id'], 'quantity' => '2', 'reason' => 'Synthetic expired receipt return'];
        $key = $this->key();
        $returned = $this->command('purchasing.return', $input, key: $key);
        $this->assertSame('40', $returned['order']['financials']['payable_minor']);
        $this->assertTrue($this->command('purchasing.return', $input, key: $key)['replayed']);
        $this->assertDatabaseCount('purchase_returns', 1);
        $this->assertDatabaseCount('inv_restricted_withdrawals', 1);
        $this->assertEquals(2, DB::table('inv_stock_balances')->where('warehouse_id', $this->pharmacy->warehouse_id)->sum('quantity_on_hand'));
        $this->fails(409, fn () => $this->command('purchasing.return', array_replace($input, ['expected_version' => 4, 'quantity' => '3'])));
        $this->assertDatabaseCount('purchase_returns', 1);
    }

    public function test_pharmacy_permission_does_not_imply_shared_purchasing_permission(): void
    {
        $actor = Admin::factory()->create(['must_change_password' => false]);
        $role = Role::query()->create(['key' => 'ph-only-purchasing', 'name' => 'Pharmacy only']);
        $role->permissions()->sync(DB::table('permissions')->whereIn('key', ['pharmacy.view', 'pharmacy.purchasing.manage'])->pluck('id'));
        AdminRoleAssignment::query()->create(['admin_id' => $actor->id, 'role_id' => $role->id, 'scope_type' => 'global']);
        DB::table('ph_memberships')->insert(['public_id' => (string) Str::uuid(), 'chain_id' => $this->pharmacy->chain_id, 'pharmacy_id' => null, 'admin_id' => $actor->id, 'permissions' => json_encode(['pharmacy.view', 'pharmacy.purchasing.manage']), 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->fails(403, fn () => $this->command('supplier.save', ['code' => 'NO-GLOBAL', 'name' => 'Synthetic', 'license_number' => 'TEST', 'valid_until' => now()->addYear()->toDateTimeString()], $actor->fresh()));
        $this->assertDatabaseCount('purchase_suppliers', 0);
    }

    public function test_expired_qualification_and_changed_retry_are_rejected_without_po_side_effects(): void
    {
        $supplier = $this->supplier();
        DB::table('ph_supplier_links')->update(['valid_until' => now()->subDay()]);
        $input = ['supplier_id' => $supplier['supplier']['public_id'], 'lines' => [['product_id' => $this->product->public_id, 'quantity' => '2', 'unit_cost' => '20']]];
        $this->fails(409, fn () => $this->command('purchasing.create', $input));
        $this->assertDatabaseCount('purchase_orders', 0);
    }
}
