<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\SiteContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Domain\FnbValidationException;
use Modules\FnbPos\Services\FnbService;
use Tests\TestCase;

final class FnbDomainInvariantTest extends TestCase
{
    use RefreshDatabase;

    private FnbService $fnb;

    private FnbContext $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $this->actingAs($owner, 'admin');
        $this->postJson('/admin/api/modules/fnb-pos/install')->assertOk();
        $this->postJson('/admin/api/modules/fnb-pos/enable')->assertOk();

        $this->fnb = app(FnbService::class);
        $website = app(SiteContext::class)->websiteKey();
        $onboard = $this->fnb->onboard(new FnbContext($website, 0, $owner->id), [
            'outlet' => ['code' => 'INV', 'name' => 'Invariant Cafe', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND'],
            'terminal' => ['code' => 'POS-1', 'name' => 'POS 1', 'type' => 'pos'],
            'station' => ['code' => 'BAR', 'name' => 'Bar'],
            'table_count' => 0,
        ], (string) Str::uuid());
        $this->ctx = new FnbContext(
            $website,
            (int) $onboard['resource']['outlet']['id'],
            $owner->id,
            (int) $onboard['resource']['terminal']['id'],
        );
    }

    public function test_catalog_replacement_keeps_historical_variants_but_replaces_categories(): void
    {
        $menuId = (int) DB::table('fnb_menus')->value('id');
        $categoryIds = [];
        foreach ([['HOT', 'Nóng'], ['COLD', 'Lạnh']] as $index => [$code, $name]) {
            $categoryIds[] = DB::table('fnb_menu_categories')->insertGetId([
                'website_key' => $this->ctx->websiteKey, 'menu_id' => $menuId, 'code' => $code, 'name' => $name,
                'sort_order' => $index, 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $station = (int) DB::table('fnb_prep_stations')->value('id');
        $created = $this->fnb->saveCatalogItem($this->ctx, [
            'code' => 'LATTE', 'name' => 'Latte', 'category_ids' => $categoryIds,
            'variants' => [
                ['code' => 'S', 'name' => 'Small', 'base_price_minor' => 30_000, 'is_default' => true, 'station_id' => $station],
                ['code' => 'L', 'name' => 'Large', 'base_price_minor' => 40_000, 'station_id' => $station],
            ],
            'modifier_group_ids' => [],
        ], (string) Str::uuid());
        $item = $created['resource'];
        $small = collect($item['variants'])->firstWhere('code', 'S');
        $large = collect($item['variants'])->firstWhere('code', 'L');

        $updated = $this->fnb->saveCatalogItem($this->ctx, [
            'id' => $item['id'], 'code' => 'LATTE', 'name' => 'Latte', 'category_ids' => [$categoryIds[1]],
            'variants' => [[
                'id' => $large['id'], 'code' => 'L', 'name' => 'Large', 'base_price_minor' => 42_000,
                'is_default' => true, 'station_id' => $station,
            ]],
            'modifier_group_ids' => [],
        ], (string) Str::uuid(), (int) $item['version']);

        $this->assertSame([$categoryIds[1]], $updated['resource']['category_ids']);
        $this->assertDatabaseHas('fnb_item_variants', ['id' => $small['id'], 'status' => 'inactive', 'is_default' => false]);
        $this->assertDatabaseHas('fnb_item_variants', ['id' => $large['id'], 'status' => 'active', 'is_default' => true, 'base_price_minor' => 42_000]);
        $this->assertCount(1, $this->fnb->catalog($this->ctx)['resource']['items'][0]['variants']);
        $this->assertCount(2, $this->fnb->catalog($this->ctx, ['include_inactive' => true])['resource']['items'][0]['variants']);
    }

    public function test_split_multi_tender_refund_caps_versions_and_audit_replay_are_exact(): void
    {
        $station = (int) DB::table('fnb_prep_stations')->value('id');
        $item = $this->fnb->saveCatalogItem($this->ctx, [
            'code' => 'TEA', 'name' => 'Tea',
            'variant' => ['code' => 'DEFAULT', 'name' => 'Default', 'base_price_minor' => 1_000],
            'station_id' => $station, 'modifier_groups' => [],
        ], (string) Str::uuid())['resource'];
        $variantId = (int) $item['variants'][0]['id'];

        $day = $this->fnb->openBusinessDay($this->ctx, now()->toDateString(), (string) Str::uuid())['resource'];
        $shift = $this->fnb->openShift($this->ctx, (int) $day['id'], 10_000, (string) Str::uuid())['resource'];
        $session = $this->fnb->openSession($this->ctx, [
            'business_day_id' => $day['id'], 'service_type' => 'counter', 'guest_count' => 1,
        ], (string) Str::uuid())['resource'];
        $orderResult = $this->fnb->createOrder($this->ctx, (int) $session['id'], (int) $shift['id'], [], (string) Str::uuid(), (int) $session['version']);
        $order = $orderResult['resource'];
        $order = $this->fnb->addOrderLine($this->ctx, (int) $order['id'], [
            'variant_id' => $variantId, 'quantity' => '2.000000', 'modifier_option_ids' => [],
        ], (string) Str::uuid(), (int) $order['version'])['resource'];
        $order = $this->fnb->submitOrder($this->ctx, (int) $order['id'], (string) Str::uuid(), (int) $order['version'])['resource'];
        $lineId = (int) $order['lines'][0]['id'];

        $sessionVersion = (int) DB::table('fnb_service_sessions')->where('id', $session['id'])->value('version');
        $first = $this->fnb->createCheck($this->ctx, (int) $session['id'], [
            ['order_line_id' => $lineId, 'quantity' => '1.000000'],
        ], (string) Str::uuid(), $sessionVersion)['resource'];
        $sessionVersion = (int) DB::table('fnb_service_sessions')->where('id', $session['id'])->value('version');
        $second = $this->fnb->createCheck($this->ctx, (int) $session['id'], [], (string) Str::uuid(), $sessionVersion)['resource'];
        $this->assertSame(1.0, (float) $first['lines'][0]['allocated_quantity']);
        $this->assertSame(1.0, (float) $second['lines'][0]['allocated_quantity']);
        $this->assertSame(2_000, (int) $first['grand_total_minor'] + (int) $second['grand_total_minor']);

        $check = $this->fnb->finalizeCheck($this->ctx, (int) $first['id'], (string) Str::uuid(), (int) $first['version'])['resource'];
        $check = $this->fnb->setSettlementPlan($this->ctx, (int) $check['id'], 'mixed', (string) Str::uuid(), (int) $check['version'])['resource'];
        $cash = DB::table('fnb_payment_methods')->where('kind', 'cash')->firstOrFail();
        $transfer = DB::table('fnb_payment_methods')->where('kind', 'transfer')->firstOrFail();
        $paymentKey = (string) Str::uuid();
        $paymentPayload = ['payment_method_id' => $cash->id, 'amount_minor' => 400, 'tendered_minor' => 500];
        $firstPayment = $this->fnb->collectPayment($this->ctx, (int) $check['id'], (int) $shift['id'], $paymentPayload, $paymentKey, (int) $check['version']);
        $auditCount = DB::table('audit_logs')->where('action', 'fnb.command.payment.collect')->count();
        $replay = $this->fnb->collectPayment($this->ctx, (int) $check['id'], (int) $shift['id'], $paymentPayload, $paymentKey, (int) $check['version']);
        $this->assertTrue($replay['replayed']);
        $this->assertSame($auditCount, DB::table('audit_logs')->where('action', 'fnb.command.payment.collect')->count());

        $checkVersion = (int) $firstPayment['meta']['check_version'];
        try {
            $this->fnb->collectPayment($this->ctx, (int) $check['id'], (int) $shift['id'], [
                'payment_method_id' => $transfer->id, 'amount_minor' => 600, 'reference' => 'TX-1',
            ], (string) Str::uuid(), (int) $check['version']);
            $this->fail('A stale version must not collect a payment.');
        } catch (FnbConflictException $exception) {
            $this->assertArrayHasKey('current_versions', $exception->details);
        }
        $secondPayment = $this->fnb->collectPayment($this->ctx, (int) $check['id'], (int) $shift['id'], [
            'payment_method_id' => $transfer->id, 'amount_minor' => 600, 'reference' => 'TX-1',
        ], (string) Str::uuid(), $checkVersion);
        $this->assertSame('closed', $secondPayment['resource']['check']['status']);
        $this->assertSame('paid', $secondPayment['resource']['check']['projection']['payment_status']);

        $refund = $this->fnb->refundPayment($this->ctx, (int) $firstPayment['resource']['payment']['id'], (int) $shift['id'], [
            'amount_minor' => 400, 'reason' => 'Partial item refund',
            'allocations' => [['check_line_id' => $first['lines'][0]['id'], 'quantity' => '0.400000']],
        ], (string) Str::uuid());
        $this->assertSame(400, $refund['resource']['check']['projection']['refunded_total_minor']);
        $this->assertSame(600, $refund['resource']['check']['projection']['net_collected_total_minor']);
        $this->assertDatabaseHas('fnb_order_financial_projections', [
            'order_id' => $order['id'], 'gross_paid_total_minor' => 1_000, 'refunded_total_minor' => 400,
        ]);

        $trustedRefund = $this->fnb->refundAllocated($this->ctx, (int) $secondPayment['resource']['payment']['id'], (int) $shift['id'], [
            'amount_minor' => 600, 'reason' => 'Compensation allocation seam',
            'allocations' => [[
                'check_line_id' => $first['lines'][0]['id'], 'allocation_kind' => 'line', 'quantity' => '0.600000',
                'allocated_subtotal_minor' => 600, 'allocated_discount_minor' => 0,
                'allocated_service_charge_minor' => 0, 'allocated_tax_minor' => 0,
                'allocated_pricing_rounding_minor' => 0, 'allocated_cash_rounding_minor' => 0,
                'allocated_total_minor' => 600,
            ]],
        ], (string) Str::uuid());
        $this->assertSame('refunded', $trustedRefund['resource']['check']['projection']['refund_status']);
        $this->assertDatabaseHas('fnb_order_financial_projections', [
            'order_id' => $order['id'], 'gross_paid_total_minor' => 1_000, 'refunded_total_minor' => 1_000,
        ]);

        $this->expectException(FnbConflictException::class);
        $this->fnb->refundPayment($this->ctx, (int) $firstPayment['resource']['payment']['id'], (int) $shift['id'], [
            'amount_minor' => 1, 'reason' => 'Over cap refund',
            'allocations' => [['check_line_id' => $first['lines'][0]['id'], 'quantity' => '0.001000']],
        ], (string) Str::uuid());
    }

    public function test_draft_edits_discount_tax_allocation_and_check_replacement_guards_are_exact(): void
    {
        $station = (int) DB::table('fnb_prep_stations')->value('id');
        $item = $this->fnb->saveCatalogItem($this->ctx, [
            'code' => 'VAT8', 'name' => 'VAT inclusive item', 'tax_rate_bps' => 800, 'tax_inclusive' => true,
            'variant' => ['code' => 'ONE', 'name' => 'One', 'base_price_minor' => 108_000],
            'station_id' => $station, 'modifier_groups' => [],
        ], (string) Str::uuid())['resource'];
        $variantId = (int) $item['variants'][0]['id'];

        $day = $this->fnb->openBusinessDay($this->ctx, now()->toDateString(), (string) Str::uuid())['resource'];
        $shift = $this->fnb->openShift($this->ctx, (int) $day['id'], 0, (string) Str::uuid())['resource'];
        $session = $this->fnb->openSession($this->ctx, [
            'business_day_id' => $day['id'], 'service_type' => 'counter', 'guest_count' => 1,
        ], (string) Str::uuid())['resource'];
        $order = $this->fnb->createOrder($this->ctx, (int) $session['id'], (int) $shift['id'], [],
            (string) Str::uuid(), (int) $session['version'])['resource'];

        $order = $this->fnb->addOrderLine($this->ctx, (int) $order['id'], [
            'variant_id' => $variantId, 'quantity' => '2',
        ], (string) Str::uuid(), (int) $order['version'])['resource'];
        $firstLineId = (int) $order['lines'][0]['id'];
        $this->assertSame(200_000, (int) $order['subtotal_minor']);
        $this->assertSame(16_000, (int) $order['tax_total_minor']);

        $order = $this->fnb->updateDraftOrderLine($this->ctx, $firstLineId, [
            'variant_id' => $variantId, 'quantity' => '1', 'note' => 'updated',
        ], (string) Str::uuid(), (int) $order['version'])['resource'];
        $this->assertSame(100_000, (int) $order['subtotal_minor']);
        $this->assertSame('updated', $order['lines'][0]['note']);

        $order = $this->fnb->addOrderLine($this->ctx, (int) $order['id'], [
            'variant_id' => $variantId, 'quantity' => '1',
        ], (string) Str::uuid(), (int) $order['version'])['resource'];
        $removedLineId = (int) $order['lines'][1]['id'];
        $order = $this->fnb->removeDraftOrderLine($this->ctx, $removedLineId, (string) Str::uuid(), (int) $order['version'])['resource'];
        $this->assertCount(1, $order['lines']);
        $this->assertDatabaseMissing('fnb_order_lines', ['id' => $removedLineId]);

        foreach ([2, 3] as $unused) {
            $order = $this->fnb->addOrderLine($this->ctx, (int) $order['id'], [
                'variant_id' => $variantId, 'quantity' => '1',
            ], (string) Str::uuid(), (int) $order['version'])['resource'];
        }
        $discountKey = (string) Str::uuid();
        $discountInput = ['amount_minor' => 100, 'reason' => 'Stable allocation test'];
        $discountExpectedVersion = (int) $order['version'];
        $discounted = $this->fnb->applyOrderDiscount($this->ctx, (int) $order['id'], $discountInput,
            $discountKey, $discountExpectedVersion);
        $order = $discounted['resource'];
        $sortedLines = collect($order['lines'])->sortBy(fn (array $line): string => (string) $line['public_id'], SORT_STRING)->values();
        $this->assertSame(
            [34, 33, 33],
            $sortedLines->pluck('discount_total_minor')->map(fn ($value) => (int) $value)->all(),
            $sortedLines->map(fn (array $line): array => ['id' => $line['id'], 'public_id' => $line['public_id'], 'discount' => $line['discount_total_minor']])->toJson(),
        );
        $this->assertSame([7_997, 7_997, 7_997], $sortedLines->pluck('tax_total_minor')->map(fn ($value) => (int) $value)->all());
        $this->assertSame(300_000, (int) $order['subtotal_minor']);
        $this->assertSame(100, (int) $order['discount_total_minor']);
        $this->assertSame(23_991, (int) $order['tax_total_minor']);
        $this->assertSame(323_891, (int) $order['grand_total_minor']);
        $this->assertCount(1, $order['adjustments']);
        $this->assertTrue($this->fnb->applyOrderDiscount($this->ctx, (int) $order['id'], $discountInput,
            $discountKey, $discountExpectedVersion)['replayed']);
        $this->assertSame(1, DB::table('fnb_order_adjustments')->where('order_id', $order['id'])->count());

        try {
            $this->fnb->addOrderLine($this->ctx, (int) $order['id'], [
                'variant_id' => $variantId, 'quantity' => '1',
            ], (string) Str::uuid(), (int) $order['version']);
            $this->fail('Line mutation after a pricing adjustment must be blocked.');
        } catch (FnbConflictException) {
            $this->addToAssertionCount(1);
        }

        $order = $this->fnb->submitOrder($this->ctx, (int) $order['id'], (string) Str::uuid(), (int) $order['version'])['resource'];
        $this->assertSame(100, (int) data_get($order, 'pricing_snapshot.components_minor.discount'));
        try {
            $this->fnb->applyOrderDiscount($this->ctx, (int) $order['id'], ['amount_minor' => 1, 'reason' => 'Too late'],
                (string) Str::uuid(), (int) $order['version']);
            $this->fail('Submitted order repricing must be blocked.');
        } catch (FnbConflictException) {
            $this->addToAssertionCount(1);
        }

        $sessionVersion = (int) DB::table('fnb_service_sessions')->where('id', $session['id'])->value('version');
        $check = $this->fnb->createCheck($this->ctx, (int) $session['id'], [], (string) Str::uuid(), $sessionVersion)['resource'];
        $check = $this->fnb->finalizeCheck($this->ctx, (int) $check['id'], (string) Str::uuid(), (int) $check['version'])['resource'];
        $check = $this->fnb->reopenCheck($this->ctx, (int) $check['id'], 'Correct customer split', (string) Str::uuid(), (int) $check['version'])['resource'];
        $this->assertSame('open', $check['status']);
        $check = $this->fnb->finalizeCheck($this->ctx, (int) $check['id'], (string) Str::uuid(), (int) $check['version'])['resource'];
        $check = $this->fnb->setSettlementPlan($this->ctx, (int) $check['id'], 'mixed', (string) Str::uuid(), (int) $check['version'])['resource'];
        try {
            $this->fnb->reopenCheck($this->ctx, (int) $check['id'], 'Plan already selected', (string) Str::uuid(), (int) $check['version']);
            $this->fail('Settlement-planned check reopen must be blocked.');
        } catch (FnbConflictException) {
            $this->addToAssertionCount(1);
        }
        $check = $this->fnb->voidCheck($this->ctx, (int) $check['id'], 'Replace incorrect check', (string) Str::uuid(), (int) $check['version'])['resource'];
        $this->assertSame('void', $check['status']);

        $sessionVersion = (int) DB::table('fnb_service_sessions')->where('id', $session['id'])->value('version');
        $replacement = $this->fnb->createCheck($this->ctx, (int) $session['id'], [], (string) Str::uuid(), $sessionVersion)['resource'];
        $this->assertSame(323_891, (int) $replacement['grand_total_minor']);
        $this->assertCount(3, $replacement['lines']);
    }

    public function test_reports_separate_closed_sales_from_processing_day_refund_cash_flow_and_scope_filters(): void
    {
        $item = $this->fnb->saveCatalogItem($this->ctx, [
            'code' => 'REPORT', 'name' => 'Report service', 'item_type' => 'service',
            'tax_rate_bps' => 800, 'tax_inclusive' => true,
            'variant' => ['code' => 'ONE', 'name' => 'One', 'base_price_minor' => 108_000],
            'modifier_groups' => [],
        ], (string) Str::uuid())['resource'];
        $variantId = (int) $item['variants'][0]['id'];
        $transferId = (int) DB::table('fnb_payment_methods')->where('outlet_id', $this->ctx->outletId)->where('kind', 'transfer')->value('id');

        $dayOneDate = now()->toDateString();
        $dayOne = $this->fnb->openBusinessDay($this->ctx, $dayOneDate, (string) Str::uuid())['resource'];
        $shiftOne = $this->fnb->openShift($this->ctx, (int) $dayOne['id'], 0, (string) Str::uuid())['resource'];
        $sessionOne = $this->fnb->openSession($this->ctx, [
            'business_day_id' => $dayOne['id'], 'service_type' => 'counter', 'guest_count' => 1,
        ], (string) Str::uuid())['resource'];
        $orderOne = $this->fnb->createOrder($this->ctx, (int) $sessionOne['id'], (int) $shiftOne['id'], [],
            (string) Str::uuid(), (int) $sessionOne['version'])['resource'];
        $orderOne = $this->fnb->addOrderLine($this->ctx, (int) $orderOne['id'], [
            'variant_id' => $variantId, 'quantity' => '1',
        ], (string) Str::uuid(), (int) $orderOne['version'])['resource'];
        $orderOne = $this->fnb->submitOrder($this->ctx, (int) $orderOne['id'], (string) Str::uuid(), (int) $orderOne['version'])['resource'];
        $sessionVersion = (int) DB::table('fnb_service_sessions')->where('id', $sessionOne['id'])->value('version');
        $checkOne = $this->fnb->createCheck($this->ctx, (int) $sessionOne['id'], [], (string) Str::uuid(), $sessionVersion)['resource'];
        $checkLineOne = (int) $checkOne['lines'][0]['id'];
        $checkOne = $this->fnb->finalizeCheck($this->ctx, (int) $checkOne['id'], (string) Str::uuid(), (int) $checkOne['version'])['resource'];
        $checkOne = $this->fnb->setSettlementPlan($this->ctx, (int) $checkOne['id'], 'single_non_cash', (string) Str::uuid(), (int) $checkOne['version'])['resource'];
        $payment = $this->fnb->collectPayment($this->ctx, (int) $checkOne['id'], (int) $shiftOne['id'], [
            'payment_method_id' => $transferId, 'amount_minor' => 108_000, 'reference' => 'REPORT-DAY-ONE',
        ], (string) Str::uuid(), (int) $checkOne['version'])['resource']['payment'];

        $sessionVersion = (int) DB::table('fnb_service_sessions')->where('id', $sessionOne['id'])->value('version');
        $this->fnb->settleSession($this->ctx, (int) $sessionOne['id'], (string) Str::uuid(), $sessionVersion);
        $sessionVersion = (int) DB::table('fnb_service_sessions')->where('id', $sessionOne['id'])->value('version');
        $this->fnb->closeSession($this->ctx, (int) $sessionOne['id'], (string) Str::uuid(), $sessionVersion);
        $shiftVersion = (int) DB::table('fnb_shifts')->where('id', $shiftOne['id'])->value('version');
        $this->fnb->closeShift($this->ctx, (int) $shiftOne['id'], [
            ['payment_method_id' => $transferId, 'counted_minor' => 108_000],
        ], (string) Str::uuid(), $shiftVersion);
        $dayVersion = (int) DB::table('fnb_business_days')->where('id', $dayOne['id'])->value('version');
        $this->fnb->closeBusinessDay($this->ctx, (int) $dayOne['id'], (string) Str::uuid(), $dayVersion);

        $dayTwoDate = now()->addDay()->toDateString();
        $dayTwo = $this->fnb->openBusinessDay($this->ctx, $dayTwoDate, (string) Str::uuid())['resource'];
        $shiftTwo = $this->fnb->openShift($this->ctx, (int) $dayTwo['id'], 0, (string) Str::uuid())['resource'];
        $this->fnb->refundPayment($this->ctx, (int) $payment['id'], (int) $shiftTwo['id'], [
            'amount_minor' => 108_000, 'reason' => 'Refund processed next day',
            'allocations' => [['check_line_id' => $checkLineOne, 'quantity' => '1']],
        ], (string) Str::uuid());

        // An allocated but still-open day-two check must never count as a sale.
        $sessionTwo = $this->fnb->openSession($this->ctx, [
            'business_day_id' => $dayTwo['id'], 'service_type' => 'counter', 'guest_count' => 1,
        ], (string) Str::uuid())['resource'];
        $orderTwo = $this->fnb->createOrder($this->ctx, (int) $sessionTwo['id'], (int) $shiftTwo['id'], [],
            (string) Str::uuid(), (int) $sessionTwo['version'])['resource'];
        $orderTwo = $this->fnb->addOrderLine($this->ctx, (int) $orderTwo['id'], [
            'variant_id' => $variantId, 'quantity' => '1',
        ], (string) Str::uuid(), (int) $orderTwo['version'])['resource'];
        $this->fnb->submitOrder($this->ctx, (int) $orderTwo['id'], (string) Str::uuid(), (int) $orderTwo['version']);
        $sessionVersion = (int) DB::table('fnb_service_sessions')->where('id', $sessionTwo['id'])->value('version');
        $this->fnb->createCheck($this->ctx, (int) $sessionTwo['id'], [], (string) Str::uuid(), $sessionVersion);

        $otherTerminalId = DB::table('fnb_terminals')->insertGetId([
            'website_key' => $this->ctx->websiteKey, 'outlet_id' => $this->ctx->outletId,
            'public_id' => (string) Str::uuid(), 'code' => 'REPORT-OTHER', 'name' => 'Other terminal',
            'type' => 'pos', 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $dayOneReport = $this->fnb->summaryReport($this->ctx, ['business_day_id' => $dayOne['id']])['resource'];
        $this->assertSame(108_000, $dayOneReport['totals']['gross_collected_minor']);
        $this->assertSame(0, $dayOneReport['totals']['refund_disbursed_minor']);
        $this->assertSame(108_000, $dayOneReport['totals']['closed_sales_minor']);
        $this->assertSame(1, $dayOneReport['totals']['checks']);
        $this->assertSame(108_000, $dayOneReport['totals']['average_check_minor']);
        $this->assertSame(108_000, $dayOneReport['top_items'][0]['gross_sales_minor']);
        $this->assertSame(0, $dayOneReport['top_items'][0]['refunded_minor']);

        $dayTwoReport = $this->fnb->summaryReport($this->ctx, [
            'business_day_id' => $dayTwo['id'], 'shift_id' => $shiftTwo['id'], 'terminal_id' => $this->ctx->terminalId,
        ])['resource'];
        $this->assertSame(0, $dayTwoReport['totals']['gross_collected_minor']);
        $this->assertSame(108_000, $dayTwoReport['totals']['refund_disbursed_minor']);
        $this->assertSame(-108_000, $dayTwoReport['totals']['net_collected_minor']);
        $this->assertSame(0, $dayTwoReport['totals']['closed_sales_minor']);
        $this->assertSame(0, $dayTwoReport['totals']['checks']);
        $this->assertSame('0.000000', $dayTwoReport['top_items'][0]['quantity']);
        $this->assertSame('1.000000', $dayTwoReport['top_items'][0]['refunded_quantity']);
        $this->assertSame(-108_000, $dayTwoReport['top_items'][0]['net_sales_minor']);

        try {
            $this->fnb->summaryReport($this->ctx, [
                'business_day_id' => $dayTwo['id'], 'terminal_id' => $otherTerminalId,
            ]);
            $this->fail('A terminal-bound report context must reject a different terminal filter.');
        } catch (FnbValidationException) {
            $this->addToAssertionCount(1);
        }
        $otherTerminalContext = new FnbContext(
            $this->ctx->websiteKey,
            $this->ctx->outletId,
            $this->ctx->actorId,
            $otherTerminalId,
        );
        $otherTerminalReport = $this->fnb->summaryReport($otherTerminalContext, [
            'business_day_id' => $dayTwo['id'],
        ])['resource'];
        $this->assertSame(0, $otherTerminalReport['totals']['gross_collected_minor']);
        $this->assertSame(0, $otherTerminalReport['totals']['refund_disbursed_minor']);
        $this->assertSame(0, $otherTerminalReport['totals']['closed_sales_minor']);
        $this->assertSame([], $otherTerminalReport['top_items']);
    }
}
