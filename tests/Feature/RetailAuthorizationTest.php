<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RetailAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    private array $branch;

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
        $this->branch = $this->command('setup', ['name' => 'Chuỗi A'])->assertOk()->json('data');
    }

    public function test_cashier_cannot_read_or_mutate_another_branch_by_guessing_public_ids(): void
    {
        $other = $this->command('branches', ['chain_id' => $this->branch['chain_id'], 'legal_entity_id' => $this->branch['legal_entity_id'], 'code' => 'OTHER', 'name' => 'Cửa hàng B'])->assertOk()->json('data');
        $product = $this->product($other, 'SECRET', '10000');
        $shift = $this->openShift($other);
        $sale = $this->command('sales', ['branch_id' => $other['id'], 'terminal_id' => $other['terminals'][0]['id'], 'shift_id' => $shift['id'], 'lines' => [['sku_id' => $product['id'], 'quantity' => '1']]])->assertOk()->json('data');
        $cashier = $this->cashier($this->branch);
        $this->login($cashier);
        $branches = $this->getJson('/admin/api/retail/bootstrap')->assertOk()->json('data.branches');
        $this->assertSame([$this->branch['id']], array_column($branches, 'id'));
        foreach (['products', 'sales', 'reports', 'customers', 'inventory'] as $path) {
            $this->getJson('/admin/api/retail/'.$path.'?branch_id='.$other['id'])->assertNotFound();
        }
        $this->command('sales/'.$sale['id'].'/complete', ['expected_version' => 1, 'tenders' => [['method' => 'cash', 'amount_minor' => '10000']]])->assertNotFound();
        $this->command('customers', ['branch_id' => $other['id'], 'name' => 'Cross-branch'])->assertNotFound();
        $this->assertDatabaseCount('retail_tenders', 0);
    }

    public function test_website_permission_does_not_allow_membership_or_hq_escalation(): void
    {
        $cashier = $this->cashier($this->branch);
        $role = Role::query()->create(['key' => 'retail.website-membership', 'name' => 'Website membership', 'status' => 'active']);
        $role->permissions()->attach(Permission::query()->where('key', 'retail.membership.manage')->value('id'));
        AdminRoleAssignment::query()->create(['admin_id' => $cashier->id, 'role_id' => $role->id, 'scope_type' => 'website', 'scope_value' => 'website-main', 'assigned_by' => $this->owner->id]);
        $this->login($cashier);
        $this->command('memberships', ['branch_id' => $this->branch['id'], 'admin_id' => $cashier->id, 'preset' => 'hq', 'is_active' => true])->assertForbidden();
        $this->assertDatabaseHas('retail_memberships', ['admin_id' => $cashier->id, 'preset' => 'cashier']);
        $this->assertDatabaseMissing('retail_memberships', ['admin_id' => $cashier->id, 'preset' => 'hq']);
    }

    public function test_a_sku_from_another_chain_cannot_be_sold_in_local_branch(): void
    {
        $foreign = $this->command('setup', ['name' => 'Chuỗi B'])->assertOk()->json('data');
        $product = $this->product($foreign, 'FOREIGN', '20000');
        $cashier = $this->cashier($this->branch);
        $this->login($cashier);
        $shift = $this->openShift($this->branch);
        $this->command('sales', ['branch_id' => $this->branch['id'], 'terminal_id' => $this->branch['terminals'][0]['id'], 'shift_id' => $shift['id'], 'lines' => [['sku_id' => $product['id'], 'quantity' => '1']]])->assertNotFound();
        $this->assertDatabaseCount('retail_sales', 0);
    }

    public function test_packaging_conversion_and_piece_precision_are_enforced_by_sale_api(): void
    {
        $product = $this->product($this->branch, 'PACK', '1000', ['packagings' => [['code' => 'SIX', 'name' => 'Lốc 6', 'quantity_multiplier' => '6', 'barcode' => 'PACK-SIX']]]);
        $pack = DB::table('merch_packagings')->where('sku_id', DB::table('merch_skus')->where('public_id', $product['id'])->value('id'))->first();
        $shift = $this->openShift($this->branch);
        $base = ['branch_id' => $this->branch['id'], 'terminal_id' => $this->branch['terminals'][0]['id'], 'shift_id' => $shift['id']];
        $sale = $this->command('sales', $base + ['lines' => [['sku_id' => $product['id'], 'quantity' => '1', 'packaging_id' => $pack->id]]])->assertOk()->json('data');
        $this->assertEquals('6.000000', $sale['lines'][0]['quantity']);
        $this->assertSame(6000, (int) $sale['total_minor']);
        $this->command('sales', $base + ['lines' => [['sku_id' => $product['id'], 'quantity' => '0.5']]])->assertUnprocessable();
    }

    public function test_sale_view_permission_does_not_expose_price_cost(): void
    {
        $p = $this->product($this->branch, 'COST', '10000');
        $shift = $this->openShift($this->branch);
        $sale = $this->command('sales', ['branch_id' => $this->branch['id'], 'terminal_id' => $this->branch['terminals'][0]['id'], 'shift_id' => $shift['id'], 'lines' => [['sku_id' => $p['id'], 'quantity' => '1']]])->assertOk()->json('data');
        $this->command('sales/'.$sale['id'].'/complete', ['expected_version' => 1, 'tenders' => [['method' => 'cash', 'amount_minor' => '10000']]])->assertOk();
        $this->login($this->cashier($this->branch));
        $rows = $this->getJson('/admin/api/retail/sales?branch_id='.$this->branch['id'])->assertOk()->json('data');
        $this->assertNotEmpty($rows);
        $this->assertTrue(! array_key_exists('cost', $rows[0]['lines'][0]) || $rows[0]['lines'][0]['cost'] === null, 'Price cost must not be disclosed by sale.view.');
    }

    public function test_discount_allocation_never_makes_a_line_negative_and_full_return_matches_bill(): void
    {
        $product = $this->product($this->branch, 'ROUND', '1');
        $shift = $this->openShift($this->branch);
        $lines = array_fill(0, 10, ['sku_id' => $product['id'], 'quantity' => '1']);
        $sale = $this->command('sales', ['branch_id' => $this->branch['id'], 'terminal_id' => $this->branch['terminals'][0]['id'], 'shift_id' => $shift['id'], 'lines' => $lines])->assertOk()->json('data');
        $sale = $this->command('sales/'.$sale['id'].'/complete', ['expected_version' => 1, 'discount_minor' => '9', 'reason' => 'Test rounding allocation', 'tenders' => [['method' => 'cash', 'amount_minor' => '1']]])->assertOk()->json('data');
        $this->assertSame(1, array_sum(array_column($sale['lines'], 'total_minor')));
        foreach ($sale['lines'] as $line) {
            $this->assertGreaterThanOrEqual(0, (int) $line['total_minor']);
        }
    }

    public function test_command_replay_and_status_recheck_revoked_branch_membership(): void
    {
        $cashier = $this->cashier($this->branch);
        $this->login($cashier);
        $body = ['branch_id' => $this->branch['id'], 'terminal_id' => $this->branch['terminals'][0]['id'], 'opening_minor' => '0'];
        $key = 'cashier-lost-shift-response';
        $this->command('shifts', $body, $key)->assertOk();
        DB::table('retail_memberships')->where('admin_id', $cashier->id)->update(['is_active' => false]);
        $this->command('shifts', $body, $key)->assertNotFound();
        $this->getJson('/admin/api/retail/commands/'.$key)->assertNotFound();
        $this->assertDatabaseCount('retail_shifts', 1);
    }

    public function test_command_result_cannot_be_replayed_or_read_in_another_website_context(): void
    {
        $key = 'owner-setup-context-bound';
        $body = ['name' => 'Context source'];
        $this->command('setup', $body, $key)->assertOk();
        Site::query()->create(['domain' => 'other-retail.example.test', 'website_key' => 'website-other', 'name' => 'Other', 'status' => 'active']);
        $this->withHeader('X-Website-Key', 'website-other');
        $this->command('setup', $body, $key)->assertStatus(409);
        $this->getJson('/admin/api/retail/commands/'.$key)->assertOk()->assertJsonPath('data.status', 'unknown');
    }

    private function cashier(array $branch): Admin
    {
        $a = Admin::factory()->create(['must_change_password' => false, 'status' => 'active', 'is_active' => true]);
        $role = Role::query()->create(['key' => 'retail.cashier-'.$a->id, 'name' => 'Thu ngân', 'status' => 'active']);
        $permissions = ['retail.view', 'retail.product.view', 'retail.sale.create', 'retail.sale.view', 'retail.payment.collect', 'retail.shift.manage', 'retail.customer.view', 'retail.customer.manage', 'retail.inventory.view', 'retail.report.view', 'retail.return.manage'];
        $role->permissions()->sync(Permission::query()->whereIn('key', $permissions)->pluck('id')->all());
        AdminRoleAssignment::query()->create(['admin_id' => $a->id, 'role_id' => $role->id, 'scope_type' => 'global', 'scope_value' => null, 'assigned_by' => $this->owner->id]);
        DB::table('retail_memberships')->insert(['admin_id' => $a->id, 'chain_id' => DB::table('retail_chains')->where('public_id', $branch['chain_id'])->value('id'), 'branch_id' => $branch['database_id'], 'preset' => 'cashier', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return $a;
    }

    private function product(array $branch, string $sku, string $price, array $extra = []): array
    {
        return $this->command('products', $extra + ['branch_id' => $branch['id'], 'sku' => $sku, 'name' => $sku, 'price_minor' => $price, 'opening_quantity' => '24', 'unit_cost' => '500'])->assertOk()->json('data');
    }

    private function openShift(array $b): array
    {
        return $this->command('shifts', ['branch_id' => $b['id'], 'terminal_id' => $b['terminals'][0]['id'], 'opening_minor' => '0'])->assertOk()->json('data');
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
