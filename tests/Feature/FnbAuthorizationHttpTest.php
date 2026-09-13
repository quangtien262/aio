<?php

namespace Tests\Feature;

use App\Models\Admin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\FnbService;
use Modules\FnbPos\Services\Security\FnbStaffAssignmentService;
use Tests\TestCase;

class FnbAuthorizationHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_customer_create_cannot_smuggle_falsey_full_profile_fields(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $owner->forceFill([
            'password' => 'OwnerPassword123!',
            'must_change_password' => false,
        ])->save();
        $this->actingAs($owner, 'admin')->withSession(['admin_auth_version' => $owner->auth_version]);
        $this->postJson('/admin/api/modules/fnb-pos/install')->assertOk();
        $this->postJson('/admin/api/modules/fnb-pos/enable')->assertOk();

        [$outletId, $terminalId] = $this->draftOrder($owner);
        $cashier = Admin::factory()->create([
            'status' => 'active',
            'is_active' => true,
            'password' => 'CashierPassword123!',
            'must_change_password' => false,
        ]);
        DB::transaction(function () use ($owner, $outletId, $cashier): void {
            app(FnbStaffAssignmentService::class)->assign(
                $owner,
                'website-main',
                $outletId,
                $cashier,
                'fnb-cashier',
            );
        });

        $this->asAdmin($cashier->fresh(), $outletId, $terminalId);
        $this->withHeader('Idempotency-Key', 'customer-smuggled-consent')
            ->postJson('/admin/api/fnb/customers', [
                'name' => 'Smuggled Consent',
                'phone' => '0900000001',
                'marketing_consent' => false,
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'FNB_PERMISSION_DENIED');
        $this->assertDatabaseMissing('fnb_customer_profiles', ['phone_normalized' => '0900000001']);

        $this->withHeader('Idempotency-Key', 'customer-minimal-profile')
            ->postJson('/admin/api/fnb/customers', [
                'name' => 'Minimal Customer',
                'phone' => '0900000002',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Minimal Customer');
        $this->assertDatabaseHas('fnb_customer_profiles', ['phone_normalized' => '0900000002']);
    }

    public function test_operations_report_keeps_counts_and_quantities_but_projects_out_all_money(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $owner->forceFill([
            'password' => 'OwnerPassword123!',
            'must_change_password' => false,
        ])->save();
        $this->actingAs($owner, 'admin')->withSession(['admin_auth_version' => $owner->auth_version]);
        $this->postJson('/admin/api/modules/fnb-pos/install')->assertOk();
        $this->postJson('/admin/api/modules/fnb-pos/enable')->assertOk();

        [$outletId, $terminalId, $orderId] = $this->draftOrder($owner);
        $this->settleOrder($owner, $outletId, $terminalId, $orderId);

        $context = new FnbContext('website-main', $outletId, $owner->id, $terminalId);
        $raw = app(FnbService::class)->summaryReport($context)['resource'];
        $this->assertSame(25000, (int) $raw['totals']['gross_sales_minor']);
        $this->assertSame(25000, (int) $raw['top_items'][0]['gross_sales_minor']);

        $operator = Admin::factory()->create([
            'status' => 'active',
            'is_active' => true,
            'password' => 'OperatorPassword123!',
            'must_change_password' => false,
        ]);
        DB::transaction(function () use ($owner, $outletId, $operator): void {
            app(FnbStaffAssignmentService::class)->assign(
                $owner,
                'website-main',
                $outletId,
                $operator,
                'fnb-stockkeeper',
            );
        });

        $this->asAdmin($operator->fresh(), $outletId, $terminalId);
        $response = $this->getJson("/admin/api/fnb/outlets/{$outletId}/reports/summary?report_type=operations")
            ->assertOk()
            ->assertJsonPath('data.totals.checks', 1)
            ->assertJsonPath('data.totals.orders', 1)
            ->assertJsonPath('data.top_items.0.quantity', '1.000000');

        $data = $response->json('data');
        $this->assertSame(['period', 'totals', 'top_items'], array_keys($data));
        $this->assertSame(['checks', 'orders'], array_keys($data['totals']));
        $this->assertSame(
            ['item_id', 'item_code', 'item_name', 'quantity'],
            array_keys($data['top_items'][0]),
        );
        foreach ([
            'gross_sales_minor', 'refunds_minor', 'net_sales_minor', 'gross_collected_minor',
            'refund_disbursed_minor', 'net_collected_minor', 'closed_sales_minor',
            'average_check_minor', 'cash_variance_minor', 'refunded_minor', 'tenders',
            'basis', 'generated_at',
        ] as $sensitiveField) {
            $this->assertStringNotContainsString('"'.$sensitiveField.'"', $response->getContent());
        }
    }

    public function test_discount_requires_real_two_person_approval_and_persists_server_derived_evidence(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $owner->forceFill([
            'password' => 'OwnerPassword123!',
            'must_change_password' => false,
        ])->save();
        $this->actingAs($owner, 'admin')->withSession(['admin_auth_version' => $owner->auth_version]);
        $this->postJson('/admin/api/modules/fnb-pos/install')->assertOk();
        $this->postJson('/admin/api/modules/fnb-pos/enable')->assertOk();

        [$outletId, $terminalId, $orderId] = $this->draftOrder($owner);
        $requesterPassword = 'RequesterPassword123!';
        $approverPassword = 'ApproverPassword123!';
        $requester = Admin::factory()->create([
            'status' => 'active',
            'is_active' => true,
            'password' => $requesterPassword,
            'must_change_password' => false,
        ]);
        $approver = Admin::factory()->create([
            'status' => 'active',
            'is_active' => true,
            'password' => $approverPassword,
            'must_change_password' => false,
        ]);
        DB::transaction(function () use ($owner, $outletId, $requester, $approver): void {
            $staff = app(FnbStaffAssignmentService::class);
            $staff->assign($owner, 'website-main', $outletId, $requester, 'fnb-cashier');
            $staff->assign($owner, 'website-main', $outletId, $approver, 'fnb-manager');
        });
        $requester = $requester->fresh();
        $approver = $approver->fresh();

        $commandKey = 'discount-two-person-command';
        $payload = [
            'amount_minor' => 1000,
            'reason' => 'Approved service recovery discount',
            'expected_version' => (int) DB::table('fnb_orders')->where('id', $orderId)->value('version'),
            // These attacker-controlled fields must never become authority.
            'approved' => true,
            'approved_by' => $requester->id,
            'approval_id' => 999999,
        ];
        $commandUrl = "/admin/api/fnb/orders/{$orderId}/discount";

        $this->asAdmin($requester, $outletId, $terminalId);
        $challenge = $this->withHeader('Idempotency-Key', $commandKey)
            ->postJson($commandUrl, $payload)
            ->assertStatus(423)
            ->assertJsonPath('code', 'FNB_REAUTH_REQUIRED')
            ->assertJsonPath('details.action', 'order.discount')
            ->assertJsonPath('details.subject', (string) $orderId);
        $payloadHash = (string) $challenge->json('details.payload_hash');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $payloadHash);
        $this->assertDatabaseCount('fnb_order_adjustments', 0);

        $requesterProof = $this->postJson('/admin/api/fnb/reauth/proofs', [
            'action' => 'order.discount',
            'subject' => (string) $orderId,
            'payload_hash' => $payloadHash,
            'password' => $requesterPassword,
        ])->assertOk()->json('data.reauth_proof');
        $this->assertNotEmpty($requesterProof);

        $this->withHeaders([
            'Idempotency-Key' => $commandKey,
            'X-FNB-Reauth-Proof' => $requesterProof,
            'X-FNB-Approval-Token' => str_repeat('f', 43),
        ])->postJson($commandUrl, $payload)
            ->assertForbidden()
            ->assertJsonPath('code', 'FNB_PERMISSION_DENIED');
        $this->assertDatabaseCount('fnb_order_adjustments', 0);
        $this->assertNull(DB::table('admin_reauth_proofs')->where('token_hash', hash('sha256', $requesterProof))->value('consumed_at'));

        $approval = $this->withHeader('Idempotency-Key', 'discount-approval-request')
            ->postJson('/admin/api/fnb/approvals', [
                'action' => 'order.discount',
                'subject' => (string) $orderId,
                'payload_hash' => $payloadHash,
                'reason' => 'Manager must authorize this discount',
            ])->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.requester_id', $requester->id)
            ->json('data');
        $approvalId = (int) $approval['id'];
        $this->assertArrayNotHasKey('approval_token', $approval);

        $this->withHeaders([
            'X-FNB-Reauth-Proof' => 'forged-self-approval-proof',
        ])->postJson("/admin/api/fnb/approvals/{$approvalId}/approve", [
            'expected_version' => 1,
            'approver_id' => $approver->id,
        ])->assertForbidden()->assertJsonPath('code', 'FNB_PERMISSION_DENIED');
        $this->assertDatabaseHas('fnb_approvals', [
            'id' => $approvalId,
            'status' => 'pending',
            'approver_id' => null,
        ]);

        $this->asAdmin($approver, $outletId, $terminalId);
        $approverProof = $this->postJson('/admin/api/fnb/reauth/proofs', [
            'action' => 'approval.approve',
            'subject' => (string) $approval['public_id'],
            'payload_hash' => $payloadHash,
            'password' => $approverPassword,
        ])->assertOk()->json('data.reauth_proof');
        $approved = $this->withHeader('X-FNB-Reauth-Proof', $approverProof)
            ->postJson("/admin/api/fnb/approvals/{$approvalId}/approve", [
                'expected_version' => 1,
                'note' => 'Verified by the duty manager',
            ])->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approver_id', $approver->id)
            ->json('data');
        $this->assertArrayNotHasKey('approval_token', $approved);

        $this->asAdmin($requester, $outletId, $terminalId);
        $claimedToken = $this->postJson("/admin/api/fnb/approvals/{$approvalId}/claim-token")
            ->assertOk()
            ->json('data.approval_token');
        $this->assertNotEmpty($claimedToken);

        $completed = $this->withHeaders([
            'Idempotency-Key' => $commandKey,
            'X-FNB-Reauth-Proof' => $requesterProof,
            'X-FNB-Approval-Token' => $claimedToken,
        ])->postJson($commandUrl, $payload)
            ->assertOk()
            ->assertJsonPath('meta.replayed', false);

        $adjustment = DB::table('fnb_order_adjustments')->where('order_id', $orderId)->firstOrFail();
        $event = DB::table('fnb_order_events')->where('order_id', $orderId)
            ->where('event_type', 'discount_applied')->firstOrFail();
        $this->assertSame($requester->id, (int) $adjustment->actor_id);
        $this->assertSame($approvalId, (int) $adjustment->approval_id);
        $this->assertNotSame(999999, (int) $adjustment->approval_id);
        $this->assertSame($approver->id, (int) $event->approver_id);
        $this->assertDatabaseHas('fnb_approvals', [
            'id' => $approvalId,
            'requester_id' => $requester->id,
            'approver_id' => $approver->id,
            'status' => 'consumed',
        ]);
        $this->assertNotNull(DB::table('fnb_approvals')->where('id', $approvalId)->value('consumed_at'));
        $this->assertNotNull(DB::table('admin_reauth_proofs')->where('token_hash', hash('sha256', $requesterProof))->value('consumed_at'));
        $this->assertSame(
            ['requested', 'approved', 'token_claimed', 'consumed'],
            DB::table('fnb_approval_events')->where('approval_id', $approvalId)->orderBy('id')->pluck('event_type')->all(),
        );
        $this->assertSame(1000, (int) $completed->json('data.adjustments.0.amount_minor'));
    }

    /** @return array{int,int,int} */
    private function draftOrder(Admin $owner): array
    {
        $fnb = app(FnbService::class);
        $onboarded = $fnb->onboard(new FnbContext('website-main', 0, $owner->id), [
            'outlet' => [
                'code' => 'AUTH-CAFE',
                'name' => 'Authorization Cafe',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'currency' => 'VND',
            ],
            'terminal' => ['code' => 'POS-AUTH', 'name' => 'Authorization POS', 'type' => 'pos'],
            'station' => ['code' => 'AUTH-BAR', 'name' => 'Authorization Bar'],
            'payment_methods' => [['code' => 'CASH', 'name' => 'Cash', 'kind' => 'cash']],
            'sample_menu' => false,
            'table_count' => 0,
        ], 'auth-http-onboard');
        $outletId = (int) $onboarded['resource']['outlet']['id'];
        $terminalId = (int) $onboarded['resource']['terminal']['id'];
        $stationId = (int) $onboarded['resource']['station']['id'];
        $context = new FnbContext('website-main', $outletId, $owner->id, $terminalId);

        $item = $fnb->saveCatalogItem($context, [
            'code' => 'AUTH-LATTE',
            'name' => 'Authorization Latte',
            'status' => 'active',
            'tax_category' => 'standard',
            'tax_rate_bps' => 0,
            'tax_inclusive' => true,
            'variant' => [
                'code' => 'DEFAULT',
                'name' => 'Default',
                'base_price_minor' => 25000,
            ],
            'station_id' => $stationId,
            'modifier_groups' => [],
        ], 'auth-http-item');
        $variantId = (int) $item['resource']['variants'][0]['id'];
        $day = $fnb->openBusinessDay($context, now()->toDateString(), 'auth-http-day');
        $shift = $fnb->openShift($context, (int) $day['resource']['id'], 0, 'auth-http-shift');
        $session = $fnb->openSession($context, [
            'business_day_id' => (int) $day['resource']['id'],
            'service_type' => 'counter',
        ], 'auth-http-session');
        $order = $fnb->createOrder(
            $context,
            (int) $session['resource']['id'],
            (int) $shift['resource']['id'],
            [],
            'auth-http-order',
            (int) $session['resource']['version'],
        );
        $orderId = (int) $order['resource']['id'];
        $fnb->addOrderLine($context, $orderId, [
            'variant_id' => $variantId,
            'quantity' => '1.000000',
        ], 'auth-http-line', (int) $order['resource']['version']);

        return [$outletId, $terminalId, $orderId];
    }

    private function settleOrder(Admin $owner, int $outletId, int $terminalId, int $orderId): void
    {
        $fnb = app(FnbService::class);
        $context = new FnbContext('website-main', $outletId, $owner->id, $terminalId);
        $order = DB::table('fnb_orders')->where('id', $orderId)->firstOrFail();
        $fnb->submitOrder($context, $orderId, 'auth-http-submit', (int) $order->version);

        $sessionVersion = (int) DB::table('fnb_service_sessions')
            ->where('id', $order->session_id)
            ->value('version');
        $check = $fnb->createCheck(
            $context,
            (int) $order->session_id,
            [],
            'auth-http-check',
            $sessionVersion,
        );
        $checkId = (int) $check['resource']['id'];
        $finalized = $fnb->finalizeCheck(
            $context,
            $checkId,
            'auth-http-finalize',
            (int) $check['resource']['version'],
        );
        $planned = $fnb->setSettlementPlan(
            $context,
            $checkId,
            'single_cash',
            'auth-http-plan',
            (int) $finalized['resource']['version'],
        );
        $paymentMethodId = (int) DB::table('fnb_payment_methods')
            ->where('website_key', 'website-main')
            ->where('outlet_id', $outletId)
            ->where('code', 'CASH')
            ->value('id');
        $fnb->collectPayment(
            $context,
            $checkId,
            (int) $order->shift_id,
            [
                'payment_method_id' => $paymentMethodId,
                'amount_minor' => 25000,
                'tendered_minor' => 25000,
            ],
            'auth-http-payment',
            (int) $planned['resource']['version'],
        );
    }

    private function asAdmin(Admin $admin, int $outletId, int $terminalId): void
    {
        $this->actingAs($admin, 'admin')->withSession(['admin_auth_version' => (int) $admin->auth_version]);
        $this->withHeaders([
            'X-FNB-Outlet' => (string) $outletId,
            'X-FNB-Terminal' => (string) $terminalId,
            'X-FNB-Reauth-Proof' => '',
            'X-FNB-Approval-Token' => '',
        ]);
    }
}
