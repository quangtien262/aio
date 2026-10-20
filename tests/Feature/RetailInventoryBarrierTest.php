<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleLifecycleCoordinator;
use App\Models\Admin;
use Brick\Math\BigDecimal;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Inventory\Services\InventoryRuntimeState;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\RetailPos\Services\RetailRuntimeState;
use Modules\RetailPos\Services\RetailService;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class RetailInventoryBarrierTest extends TestCase
{
    use RefreshDatabase;

    private array $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(1);
        $owner->forceFill(['must_change_password' => false])->save();
        $this->actingAs($owner, 'admin')->withSession(['admin_auth_version' => $owner->auth_version]);
        foreach (['merchandise', 'inventory', 'retail-pos'] as $key) {
            $this->postJson('/admin/api/modules/'.$key.'/install')->assertOk();
            $this->postJson('/admin/api/modules/'.$key.'/enable')->assertOk();
        }
        $this->branch = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/admin/api/retail/setup', ['name' => 'Barrier chain'])->assertOk()->json('data');
    }

    public function test_durable_retail_drain_blocks_new_shift_and_safe_compensation_restores_runtime(): void
    {
        $coordinator = app(ModuleLifecycleCoordinator::class);
        $lease = $coordinator->begin('retail-pos', 'disable', '0.1.0-dev.1', '0.1.0-dev.1');
        $state = app(RetailRuntimeState::class);
        $state->prepare($lease);
        $this->assertDatabaseHas('retail_runtime_states', ['operational_state' => 'draining', 'operation_id' => $lease->operationId]);
        $body = ['branch_id' => $this->branch['id'], 'terminal_id' => $this->branch['terminals'][0]['id'], 'opening_minor' => '0'];
        // The HTTP module boundary hides draining routes; background callers hit the durable guard.
        $this->withHeader('Idempotency-Key', 'drain-must-not-open-shift')->postJson('/admin/api/retail/shifts', $body)->assertNotFound();
        $retail = app(RetailService::class);
        try {
            $retail->command(Admin::query()->findOrFail(1), 'drain-must-not-open-shift', 'shift.open', ['data' => $body], fn ($live) => $retail->openShift($live, $body));
            $this->fail('Background Retail command bypassed the prepared drain.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('retail_shifts', 0);
        $this->assertDatabaseMissing('retail_commands', ['command_key' => 'drain-must-not-open-shift']);
        $state->compensate($lease);
        $coordinator->fail($lease, new RuntimeException('Synthetic pre-hook rejection'), false);
        $this->assertDatabaseHas('retail_runtime_states', ['operational_state' => 'active', 'operation_id' => null]);
        $this->withHeader('Idempotency-Key', 'after-drain-open-shift')->postJson('/admin/api/retail/shifts', $body)->assertOk();
        $this->assertDatabaseCount('retail_shifts', 1);
    }

    public function test_inventory_recovery_required_drain_preserves_stock_and_blocks_command_and_metadata_writes(): void
    {
        $product = $this->withHeader('Idempotency-Key', 'barrier-opening-product')->postJson('/admin/api/retail/products', ['branch_id' => $this->branch['id'], 'sku' => 'BARRIER', 'name' => 'Barrier product', 'base_unit' => 'pcs', 'price_minor' => '10000', 'opening_quantity' => '5', 'unit_cost' => '5000'])->assertOk()->json('data');
        $item = DB::table('inv_items')->where('merchandise_sku_id', $product['database_id'] ?? DB::table('merch_skus')->where('public_id', $product['id'])->value('id'))->value('id');
        $coordinator = app(ModuleLifecycleCoordinator::class);
        $lease = $coordinator->begin('inventory', 'upgrade', '0.3.0', '0.3.0');
        app(InventoryRuntimeState::class)->prepare($lease);
        $coordinator->fail($lease, new RuntimeException('Synthetic partial DDL failure'), true);
        $inventory = app(InventoryV2Service::class);
        foreach ([
            fn () => $inventory->reserve($this->branch['warehouse_id'], $item, '1', 'blocked-sale', 'blocked-during-recovery', 1),
            fn () => $inventory->ensureItem((int) DB::table('merch_skus')->where('public_id', $product['id'])->value('id')),
            fn () => $inventory->protectReservation((string) Str::uuid(), true),
            fn () => $inventory->createWarehouse(['code' => 'BLOCKED', 'name' => 'Blocked', 'catalog_id' => DB::table('merch_skus')->where('public_id', $product['id'])->value('catalog_id'), 'legal_entity_ref' => 'blocked']),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('Recovery-required runtime must not accept writes.');
            } catch (HttpExceptionInterface $exception) {
                $this->assertSame(503, $exception->getStatusCode());
            }
        }
        $this->assertDatabaseHas('inv_runtime_states', ['operational_state' => 'draining', 'operation_id' => $lease->operationId]);
        $this->assertDatabaseHas('module_lifecycle_operations', ['operation_id' => $lease->operationId, 'status' => 'recovery_required', 'active_slot' => 'active']);
        $this->assertDatabaseCount('inv_reservations', 0);
        $this->assertDatabaseMissing('inv_v2_operations', ['idempotency_key' => 'blocked-during-recovery']);
        $this->assertDatabaseMissing('inv_warehouses', ['code' => 'BLOCKED']);
        $this->assertSame('5.000000', (string) BigDecimal::of((string) DB::table('inv_stock_balances')->where('item_id', $item)->value('quantity_on_hand'))->toScale(6));
    }
}
