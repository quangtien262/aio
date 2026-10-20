<?php

namespace Tests\Feature;

use App\Models\AcctOrganization;
use App\Models\AcctParty;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\RetailPos\Services\RetailCustomerAccountingAdapter;
use Modules\RetailPos\Services\RetailCustomersProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RetailSharedCustomersTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    private array $branch;

    private array $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = Admin::query()->findOrFail(1);
        $this->owner->forceFill(['must_change_password' => false])->save();
        $this->actingAs($this->owner, 'admin')->withSession(['admin_auth_version' => $this->owner->auth_version]);
        foreach (['merchandise', 'inventory', 'retail-pos'] as $key) {
            $this->postJson('/admin/api/modules/'.$key.'/install')->assertOk();
            $this->postJson('/admin/api/modules/'.$key.'/enable')->assertOk();
        }
        $this->branch = $this->command('setup', ['name' => 'Retail customer fixture'])->assertOk()->json('data');
        $this->product = $this->command('products', ['branch_id' => $this->branch['id'], 'sku' => 'CUSTOMER-SKU', 'name' => 'Customer product', 'price_minor' => '10000', 'opening_quantity' => '24', 'unit_cost' => '5000'])->assertOk()->json('data');
    }

    public function test_legacy_customer_works_without_customers_module_and_sale_snapshot_is_immutable(): void
    {
        $customer = $this->command('customers', ['branch_id' => $this->branch['id'], 'name' => 'Original legacy buyer', 'phone' => '0901000000'])->assertOk()->json('data');
        $sale = $this->sale(['customer_id' => $customer['id']]);
        $this->assertSame('Original legacy buyer', $sale['buyer_snapshot']['name']);
        $this->assertNull($sale['shared_customer_id']);
        DB::table('retail_customers')->where('public_id', $customer['id'])->update(['name' => 'Changed legacy buyer']);
        $this->complete($sale)->assertOk()->assertJsonPath('data.buyer_snapshot.name', 'Original legacy buyer');
        $this->assertNotNull(DB::table('retail_sales')->value('customer_id'));
    }

    public function test_shared_branch_selects_book_customer_and_never_rewrites_buyer_history(): void
    {
        $profile = $this->sharedProfile();
        $this->getJson('/admin/api/retail/customer-context?branch_id='.$this->branch['id'])->assertOk()->assertJsonPath('data.mode', 'shared');
        $sale = $this->sale(['shared_customer_id' => $profile]);
        $this->assertSame($profile, $sale['shared_customer_id']);
        $this->assertNull($sale['customer_id']);
        DB::table('cust_profiles')->where('public_id', $profile)->update(['name' => 'Changed shared buyer', 'version' => 2]);
        $this->complete($sale)->assertOk()->assertJsonPath('data.buyer_snapshot.name', 'Original shared buyer');
        $this->assertDatabaseCount('retail_customers', 0);
    }

    public function test_cutover_never_falls_back_to_legacy_on_disabled_customers(): void
    {
        $this->sharedProfile();
        DB::table('module_installations')->where('key', 'customers')->update(['status' => 'disabled']);
        $this->getJson('/admin/api/retail/customer-context?branch_id='.$this->branch['id'])->assertOk()->assertJsonPath('data.mode', 'shared')->assertJsonPath('data.ready', false);
        $this->getJson('/admin/api/retail/customers?branch_id='.$this->branch['id'])->assertStatus(503);
        $this->command('customers', ['branch_id' => $this->branch['id'], 'name' => 'No fallback'])->assertStatus(503);
        $this->assertDatabaseCount('retail_customers', 0);
    }

    public function test_anonymous_sales_remain_available_when_shared_customers_is_disabled(): void
    {
        $this->sharedProfile();
        DB::table('module_installations')->where('key', 'customers')->update(['status' => 'disabled']);
        $sale = $this->sale([]);
        $this->assertNull($sale['shared_customer_id']);
        $this->assertNull($sale['buyer_snapshot']);
        $this->complete($sale)->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertDatabaseCount('retail_tenders', 1);
        $this->assertDatabaseCount('retail_customers', 0);
    }

    public function test_shared_selection_cannot_reference_another_book(): void
    {
        $this->sharedProfile();
        $other = $this->profile($this->book('OTHER'), 'Foreign buyer');
        $shift = $this->shift();
        $this->command('sales', ['branch_id' => $this->branch['id'], 'terminal_id' => $this->branch['terminals'][0]['id'], 'shift_id' => $shift['id'], 'shared_customer_id' => $other, 'lines' => [['sku_id' => $this->product['id'], 'quantity' => '1']]])->assertNotFound();
        $this->assertDatabaseCount('retail_sales', 0);
    }

    public function test_shared_history_includes_explicitly_mapped_legacy_sales_with_original_buyer_snapshot(): void
    {
        $legacy = $this->command('customers', ['branch_id' => $this->branch['id'], 'name' => 'Historical legacy buyer'])->assertOk()->json('data');
        $sale = $this->sale(['customer_id' => $legacy['id']]);
        $this->complete($sale)->assertOk();
        $shared = $this->sharedProfile();
        $profile = DB::table('cust_profiles')->where('public_id', $shared)->first();
        DB::table('cust_sources')->insert(['website_key' => 'website-main', 'book_id' => $profile->book_id, 'profile_id' => $profile->id, 'module_key' => 'retail-pos', 'scope_type' => 'chain', 'scope_key' => $this->branch['chain_id'], 'source_id' => $legacy['id'], 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson('/admin/api/retail/customers/'.$shared.'/history?branch_id='.$this->branch['id'])->assertOk()->assertJsonPath('data.scope', 'branch')->assertJsonPath('data.items.0.id', $sale['id'])->assertJsonPath('data.items.0.buyer_snapshot.name', 'Historical legacy buyer');
        $this->assertSame('Historical legacy buyer', DB::table('retail_customers')->where('public_id', $legacy['id'])->value('name'));
        $activities = app(RetailCustomersProvider::class)->activities($this->owner, 'website-main', $this->branch['chain_id'], $shared);
        $this->assertSame($sale['id'], $activities[0]['resource_id']);
        $this->assertSame('10000', $activities[0]['amount_minor']);
        $this->assertArrayNotHasKey('buyer_snapshot', $activities[0]);
        $operator = $this->operator(['retail.view', 'retail.customer.view']);
        $this->assertSame([], app(RetailCustomersProvider::class)->activities($operator, 'website-main', $this->branch['chain_id'], $shared));
    }

    public function test_disabling_shared_customers_after_quote_blocks_collection_without_stock_or_tender_side_effect(): void
    {
        $shared = $this->sharedProfile();
        $sale = $this->sale(['shared_customer_id' => $shared]);
        DB::table('module_installations')->where('key', 'customers')->update(['status' => 'disabled']);
        $this->complete($sale)->assertStatus(503);
        $this->assertDatabaseCount('retail_tenders', 0);
        $this->assertDatabaseCount('inv_reservations', 0);
        $this->assertSame('priced', DB::table('retail_sales')->where('public_id', $sale['id'])->value('status'));
    }

    public function test_merged_profile_history_follows_aliases_without_rewriting_completed_sale_identity(): void
    {
        $source = $this->sharedProfile();
        $sale = $this->sale(['shared_customer_id' => $source]);
        $this->complete($sale)->assertOk();
        $profile = DB::table('cust_profiles')->where('public_id', $source)->first();
        $target = $this->profile($profile->book_id, 'Canonical buyer');
        $book = DB::table('cust_books')->where('id', $profile->book_id)->value('public_id');
        $this->withHeader('Idempotency-Key', 'retail-merge-history-key')->postJson('/admin/api/customers/books/'.$book.'/profiles/'.$source.'/merge', ['target_id' => $target, 'expected_version' => 1, 'target_version' => 1, 'reason' => 'Xác nhận hai hồ sơ của cùng khách hàng'])->assertOk();
        $this->getJson('/admin/api/retail/customers/'.$target.'/history?branch_id='.$this->branch['id'])->assertOk()->assertJsonPath('data.items.0.id', $sale['id'])->assertJsonPath('data.items.0.shared_customer_id', $source)->assertJsonPath('data.items.0.buyer_snapshot.name', 'Original shared buyer');
        $this->assertSame($source, DB::table('retail_sales')->where('public_id', $sale['id'])->value('shared_customer_id'));
    }

    public function test_binding_configuration_requires_global_setup_and_live_chain_hq_membership(): void
    {
        $operator = $this->operator(['retail.view', 'retail.setup.manage', 'retail.customer.view']);
        app(RetailCustomersProvider::class)->authorize($this->owner, 'website-main', $this->branch['id'], 'configure');
        try {
            app(RetailCustomersProvider::class)->authorize($operator, 'website-main', $this->branch['id'], 'configure');
            $this->fail('A branch operator cannot reconfigure a whole-chain customer binding.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_provider_uses_live_membership_and_rejects_an_untrusted_website(): void
    {
        $operator = $this->operator(['retail.view', 'retail.customer.view']);
        $provider = app(RetailCustomersProvider::class);
        $this->assertSame(['type' => 'chain', 'key' => $this->branch['chain_id']], $provider->bindingScope('website-main', $this->branch['id']));
        $provider->authorize($operator, 'website-main', $this->branch['id'], 'view');
        DB::table('retail_memberships')->where('admin_id', $operator->id)->update(['is_active' => false]);
        try {
            $provider->authorize($operator, 'website-main', $this->branch['id'], 'view');
            $this->fail('Revoked membership must not authorize shared customer access.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
        try {
            $provider->authorize($this->owner, 'website-foreign', $this->branch['id'], 'view');
            $this->fail('Untrusted website must be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_sale_reader_without_customer_permission_cannot_read_buyer_contacts(): void
    {
        $customer = $this->command('customers', ['branch_id' => $this->branch['id'], 'name' => 'Receipt buyer', 'phone' => '0901000000', 'email' => 'private@example.test'])->assertOk()->json('data');
        $sale = $this->sale(['customer_id' => $customer['id']]);
        $operator = $this->operator(['retail.view', 'retail.sale.view']);
        $this->actingAs($operator, 'admin')->withSession(['admin_auth_version' => $operator->auth_version]);
        $this->getJson('/admin/api/retail/sales?branch_id='.$this->branch['id'])->assertOk()->assertJsonPath('data.0.id', $sale['id'])->assertJsonMissingPath('data.0.buyer_snapshot.phone')->assertJsonMissingPath('data.0.buyer_snapshot.email');
    }

    public function test_accounting_mapping_is_explicit_idempotent_and_preserves_accountant_owned_values(): void
    {
        $profile = $this->sharedProfile();
        $organization = $this->accountingOrganization();
        $adapter = app(RetailCustomerAccountingAdapter::class);
        $first = $adapter->sync($this->owner, $this->branch['id'], $profile, $organization->id, 'party-source-stable-key');
        AcctParty::query()->whereKey($first['party_id'])->update(['name' => 'Accountant legal name']);
        $again = $adapter->sync($this->owner, $this->branch['id'], $profile, $organization->id, 'party-source-stable-key');
        $newKey = $adapter->sync($this->owner, $this->branch['id'], $profile, $organization->id, 'party-source-another-key');
        $this->assertSame($first, $again);
        $this->assertSame($first['party_id'], $newKey['party_id']);
        $this->assertFalse($newKey['created']);
        $this->assertSame('Accountant legal name', AcctParty::query()->sole()->name);
        $this->assertDatabaseCount('acct_party_sources', 1);
        $this->assertDatabaseCount('acct_documents', 0);
    }

    public function test_accounting_sync_rechecks_native_organization_grants_before_replay(): void
    {
        $profile = $this->sharedProfile();
        $organization = $this->accountingOrganization();
        $operator = $this->operator(['retail.view', 'retail.customer.view', 'accounting.party.manage', 'accounting.integration.sync']);
        $adapter = app(RetailCustomerAccountingAdapter::class);
        $adapter->sync($operator, $this->branch['id'], $profile, $organization->id, 'native-party-replay-key');
        DB::table('permission_role')->where('permission_id', Permission::query()->where('key', 'accounting.integration.sync')->value('id'))->where('role_id', $operator->roles()->sole()->id)->delete();
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(0);
        try {
            $adapter->sync($operator, $this->branch['id'], $profile, $organization->id, 'native-party-replay-key');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            throw $e;
        }
    }

    private function sharedProfile(): string
    {
        $this->postJson('/admin/api/modules/customers/install')->assertOk();
        $this->postJson('/admin/api/modules/customers/enable')->assertOk();
        $book = $this->book('SHARED');
        DB::table('cust_bindings')->insert(['public_id' => (string) Str::uuid(), 'website_key' => 'website-main', 'book_id' => $book, 'module_key' => 'retail-pos', 'scope_type' => 'chain', 'scope_key' => $this->branch['chain_id'], 'abilities' => json_encode(['lookup', 'view', 'create', 'update', 'attach', 'history']), 'mode' => 'shared', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return $this->profile($book, 'Original shared buyer');
    }

    private function book(string $code): int
    {
        return DB::table('cust_books')->insertGetId(['public_id' => (string) Str::uuid(), 'website_key' => 'website-main', 'code' => $code, 'name' => $code, 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function profile(int $book, string $name): string
    {
        $uuid = (string) Str::uuid();
        DB::table('cust_profiles')->insert(['public_id' => $uuid, 'book_id' => $book, 'code' => 'C-'.Str::random(10), 'type' => 'person', 'name' => $name, 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return $uuid;
    }

    private function accountingOrganization(): AcctOrganization
    {
        $this->postJson('/admin/api/modules/accounting-tax/install')->assertOk();
        $this->postJson('/admin/api/modules/accounting-tax/enable')->assertOk();
        $organization = AcctOrganization::query()->create(['name' => 'Customer Accounting', 'legal_name' => 'Customer Accounting', 'default_currency' => 'VND', 'status' => 'active']);
        DB::table('retail_legal_accounting_links')->insert(['legal_entity_id' => DB::table('retail_legal_entities')->where('public_id', $this->branch['legal_entity_id'])->value('id'), 'organization_id' => $organization->id, 'created_at' => now(), 'updated_at' => now()]);

        return $organization;
    }

    private function operator(array $permissions): Admin
    {
        $actor = Admin::factory()->create(['must_change_password' => false, 'status' => 'active', 'is_active' => true]);
        $role = Role::query()->create(['key' => 'retail-customer-test-'.$actor->id, 'name' => 'Customer operator', 'status' => 'active']);
        $role->permissions()->sync(Permission::query()->whereIn('key', $permissions)->pluck('id')->all());
        AdminRoleAssignment::query()->create(['admin_id' => $actor->id, 'role_id' => $role->id, 'scope_type' => 'global', 'scope_value' => null, 'assigned_by' => $this->owner->id]);
        DB::table('retail_memberships')->insert(['admin_id' => $actor->id, 'chain_id' => DB::table('retail_chains')->where('public_id', $this->branch['chain_id'])->value('id'), 'branch_id' => $this->branch['database_id'], 'preset' => 'cashier', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return $actor->fresh();
    }

    private function shift(): array
    {
        return $this->command('shifts', ['branch_id' => $this->branch['id'], 'terminal_id' => $this->branch['terminals'][0]['id'], 'opening_minor' => '0'])->assertOk()->json('data');
    }

    private function sale(array $customer): array
    {
        $shift = $this->shift();

        return $this->command('sales', $customer + ['branch_id' => $this->branch['id'], 'terminal_id' => $this->branch['terminals'][0]['id'], 'shift_id' => $shift['id'], 'lines' => [['sku_id' => $this->product['id'], 'quantity' => '1']]])->assertOk()->json('data');
    }

    private function complete(array $sale): TestResponse
    {
        return $this->command('sales/'.$sale['id'].'/complete', ['expected_version' => $sale['version'], 'tenders' => [['method' => 'cash', 'amount_minor' => '10000']]]);
    }

    private function command(string $path, array $data): TestResponse
    {
        return $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/admin/api/retail/'.$path, $data);
    }
}
