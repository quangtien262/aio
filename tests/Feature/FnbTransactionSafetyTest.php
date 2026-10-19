<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\SiteContext;
use Closure;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\FnbCompensationService;
use Modules\FnbPos\Services\FnbService;
use Tests\TestCase;

final class FnbTransactionSafetyTest extends TestCase
{
    use RefreshDatabase;

    private FnbService $fnb;

    private FnbContext $ctx;

    private array $shift;

    private array $session;

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
            'outlet' => ['code' => 'REVIEW', 'name' => 'Review fixture', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND'],
            'terminal' => ['code' => 'POS-1', 'name' => 'POS1', 'type' => 'pos'], 'station' => ['code' => 'BAR', 'name' => 'Bar'], 'table_count' => 0,
        ], (string) Str::uuid())['resource'];
        $this->ctx = new FnbContext($website, $onboard['outlet']['id'], $owner->id, $onboard['terminal']['id']);
        $day = $this->fnb->openBusinessDay($this->ctx, '2026-10-18', (string) Str::uuid())['resource'];
        $this->shift = $this->fnb->openShift($this->ctx, $day['id'], 100000, (string) Str::uuid())['resource'];
        $this->session = $this->fnb->openSession($this->ctx, ['business_day_id' => $day['id'], 'service_type' => 'counter', 'guest_count' => 1], (string) Str::uuid())['resource'];
    }

    private function order(int $price, string $quantity = '1.000000', int $discountMinor = 0): array
    {
        $item = $this->fnb->saveCatalogItem($this->ctx, ['code' => 'REVIEW-ITEM', 'name' => 'Review item', 'station_id' => (int) DB::table('fnb_prep_stations')->value('id'), 'variant' => ['code' => 'DEFAULT', 'name' => 'Default', 'base_price_minor' => $price]], (string) Str::uuid())['resource'];
        $order = $this->fnb->createOrder($this->ctx, $this->session['id'], $this->shift['id'], [], (string) Str::uuid(), $this->session['version'])['resource'];
        $order = $this->fnb->addOrderLine($this->ctx, $order['id'], ['variant_id' => $item['variants'][0]['id'], 'quantity' => $quantity], (string) Str::uuid(), $order['version'])['resource'];
        if ($discountMinor > 0) {
            $order = $this->fnb->applyOrderDiscount($this->ctx, $order['id'], ['amount_minor' => $discountMinor, 'reason' => 'Complimentary drink'], (string) Str::uuid(), $order['version'])['resource'];
        }

        return $this->fnb->submitOrder($this->ctx, $order['id'], (string) Str::uuid(), $order['version'])['resource'];
    }

    private function orderForBulkCompletion(bool $sameStation = false): array
    {
        $station = (array) DB::table('fnb_prep_stations')->first();
        unset($station['id']);
        $station['code'] = 'SECOND';
        $station['name'] = 'Bếp';
        $secondStation = DB::table('fnb_prep_stations')->insertGetId($station);
        $order = $this->fnb->createOrder($this->ctx, $this->session['id'], $this->shift['id'], [], (string) Str::uuid(), $this->session['version'])['resource'];
        foreach ([(int) DB::table('fnb_prep_stations')->value('id'), $sameStation ? (int) DB::table('fnb_prep_stations')->value('id') : $secondStation] as $index => $stationId) {
            $item = $this->fnb->saveCatalogItem($this->ctx, ['code' => 'BULK-'.$index, 'name' => 'Món '.$index, 'station_id' => $stationId, 'variant' => ['code' => 'DEFAULT', 'name' => 'Mặc định', 'base_price_minor' => 30000]], (string) Str::uuid())['resource'];
            $order = $this->fnb->addOrderLine($this->ctx, $order['id'], ['variant_id' => $item['variants'][0]['id'], 'quantity' => '2'], (string) Str::uuid(), $order['version'])['resource'];
        }
        return $this->fnb->submitOrder($this->ctx, $order['id'], (string) Str::uuid(), $order['version'])['resource'];
    }

    public function test_complete_order_serves_all_stations_atomically_and_replays_without_duplicate_quantities(): void
    {
        $order = $this->orderForBulkCompletion();
        $tickets = DB::table('fnb_kitchen_tickets')->where('order_id', $order['id'])->get();
        $this->assertCount(2, $tickets);
        $line = DB::table('fnb_kitchen_ticket_lines')->where('ticket_id', $tickets[0]->id)->first();
        $this->fnb->transitionKitchenLine($this->ctx, $line->id, 'preparing', (string) Str::uuid(), 1);
        $versions = DB::table('fnb_kitchen_tickets')->where('order_id', $order['id'])->pluck('version', 'id')->all();
        $key = (string) Str::uuid();
        $owner = Admin::findOrFail(Admin::SYSTEM_OWNER_ID);
        $owner->forceFill(['must_change_password' => false])->save();
        $this->actingAs($owner, 'admin')->withSession(['admin_auth_version' => $owner->auth_version]);
        $result = $this->withHeaders(['X-FNB-Outlet' => (string) $this->ctx->outletId, 'X-FNB-Terminal' => (string) $this->ctx->terminalId, 'Idempotency-Key' => $key])
            ->postJson('/admin/api/fnb/kitchen/orders/'.$order['id'].'/complete', ['expected_ticket_versions' => $versions])->assertOk()->json('data');
        $this->assertSame('completed', $result['lifecycle_status']);
        $this->assertSame('fulfilled', $result['fulfillment_status']);
        $this->assertTrue($this->fnb->completeKitchenOrder($this->ctx, $order['id'], $versions, $key)['replayed']);
        foreach (DB::table('fnb_order_lines')->where('order_id', $order['id'])->get() as $row) {
            $this->assertSame('served', $row->status);
            $this->assertSame(0, bccomp('2', (string) $row->fulfilled_quantity, 6));
        }
        foreach (DB::table('fnb_kitchen_ticket_lines')->where('order_id', $order['id'])->get() as $row) {
            $this->assertSame('served', $row->status_rollup);
            $this->assertSame(0, bccomp('2', (string) $row->served_quantity, 6));
        }
    }

    public function test_bulk_completion_rejects_missing_stale_and_foreign_versions_and_rolls_back_all_lines(): void
    {
        $order = $this->orderForBulkCompletion();
        $versions = DB::table('fnb_kitchen_tickets')->where('order_id', $order['id'])->pluck('version', 'id')->all();
        $bad = $versions;
        $bad[array_key_first($bad)] = 99;
        foreach ([array_slice($versions, 0, 1, true), $bad, $versions + [999999 => 1]] as $input) {
            try {
                $this->fnb->completeKitchenOrder($this->ctx, $order['id'], $input, (string) Str::uuid());
                $this->fail('Stale or incomplete ticket versions must fail.');
            } catch (FnbConflictException) {
                $this->assertSame(0, DB::table('fnb_kitchen_ticket_lines')->where('order_id', $order['id'])->where('status_rollup', 'served')->count());
            }
        }
        $last = DB::table('fnb_order_lines')->where('order_id', $order['id'])->orderByDesc('id')->first();
        DB::table('fnb_order_lines')->where('id', $last->id)->update(['fulfilled_quantity' => 10]);
        try {
            $this->fnb->completeKitchenOrder($this->ctx, $order['id'], $versions, (string) Str::uuid());
            $this->fail('Inconsistent quantity ledgers must roll back every line.');
        } catch (FnbConflictException) {
            $this->assertSame(['waiting'], DB::table('fnb_kitchen_ticket_lines')->where('order_id', $order['id'])->pluck('status_rollup')->unique()->values()->all());
            $this->assertSame($versions, DB::table('fnb_kitchen_tickets')->where('order_id', $order['id'])->pluck('version', 'id')->all());
        }
    }

    public function test_bulk_completion_http_requires_kitchen_update_permission(): void
    {
        $order = $this->orderForBulkCompletion();
        $staff = Admin::factory()->create(['must_change_password' => false])->fresh();
        $this->actingAs($staff, 'admin')->withSession(['admin_auth_version' => $staff->auth_version]);
        $versions = DB::table('fnb_kitchen_tickets')->where('order_id', $order['id'])->pluck('version', 'id')->all();
        $this->withHeaders(['X-FNB-Outlet' => (string) $this->ctx->outletId, 'X-FNB-Terminal' => (string) $this->ctx->terminalId, 'Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/admin/api/fnb/kitchen/orders/'.$order['id'].'/complete', ['expected_ticket_versions' => $versions])->assertForbidden();
        $this->assertSame(['waiting'], DB::table('fnb_kitchen_ticket_lines')->where('order_id', $order['id'])->pluck('status_rollup')->unique()->values()->all());
    }

    public function test_kitchen_completed_history_includes_partially_served_and_fully_completed_tickets(): void
    {
        $order = $this->orderForBulkCompletion(true);
        $this->assertEmpty($this->fnb->pollKitchen($this->ctx, view: 'completed')['resource']['tickets']);
        $line = DB::table('fnb_kitchen_ticket_lines')->where('order_id', $order['id'])->first();
        foreach (['preparing', 'ready', 'served'] as $status) {
            $this->fnb->transitionKitchenLine($this->ctx, $line->id, $status, (string) Str::uuid(), $this->version('fnb_kitchen_tickets', $line->ticket_id));
        }
        $history = $this->fnb->pollKitchen($this->ctx, view: 'completed')['resource']['tickets'];
        $this->assertCount(1, $history);
        $this->assertCount(1, $this->fnb->pollKitchen($this->ctx)['resource']['tickets']);
        $served = collect($history[0]['lines'])->firstWhere('status_rollup', 'served');
        $this->assertNotNull($served['served_at']);
        $this->assertSame($line->id, $served['id']);
        $stationId = DB::table('fnb_kitchen_tickets')->where('id', $line->ticket_id)->value('prep_station_id');
        $otherStationId = DB::table('fnb_prep_stations')->where('id', '!=', $stationId)->value('id');
        $this->assertCount(1, $this->fnb->pollKitchen($this->ctx, stationId: $stationId, view: 'completed')['resource']['tickets']);
        $this->assertEmpty($this->fnb->pollKitchen($this->ctx, stationId: $otherStationId, view: 'completed')['resource']['tickets']);
        $this->fnb->completeKitchenOrder($this->ctx, $order['id'], [$line->ticket_id => $this->version('fnb_kitchen_tickets', $line->ticket_id)], (string) Str::uuid());
        $this->assertEmpty($this->fnb->pollKitchen($this->ctx)['resource']['tickets']);
        $this->assertCount(1, $this->fnb->pollKitchen($this->ctx, view: 'completed')['resource']['tickets']);
        $owner = Admin::findOrFail(Admin::SYSTEM_OWNER_ID);
        $owner->forceFill(['must_change_password' => false])->save();
        $this->actingAs($owner, 'admin')->withSession(['admin_auth_version' => $owner->auth_version]);
        $this->withHeaders(['X-FNB-Terminal' => (string) $this->ctx->terminalId])
            ->getJson('/admin/api/fnb/outlets/'.$this->ctx->outletId.'/kitchen/tickets?view=completed')->assertOk()->assertJsonCount(1, 'data.tickets');
        $this->getJson('/admin/api/fnb/outlets/'.$this->ctx->outletId.'/kitchen/tickets?view=wrong')->assertUnprocessable();
    }

    private function check(): array
    {
        return $this->fnb->createCheck($this->ctx, $this->session['id'], [], (string) Str::uuid(), (int) DB::table('fnb_service_sessions')->where('id', $this->session['id'])->value('version'))['resource'];
    }

    public function test_order_add_and_edit_accept_only_positive_whole_item_quantities(): void
    {
        $item = $this->fnb->saveCatalogItem($this->ctx, [
            'code' => 'WHOLE', 'name' => 'Cafe Nâu',
            'station_id' => (int) DB::table('fnb_prep_stations')->where('outlet_id', $this->ctx->outletId)->value('id'),
            'variant' => ['code' => 'DEFAULT', 'name' => 'Mặc định', 'base_price_minor' => 30000],
        ], (string) Str::uuid())['resource'];
        $order = $this->fnb->createOrder($this->ctx, $this->session['id'], $this->shift['id'], [], (string) Str::uuid(), $this->session['version'])['resource'];
        $input = ['variant_id' => $item['variants'][0]['id'], 'quantity' => '1'];
        foreach (['0', '-1', '0.5', '1.25'] as $quantity) {
            try {
                $this->fnb->addOrderLine($this->ctx, $order['id'], array_replace($input, ['quantity' => $quantity]), (string) Str::uuid(), $order['version']);
                $this->fail('A fractional or nonpositive item quantity must be rejected.');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $this->assertArrayHasKey('quantity', $exception->errors());
            }
        }
        $order = $this->fnb->addOrderLine($this->ctx, $order['id'], $input, (string) Str::uuid(), $order['version'])['resource'];
        $lineId = $order['lines'][0]['id'];
        try {
            $this->fnb->updateDraftOrderLine($this->ctx, $lineId, array_replace($input, ['quantity' => '2.5']), (string) Str::uuid(), $order['version']);
            $this->fail('Editing must reject a fractional item quantity.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
        $order = $this->fnb->updateDraftOrderLine($this->ctx, $lineId, array_replace($input, ['quantity' => '2.000000']), (string) Str::uuid(), $order['version'])['resource'];
        $this->assertEquals(2, $order['lines'][0]['ordered_quantity']);
        $this->assertEquals(60000, $order['grand_total_minor']);
        $this->assertDatabaseCount('fnb_order_lines', 1);
    }

    public function test_empty_open_session_releases_its_table_once_and_allows_a_new_guest(): void
    {
        $areaId = DB::table('fnb_service_areas')->insertGetId([
            'website_key' => $this->ctx->websiteKey, 'outlet_id' => $this->ctx->outletId,
            'code' => 'RETURN', 'name' => 'Khu trả bàn', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $tableId = DB::table('fnb_dining_tables')->insertGetId([
            'website_key' => $this->ctx->websiteKey, 'outlet_id' => $this->ctx->outletId,
            'service_area_id' => $areaId,
            'code' => 'RETURN-1', 'name' => 'Bàn trả', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $input = ['business_day_id' => $this->session['business_day_id'], 'service_type' => 'dine_in', 'table_id' => $tableId];
        $session = $this->fnb->openSession($this->ctx, $input, (string) Str::uuid())['resource'];
        $this->assertConflict(fn () => $this->fnb->closeSession($this->ctx, $session['id'], (string) Str::uuid(), $session['version'] - 1));
        $this->assertDatabaseHas('fnb_service_session_tables', ['session_id' => $session['id'], 'active_slot' => 'active']);
        $key = (string) Str::uuid();
        $closed = $this->fnb->closeSession($this->ctx, $session['id'], $key, $session['version']);
        $this->assertSame('closed', $closed['resource']['status']);
        $this->assertDatabaseHas('fnb_service_session_tables', ['session_id' => $session['id'], 'active_slot' => null]);
        $this->assertNotNull(DB::table('fnb_service_session_tables')->where('session_id', $session['id'])->value('left_at'));
        $this->assertTrue($this->fnb->closeSession($this->ctx, $session['id'], $key, $session['version'])['replayed']);
        $newSession = $this->fnb->openSession($this->ctx, $input, (string) Str::uuid())['resource'];
        $this->assertNotSame($session['id'], $newSession['id']);
        $this->assertDatabaseHas('fnb_service_session_tables', ['session_id' => $newSession['id'], 'table_id' => $tableId, 'active_slot' => 'active']);
        $this->assertDatabaseCount('fnb_checks', 0);
    }

    public function test_direct_return_of_open_session_cannot_bypass_active_or_unbilled_orders(): void
    {
        $order = $this->order(30000);
        $this->assertConflict(fn () => $this->fnb->closeSession($this->ctx, $this->session['id'], (string) Str::uuid(), $this->version('fnb_service_sessions', $this->session['id'])));
        $this->serve($order);
        $this->assertConflict(fn () => $this->fnb->closeSession($this->ctx, $this->session['id'], (string) Str::uuid(), $this->version('fnb_service_sessions', $this->session['id'])));
        $this->assertDatabaseHas('fnb_service_sessions', ['id' => $this->session['id'], 'status' => 'open']);
    }

    public function test_paid_and_served_session_can_close_directly_without_requesting_settlement(): void
    {
        $order = $this->order(30000);
        $this->serve($order);
        $check = $this->plan($this->check());
        $this->assertConflict(fn () => $this->fnb->closeSession($this->ctx, $this->session['id'], (string) Str::uuid(), $this->version('fnb_service_sessions', $this->session['id'])));
        $cash = DB::table('fnb_payment_methods')->where('outlet_id', $this->ctx->outletId)->where('kind', 'cash')->value('id');
        $this->fnb->collectPayment($this->ctx, $check['id'], $this->shift['id'], ['payment_method_id' => $cash, 'amount_minor' => 30000], (string) Str::uuid(), $check['version']);
        $closed = $this->fnb->closeSession($this->ctx, $this->session['id'], (string) Str::uuid(), $this->version('fnb_service_sessions', $this->session['id']))['resource'];
        $this->assertSame('closed', $closed['status']);
        $this->assertDatabaseHas('fnb_checks', ['id' => $check['id'], 'status' => 'closed']);
        $this->assertDatabaseHas('fnb_payments', ['check_id' => $check['id'], 'status' => 'succeeded', 'amount_minor' => 30000]);
    }

    public function test_paid_settling_session_accepts_a_new_order_round_without_changing_the_paid_bill(): void
    {
        $original = $this->order(30000);
        $this->serve($original);
        $check = $this->plan($this->check());
        $cash = DB::table('fnb_payment_methods')->where('outlet_id', $this->ctx->outletId)->where('kind', 'cash')->value('id');
        $this->fnb->collectPayment($this->ctx, $check['id'], $this->shift['id'], ['payment_method_id' => $cash, 'amount_minor' => 30000], (string) Str::uuid(), $check['version']);
        $session = $this->fnb->settleSession($this->ctx, $this->session['id'], (string) Str::uuid(), $this->version('fnb_service_sessions', $this->session['id']))['resource'];
        $snapshots = [];
        foreach (['fnb_checks', 'fnb_check_lines', 'fnb_payments', 'fnb_order_lines'] as $table) {
            $snapshots[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        $key = (string) Str::uuid();
        $result = $this->fnb->createOrder($this->ctx, $session['id'], $this->shift['id'], [], $key, $session['version']);
        $new = $result['resource'];
        $this->assertNotSame($original['id'], $new['id']);
        $this->assertDatabaseHas('fnb_service_sessions', ['id' => $session['id'], 'status' => 'open', 'settling_at' => null, 'version' => $session['version'] + 1]);
        $this->assertTrue($this->fnb->createOrder($this->ctx, $session['id'], $this->shift['id'], [], $key, $session['version'])['replayed']);
        $this->assertDatabaseCount('fnb_orders', 2);
        $this->assertSame(1, DB::table('fnb_outbox_events')->where('event_type', 'fnb.service_session.resumed')->count());
        $this->assertConflict(fn () => $this->fnb->createOrder($this->ctx, $session['id'], $this->shift['id'], [], (string) Str::uuid(), $session['version']));
        foreach ($snapshots as $table => $snapshot) {
            $this->assertSame($snapshot, DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        }

        $new = $this->fnb->addOrderLine($this->ctx, $new['id'], ['variant_id' => $original['lines'][0]['variant_id'], 'quantity' => '2'], (string) Str::uuid(), $new['version'])['resource'];
        $this->fnb->submitOrder($this->ctx, $new['id'], (string) Str::uuid(), $new['version']);
        $nextBill = $this->fnb->createCheck($this->ctx, $session['id'], [], (string) Str::uuid(), $this->version('fnb_service_sessions', $session['id']))['resource'];
        $this->assertEquals(60000, $nextBill['grand_total_minor']);
        $this->assertCount(1, $nextBill['lines']);
        $this->assertSame($new['lines'][0]['id'], $nextBill['lines'][0]['order_line_id']);
        $this->assertDatabaseHas('fnb_checks', ['id' => $check['id'], 'status' => 'closed', 'grand_total_minor' => 30000]);
        $this->assertDatabaseCount('fnb_payments', 1);
    }

    public function test_closed_session_still_cannot_accept_an_additional_order(): void
    {
        $closed = $this->fnb->closeSession($this->ctx, $this->session['id'], (string) Str::uuid(), $this->session['version'])['resource'];
        $this->assertConflict(fn () => $this->fnb->createOrder($this->ctx, $closed['id'], $this->shift['id'], [], (string) Str::uuid(), $closed['version']));
        $this->assertDatabaseCount('fnb_orders', 0);
    }

    public function test_completed_unbilled_orders_block_session_and_shift_closure(): void
    {
        $order = $this->order(30000);
        $this->serve($order);
        $session = $this->fnb->settleSession($this->ctx, $this->session['id'], (string) Str::uuid(), $this->version('fnb_service_sessions', $this->session['id']))['resource'];
        $this->assertConflict(fn () => $this->fnb->closeSession($this->ctx, $session['id'], (string) Str::uuid(), $session['version']));
        $this->assertConflict(fn () => $this->fnb->closeShift($this->ctx, $this->shift['id'], [], (string) Str::uuid(), $this->version('fnb_shifts', $this->shift['id'])));
        $this->assertDatabaseHas('fnb_service_sessions', ['id' => $session['id'], 'status' => 'settling']);
        $this->assertDatabaseHas('fnb_shifts', ['id' => $this->shift['id'], 'status' => 'open']);
    }

    public function test_paying_only_part_of_the_fulfilled_quantity_cannot_release_the_session(): void
    {
        $order = $this->order(30000, '2.000000');
        $this->serve($order);
        $check = $this->fnb->createCheck($this->ctx, $this->session['id'], [['order_line_id' => $order['lines'][0]['id'], 'quantity' => '1.000000']], (string) Str::uuid(), $this->version('fnb_service_sessions', $this->session['id']))['resource'];
        $check = $this->plan($check);
        $cash = DB::table('fnb_payment_methods')->where('kind', 'cash')->value('id');
        $this->fnb->collectPayment($this->ctx, $check['id'], $this->shift['id'], ['payment_method_id' => $cash, 'amount_minor' => 30000], (string) Str::uuid(), $check['version']);
        $session = $this->fnb->settleSession($this->ctx, $this->session['id'], (string) Str::uuid(), $this->version('fnb_service_sessions', $this->session['id']))['resource'];
        $this->assertConflict(fn () => $this->fnb->closeSession($this->ctx, $session['id'], (string) Str::uuid(), $session['version']));
        $this->assertConflict(fn () => $this->fnb->closeShift($this->ctx, $this->shift['id'], [], (string) Str::uuid(), $this->version('fnb_shifts', $this->shift['id'])));
    }

    public function test_zero_charge_closure_records_sale_without_fake_money_and_replays_once(): void
    {
        $order = $this->order(30000, '1.000000', 30000);
        $this->serve($order);
        $check = $this->plan($this->check());
        $key = (string) Str::uuid();
        $result = $this->fnb->closeZeroChargeCheck($this->ctx, $check['id'], $this->shift['id'], 'Complimentary drink', $key, $check['version']);
        $this->assertSame('closed', $result['resource']['status']);
        $this->assertSame('paid', $result['resource']['projection']['payment_status']);
        $this->assertSame(0, $result['resource']['projection']['gross_paid_total_minor']);
        $this->assertTrue($this->fnb->closeZeroChargeCheck($this->ctx, $check['id'], $this->shift['id'], 'Complimentary drink', $key, $check['version'])['replayed']);
        $this->assertConflict(fn () => $this->fnb->closeZeroChargeCheck($this->ctx, $check['id'], $this->shift['id'], 'Complimentary drink', (string) Str::uuid(), $check['version']));
        $this->assertDatabaseCount('fnb_payments', 0);
        $this->assertDatabaseCount('fnb_cash_movements', 0);
        $this->assertDatabaseCount('fnb_stock_consumptions', 1);
        $this->assertDatabaseHas('fnb_check_adjustments', ['check_id' => $check['id'], 'kind' => 'zero_charge_close', 'amount_minor' => 0, 'reason' => 'Complimentary drink', 'actor_id' => $this->ctx->actorId]);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'fnb.command.check.close_zero_charge')->count());
        $this->assertDatabaseHas('fnb_order_financial_projections', ['order_id' => $order['id'], 'payment_status' => 'paid', 'gross_paid_total_minor' => 0]);
        $session = $this->fnb->settleSession($this->ctx, $this->session['id'], (string) Str::uuid(), $this->version('fnb_service_sessions', $this->session['id']))['resource'];
        $closed = $this->fnb->closeSession($this->ctx, $session['id'], (string) Str::uuid(), $session['version'])['resource'];
        $this->assertSame('closed', $closed['status']);
        $cash = DB::table('fnb_payment_methods')->where('kind', 'cash')->value('id');
        $shift = $this->fnb->closeShift($this->ctx, $this->shift['id'], [['payment_method_id' => $cash, 'counted_minor' => 100000]], (string) Str::uuid(), $this->version('fnb_shifts', $this->shift['id']))['resource'];
        $this->assertSame('reconciled', $shift['status']);
    }

    public function test_zero_charge_command_rejects_positive_unplanned_and_wrong_terminal_checks(): void
    {
        $order = $this->order(0);
        $check = $this->check();
        $this->assertConflict(fn () => $this->fnb->closeZeroChargeCheck($this->ctx, $check['id'], $this->shift['id'], 'Not finalized', (string) Str::uuid(), $check['version']));
        $check = $this->plan($check);
        $unbound = new FnbContext($this->ctx->websiteKey, $this->ctx->outletId, $this->ctx->actorId);
        $this->assertConflict(fn () => $this->fnb->closeZeroChargeCheck($unbound, $check['id'], $this->shift['id'], 'Missing terminal', (string) Str::uuid(), $check['version']));
        DB::table('fnb_checks')->where('id', $check['id'])->update(['settlement_total_minor' => 1]);
        $this->assertConflict(fn () => $this->fnb->closeZeroChargeCheck($this->ctx, $check['id'], $this->shift['id'], 'Nonzero balance', (string) Str::uuid(), $check['version']));
        $this->assertDatabaseCount('fnb_check_adjustments', 0);
        $this->assertDatabaseCount('fnb_stock_consumptions', 0);
    }

    public function test_voiding_an_unpaid_check_releases_line_for_void_without_deleting_history(): void
    {
        $order = $this->order(30000);
        $check = $this->check();
        $this->assertConflict(fn () => $this->fnb->voidOrderLine($this->ctx, $order['lines'][0]['id'], 'Check still active', (string) Str::uuid(), $order['version']));
        $this->fnb->voidCheck($this->ctx, $check['id'], 'Customer cancelled', (string) Str::uuid(), $check['version']);
        $void = $this->fnb->voidOrderLine($this->ctx, $order['lines'][0]['id'], 'Customer cancelled', (string) Str::uuid(), $order['version'])['resource'];
        $this->assertSame('voided', $void['lines'][0]['status']);
        $this->assertDatabaseHas('fnb_checks', ['id' => $check['id'], 'status' => 'void']);
        $this->assertDatabaseHas('fnb_check_lines', ['check_id' => $check['id'], 'order_line_id' => $order['lines'][0]['id']]);
        $session = $this->fnb->settleSession($this->ctx, $this->session['id'], (string) Str::uuid(), $this->version('fnb_service_sessions', $this->session['id']))['resource'];
        $this->assertSame('closed', $this->fnb->closeSession($this->ctx, $session['id'], (string) Str::uuid(), $session['version'])['resource']['status']);
    }

    public function test_void_bill_history_does_not_bypass_a_new_paid_allocation(): void
    {
        $order = $this->order(30000);
        $original = $this->check();
        $this->fnb->voidCheck($this->ctx, $original['id'], 'Replace original bill', (string) Str::uuid(), $original['version']);
        $replacement = $this->plan($this->check());
        $cash = DB::table('fnb_payment_methods')->where('kind', 'cash')->value('id');
        $this->fnb->collectPayment($this->ctx, $replacement['id'], $this->shift['id'], ['payment_method_id' => $cash, 'amount_minor' => 30000], (string) Str::uuid(), $replacement['version']);
        $this->assertConflict(fn () => $this->fnb->voidOrderLine($this->ctx, $order['lines'][0]['id'], 'Paid needs compensation', (string) Str::uuid(), $order['version']));
        $this->assertDatabaseHas('fnb_order_lines', ['id' => $order['lines'][0]['id'], 'status' => 'waiting']);
    }

    public function test_kds_preserves_modifier_snapshots_and_batches_ticket_reads(): void
    {
        $now = now();
        $group = DB::table('fnb_modifier_groups')->insertGetId(['website_key' => $this->ctx->websiteKey, 'code' => 'TOPPING', 'name' => 'Toppings', 'min_select' => 0, 'max_select' => 3, 'free_quantity' => 0, 'status' => 'active', 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $option = DB::table('fnb_modifier_options')->insertGetId(['website_key' => $this->ctx->websiteKey, 'group_id' => $group, 'code' => 'PEARL', 'name' => 'Original pearl topping', 'base_price_delta_minor' => 5000, 'status' => 'active', 'sort_order' => 0, 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $item = $this->fnb->saveCatalogItem($this->ctx, ['code' => 'TEA-TOPPING', 'name' => 'Tea', 'station_id' => (int) DB::table('fnb_prep_stations')->value('id'), 'modifier_group_ids' => [$group], 'variant' => ['code' => 'L', 'name' => 'Large', 'base_price_minor' => 30000]], (string) Str::uuid())['resource'];
        $submit = function () use ($item, $option) {
            $order = $this->fnb->createOrder($this->ctx, $this->session['id'], $this->shift['id'], [], (string) Str::uuid(), $this->version('fnb_service_sessions', $this->session['id']))['resource'];
            $order = $this->fnb->addOrderLine($this->ctx, $order['id'], ['variant_id' => $item['variants'][0]['id'], 'quantity' => '1.000000', 'modifier_option_ids' => [$option]], (string) Str::uuid(), $order['version'])['resource'];
            $this->fnb->submitOrder($this->ctx, $order['id'], (string) Str::uuid(), $order['version']);
        };
        $submit();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $first = $this->fnb->pollKitchen($this->ctx)['resource'];
        $oneCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 0; $i < 5; $i++) {
            $submit();
        }
        DB::table('fnb_modifier_options')->where('id', $option)->update(['name' => 'Changed catalogue topping']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $many = $this->fnb->pollKitchen($this->ctx, $first['cursor'])['resource'];
        $manyCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertCount(6, $many['tickets']);
        $this->assertSame($oneCount, $manyCount);
        $this->assertLessThanOrEqual(6, $manyCount);
        foreach ($many['tickets'] as $ticket) {
            $this->assertSame('Large', $ticket['lines'][0]['variant_name_snapshot']);
            $this->assertSame('Original pearl topping', $ticket['lines'][0]['modifiers'][0]['option_name_snapshot']);
            $this->assertSame('Original pearl topping', $ticket['lines'][0]['modifiers'][0]['name_snapshot']);
        }
        $next = $this->fnb->pollKitchen($this->ctx, $many['cursor'])['resource'];
        $this->assertSame([], $next['events']);
        $this->assertCount(6, $next['tickets']);
        foreach (['unit_price_delta_minor', 'base_price_delta_minor', 'recipe_snapshot', 'amount_minor'] as $private) {
            $this->assertStringNotContainsString('"'.$private.'"', json_encode($next));
        }
    }

    public function test_fully_compensated_quantity_keeps_closed_source_bill_and_can_release_the_session(): void
    {
        $order = $this->order(30000);
        $check = $this->plan($this->check());
        $cash = DB::table('fnb_payment_methods')->where('kind', 'cash')->value('id');
        $this->fnb->collectPayment($this->ctx, $check['id'], $this->shift['id'], ['payment_method_id' => $cash, 'amount_minor' => 30000], (string) Str::uuid(), $check['version']);
        app(FnbCompensationService::class)->compensate($this->ctx, $check['lines'][0]['id'], [
            'shift_id' => $this->shift['id'], 'quantity' => '1.000000',
            'expected_version' => $this->version('fnb_orders', $order['id']), 'reason' => 'Cannot fulfill this drink',
        ], (string) Str::uuid());
        $session = $this->fnb->settleSession($this->ctx, $this->session['id'], (string) Str::uuid(), $this->version('fnb_service_sessions', $this->session['id']))['resource'];
        $this->assertSame('closed', $this->fnb->closeSession($this->ctx, $session['id'], (string) Str::uuid(), $session['version'])['resource']['status']);
        $shift = $this->fnb->closeShift($this->ctx, $this->shift['id'], [['payment_method_id' => $cash, 'counted_minor' => 100000]], (string) Str::uuid(), $this->version('fnb_shifts', $this->shift['id']))['resource'];
        $this->assertSame('reconciled', $shift['status']);
        $this->assertDatabaseHas('fnb_order_lines', ['id' => $order['lines'][0]['id'], 'compensated_quantity' => 1]);
        $this->assertDatabaseHas('fnb_checks', ['id' => $check['id'], 'status' => 'closed']);
    }

    public function test_cash_rounding_down_closes_the_whole_order_without_leaving_false_debt(): void
    {
        $order = $this->order(30249);
        $check = $this->fnb->finalizeCheck($this->ctx, $this->check()['id'], (string) Str::uuid(), 1)['resource'];
        $check = $this->fnb->setSettlementPlan($this->ctx, $check['id'], 'single_cash', (string) Str::uuid(), $check['version'])['resource'];
        $this->assertSame(30000, $check['settlement_total_minor']);
        $cash = DB::table('fnb_payment_methods')->where('kind', 'cash')->value('id');
        $this->fnb->collectPayment($this->ctx, $check['id'], $this->shift['id'], ['payment_method_id' => $cash, 'amount_minor' => 30000], (string) Str::uuid(), $check['version']);
        $this->assertDatabaseHas('fnb_order_financial_projections', ['order_id' => $order['id'], 'payment_status' => 'paid', 'gross_paid_total_minor' => 30000]);
        $this->assertDatabaseHas('fnb_checks', ['id' => $check['id'], 'grand_total_minor' => 30249, 'cash_rounding_minor' => -249, 'status' => 'closed']);
    }

    public function test_zero_charge_partial_allocation_does_not_mark_unbilled_free_quantity_paid(): void
    {
        $order = $this->order(0, '2.000000');
        $check = $this->fnb->createCheck($this->ctx, $this->session['id'], [['order_line_id' => $order['lines'][0]['id'], 'quantity' => '1.000000']], (string) Str::uuid(), $this->version('fnb_service_sessions', $this->session['id']))['resource'];
        $check = $this->plan($check);
        $this->fnb->closeZeroChargeCheck($this->ctx, $check['id'], $this->shift['id'], 'First free drink', (string) Str::uuid(), $check['version']);
        $this->assertDatabaseHas('fnb_order_financial_projections', ['order_id' => $order['id'], 'payment_status' => 'unpaid', 'gross_paid_total_minor' => 0]);
        $remaining = $this->plan($this->check());
        $this->fnb->closeZeroChargeCheck($this->ctx, $remaining['id'], $this->shift['id'], 'Second free drink', (string) Str::uuid(), $remaining['version']);
        $this->assertDatabaseHas('fnb_order_financial_projections', ['order_id' => $order['id'], 'payment_status' => 'paid', 'gross_paid_total_minor' => 0]);
    }

    private function serve(array $order): void
    {
        $ticket = DB::table('fnb_kitchen_tickets')->where('order_id', $order['id'])->firstOrFail();
        $line = DB::table('fnb_kitchen_ticket_lines')->where('ticket_id', $ticket->id)->firstOrFail();
        foreach (['preparing', 'ready', 'served'] as $status) {
            $this->fnb->transitionKitchenLine($this->ctx, $line->id, $status, (string) Str::uuid(), $this->version('fnb_kitchen_tickets', $ticket->id));
        }
    }

    private function plan(array $check): array
    {
        $check = $this->fnb->finalizeCheck($this->ctx, $check['id'], (string) Str::uuid(), $check['version'])['resource'];

        return $this->fnb->setSettlementPlan($this->ctx, $check['id'], 'mixed', (string) Str::uuid(), $check['version'])['resource'];
    }

    private function version(string $table, int $id): int
    {
        return (int) DB::table($table)->where('id', $id)->value('version');
    }

    private function assertConflict(Closure $operation): void
    {
        try {
            $operation();
            $this->fail('Expected a domain conflict.');
        } catch (FnbConflictException) {
            $this->addToAssertionCount(1);
        }
    }
}
