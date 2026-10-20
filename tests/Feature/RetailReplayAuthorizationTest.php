<?php

namespace Tests\Feature;

use App\Models\AcctOrganization;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Permission;
use App\Models\Role;
use Brick\Math\BigDecimal;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RetailReplayAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    private Admin $actor;

    private Role $role;

    private array $branch;

    private array $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = Admin::query()->findOrFail(1);
        $this->owner->forceFill(['must_change_password' => false])->save();
        $this->login($this->owner);
        foreach (['merchandise', 'inventory', 'retail-pos'] as $key) {
            $this->postJson('/admin/api/modules/'.$key.'/install')->assertOk();
            $this->postJson('/admin/api/modules/'.$key.'/enable')->assertOk();
        }
        $this->branch = $this->command('setup', ['name' => 'Replay authorization'])->assertOk()->json('data');
        $this->product = $this->command('products', ['branch_id' => $this->branch['id'], 'sku' => 'REPLAY', 'name' => 'Original source', 'price_minor' => '10000', 'opening_quantity' => '24', 'unit_cost' => '5000'])->assertOk()->json('data');
        $this->actor = Admin::factory()->create(['must_change_password' => false, 'status' => 'active', 'is_active' => true]);
        $this->role = Role::query()->create(['key' => 'retail.replay-'.$this->actor->id, 'name' => 'Replay operator', 'status' => 'active']);
        $this->role->permissions()->sync(Permission::query()->whereIn('module_key', ['retail-pos', 'inventory'])->pluck('id')->all());
        AdminRoleAssignment::query()->create(['admin_id' => $this->actor->id, 'role_id' => $this->role->id, 'scope_type' => 'global', 'scope_value' => null, 'assigned_by' => $this->owner->id]);
        $chainId = DB::table('retail_chains')->where('public_id', $this->branch['chain_id'])->value('id');
        foreach ([0, $this->branch['database_id']] as $branchId) {
            DB::table('retail_memberships')->insert(['admin_id' => $this->actor->id, 'chain_id' => $chainId, 'branch_id' => $branchId, 'preset' => $branchId === 0 ? 'hq' : 'manager', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->login($this->actor);
    }

    public function test_receipt_replay_and_status_require_the_current_native_inventory_grant(): void
    {
        $body = ['branch_id' => $this->branch['id'], 'sku_id' => $this->product['id'], 'quantity' => '2', 'unit_cost' => '5000'];
        $key = 'receipt-native-replay-key';
        $this->command('inventory/receipts', $body, $key)->assertOk();
        $this->revoke('inventory.receipt.manage');
        $this->assertDenied('inventory/receipts', $body, $key);
        $this->assertDatabaseCount('inv_stock_documents', 2);
        $this->assertSame('26.000000', (string) BigDecimal::of((string) DB::table('inv_stock_balances')->sum('quantity_on_hand'))->toScale(6));
    }

    public function test_hq_demotion_blocks_cached_product_price_and_terminal_commands_while_branch_access_remains(): void
    {
        $commands = [
            ['products', ['branch_id' => $this->branch['id'], 'sku' => 'CREATED', 'name' => 'Created as HQ', 'price_minor' => '1000'], 'hq-product-replay-key'],
            ['assortments', ['branch_id' => $this->branch['id'], 'sku_id' => $this->product['id'], 'price_minor' => '11000', 'expected_version' => 1], 'hq-price-replay-key'],
            ['terminals', ['branch_id' => $this->branch['id'], 'code' => 'POS-02', 'name' => 'Quầy hai'], 'hq-terminal-replay-key'],
        ];
        foreach ($commands as [$path, $body, $key]) {
            $this->command($path, $body, $key)->assertOk();
        }
        DB::table('retail_memberships')->where('admin_id', $this->actor->id)->where('branch_id', 0)->update(['preset' => 'manager']);
        $this->getJson('/admin/api/retail/bootstrap')->assertOk()->assertJsonPath('data.branches.0.id', $this->branch['id']);
        foreach ($commands as [$path, $body, $key]) {
            $this->assertDenied($path, $body, $key);
        }
        $this->assertDatabaseCount('merch_skus', 2);
        $this->assertDatabaseCount('retail_terminals', 2);
    }

    public function test_discount_and_manual_transfer_grants_are_rechecked_without_revalidating_a_committed_quote(): void
    {
        [$shift, $sale] = $this->pricedSale();
        $body = ['expected_version' => $sale['version'], 'discount_minor' => '1000', 'reason' => 'Chiết khấu được cấp quyền', 'tenders' => [['method' => 'manual_transfer', 'amount_minor' => '9000', 'reference' => 'BANK-ORIGINAL']]];
        $path = 'sales/'.$sale['id'].'/complete';
        $key = 'manual-discount-replay-key';
        $completed = $this->command($path, $body, $key)->assertOk()->json('data');
        foreach (['retail.settings.manage', 'retail.price.manage'] as $permission) {
            $this->revoke($permission);
            $this->assertDenied($path, $body, $key);
            $this->grant($permission);
        }
        $this->travel(10)->minutes();
        DB::table('retail_assortments')->update(['price_minor' => '12000', 'version' => 2]);
        DB::table('retail_shifts')->where('public_id', $shift['id'])->update(['status' => 'reconciled', 'version' => 3]);
        $this->command($path, $body, $key)->assertOk()->assertJsonPath('data', $completed);
        $this->getJson('/admin/api/retail/commands/'.$key)->assertOk()->assertJsonPath('data.resource', $completed);
        $this->assertDatabaseCount('retail_tenders', 1);
        $this->assertDatabaseCount('inv_reservations', 1);
    }

    public function test_refund_replay_requires_payment_and_manual_confirmation_permissions_after_commit(): void
    {
        [$shift, $sale] = $this->pricedSale();
        $completed = $this->command('sales/'.$sale['id'].'/complete', ['expected_version' => $sale['version'], 'tenders' => [['method' => 'cash', 'amount_minor' => '10000']]])->assertOk()->json('data');
        $returned = $this->command('sales/'.$sale['id'].'/returns', ['expected_version' => $completed['version'], 'reason' => 'Khách trả lại hàng', 'lines' => [['sale_line_id' => $completed['lines'][0]['id'], 'quantity' => '1', 'disposition' => 'saleable']]])->assertOk()->json('data');
        $path = 'returns/'.$returned['id'].'/refund';
        $body = ['shift_id' => $shift['id'], 'method' => 'manual_transfer', 'reference' => 'REFUND-ORIGINAL'];
        $key = 'refund-confirm-replay-key';
        $result = $this->command($path, $body, $key)->assertOk()->json('data');
        foreach (['retail.payment.collect', 'retail.settings.manage'] as $permission) {
            $this->revoke($permission);
            $this->assertDenied($path, $body, $key);
            $this->grant($permission);
        }
        DB::table('retail_shifts')->where('public_id', $shift['id'])->update(['status' => 'reconciled']);
        $this->command($path, $body, $key)->assertOk()->assertJsonPath('data', $result);
        $this->assertDatabaseCount('retail_returns', 1);
        $this->assertDatabaseCount('retail_cash_movements', 1);
    }

    public function test_transfer_replay_requires_native_grant_after_stock_has_already_left_source(): void
    {
        $this->login($this->owner);
        $destination = $this->command('branches', ['chain_id' => $this->branch['chain_id'], 'legal_entity_id' => $this->branch['legal_entity_id'], 'code' => 'SECOND', 'name' => 'Second branch'])->assertOk()->json('data');
        $this->login($this->actor);
        $body = ['source_branch_id' => $this->branch['id'], 'destination_branch_id' => $destination['id'], 'lines' => [['sku_id' => $this->product['id'], 'quantity' => '2']]];
        $key = 'transfer-native-replay-key';
        $this->command('transfers', $body, $key)->assertOk();
        $this->revoke('inventory.transfer.manage');
        $this->assertDenied('transfers', $body, $key);
        $this->assertDatabaseCount('inv_transfers', 1);
    }

    public function test_stocktake_replay_requires_native_grant_without_starting_another_count(): void
    {
        $body = ['branch_id' => $this->branch['id']];
        $key = 'stocktake-native-replay-key';
        $this->command('stocktakes', $body, $key)->assertOk();
        $this->revoke('inventory.stocktake.manage');
        $this->assertDenied('stocktakes', $body, $key);
        $this->assertDatabaseCount('inv_stocktakes', 1);
    }

    public function test_mapping_ack_does_not_journal_organization_options_and_status_rechecks_native_grants(): void
    {
        $this->login($this->owner);
        $this->postJson('/admin/api/modules/accounting-tax/install')->assertOk();
        $this->postJson('/admin/api/modules/accounting-tax/enable')->assertOk();
        $organization = AcctOrganization::query()->create(['name' => 'Sensitive Accounting organization', 'legal_name' => 'Accounting', 'default_currency' => 'VND', 'status' => 'active']);
        $this->grant('accounting.document.create');
        $this->grant('accounting.integration.sync');
        $this->login($this->actor);
        $body = ['branch_id' => $this->branch['id'], 'organization_id' => $organization->id, 'enabled' => true, 'expected_version' => 0, 'sku_mappings' => []];
        $key = 'accounting-map-replay-key';
        $this->withHeader('Idempotency-Key', $key)->putJson('/admin/api/retail/integrations/mapping', $body)->assertOk()->assertJsonPath('data.accounting.enabled', true)->assertJsonMissingPath('data.accounting.organizations')->assertJsonMissingPath('data.accounting.items');
        $stored = DB::table('retail_commands')->where('actor_id', $this->actor->id)->where('command_key', $key)->value('response');
        $this->assertStringNotContainsString($organization->name, $stored);
        $this->getJson('/admin/api/retail/integrations?branch_id='.$this->branch['id'])->assertOk()->assertJsonPath('data.accounting.organizations.0.name', $organization->name);
        $this->revoke('accounting.integration.sync');
        $this->getJson('/admin/api/retail/commands/'.$key)->assertForbidden();
        $this->withHeader('Idempotency-Key', $key)->putJson('/admin/api/retail/integrations/mapping', $body)->assertForbidden();
        $this->assertDatabaseCount('retail_accounting_mappings', 1);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function pricedSale(): array
    {
        $shift = $this->command('shifts', ['branch_id' => $this->branch['id'], 'terminal_id' => $this->branch['terminals'][0]['id'], 'opening_minor' => '0'])->assertOk()->json('data');
        $sale = $this->command('sales', ['branch_id' => $this->branch['id'], 'terminal_id' => $this->branch['terminals'][0]['id'], 'shift_id' => $shift['id'], 'lines' => [['sku_id' => $this->product['id'], 'quantity' => '1']]])->assertOk()->json('data');

        return [$shift, $sale];
    }

    private function assertDenied(string $path, array $body, string $key): void
    {
        $this->command($path, $body, $key)->assertForbidden();
        $this->getJson('/admin/api/retail/commands/'.$key)->assertForbidden();
    }

    private function revoke(string $permission): void
    {
        // Bypass auth_version intentionally: the replay path must recheck current grants.
        $this->role->permissions()->detach(Permission::query()->where('key', $permission)->value('id'));
    }

    private function grant(string $permission): void
    {
        $this->role->permissions()->syncWithoutDetaching([Permission::query()->where('key', $permission)->value('id')]);
    }

    private function command(string $path, array $body, ?string $key = null): TestResponse
    {
        return $this->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson('/admin/api/retail/'.$path, $body);
    }

    private function login(Admin $admin): void
    {
        $admin = $admin->fresh();
        $this->actingAs($admin, 'admin')->withSession(['admin_auth_version' => $admin->auth_version]);
    }
}
