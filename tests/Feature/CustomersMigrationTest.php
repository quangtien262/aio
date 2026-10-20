<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Customers\Services\ConsumerCustomerService;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\FnbService;
use Tests\TestCase;

class CustomersMigrationTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    private array $branch;

    private string $book;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = Admin::query()->findOrFail(1);
        $this->owner->forceFill(['must_change_password' => false])->save();
        $this->login($this->owner);
        foreach (['merchandise', 'inventory', 'retail-pos', 'customers'] as $module) {
            $this->postJson('/admin/api/modules/'.$module.'/install')->assertOk();
            $this->postJson('/admin/api/modules/'.$module.'/enable')->assertOk();
        }
        $this->branch = $this->postCommand('/admin/api/retail/setup', ['name' => 'Migration chain'])->assertOk()->json('data');
        $this->book = $this->postCommand('/admin/api/customers/books', ['name' => 'Migration book', 'code' => 'MIGRATE'])->assertOk()->json('data.public_id');
    }

    public function test_actual_fnb_binding_preview_and_cutover_preserve_numeric_sources_and_private_consent(): void
    {
        $this->postJson('/admin/api/modules/fnb-pos/install')->assertOk();
        $this->postJson('/admin/api/modules/fnb-pos/enable')->assertOk();
        $fnb = app(FnbService::class);
        $setup = $fnb->onboard(new FnbContext('website-main', 0, $this->owner->id), ['outlet' => ['code' => 'MIGRATE', 'name' => 'Migration cafe', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND'], 'terminal' => ['code' => 'POS-01', 'name' => 'POS 01', 'type' => 'pos'], 'station' => ['code' => 'BAR', 'name' => 'Bar'], 'payment_methods' => [['code' => 'CASH', 'name' => 'Cash', 'kind' => 'cash']], 'table_count' => 0], 'migration-fnb-onboard')['resource'];
        $context = new FnbContext('website-main', (int) $setup['outlet']['id'], $this->owner->id, (int) $setup['terminal']['id']);
        $legacy = $fnb->createCustomer($context, ['name' => 'Cafe legacy buyer', 'phone' => '0901333444', 'notes' => 'Private FNB note', 'privacy_consent' => true, 'marketing_consent' => true], 'migration-fnb-customer')['resource'];
        $binding = $this->binding('fnb-pos', 'website', 'website-main');
        $this->migrate($binding, ['commit' => false])->assertOk()->assertJsonPath('data.valid', true)->assertJsonPath('data.row_count', 1)->assertJsonPath('data.created_count', 0);
        $this->assertDatabaseCount('cust_profiles', 0);
        $this->assertSame('legacy', app(ConsumerCustomerService::class)->status($this->owner, 'fnb-pos', (string) $context->outletId)['mode']);
        $key = 'migration-fnb-cutover-key';
        $this->migrate($binding, ['commit' => true, 'cutover' => true], $key)->assertOk()->assertJsonPath('data.created_count', 1)->assertJsonPath('data.cutover', true);
        $this->migrate($binding, ['commit' => true, 'cutover' => true], $key)->assertOk()->assertJsonPath('data.created_count', 1);
        $this->assertDatabaseCount('cust_profiles', 1);
        $this->assertDatabaseCount('cust_sources', 1);
        $this->assertDatabaseCount('cust_consents', 0);
        $this->assertNull(DB::table('cust_profiles')->value('notes'));
        $this->assertDatabaseHas('cust_sources', ['module_key' => 'fnb-pos', 'source_id' => (string) $legacy['id']]);
        $resolved = app(ConsumerCustomerService::class)->resolveLegacy($this->owner, 'fnb-pos', (string) $context->outletId, (string) $legacy['id']);
        $this->assertTrue(Str::isUuid($resolved['public_id']));
        $this->assertDatabaseHas('fnb_customer_profiles', ['id' => $legacy['id'], 'notes' => 'Private FNB note']);
        $this->assertSame('shared', app(ConsumerCustomerService::class)->status($this->owner, 'fnb-pos', (string) $context->outletId)['mode']);
    }

    public function test_retail_household_contact_duplicates_create_separate_profiles_without_forced_merge(): void
    {
        $first = $this->legacy('Household A', '0901444555');
        $second = $this->legacy('Household B', '0901444555');
        $binding = $this->binding();
        $preview = $this->migrate($binding, ['commit' => false])->assertOk()->assertJsonPath('data.valid', true)->json('data');
        $this->assertNotEmpty($preview['warnings']);
        $this->assertSame('create_separate_profile', $preview['warnings'][0]['action']);
        $this->migrate($binding, ['commit' => true, 'cutover' => true])->assertOk()->assertJsonPath('data.created_count', 2)->assertJsonPath('data.cutover', true);
        $this->assertDatabaseCount('cust_profiles', 2);
        $this->assertDatabaseCount('retail_customers', 2);
        $this->assertDatabaseCount('cust_sources', 2);
        $this->assertSame(2, DB::table('cust_contacts')->where('normalized', '+84901444555')->count());
        $sources = DB::table('cust_sources')->whereIn('source_id', [$first['id'], $second['id']])->pluck('profile_id')->all();
        $this->assertCount(2, array_unique($sources));
    }

    public function test_legacy_change_after_backfill_blocks_cutover_instead_of_silently_overwriting_shared_profile(): void
    {
        $legacy = $this->legacy('Original migration source', '0901666777');
        $binding = $this->binding();
        $this->migrate($binding, ['commit' => true, 'cutover' => false])->assertOk()->assertJsonPath('data.created_count', 1);
        $this->withHeader('Idempotency-Key', 'legacy-source-change-key')->putJson('/admin/api/retail/customers/'.$legacy['id'], ['branch_id' => $this->branch['id'], 'name' => 'Changed after backfill', 'phone' => '0901666777', 'expected_version' => 1])->assertOk();
        $this->migrate($binding, ['commit' => false])->assertOk()->assertJsonPath('data.valid', false)->assertJsonPath('data.existing_count', 1);
        $this->migrate($binding, ['commit' => true, 'cutover' => true])->assertUnprocessable();
        $this->assertSame('legacy', DB::table('cust_bindings')->where('public_id', $binding)->value('mode'));
        $this->assertSame('Original migration source', DB::table('cust_profiles')->value('name'));
        $this->assertDatabaseCount('cust_profiles', 1);
    }

    public function test_book_manager_with_only_one_branch_view_cannot_configure_or_migrate_whole_chain(): void
    {
        $this->legacy('Native scope fixture');
        $binding = $this->binding();
        $actor = Admin::factory()->create(['must_change_password' => false, 'is_active' => true, 'status' => 'active']);
        $role = Role::query()->create(['key' => 'book-manager-local-'.$actor->id, 'name' => 'Local book manager', 'status' => 'active']);
        $role->permissions()->sync(Permission::query()->whereIn('key', ['customers.view', 'customers.settings', 'retail.view', 'retail.customer.view'])->pluck('id')->all());
        AdminRoleAssignment::query()->create(['admin_id' => $actor->id, 'role_id' => $role->id, 'scope_type' => 'global', 'scope_value' => null, 'assigned_by' => $this->owner->id]);
        DB::table('cust_book_grants')->insert(['book_id' => DB::table('cust_books')->where('public_id', $this->book)->value('id'), 'admin_id' => $actor->id, 'role' => 'manager', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('retail_memberships')->insert(['admin_id' => $actor->id, 'chain_id' => DB::table('retail_chains')->where('public_id', $this->branch['chain_id'])->value('id'), 'branch_id' => $this->branch['database_id'], 'preset' => 'manager', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->login($actor);
        $this->migrate($binding, ['commit' => false])->assertForbidden();
        $this->migrate($binding, ['commit' => true, 'cutover' => true])->assertForbidden();
        $this->assertSame('legacy', DB::table('cust_bindings')->where('public_id', $binding)->value('mode'));
        $this->assertDatabaseCount('cust_profiles', 0);
    }

    public function test_binding_and_migration_replays_require_live_native_hq_authority(): void
    {
        $this->legacy('Native replay fixture');
        $actor = Admin::factory()->create(['must_change_password' => false, 'is_active' => true, 'status' => 'active']);
        $role = Role::query()->create(['key' => 'migration-hq-'.$actor->id, 'name' => 'Migration HQ', 'status' => 'active']);
        $role->permissions()->sync(Permission::query()->whereIn('key', ['customers.view', 'customers.settings', 'retail.view', 'retail.customer.view', 'retail.setup.manage'])->pluck('id')->all());
        AdminRoleAssignment::query()->create(['admin_id' => $actor->id, 'role_id' => $role->id, 'scope_type' => 'global', 'scope_value' => null, 'assigned_by' => $this->owner->id]);
        DB::table('cust_book_grants')->insert(['book_id' => DB::table('cust_books')->where('public_id', $this->book)->value('id'), 'admin_id' => $actor->id, 'role' => 'manager', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('retail_memberships')->insert(['admin_id' => $actor->id, 'chain_id' => DB::table('retail_chains')->where('public_id', $this->branch['chain_id'])->value('id'), 'branch_id' => 0, 'preset' => 'hq', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->login($actor);
        $payload = ['module_key' => 'retail-pos', 'scope_type' => 'chain', 'scope_key' => $this->branch['chain_id'], 'abilities' => ['lookup', 'create', 'view', 'update', 'attach', 'history']];
        $binding = $this->postCommand('/admin/api/customers/books/'.$this->book.'/bindings', $payload, 'native-hq-binding-key')->assertOk()->json('data.public_id');
        $this->migrate($binding, ['commit' => true, 'cutover' => true], 'native-hq-migration-key')->assertOk()->assertJsonPath('data.created_count', 1);

        // Direct membership revocation must be observed even without an auth-version bump.
        DB::table('retail_memberships')->where('admin_id', $actor->id)->update(['is_active' => false]);
        $this->postCommand('/admin/api/customers/books/'.$this->book.'/bindings', $payload, 'native-hq-binding-key')->assertForbidden();
        $this->migrate($binding, ['commit' => true, 'cutover' => true], 'native-hq-migration-key')->assertForbidden();
        $this->getJson('/admin/api/customers/commands/native-hq-binding-key')->assertForbidden();
        $this->getJson('/admin/api/customers/commands/native-hq-migration-key')->assertForbidden();
        $this->assertDatabaseCount('cust_profiles', 1);
        $this->assertSame('shared', DB::table('cust_bindings')->where('public_id', $binding)->value('mode'));
    }

    public function test_migration_batches_keep_legacy_until_the_final_source_is_mapped(): void
    {
        $chainId = DB::table('retail_chains')->where('public_id', $this->branch['chain_id'])->value('id');
        $rows = [];
        for ($index = 0; $index < 501; $index++) {
            $rows[] = ['public_id' => (string) Str::uuid(), 'chain_id' => $chainId, 'name' => 'Batch buyer '.$index, 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('retail_customers')->insert($rows);
        $binding = $this->binding();
        $payload = ['commit' => true, 'cutover' => true];
        $this->migrate($binding, $payload, 'migration-batch-first-key')->assertOk()->assertJsonPath('data.created_count', 500)->assertJsonPath('data.has_more', true)->assertJsonPath('data.cutover', false);
        $this->assertSame('legacy', DB::table('cust_bindings')->where('public_id', $binding)->value('mode'));
        $this->assertDatabaseCount('cust_profiles', 500);
        $this->migrate($binding, $payload, 'migration-batch-first-key')->assertOk()->assertJsonPath('data.created_count', 500)->assertJsonPath('data.cutover', false);
        $this->assertDatabaseCount('cust_profiles', 500);
        $this->migrate($binding, $payload, 'migration-batch-final-key')->assertOk()->assertJsonPath('data.created_count', 1)->assertJsonPath('data.existing_count', 500)->assertJsonPath('data.has_more', false)->assertJsonPath('data.cutover', true);
        $this->assertDatabaseCount('cust_profiles', 501);
        $this->assertDatabaseCount('cust_sources', 501);
        $this->assertSame('shared', DB::table('cust_bindings')->where('public_id', $binding)->value('mode'));
    }

    private function legacy(string $name, ?string $phone = null): array
    {
        return $this->postCommand('/admin/api/retail/customers', ['branch_id' => $this->branch['id'], 'name' => $name, 'phone' => $phone])->assertOk()->json('data');
    }

    private function binding(string $module = 'retail-pos', string $type = 'chain', ?string $scope = null): string
    {
        return $this->postCommand('/admin/api/customers/books/'.$this->book.'/bindings', ['module_key' => $module, 'scope_type' => $type, 'scope_key' => $scope ?? $this->branch['chain_id'], 'abilities' => ['lookup', 'create', 'view', 'update', 'attach', 'history']])->assertOk()->json('data.public_id');
    }

    private function migrate(string $binding, array $data, ?string $key = null): TestResponse
    {
        return $this->postCommand('/admin/api/customers/bindings/'.$binding.'/migration', $data, $key);
    }

    private function postCommand(string $path, array $data, ?string $key = null): TestResponse
    {
        return $this->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson($path, $data);
    }

    private function login(Admin $actor): void
    {
        $actor = $actor->fresh();
        $this->actingAs($actor, 'admin')->withSession(['admin_auth_version' => $actor->auth_version]);
    }
}
