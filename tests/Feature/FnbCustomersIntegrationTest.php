<?php

namespace Tests\Feature;

use App\Models\Admin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Customers\Services\CustomerService;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\FnbCustomerDirectory;
use Modules\FnbPos\Services\FnbCustomerService;
use Modules\FnbPos\Services\FnbCustomersProvider;
use Modules\FnbPos\Services\FnbService;
use Modules\FnbPos\Services\Security\FnbStaffAssignmentService;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class FnbCustomersIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    private FnbService $fnb;

    private FnbContext $context;

    private int $day;

    private int $book;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = Admin::query()->findOrFail(1);
        $this->owner->forceFill(['must_change_password' => false])->save();
        $this->actingAs($this->owner, 'admin')->withSession(['admin_auth_version' => $this->owner->auth_version]);
        foreach (['fnb-pos', 'customers'] as $module) {
            $this->postJson('/admin/api/modules/'.$module.'/install')->assertOk();
            $this->postJson('/admin/api/modules/'.$module.'/enable')->assertOk();
        }
        $this->fnb = app(FnbService::class);
        $setup = $this->fnb->onboard(new FnbContext('website-main', 0, 1), [
            'outlet' => ['code' => 'SHARED', 'name' => 'Shared Cafe', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND'],
            'terminal' => ['code' => 'POS-01', 'name' => 'POS 01', 'type' => 'pos'], 'station' => ['code' => 'BAR', 'name' => 'Bar'],
            'payment_methods' => [['code' => 'CASH', 'name' => 'Cash', 'kind' => 'cash']], 'table_count' => 0,
        ], 'fnb-shared-onboarding')['resource'];
        $this->context = new FnbContext('website-main', (int) $setup['outlet']['id'], 1, (int) $setup['terminal']['id']);
        $this->day = $this->fnb->openBusinessDay($this->context, now()->toDateString(), 'fnb-shared-day')['resource']['id'];
        $this->book = DB::table('cust_books')->insertGetId(['public_id' => (string) Str::uuid(), 'website_key' => 'website-main', 'code' => 'FNB', 'name' => 'Shared directory', 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function shared(): void
    {
        DB::table('cust_bindings')->insert(['public_id' => (string) Str::uuid(), 'website_key' => 'website-main', 'book_id' => $this->book, 'module_key' => 'fnb-pos', 'scope_type' => 'website', 'scope_key' => 'website-main', 'mode' => 'shared', 'abilities' => json_encode(['lookup', 'create', 'view', 'update', 'attach', 'history'], JSON_THROW_ON_ERROR), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function create(string $name = 'Shared Alice', string $key = 'fnb-shared-alice'): array
    {
        return $this->fnb->createCustomer($this->context, ['name' => $name, 'phone' => '0901234567'], $key)['resource'];
    }

    private function openSharedSession(int|string|null $customer = null): int
    {
        return $this->fnb->openSession($this->context, ['business_day_id' => $this->day, 'service_type' => 'counter'] + ($customer !== null ? ['customer_profile_id' => $customer] : []), 'shared-session-'.Str::uuid())['resource']['id'];
    }

    public function test_installing_customers_keeps_existing_fnb_profiles_in_legacy_mode_until_cutover(): void
    {
        $profile = $this->create('Legacy Alice');
        $this->assertIsInt($profile['id']);
        $this->assertDatabaseCount('fnb_customer_profiles', 1);
        $this->assertDatabaseCount('cust_profiles', 0);
        $this->assertNull($profile['privacy_consented_at']);
        $this->assertNull($profile['marketing_consented_at']);
        $this->assertSame('legacy', app(FnbCustomerDirectory::class)->status($this->context)['mode']);
    }

    public function test_shared_create_and_pos_lookup_use_shared_uuid_without_legacy_phone_uniqueness(): void
    {
        $this->shared();
        $alice = $this->create();
        $bob = $this->create('Shared Bob', 'fnb-shared-bob');
        $this->assertTrue(Str::isUuid($alice['id']));
        $this->assertNotSame($alice['id'], $bob['id']);
        $this->assertDatabaseCount('cust_profiles', 2);
        $this->assertDatabaseCount('fnb_customer_profiles', 0);
        $items = $this->fnb->searchCustomers($this->context, 'Shared')['resource']['items'];
        $this->assertCount(2, $items);
        $this->assertSame('***4567', $items[0]['phone_normalized']);
        $this->assertStringNotContainsString('0901234567', json_encode($items, JSON_THROW_ON_ERROR));
        $this->assertSame($alice['id'], $this->create()['id']);
        $this->assertDatabaseCount('cust_profiles', 2);
    }

    public function test_shared_buyer_is_snapshotted_on_session_and_attach_preserves_finalized_identity(): void
    {
        $this->shared();
        $profile = $this->create();
        $session = $this->openSharedSession($profile['id']);
        $stored = DB::table('fnb_service_sessions')->find($session);
        $this->assertNull($stored->customer_profile_id);
        $this->assertSame($profile['id'], $stored->shared_customer_id);
        $this->assertSame('Shared Alice', json_decode($stored->customer_snapshot, true, flags: JSON_THROW_ON_ERROR)['name']);
        $customers = app(FnbCustomerService::class);
        $customers->updateProfile($this->context, $profile['id'], ['name' => 'Alice renamed'], 'fnb-shared-rename', 1);
        $this->assertSame('Shared Alice', json_decode(DB::table('fnb_service_sessions')->where('id', $session)->value('customer_snapshot'), true, flags: JSON_THROW_ON_ERROR)['name']);
        $check = $this->check($session);
        $customers->attachToSession($this->context, $session, $profile['id'], 'fnb-shared-attach', 1);
        $this->assertSame($profile['id'], DB::table('fnb_checks')->where('id', $check)->value('shared_customer_id'));
        $this->assertSame('Alice renamed', json_decode(DB::table('fnb_checks')->where('id', $check)->value('buyer_snapshot'), true, flags: JSON_THROW_ON_ERROR)['name']);
        DB::table('fnb_checks')->where('id', $check)->update(['finalized_at' => now(), 'status' => 'finalized']);
        $this->expectException(FnbConflictException::class);
        $customers->attachToSession($this->context, $session, $profile['id'], 'fnb-shared-attach-frozen', 2);
    }

    public function test_customer_directory_disable_fails_closed_but_anonymous_service_can_continue(): void
    {
        $this->shared();
        $this->create();
        DB::table('module_installations')->where('key', 'customers')->update(['status' => 'disabled']);
        $status = app(FnbCustomerDirectory::class)->status($this->context);
        $this->assertSame('shared', $status['mode']);
        $this->assertFalse($status['ready']);
        foreach ([fn () => $this->create('Must not fall back', 'disabled-shared-create'), fn () => $this->fnb->searchCustomers($this->context, '')] as $action) {
            try {
                $action();
                $this->fail('Shared mode must fail closed.');
            } catch (HttpExceptionInterface $exception) {
                $this->assertSame(503, $exception->getStatusCode());
            }
        }
        $this->assertGreaterThan(0, $this->openSharedSession());
        $this->assertDatabaseCount('fnb_customer_profiles', 0);
        $this->assertDatabaseCount('cust_profiles', 1);
    }

    public function test_fnb_cached_command_replay_rechecks_revoked_shared_abilities(): void
    {
        $this->shared();
        $this->create();
        DB::table('cust_bindings')->where('module_key', 'fnb-pos')->update(['abilities' => json_encode(['lookup', 'view', 'attach', 'history'])]);
        try {
            $this->create();
            $this->fail('Cached FNB response bypassed the shared grant.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('cust_profiles', 1);
    }

    public function test_shared_profile_keeps_fnb_notes_and_scoped_consent_out_of_master_profile(): void
    {
        $this->shared();
        $created = $this->fnb->createCustomer($this->context, ['name' => 'Private preferences', 'notes' => 'FNB private service note', 'privacy_consent' => true, 'marketing_consent' => false], 'fnb-private-notes')['resource'];
        $profile = app(FnbCustomerService::class)->profile($this->context, $created['id'])['resource'];
        $this->assertSame('FNB private service note', $profile['notes']);
        $this->assertNotNull($profile['privacy_consented_at']);
        $this->assertNull($profile['marketing_consented_at']);
        $this->assertNull(DB::table('cust_profiles')->value('notes'));
        $this->assertDatabaseCount('cust_consents', 0);
        $this->assertDatabaseCount('fnb_shared_customer_extensions', 1);
    }

    public function test_mapped_legacy_history_and_new_shared_history_preserve_original_buyer_snapshots(): void
    {
        $legacy = $this->create('Original legacy buyer');
        $oldSession = $this->openSharedSession($legacy['id']);
        $oldCheck = $this->check($oldSession, 'closed', $legacy['id'], ['name' => 'Historic frozen name']);
        $this->shared();
        $shared = $this->create('Canonical buyer', 'fnb-history-canonical');
        DB::table('cust_sources')->insert(['website_key' => 'website-main', 'book_id' => $this->book, 'profile_id' => DB::table('cust_profiles')->where('public_id', $shared['id'])->value('id'), 'module_key' => 'fnb-pos', 'scope_type' => 'website', 'scope_key' => 'website-main', 'source_id' => (string) $legacy['id'], 'created_at' => now(), 'updated_at' => now()]);
        $newSession = $this->openSharedSession($shared['id']);
        $newCheck = $this->check($newSession, 'closed', null, ['name' => 'New frozen buyer'], $shared['id']);
        $history = app(FnbCustomerService::class)->purchaseHistory($this->context, $shared['id'])['resource']['items'];
        $this->assertSame([$newCheck, $oldCheck], array_column($history, 'id'));
        $this->assertSame(['New frozen buyer', 'Historic frozen name'], array_column(array_column($history, 'buyer_snapshot'), 'name'));
        $activities = app(FnbCustomersProvider::class)->activities($this->owner, 'website-main', (string) $this->context->outletId, $shared['id']);
        $this->assertCount(2, $activities);
        $this->assertArrayNotHasKey('buyer_snapshot', $activities[0]);
    }

    public function test_shared_uuid_http_crud_lookup_and_session_attachment_work_without_rewriting_legacy_keys(): void
    {
        $this->shared();
        $this->withHeaders(['X-FNB-Outlet' => $this->context->outletId, 'X-FNB-Terminal' => $this->context->terminalId]);
        $profile = $this->withHeader('Idempotency-Key', 'http-shared-create')->postJson('/admin/api/fnb/customers', ['name' => 'HTTP shared', 'phone' => '0901234567', 'notes' => 'Private HTTP note'])->assertOk()->json('data');
        $id = $profile['id'];
        $this->assertTrue(Str::isUuid($id));
        $this->getJson('/admin/api/fnb/customers/'.$id)->assertOk()->assertJsonPath('data.notes', 'Private HTTP note');
        $this->getJson('/admin/api/fnb/outlets/'.$this->context->outletId.'/customer-profiles')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.items.0.id', $id);
        $this->withHeader('Idempotency-Key', 'http-shared-update')->putJson('/admin/api/fnb/customers/'.$id, ['name' => 'HTTP renamed', 'expected_version' => 1])->assertOk()->assertJsonPath('data.version', 2);
        $session = $this->withHeader('Idempotency-Key', 'http-shared-session')->postJson('/admin/api/fnb/sessions', ['business_day_id' => $this->day, 'service_type' => 'counter', 'customer_profile_id' => $id])->assertOk()->json('data.id');
        $this->assertSame($id, DB::table('fnb_service_sessions')->where('id', $session)->value('shared_customer_id'));
        $this->withHeader('Idempotency-Key', 'http-shared-archive')->deleteJson('/admin/api/fnb/customers/'.$id, ['expected_version' => 2])->assertOk()->assertJsonPath('data.status', 'archived');
        $this->getJson('/admin/api/fnb/outlets/'.$this->context->outletId.'/customers')->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson('/admin/api/fnb/outlets/'.$this->context->outletId.'/customer-profiles?status=archived')->assertOk()->assertJsonPath('data.items.0.id', $id);
        $this->withHeader('Idempotency-Key', 'http-shared-restore')->putJson('/admin/api/fnb/customers/'.$id, ['status' => 'active', 'expected_version' => 3])->assertOk()->assertJsonPath('data.version', 4);
        $this->getJson('/admin/api/fnb/outlets/'.$this->context->outletId.'/customers-integration')->assertOk()->assertJsonPath('data.mode', 'shared')->assertJsonPath('data.ready', true);
        $this->assertDatabaseCount('fnb_customer_profiles', 0);
    }

    public function test_merging_shared_profiles_keeps_old_shared_check_history_and_uses_survivor_for_new_sessions(): void
    {
        $this->shared();
        $source = $this->create('Source buyer', 'merge-source-buyer');
        $target = $this->create('Surviving buyer', 'merge-target-buyer');
        $session = $this->openSharedSession($source['id']);
        $check = $this->check($session, 'closed', null, ['name' => 'Immutable source buyer'], $source['id']);
        app(CustomerService::class)->merge($this->owner, DB::table('cust_books')->where('id', $this->book)->value('public_id'), $source['id'], ['target_id' => $target['id'], 'expected_version' => 1, 'target_version' => 1, 'reason' => 'Reviewed duplicate identity'], 'fnb-shared-merge');
        $history = app(FnbCustomerService::class)->purchaseHistory($this->context, $target['id'])['resource']['items'];
        $this->assertSame([$check], array_column($history, 'id'));
        $this->assertSame('Immutable source buyer', $history[0]['buyer_snapshot']['name']);
        $this->assertSame($source['id'], DB::table('fnb_checks')->where('id', $check)->value('shared_customer_id'));
        $new = $this->openSharedSession($source['id']);
        $this->assertSame($target['id'], DB::table('fnb_service_sessions')->where('id', $new)->value('shared_customer_id'));
    }

    public function test_first_partial_shared_extension_update_preserves_unedited_legacy_private_fields(): void
    {
        $legacy = $this->fnb->createCustomer($this->context, ['name' => 'Mapped guest', 'notes' => 'Original private note', 'birthday' => '1990-02-03', 'privacy_consent' => true], 'legacy-private-fields')['resource'];
        $this->shared();
        $shared = $this->create('Mapped master', 'mapped-private-master');
        DB::table('cust_sources')->insert(['website_key' => 'website-main', 'book_id' => $this->book, 'profile_id' => DB::table('cust_profiles')->where('public_id', $shared['id'])->value('id'), 'module_key' => 'fnb-pos', 'scope_type' => 'website', 'scope_key' => 'website-main', 'source_id' => (string) $legacy['id'], 'created_at' => now(), 'updated_at' => now()]);
        $profile = app(FnbCustomerService::class)->updateProfile($this->context, $shared['id'], ['marketing_consent' => true], 'partial-private-update', 1)['resource'];
        $this->assertSame('Original private note', $profile['notes']);
        $this->assertSame('1990-02-03', $profile['birthday']);
        $this->assertSame($legacy['privacy_consented_at'], $profile['privacy_consented_at']);
        $this->assertNotNull($profile['marketing_consented_at']);
        $this->assertNull(DB::table('cust_profiles')->where('public_id', $shared['id'])->value('notes'));
        $this->assertDatabaseCount('cust_consents', 0);
    }

    public function test_native_shared_cashier_cannot_inject_private_fields_or_replay_after_membership_revocation(): void
    {
        $this->shared();
        $cashier = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        DB::transaction(fn () => app(FnbStaffAssignmentService::class)->assign($this->owner, 'website-main', $this->context->outletId, $cashier, 'fnb-cashier'));
        $context = new FnbContext('website-main', $this->context->outletId, $cashier->id, $this->context->terminalId);
        $profile = $this->fnb->createCustomer($context, ['name' => 'Cashier quick guest'], 'cashier-shared-quick')['resource'];
        $this->assertTrue(Str::isUuid($profile['id']));
        try {
            $this->fnb->createCustomer($context, ['name' => 'Forbidden private fields', 'notes' => 'Injected note', 'privacy_consent' => true], 'cashier-private-injection');
            $this->fail('Native service must enforce private-field permission without an HTTP callback.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('cust_profiles', 1);
            $this->assertDatabaseCount('fnb_shared_customer_extensions', 0);
        }
        DB::table('fnb_outlet_staff')->where('admin_id', $cashier->id)->update(['revoked_at' => now(), 'is_active' => false]);
        $this->expectException(AuthorizationException::class);
        $this->fnb->createCustomer($context, ['name' => 'Cashier quick guest'], 'cashier-shared-quick');
    }

    public function test_website_binding_configuration_requires_native_hq_authority(): void
    {
        $manager = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        DB::transaction(fn () => app(FnbStaffAssignmentService::class)->assign($this->owner, 'website-main', $this->context->outletId, $manager, 'fnb-manager'));
        $manager = $manager->fresh();
        app(FnbCustomersProvider::class)->authorize($manager, 'website-main', (string) $this->context->outletId, 'view');
        $this->expectException(AuthorizationException::class);
        app(FnbCustomersProvider::class)->authorize($manager, 'website-main', (string) $this->context->outletId, 'configure');
    }

    private function check(int $sessionId, string $status = 'open', ?int $legacy = null, ?array $snapshot = null, ?string $shared = null): int
    {
        $session = DB::table('fnb_service_sessions')->find($sessionId);
        $sequence = (int) DB::table('fnb_checks')->max('sequence_no') + 1;

        return DB::table('fnb_checks')->insertGetId(['website_key' => 'website-main', 'outlet_id' => $this->context->outletId, 'public_id' => (string) Str::uuid(), 'creation_key' => 'shared-check-'.Str::uuid(), 'business_day_id' => $this->day, 'session_id' => $sessionId, 'customer_profile_id' => $legacy, 'shared_customer_id' => $shared, 'sequence_no' => $sequence, 'check_no' => 'CHK-'.$sequence, 'status' => $status, 'currency' => $session->currency, 'timezone_snapshot' => $session->timezone_snapshot, 'subtotal_minor' => 10000, 'discount_total_minor' => 0, 'tax_total_minor' => 0, 'service_charge_total_minor' => 0, 'pricing_rounding_minor' => 0, 'grand_total_minor' => 10000, 'settlement_total_minor' => $status === 'closed' ? 10000 : null, 'buyer_snapshot' => $snapshot ? json_encode($snapshot, JSON_THROW_ON_ERROR) : null, 'finalized_at' => $status === 'closed' ? now() : null, 'closed_at' => $status === 'closed' ? now() : null, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }
}
