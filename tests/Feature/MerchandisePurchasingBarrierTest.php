<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleLifecycleCoordinator;
use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Merchandise\Http\MerchandiseController;
use Modules\Merchandise\Services\MerchandiseRuntimeState;
use Modules\Merchandise\Services\MerchandiseService;
use Modules\Purchasing\Http\PurchasingController;
use Modules\Purchasing\Services\PurchasingRuntimeState;
use Modules\Purchasing\Services\PurchasingService;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MerchandisePurchasingBarrierTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    private Admin $checker;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = Admin::factory()->create(['id' => 1])->fresh();
        $this->checker = Admin::factory()->create(['id' => 2])->fresh();
        foreach (['merchandise', 'inventory', 'purchasing'] as $key) {
            app(ModuleManager::class)->install($key);
            app(ModuleManager::class)->enable($key);
        }
        $this->role = Role::query()->create(['key' => 'barrier-checker', 'name' => 'Barrier checker', 'status' => 'active']);
        $this->role->permissions()->sync(Permission::query()->whereIn('module_key', ['merchandise', 'inventory', 'purchasing'])->pluck('id')->all());
        AdminRoleAssignment::query()->create(['admin_id' => $this->checker->id, 'role_id' => $this->role->id, 'scope_type' => 'global', 'scope_value' => null]);
    }

    public function test_purchasing_drain_blocks_a_service_loaded_before_prepare_then_compensation_restores_it(): void
    {
        [$service, $order] = $this->order();
        $coordinator = app(ModuleLifecycleCoordinator::class);
        $lease = $coordinator->begin('purchasing', 'disable', '0.1.0', '0.1.0');
        app(PurchasingRuntimeState::class)->prepare($lease);

        $this->assertDatabaseHas('module_installations', ['key' => 'purchasing', 'status' => 'enabled']);
        $this->assertDatabaseHas('purch_runtime_states', ['module_key' => 'purchasing', 'operational_state' => 'draining', 'operation_id' => $lease->operationId]);
        $this->denied(503, fn () => $service->approveOrder($order['public_id'], 1, 'approval-during-drain', $this->checker->id));
        $this->assertDatabaseMissing('purchase_commands', ['command_key' => 'approval-during-drain']);
        $this->assertSame('draft', $service->order($order['public_id'])['status']);

        app(PurchasingRuntimeState::class)->compensate($lease);
        $coordinator->fail($lease, new RuntimeException('Pre-hook failure'), false);
        $this->assertSame('approved', $service->approveOrder($order['public_id'], 1, 'approval-after-drain', $this->checker->id)['status']);
    }

    public function test_merchandise_worker_failure_stays_drained_until_owned_recovery_and_stale_owner_cannot_reopen_it(): void
    {
        $coordinator = app(ModuleLifecycleCoordinator::class);
        $lease = $coordinator->begin('merchandise', 'upgrade', '0.1.0', '0.1.0');
        app(MerchandiseRuntimeState::class)->prepare($lease);
        $coordinator->fail($lease, new RuntimeException('Worker interrupted'), true);
        $this->denied(503, fn () => app(MerchandiseService::class)->createCatalog(['code' => 'BLOCKED', 'name' => 'Blocked']));

        $resumed = $coordinator->resume($lease->operationId);
        try {
            app(MerchandiseRuntimeState::class)->compensate($lease);
            $this->fail('The previous worker must lose its lifecycle token.');
        } catch (RuntimeException) {
            $this->assertDatabaseHas('merch_runtime_states', ['module_key' => 'merchandise', 'operational_state' => 'draining']);
        }
        app(MerchandiseRuntimeState::class)->prepare($resumed);
        app(MerchandiseRuntimeState::class)->compensate($resumed);
        $coordinator->fail($resumed, new RuntimeException('Safely compensated'), false);
        $catalog = app(MerchandiseService::class)->createCatalog(['code' => 'RECOVERED', 'name' => 'Recovered']);
        $this->assertSame('RECOVERED', $catalog['code']);
        $this->assertDatabaseMissing('merch_catalogs', ['code' => 'BLOCKED']);
    }

    public function test_failed_purchasing_disable_compensates_and_successful_disable_blocks_new_commands(): void
    {
        [$service, $order] = $this->order();
        $order = $service->approveOrder($order['public_id'], 1, 'approval-before-disable', $this->checker->id);
        try {
            app(ModuleManager::class)->disable('purchasing');
            $this->fail('Open approved orders must block disable.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('purch_runtime_states', ['module_key' => 'purchasing', 'operational_state' => 'active', 'operation_id' => null]);
            $this->assertDatabaseHas('module_installations', ['key' => 'purchasing', 'status' => 'enabled']);
        }
        $service->closeOrder($order['public_id'], 2, 'close-after-failed-disable', $this->checker->id);
        app(ModuleManager::class)->disable('purchasing');
        $this->assertDatabaseHas('purch_runtime_states', ['module_key' => 'purchasing', 'operational_state' => 'disabled']);
        $this->denied(503, fn () => $service->createSupplier(['code' => 'LATE', 'name' => 'Late'], 'supplier-after-disable', $this->owner->id));
        $this->assertDatabaseMissing('purchase_suppliers', ['code' => 'LATE']);
    }

    public function test_purchasing_replay_rechecks_live_native_permission_before_returning_a_cached_result(): void
    {
        [$service, $order] = $this->order();
        $service->approveOrder($order['public_id'], 1, 'approval-before-revoke', $this->checker->id);
        $this->revoke('purchasing.order.approve');
        $this->denied(403, fn () => $service->approveOrder($order['public_id'], 1, 'approval-before-revoke', $this->checker->id));
        $this->assertSame(1, DB::table('purchase_commands')->where('command_key', 'approval-before-revoke')->count());
        $this->assertSame(2, (int) $service->order($order['public_id'])['version']);
    }

    public function test_purchasing_website_permission_cannot_replace_native_global_approval(): void
    {
        [$service, $order] = $this->order();
        AdminRoleAssignment::query()->where('admin_id', $this->checker->id)->update(['scope_type' => 'website', 'scope_value' => 'website-main']);
        $this->denied(403, fn () => $service->approveOrder($order['public_id'], 1, 'approval-wrong-scope', $this->checker->id));
        $this->assertSame('draft', $service->order($order['public_id'])['status']);
    }

    public function test_receiving_and_supplier_return_recheck_native_inventory_permissions_before_stock_mutation(): void
    {
        [$service, $order, $warehouse] = $this->order();
        $order = $service->approveOrder($order['public_id'], 1, 'approval-native-stock', $this->checker->id);
        $body = ['expected_version' => 2, 'lines' => [['order_line_id' => $order['lines'][0]['id'], 'quantity' => '2']]];
        $this->revoke('inventory.receipt.manage');
        $this->denied(403, fn () => $service->receiveOrder($order['public_id'], $body, 'receive-after-revoke', $this->checker->id));
        $this->assertDatabaseCount('purchase_receipts', 0);
        $this->assertDatabaseCount('inv_stock_movements', 0);

        $this->role->permissions()->attach(Permission::query()->where('key', 'inventory.receipt.manage')->value('id'));
        $order = $service->receiveOrder($order['public_id'], $body, 'receive-before-issue-revoke', $this->checker->id);
        $this->revoke('inventory.issue.manage');
        $this->denied(403, fn () => $service->returnReceiptLine($order['public_id'], ['expected_version' => 3, 'receipt_line_id' => $order['receipts'][0]['lines'][0]['id'], 'quantity' => '1', 'reason' => 'Damaged'], 'return-after-revoke', $this->checker->id));
        $this->assertDatabaseCount('purchase_returns', 0);
        $this->assertSame('2.000000', app(InventoryV2Service::class)->availability($warehouse['id'], $order['lines'][0]['inventory_item_id']));
    }

    public function test_merchandise_http_mutation_rechecks_permission_after_an_earlier_authorization(): void
    {
        $this->assertTrue($this->checker->canAccess('merchandise.manage', 'global'));
        app(MerchandiseController::class)->createCatalog($this->request($this->checker, ['code' => 'AUTHORIZED', 'name' => 'Authorized']));
        $this->assertDatabaseHas('merch_catalogs', ['code' => 'AUTHORIZED']);
        $request = $this->request($this->checker, ['code' => 'REVOKED', 'name' => 'Revoked']);
        $this->revoke('merchandise.manage');
        $this->denied(403, fn () => app(MerchandiseController::class)->createCatalog($request));
        $this->assertDatabaseMissing('merch_catalogs', ['code' => 'REVOKED']);
    }

    public function test_native_http_mutations_accept_current_authorization_and_reject_a_late_account_lock(): void
    {
        app(PurchasingController::class)->createSupplier($this->request($this->checker, ['code' => 'AUTHORIZED', 'name' => 'Authorized']));
        $this->assertDatabaseHas('purchase_suppliers', ['code' => 'AUTHORIZED']);
        $request = $this->request($this->checker, ['code' => 'LOCKED', 'name' => 'Locked']);
        DB::table('admins')->where('id', $this->checker->id)->update(['locked_at' => now()]);
        $this->denied(403, fn () => app(MerchandiseController::class)->createCatalog($request));
        $this->denied(403, fn () => app(PurchasingController::class)->createSupplier($request));
        $this->assertDatabaseMissing('merch_catalogs', ['code' => 'LOCKED']);
        $this->assertDatabaseMissing('purchase_suppliers', ['code' => 'LOCKED']);
    }

    public function test_native_http_mutations_recheck_the_original_auth_version_within_the_actor_lock(): void
    {
        $request = $this->request($this->checker, ['code' => 'OLD-AUTH', 'name' => 'Old auth']);
        DB::table('admins')->where('id', $this->checker->id)->increment('auth_version');
        $this->denied(403, fn () => app(MerchandiseController::class)->createCatalog($request));
        $this->denied(403, fn () => app(PurchasingController::class)->createSupplier($request));
        $this->assertDatabaseMissing('merch_catalogs', ['code' => 'OLD-AUTH']);
        $this->assertDatabaseMissing('purchase_suppliers', ['code' => 'OLD-AUTH']);
    }

    public function test_trusted_merchandise_service_calls_support_hq_delegation_without_native_global_permission(): void
    {
        $this->revoke('merchandise.manage');
        $this->assertFalse($this->checker->canAccess('merchandise.manage', 'global'));
        $service = app(MerchandiseService::class);
        $catalog = $service->createCatalog(['code' => 'DELEGATED', 'name' => 'Delegated'], $this->checker->id);
        $sku = $service->saveSku($catalog['id'], ['sku' => 'HQ', 'name' => 'HQ item']);
        $this->assertSame('HQ', $sku['sku']);
    }

    private function order(): array
    {
        $merchandise = app(MerchandiseService::class);
        $catalog = $merchandise->createCatalog(['code' => 'CHAIN', 'name' => 'Chain']);
        $sku = $merchandise->saveSku($catalog['id'], ['sku' => 'ITEM', 'name' => 'Item']);
        $warehouse = app(InventoryV2Service::class)->createWarehouse(['code' => 'BRANCH', 'name' => 'Branch', 'catalog_id' => $catalog['id'], 'legal_entity_ref' => 'LEGAL-1']);
        $service = app(PurchasingService::class);
        $supplier = $service->createSupplier(['code' => 'SUPPLIER', 'name' => 'Supplier']);
        $order = $service->createOrder(['supplier_id' => $supplier['id'], 'warehouse_id' => $warehouse['id'], 'catalog_id' => $catalog['id'], 'lines' => [['sku_id' => $sku['id'], 'quantity' => '2', 'unit_cost' => '1000']]], 'initial-order-create', $this->owner->id);

        return [$service, $order, $warehouse];
    }

    private function revoke(string $permission): void
    {
        $this->role->permissions()->detach(Permission::query()->where('key', $permission)->value('id'));
    }

    private function request(Admin $actor, array $body): Request
    {
        $request = Request::create('/native-command', 'POST', $body);
        $request->setUserResolver(fn () => $actor);
        $request->headers->set('Idempotency-Key', 'native-stale-auth-command');

        return $request;
    }

    private function denied(int $status, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected HTTP '.$status);
        } catch (HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode(), $exception->getMessage());
        }
    }
}
