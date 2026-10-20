<?php

namespace Tests\Feature;

use App\Models\Admin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\FnbCustomerService;
use Modules\FnbPos\Services\FnbService;
use Modules\FnbPos\Services\Security\FnbStaffAssignmentService;
use Tests\TestCase;

class FnbCustomerServiceTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    private FnbService $fnb;

    private FnbCustomerService $customers;

    private int $outletId;

    private int $terminalId;

    private int $businessDayId;

    private int $shiftId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $this->owner->forceFill([
            'password' => 'FnbPassword123!',
            'must_change_password' => false,
        ])->save();
        $this->actingAs($this->owner, 'admin');
        $this->postJson('/admin/api/modules/fnb-pos/install')->assertOk();
        $this->postJson('/admin/api/modules/fnb-pos/enable')->assertOk();

        $this->fnb = app(FnbService::class);
        $this->customers = app(FnbCustomerService::class);
        $onboarded = $this->fnb->onboard(new FnbContext('website-main', 0, $this->owner->id), [
            'outlet' => [
                'code' => 'CUSTOMER-TEST',
                'name' => 'Customer Test Cafe',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'currency' => 'VND',
            ],
            'terminal' => ['code' => 'POS-01', 'name' => 'POS 01', 'type' => 'pos'],
            'station' => ['code' => 'BAR', 'name' => 'Bar'],
            'payment_methods' => [
                ['code' => 'CASH', 'name' => 'Cash', 'kind' => 'cash'],
            ],
            'table_count' => 0,
        ], 'customer-test-onboard');
        $this->outletId = (int) $onboarded['resource']['outlet']['id'];
        $this->terminalId = (int) $onboarded['resource']['terminal']['id'];

        $context = $this->context();
        $day = $this->fnb->openBusinessDay($context, now()->toDateString(), 'customer-test-day');
        $this->businessDayId = (int) $day['resource']['id'];
        $shift = $this->fnb->openShift($context, $this->businessDayId, 0, 'customer-test-shift');
        $this->shiftId = (int) $shift['resource']['id'];
    }

    public function test_customer_picker_lists_active_customers_without_a_query_and_filters_short_queries(): void
    {
        $alice = $this->createCustomer('CUS-ALICE', 'Alice', '0901234567');
        $bob = $this->createCustomer('CUS-BOB', 'Bob', '0987654321');
        $archived = $this->createCustomer('CUS-ARCHIVED', 'Archived', '0911223344');
        DB::table('fnb_customer_profiles')->where('id', $archived)->update(['status' => 'archived']);

        $items = $this->fnb->searchCustomers($this->context(), '')['resource']['items'];
        $this->assertSame([$alice, $bob], array_column($items, 'id'));
        $this->assertSame([$alice], array_column($this->fnb->searchCustomers($this->context(), ' ', 1)['resource']['items'], 'id'));
        $this->assertSame([$bob], array_column($this->fnb->searchCustomers($this->context(), 'B')['resource']['items'], 'id'));
        $this->assertSame([$alice], array_column($this->fnb->searchCustomers($this->context(), 'CUS-AL')['resource']['items'], 'id'));
        $this->assertSame([$bob], array_column($this->fnb->searchCustomers($this->context(), '4321')['resource']['items'], 'id'));
        $this->assertSame([], $this->fnb->searchCustomers($this->context(), 'No match')['resource']['items']);
    }

    public function test_attach_updates_open_session_and_mutable_checks_but_freezes_after_finalization(): void
    {
        $customerId = $this->createCustomer('CUS-A', 'Alice Original', '0901 234 567');
        $sessionId = $this->openSession();
        $checkId = $this->insertCheck($this->outletId, $sessionId, 'open', null, null, 3);
        $key = 'customer-attach-success';

        $result = $this->customers->attachToSession(
            $this->context(),
            $sessionId,
            $customerId,
            $key,
            1,
        );

        $this->assertFalse($result['replayed']);
        $this->assertSame(2, $result['meta']['version']);
        $this->assertSame([(string) $checkId => 4], $result['meta']['check_versions']);
        $this->assertSame([$checkId], $result['resource']['updated_check_ids']);
        $this->assertSame('***4567', $result['resource']['customer']['phone_masked']);
        $this->assertStringNotContainsString('0901234567', json_encode($result, JSON_THROW_ON_ERROR));

        $session = DB::table('fnb_service_sessions')->where('id', $sessionId)->firstOrFail();
        $check = DB::table('fnb_checks')->where('id', $checkId)->firstOrFail();
        $this->assertSame($customerId, (int) $session->customer_profile_id);
        $this->assertSame($customerId, (int) $check->customer_profile_id);
        $this->assertSame(2, (int) $session->version);
        $this->assertSame(4, (int) $check->version);
        $this->assertSame('Alice Original', json_decode($session->customer_snapshot, true, 512, JSON_THROW_ON_ERROR)['name']);
        $this->assertSame('Alice Original', json_decode($check->buyer_snapshot, true, 512, JSON_THROW_ON_ERROR)['name']);
        $event = DB::table('fnb_customer_events')->where('customer_profile_id', $customerId)
            ->where('event_type', 'attached_to_session')->firstOrFail();
        $this->assertSame(['session.customer_profile_id', 'session.customer_snapshot'], json_decode($event->changed_fields, true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame(64, strlen($event->evidence_hash));

        $replay = $this->customers->attachToSession($this->context(), $sessionId, $customerId, $key, 1);
        $this->assertTrue($replay['replayed']);
        $this->assertSame(1, DB::table('fnb_customer_events')->where('customer_profile_id', $customerId)
            ->where('event_type', 'attached_to_session')->count());

        $frozenSessionId = $this->openSession('customer-frozen-session');
        $frozenCheckId = $this->insertCheck($this->outletId, $frozenSessionId, 'finalized', null, null, 2);
        try {
            $this->customers->attachToSession(
                $this->context(),
                $frozenSessionId,
                $customerId,
                'customer-attach-frozen',
                1,
            );
            $this->fail('A finalized check must freeze the buyer identity.');
        } catch (FnbConflictException $exception) {
            $this->assertSame($frozenCheckId, $exception->details['check_id']);
        }
        $this->assertNull(DB::table('fnb_service_sessions')->where('id', $frozenSessionId)->value('customer_profile_id'));
        $this->assertNull(DB::table('fnb_checks')->where('id', $frozenCheckId)->value('customer_profile_id'));
    }

    public function test_profile_update_is_versioned_normalized_and_records_only_redacted_evidence(): void
    {
        $customerId = $this->createCustomer('CUS-B', 'Before Name', '0909 111 222');
        $payload = [
            'code' => '  VIP-01  ',
            'name' => '  After Name  ',
            'phone' => '+84 (912) 345-678',
            'email' => 'Customer.Secret@Example.Test',
            'birthday' => '1990-01-02',
            'notes' => '  prefers oat milk  ',
            'privacy_consent' => true,
            'marketing_consent' => true,
        ];

        $updated = $this->customers->updateProfile(
            $this->context(),
            $customerId,
            $payload,
            'customer-update-normalized',
            1,
        );

        $this->assertFalse($updated['replayed']);
        $this->assertSame(2, $updated['resource']['version']);
        $this->assertSame('VIP-01', $updated['resource']['code']);
        $this->assertSame('After Name', $updated['resource']['name']);
        $this->assertSame('84912345678', $updated['resource']['phone_normalized']);
        $this->assertSame('customer.secret@example.test', $updated['resource']['email']);
        $this->assertSame('prefers oat milk', $updated['resource']['notes']);
        $this->assertNotNull($updated['resource']['privacy_consented_at']);
        $this->assertNotNull($updated['resource']['marketing_consented_at']);

        $replay = $this->customers->updateProfile(
            $this->context(),
            $customerId,
            $payload,
            'customer-update-normalized',
            1,
        );
        $this->assertTrue($replay['replayed']);
        $this->assertSame(2, $replay['resource']['version']);

        $events = DB::table('fnb_customer_events')->where('customer_profile_id', $customerId)
            ->whereIn('event_type', ['updated', 'consent_changed'])->get();
        $this->assertSame(['consent_changed', 'updated'], $events->pluck('event_type')->sort()->values()->all());
        $serializedEvents = json_encode($events, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('After Name', $serializedEvents);
        $this->assertStringNotContainsString('84912345678', $serializedEvents);
        $this->assertStringNotContainsString('customer.secret@example.test', $serializedEvents);
        foreach ($events as $event) {
            $this->assertSame(64, strlen($event->evidence_hash));
            $this->assertIsArray(json_decode($event->changed_fields, true, 512, JSON_THROW_ON_ERROR));
        }

        try {
            $this->customers->updateProfile(
                $this->context(),
                $customerId,
                ['name' => 'Stale Write'],
                'customer-update-stale',
                1,
            );
            $this->fail('A stale profile update must conflict.');
        } catch (FnbConflictException $exception) {
            $this->assertSame(2, $exception->details['current_versions']["customer_profile:{$customerId}"]);
            $this->assertSame('After Name', $exception->details['current_snapshot']['name']);
        }

        $duplicateId = $this->createCustomer('CUS-C', 'Duplicate Phone', '0987 654 321');
        $this->assertNotSame($customerId, $duplicateId);
        try {
            $this->customers->updateProfile(
                $this->context(),
                $customerId,
                ['phone' => '0987-654-321'],
                'customer-update-duplicate-phone',
                2,
            );
            $this->fail('A website-scoped duplicate phone must conflict.');
        } catch (FnbConflictException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(2, (int) DB::table('fnb_customer_profiles')->where('id', $customerId)->value('version'));

        $archived = $this->customers->updateProfile(
            $this->context(),
            $customerId,
            ['status' => 'archived'],
            'customer-update-archive',
            2,
        );
        $this->assertSame('archived', $archived['resource']['status']);
        $this->assertSame(3, $archived['resource']['version']);
        $this->assertDatabaseHas('fnb_customer_events', [
            'customer_profile_id' => $customerId,
            'event_type' => 'archived',
        ]);
    }

    public function test_full_profile_and_closed_purchase_history_are_permission_and_outlet_scoped(): void
    {
        $customerId = $this->createCustomer('CUS-D', 'Current Name', '0912 000 111');
        $sessionId = $this->openSession('customer-history-session', $customerId);
        $olderCheckId = $this->insertCheck($this->outletId, $sessionId, 'closed', $customerId, [
            'customer_profile_id' => $customerId,
            'code' => 'CUS-D',
            'name' => 'Historical Name',
            'phone_normalized' => '0912000111',
        ], 7, 50000);
        $newerCheckId = $this->insertCheck($this->outletId, $sessionId, 'closed', $customerId, [
            'customer_profile_id' => $customerId,
            'code' => 'CUS-D',
            'name' => 'Historical Name',
            'phone_normalized' => '0912000111',
        ], 4, 25000);
        $this->insertCheck($this->outletId, $sessionId, 'finalized', $customerId, [
            'customer_profile_id' => $customerId,
            'name' => 'Must not appear',
        ]);
        [$paymentId] = $this->insertSucceededPaymentAndRefund($olderCheckId, 50000, 10000);
        $this->insertPayment($olderCheckId, 'failed', 999999, 'PAYMENT-SHOULD-NOT-LEAK');

        [$otherOutletId, $otherSessionId] = $this->secondaryOutletScope($customerId);
        $otherCheckId = $this->insertCheck($otherOutletId, $otherSessionId, 'closed', $customerId, [
            'customer_profile_id' => $customerId,
            'name' => 'Other Outlet',
        ], 1, 70000);

        $firstPage = $this->customers->purchaseHistory($this->context(), $customerId, 1);
        $this->assertTrue($firstPage['meta']['has_more']);
        $this->assertSame($newerCheckId, $firstPage['resource']['items'][0]['id']);
        $this->assertSame($newerCheckId, $firstPage['meta']['next_before_check_id']);
        $secondPage = $this->customers->purchaseHistory($this->context(), $customerId, 10, $newerCheckId);
        $this->assertFalse($secondPage['meta']['has_more']);
        $this->assertSame([$olderCheckId], array_column($secondPage['resource']['items'], 'id'));
        $history = $secondPage['resource']['items'][0];
        $this->assertSame('Historical Name', $history['buyer_snapshot']['name']);
        $this->assertSame(50000, $history['gross_paid_total_minor']);
        $this->assertSame(10000, $history['refunded_total_minor']);
        $this->assertSame(40000, $history['net_collected_total_minor']);
        $this->assertSame([$paymentId], array_column($history['payments'], 'id'));
        $serialized = json_encode($secondPage, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('PAYMENT-SHOULD-NOT-LEAK', $serialized);
        $this->assertStringNotContainsString('PROVIDER-SECRET', $serialized);
        $this->assertStringNotContainsString('BANK-SECRET', $serialized);
        $this->assertStringNotContainsString((string) $otherCheckId, json_encode(array_column($secondPage['resource']['items'], 'id'), JSON_THROW_ON_ERROR));

        $otherHistory = $this->customers->purchaseHistory(
            new FnbContext('website-main', $otherOutletId, $this->owner->id),
            $customerId,
        );
        $this->assertSame([$otherCheckId], array_column($otherHistory['resource']['items'], 'id'));

        $profile = $this->customers->profile($this->context(), $customerId);
        $this->assertSame('0912000111', $profile['resource']['phone_normalized']);

        $cashier = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        DB::transaction(fn () => app(FnbStaffAssignmentService::class)->assign(
            $this->owner,
            'website-main',
            $this->outletId,
            $cashier,
            'fnb-cashier',
        ));
        $cashierContext = new FnbContext('website-main', $this->outletId, $cashier->id);
        try {
            $this->customers->profile($cashierContext, $customerId);
            $this->fail('A cashier without fnb.customer.view must not read full PII.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
        try {
            $this->customers->purchaseHistory($cashierContext, $customerId);
            $this->fail('A cashier without fnb.customer.view must not read purchase history.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $attachSession = $this->openSession('customer-cashier-attach');
        $attached = $this->customers->attachToSession(
            $cashierContext,
            $attachSession,
            $customerId,
            'customer-cashier-attach-command',
            1,
        );
        $this->assertSame($customerId, $attached['resource']['customer_profile_id']);
        $this->assertStringNotContainsString('0912000111', json_encode($attached, JSON_THROW_ON_ERROR));
    }

    public function test_customer_management_http_crud_soft_delete_restore_and_history_preservation(): void
    {
        $this->withSession(['admin_auth_version' => $this->owner->auth_version])->withHeaders(['X-FNB-Outlet' => $this->outletId, 'X-FNB-Terminal' => $this->terminalId]);
        $created = $this->withHeader('Idempotency-Key', 'manage-create')->postJson('/admin/api/fnb/customers', [
            'code' => 'MANAGE', 'name' => 'Original Customer', 'phone' => '0909000111', 'email' => 'buyer@example.test',
            'birthday' => '1995-01-12', 'notes' => 'Synthetic note', 'privacy_consent' => true, 'marketing_consent' => false,
        ])->assertOk();
        $id = (int) $created->json('data.id');
        $this->getJson('/admin/api/fnb/outlets/'.$this->outletId.'/customer-profiles?query=MANAGE')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.items.0.id', $id);
        $this->getJson('/admin/api/fnb/customers/'.$id)->assertOk()->assertJsonPath('data.birthday', '1995-01-12')->assertJsonPath('data.notes', 'Synthetic note');
        $sessionId = $this->openSession('manage-history', $id);
        $buyer = ['customer_profile_id' => $id, 'name' => 'Original Customer'];
        $checkId = $this->insertCheck($this->outletId, $sessionId, 'closed', $id, $buyer);
        $updated = $this->withHeader('Idempotency-Key', 'manage-update')->putJson('/admin/api/fnb/customers/'.$id, ['name' => 'Updated Customer', 'phone' => '0909 000 111', 'expected_version' => 1]);
        $updated->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.phone_normalized', '0909000111');
        $this->withHeader('Idempotency-Key', 'manage-delete')->deleteJson('/admin/api/fnb/customers/'.$id, ['expected_version' => 2])->assertOk()->assertJsonPath('data.status', 'archived');
        $this->withHeader('Idempotency-Key', 'manage-delete')->deleteJson('/admin/api/fnb/customers/'.$id, ['expected_version' => 2])->assertOk()->assertJsonPath('meta.replayed', true);
        $this->getJson('/admin/api/fnb/outlets/'.$this->outletId.'/customers?query=MANAGE')->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson('/admin/api/fnb/outlets/'.$this->outletId.'/customer-profiles?status=archived')->assertOk()->assertJsonPath('data.items.0.id', $id);
        $this->getJson('/admin/api/fnb/customers/'.$id.'/history')->assertOk()->assertJsonPath('data.items.0.id', $checkId)->assertJsonPath('data.items.0.buyer_snapshot.name', 'Original Customer');
        $this->assertSame($id, (int) DB::table('fnb_service_sessions')->where('id', $sessionId)->value('customer_profile_id'));
        $this->withHeader('Idempotency-Key', 'manage-stale')->putJson('/admin/api/fnb/customers/'.$id, ['name' => 'Stale overwrite', 'expected_version' => 2])->assertConflict();
        $this->withHeader('Idempotency-Key', 'manage-restore')->putJson('/admin/api/fnb/customers/'.$id, ['status' => 'active', 'expected_version' => 3])->assertOk()->assertJsonPath('data.version', 4);
        $this->getJson('/admin/api/fnb/outlets/'.$this->outletId.'/customers?query=MANAGE')->assertOk()->assertJsonPath('data.items.0.id', $id);
    }

    public function test_management_listing_filters_paginates_and_excludes_other_websites(): void
    {
        for ($index = 0; $index < 12; $index++) $this->createCustomer('LIST-'.$index, sprintf('Listing %02d', $index), '090800'.str_pad((string) $index, 4, '0', STR_PAD_LEFT));
        $foreign = (array) DB::table('fnb_customer_profiles')->orderBy('id')->first();
        unset($foreign['id']);
        $foreign['website_key'] = 'other-website';
        $foreign['public_id'] = (string) Str::uuid();
        $foreignId = DB::table('fnb_customer_profiles')->insertGetId($foreign);
        $first = $this->customers->listing($this->context(), ['query' => 'Listing', 'page' => 1, 'per_page' => 10]);
        $second = $this->customers->listing($this->context(), ['query' => 'Listing', 'page' => 2, 'per_page' => 10]);
        $this->assertSame(12, $first['meta']['total']);
        $this->assertCount(10, $first['resource']['items']);
        $this->assertCount(2, $second['resource']['items']);
        $this->assertEmpty(array_intersect(array_column($first['resource']['items'], 'id'), array_column($second['resource']['items'], 'id')));
        $this->assertNotContains($foreignId, array_column($first['resource']['items'], 'id'));
        $this->assertCount(1, $this->customers->listing($this->context(), ['query' => '0908000011'])['resource']['items']);
        $this->withSession(['admin_auth_version' => $this->owner->auth_version])->withHeaders(['X-FNB-Outlet' => $this->outletId, 'X-FNB-Terminal' => $this->terminalId]);
        $this->withHeader('Idempotency-Key', 'foreign-delete')->deleteJson('/admin/api/fnb/customers/'.$foreignId, ['expected_version' => 1])->assertNotFound();
        $this->getJson('/admin/api/fnb/outlets/'.$this->outletId.'/customer-profiles?status=unknown')->assertUnprocessable();
        $this->getJson('/admin/api/fnb/outlets/'.$this->outletId.'/customer-profiles?per_page=99999')->assertUnprocessable();
    }

    public function test_customer_management_requires_full_view_and_update_permissions(): void
    {
        $id = $this->createCustomer('PERMISSION', 'Permission Customer', '0907000111');
        $cashier = Admin::factory()->create(['status' => 'active', 'is_active' => true, 'must_change_password' => false]);
        DB::transaction(fn () => app(FnbStaffAssignmentService::class)->assign($this->owner, 'website-main', $this->outletId, $cashier, 'fnb-cashier'));
        $cashier = $cashier->fresh();
        $this->actingAs($cashier, 'admin')->withSession(['admin_auth_version' => $cashier->auth_version])->withHeaders(['X-FNB-Outlet' => $this->outletId, 'X-FNB-Terminal' => $this->terminalId]);
        $this->getJson('/admin/api/fnb/outlets/'.$this->outletId.'/customer-profiles')->assertForbidden();
        $this->getJson('/admin/api/fnb/customers/'.$id)->assertForbidden();
        $this->withHeader('Idempotency-Key', 'cashier-delete-denied')->deleteJson('/admin/api/fnb/customers/'.$id, ['expected_version' => 1])->assertForbidden();
        $this->assertDatabaseHas('fnb_customer_profiles', ['id' => $id, 'status' => 'active', 'version' => 1]);
    }

    public function test_blank_customer_codes_are_generated_and_replays_keep_the_same_code(): void
    {
        $codes = [];
        foreach ([[], ['code' => null], ['code' => '   ']] as $index => $codeInput) {
            $input = ['name' => 'Auto Customer '.$index] + $codeInput;
            $result = $this->fnb->createCustomer($this->context(), $input, 'auto-code-'.$index);
            $customer = $result['resource'];
            $this->assertMatchesRegularExpression('/^KH[0-9]{6,}$/', $customer['code']);
            $this->assertDatabaseHas('fnb_customer_profiles', ['id' => $customer['id'], 'code' => $customer['code'], 'version' => 1]);
            $replayed = $this->fnb->createCustomer($this->context(), $input, 'auto-code-'.$index);
            $this->assertSame($customer['id'], $replayed['resource']['id']);
            $this->assertSame($customer['code'], $replayed['resource']['code']);
            $codes[] = $customer['code'];
        }
        $this->assertCount(3, array_unique($codes));
        foreach ([' VIP-001 ', '0'] as $index => $code) {
            $result = $this->fnb->createCustomer($this->context(), ['name' => 'Manual Customer', 'code' => $code], 'manual-code-'.$index);
            $this->assertSame(trim($code), $result['resource']['code']);
        }
    }

    public function test_generated_customer_codes_skip_existing_archived_codes_and_work_on_update(): void
    {
        $existing = $this->createCustomer('PLACEHOLDER', 'Existing', '0901222333');
        $nextId = (int) DB::table('fnb_customer_profiles')->max('id') + 1;
        $reserved = 'KH'.str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
        DB::table('fnb_customer_profiles')->where('id', $existing)->update(['code' => $reserved, 'status' => 'archived']);
        $created = $this->fnb->createCustomer($this->context(), ['name' => 'New customer'], 'collision-code')['resource'];
        $this->assertSame($reserved.'-1', $created['code']);
        $this->assertSame($reserved, DB::table('fnb_customer_profiles')->where('id', $existing)->value('code'));
        $updated = $this->customers->updateProfile($this->context(), (int) $created['id'], ['code' => '   '], 'blank-code-update', 1);
        $this->assertSame($created['code'], $updated['resource']['code']);
        $manual = $this->customers->updateProfile($this->context(), $existing, ['code' => null], 'manual-to-auto', 1);
        $this->assertSame('KH'.str_pad((string) $existing, 6, '0', STR_PAD_LEFT), $manual['resource']['code']);
    }

    public function test_pos_quick_create_generates_customer_code_for_cashier_and_replays_it(): void
    {
        $cashier = Admin::factory()->create(['status' => 'active', 'is_active' => true, 'must_change_password' => false]);
        DB::transaction(fn () => app(FnbStaffAssignmentService::class)->assign($this->owner, 'website-main', $this->outletId, $cashier, 'fnb-cashier'));
        $cashier = $cashier->fresh();
        $this->actingAs($cashier, 'admin')->withSession(['admin_auth_version' => $cashier->auth_version])
            ->withHeaders(['X-FNB-Outlet' => $this->outletId, 'X-FNB-Terminal' => $this->terminalId, 'Idempotency-Key' => 'pos-quick-create-auto-code']);
        // Same minimal payload sent by the POS quick-create form: no code or full-profile fields.
        $payload = ['name' => 'Quick Customer', 'phone' => null];
        $created = $this->postJson('/admin/api/fnb/customers', $payload)->assertOk();
        $id = $created->json('data.id');
        $code = $created->json('data.code');
        $this->assertMatchesRegularExpression('/^KH[0-9]{6,}$/', $code);
        $this->assertDatabaseHas('fnb_customer_profiles', ['id' => $id, 'code' => $code, 'website_key' => 'website-main']);
        $this->postJson('/admin/api/fnb/customers', $payload)->assertOk()
            ->assertJsonPath('meta.replayed', true)->assertJsonPath('data.id', $id)->assertJsonPath('data.code', $code);
        $this->getJson('/admin/api/fnb/outlets/'.$this->outletId.'/customers?query='.$code)->assertOk()
            ->assertJsonPath('data.items.0.id', $id)->assertJsonPath('data.items.0.code', $code);
        $this->assertSame(1, DB::table('fnb_customer_profiles')->where('id', $id)->count());
    }

    private function context(): FnbContext
    {
        return new FnbContext('website-main', $this->outletId, $this->owner->id, $this->terminalId);
    }

    private function createCustomer(string $code, string $name, string $phone): int
    {
        $result = $this->fnb->createCustomer($this->context(), [
            'code' => $code,
            'name' => $name,
            'phone' => $phone,
        ], 'create-'.strtolower($code));

        return (int) $result['resource']['id'];
    }

    private function openSession(?string $key = null, ?int $customerProfileId = null): int
    {
        $input = [
            'business_day_id' => $this->businessDayId,
            'service_type' => 'counter',
        ];
        if ($customerProfileId !== null) {
            $input['customer_profile_id'] = $customerProfileId;
        }
        $result = $this->fnb->openSession($this->context(), $input, $key ?? 'customer-session-'.Str::uuid());

        return (int) $result['resource']['id'];
    }

    /** @param array<string,mixed>|null $buyerSnapshot */
    private function insertCheck(
        int $outletId,
        int $sessionId,
        string $status,
        ?int $customerProfileId,
        ?array $buyerSnapshot = null,
        int $version = 1,
        int $totalMinor = 10000,
    ): int {
        $session = DB::table('fnb_service_sessions')->where('website_key', 'website-main')
            ->where('outlet_id', $outletId)->where('id', $sessionId)->firstOrFail();
        $sequence = (int) DB::table('fnb_checks')->where('outlet_id', $outletId)->max('sequence_no') + 1;
        $now = now();
        $closed = $status === 'closed';
        $finalized = $closed || $status === 'finalized';

        return DB::table('fnb_checks')->insertGetId([
            'website_key' => 'website-main',
            'outlet_id' => $outletId,
            'public_id' => (string) Str::uuid(),
            'creation_key' => 'raw-check-'.Str::uuid(),
            'business_day_id' => $session->business_day_id,
            'session_id' => $sessionId,
            'customer_profile_id' => $customerProfileId,
            'sequence_no' => $sequence,
            'check_no' => 'CHK-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT),
            'status' => $status,
            'currency' => $session->currency,
            'timezone_snapshot' => $session->timezone_snapshot,
            'subtotal_minor' => $totalMinor,
            'discount_total_minor' => 0,
            'tax_total_minor' => 0,
            'service_charge_total_minor' => 0,
            'pricing_rounding_minor' => 0,
            'grand_total_minor' => $totalMinor,
            'settlement_mode' => $finalized ? 'single_cash' : null,
            'cash_rounding_minor' => $finalized ? 0 : null,
            'settlement_total_minor' => $finalized ? $totalMinor : null,
            'settlement_hash' => $finalized ? hash('sha256', "{$outletId}:{$sessionId}:{$sequence}:{$totalMinor}") : null,
            'buyer_snapshot' => $buyerSnapshot === null ? null : json_encode($buyerSnapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'invoice_requested' => false,
            'finalized_at' => $finalized ? $now : null,
            'finalized_by' => $finalized ? $this->owner->id : null,
            'paid_at' => $closed ? $now : null,
            'closed_at' => $closed ? $now : null,
            'closed_by' => $closed ? $this->owner->id : null,
            'version' => $version,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array{int,int} */
    private function insertSucceededPaymentAndRefund(int $checkId, int $paidMinor, int $refundedMinor): array
    {
        $paymentId = $this->insertPayment($checkId, 'succeeded', $paidMinor, 'payment-history-success');
        $check = DB::table('fnb_checks')->where('id', $checkId)->firstOrFail();
        $method = DB::table('fnb_payment_methods')->where('website_key', 'website-main')
            ->where('outlet_id', $this->outletId)->where('kind', 'cash')->firstOrFail();
        $now = now();
        $refundId = DB::table('fnb_refunds')->insertGetId([
            'website_key' => 'website-main',
            'outlet_id' => $this->outletId,
            'public_id' => (string) Str::uuid(),
            'original_business_day_id' => $check->business_day_id,
            'processing_business_day_id' => $check->business_day_id,
            'currency' => $check->currency,
            'processed_shift_id' => $this->shiftId,
            'processed_terminal_id' => $this->terminalId,
            'payment_id' => $paymentId,
            'check_id' => $checkId,
            'method_code_snapshot' => $method->code,
            'method_name_snapshot' => $method->name,
            'method_kind_snapshot' => $method->kind,
            'sequence_no' => 1,
            'refund_no' => 'REF-00001',
            'status' => 'succeeded',
            'amount_minor' => $refundedMinor,
            'reason' => 'Customer history test',
            'idempotency_key' => 'refund-history-success',
            'request_fingerprint' => hash('sha256', 'refund-history-success'),
            'provider_connection_key' => 'test-provider',
            'provider_operation_key' => 'refund-operation-secret',
            'provider_reference' => 'PROVIDER-SECRET',
            'requested_at' => $now,
            'requested_by' => $this->owner->id,
            'reserved_at' => $now,
            'processed_at' => $now,
            'metadata' => json_encode(['secret' => 'REFUND-METADATA-SECRET'], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$paymentId, $refundId];
    }

    private function insertPayment(int $checkId, string $status, int $amountMinor, string $key): int
    {
        $check = DB::table('fnb_checks')->where('id', $checkId)->firstOrFail();
        $method = DB::table('fnb_payment_methods')->where('website_key', 'website-main')
            ->where('outlet_id', $this->outletId)->where('kind', 'cash')->firstOrFail();
        $now = now();

        return DB::table('fnb_payments')->insertGetId([
            'website_key' => 'website-main',
            'outlet_id' => $this->outletId,
            'public_id' => (string) Str::uuid(),
            'business_day_id' => $check->business_day_id,
            'currency' => $check->currency,
            'check_id' => $checkId,
            'shift_id' => $this->shiftId,
            'terminal_id' => $this->terminalId,
            'payment_method_id' => $method->id,
            'method_code_snapshot' => $method->code,
            'method_name_snapshot' => $method->name,
            'method_kind_snapshot' => $method->kind,
            'provider_connection_key' => 'test-provider',
            'provider_operation_key' => 'payment-operation-'.$key,
            'provider_reference' => 'PROVIDER-SECRET-'.$key,
            'status' => $status,
            'amount_minor' => $amountMinor,
            'tendered_minor' => $amountMinor,
            'change_minor' => 0,
            'reference' => 'BANK-SECRET',
            'idempotency_key' => $key,
            'request_fingerprint' => hash('sha256', $key),
            'reserved_at' => $now,
            'processed_at' => $status === 'succeeded' ? $now : null,
            'metadata' => json_encode(['secret' => 'PAYMENT-METADATA-SECRET'], JSON_THROW_ON_ERROR),
            'created_by' => $this->owner->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array{int,int} */
    private function secondaryOutletScope(int $customerProfileId): array
    {
        $now = now();
        $outletId = DB::table('fnb_outlets')->insertGetId([
            'website_key' => 'website-main',
            'public_id' => (string) Str::uuid(),
            'code' => 'OTHER-OUTLET',
            'name' => 'Other Outlet',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'currency' => 'VND',
            'status' => 'active',
            'version' => 1,
            'created_by' => $this->owner->id,
            'updated_by' => $this->owner->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $dayId = DB::table('fnb_business_days')->insertGetId([
            'website_key' => 'website-main',
            'outlet_id' => $outletId,
            'business_date' => now()->subDay()->toDateString(),
            'currency' => 'VND',
            'timezone_snapshot' => 'Asia/Ho_Chi_Minh',
            'status' => 'closed',
            'open_slot' => null,
            'opened_at' => $now,
            'opened_by' => $this->owner->id,
            'closed_at' => $now,
            'closed_by' => $this->owner->id,
            'version' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sessionId = DB::table('fnb_service_sessions')->insertGetId([
            'website_key' => 'website-main',
            'outlet_id' => $outletId,
            'public_id' => (string) Str::uuid(),
            'creation_key' => 'other-outlet-session',
            'business_day_id' => $dayId,
            'currency' => 'VND',
            'timezone_snapshot' => 'Asia/Ho_Chi_Minh',
            'service_type' => 'counter',
            'source_channel' => 'pos',
            'customer_profile_id' => $customerProfileId,
            'status' => 'closed',
            'guest_count' => 1,
            'customer_snapshot' => json_encode(['customer_profile_id' => $customerProfileId, 'name' => 'Other Outlet'], JSON_THROW_ON_ERROR),
            'opened_at' => $now,
            'opened_by' => $this->owner->id,
            'closed_at' => $now,
            'closed_by' => $this->owner->id,
            'version' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$outletId, $sessionId];
    }
}
