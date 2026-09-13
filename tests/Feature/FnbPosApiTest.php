<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ModuleInstallation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Jobs\BuildFnbReportExport;
use Modules\FnbPos\Services\FnbCompensationService;
use Modules\FnbPos\Services\FnbService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FnbPosApiTest extends TestCase
{
    use RefreshDatabase;

    private int $outlet;

    private int $terminal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(1);
        $owner->forceFill(['password' => 'FnbPassword123!', 'must_change_password' => false])->save();
        $this->actingAs($owner, 'admin')->withSession(['admin_auth_version' => $owner->auth_version]);
        $this->postJson('/admin/api/modules/fnb-pos/install')->assertOk();
        $this->postJson('/admin/api/modules/fnb-pos/enable')->assertOk();
    }

    public function test_onboarding_requires_bound_reauth_and_replays_without_reusing_proof(): void
    {
        $this->onboard();
        $this->getJson('/admin/api/fnb/bootstrap')->assertOk()->assertJsonPath('data.onboarding.completed', true)
            ->assertJsonPath('data.outlets.0.terminals.0.id', $this->terminal);
        $this->assertDatabaseCount('fnb_outlets', 1);
        $this->assertSame(1, DB::table('admin_reauth_proofs')->whereNotNull('consumed_at')->count());
    }

    public function test_counter_sale_kitchen_cash_and_report_share_one_immutable_transaction_history(): void
    {
        Queue::fake();
        Storage::fake('local');
        $this->onboard();
        $station = (int) DB::table('fnb_prep_stations')->value('id');
        $this->command('menu/items', [
            'code' => 'COFFEE', 'name' => 'Cà phê sữa', 'status' => 'active',
            'tax_category' => 'standard', 'tax_rate_bps' => 800, 'tax_inclusive' => true,
            'variant' => ['code' => 'DEFAULT', 'name' => 'Mặc định', 'base_price_minor' => 29000],
            'station_id' => $station, 'modifier_groups' => [],
        ])->assertOk();
        $this->command('outlets/'.$this->outlet.'/business-days/open', ['business_date' => now()->toDateString()])->assertOk();
        $day = (int) DB::table('fnb_business_days')->value('id');
        $this->command('outlets/'.$this->outlet.'/shifts/open', ['business_day_id' => $day, 'opening_float_minor' => 100000])->assertOk();
        $shift = (int) DB::table('fnb_shifts')->value('id');
        $this->command('sessions', ['business_day_id' => $day, 'service_type' => 'counter', 'guest_count' => 1])->assertOk();
        $session = (int) DB::table('fnb_service_sessions')->value('id');
        $this->command('sessions/'.$session.'/orders', ['shift_id' => $shift, 'expected_version' => $this->version('fnb_service_sessions', $session)])->assertOk();
        $order = (int) DB::table('fnb_orders')->value('id');
        $variant = (int) DB::table('fnb_item_variants')->value('id');
        $this->command('orders/'.$order.'/lines', [
            'variant_id' => $variant, 'quantity' => '2.000000', 'modifier_option_ids' => [],
            'expected_version' => $this->version('fnb_orders', $order),
        ])->assertOk();
        $this->command('orders/'.$order.'/submit', ['expected_version' => $this->version('fnb_orders', $order)])->assertOk();
        $kitchen = $this->getJson('/admin/api/fnb/outlets/'.$this->outlet.'/kitchen/tickets')->assertOk();
        $this->assertNotEmpty($kitchen->json('data.tickets'));
        $this->assertStringNotContainsString('29000', $kitchen->getContent());
        $ticket = (int) DB::table('fnb_kitchen_tickets')->value('id');
        $ticketLine = (int) DB::table('fnb_kitchen_ticket_lines')->value('id');
        foreach (['preparing', 'ready', 'served'] as $status) {
            $this->command('kitchen/lines/'.$ticketLine.'/transition', ['status' => $status, 'expected_version' => $this->version('fnb_kitchen_tickets', $ticket)])->assertOk();
        }
        $this->command('sessions/'.$session.'/checks', ['allocations' => [], 'expected_version' => $this->version('fnb_service_sessions', $session)])->assertOk();
        $check = (int) DB::table('fnb_checks')->value('id');
        $this->command('checks/'.$check.'/finalize', ['expected_version' => $this->version('fnb_checks', $check)])->assertOk();
        $this->command('checks/'.$check.'/settlement-plan', ['mode' => 'single_cash', 'expected_version' => $this->version('fnb_checks', $check)])->assertOk();
        $cash = (int) DB::table('fnb_payment_methods')->where('kind', 'cash')->value('id');
        $payment = ['shift_id' => $shift, 'payment_method_id' => $cash, 'amount_minor' => 58000, 'tendered_minor' => 60000, 'expected_version' => $this->version('fnb_checks', $check)];
        $key = (string) Str::uuid();
        $this->command('checks/'.$check.'/payments', $payment, $key)->assertOk();
        $this->command('checks/'.$check.'/payments', $payment, $key)->assertOk()->assertJsonPath('meta.replayed', true);
        $this->assertDatabaseCount('fnb_payments', 1);
        $this->assertDatabaseHas('fnb_payments', ['status' => 'succeeded', 'amount_minor' => 58000]);
        $pos = $this->getJson('/admin/api/fnb/outlets/'.$this->outlet.'/pos')->assertOk()
            ->assertJsonPath('data.active_sessions.0.checks.0.balance_due_minor', 0)
            ->assertJsonPath('data.active_sessions.0.checks.0.available_to_collect_minor', 0);
        foreach (['provider_operation_key', 'provider_reference', 'qr_token_hash', 'idempotency_key', 'buyer_snapshot', 'recipe_snapshot'] as $internalField) {
            $this->assertStringNotContainsString('"'.$internalField.'"', $pos->getContent());
        }
        $receiptKey = (string) Str::uuid();
        $receipt = $this->command('checks/'.$check.'/receipts', [], $receiptKey)->assertOk()
            ->assertJsonPath('data.status', 'generated')->assertJsonPath('data.request_count', 0)
            ->assertJsonPath('data.snapshot.not_tax_invoice', true)
            ->assertJsonPath('data.snapshot.lines.0.item.name', 'Cà phê sữa')->json('data');
        $this->command('checks/'.$check.'/receipts', [], $receiptKey)->assertOk()->assertJsonPath('meta.replayed', true);
        $this->assertDatabaseCount('fnb_print_jobs', 1);
        $this->command('print-jobs/'.$receipt['id'].'/confirm', ['expected_version' => 1])->assertConflict();
        $this->command('print-jobs/'.$receipt['id'].'/request', ['expected_version' => 1])->assertOk()->assertJsonPath('data.status', 'print_requested');
        $this->command('print-jobs/'.$receipt['id'].'/confirm', ['expected_version' => 2])->assertOk()->assertJsonPath('data.status', 'user_confirmed');
        $this->getJson('/admin/api/fnb/outlets/'.$this->outlet.'/reports/summary?report_type=financial')->assertOk()->assertJsonPath('data.totals.net_sales_minor', 58000);
        $export = $this->command('reports/exports', ['report_type' => 'financial', 'format' => 'csv'])->assertAccepted()->json('data.id');
        Queue::assertPushed(BuildFnbReportExport::class);
        app()->call([new BuildFnbReportExport($export), 'handle']);
        $download = $this->get('/admin/api/fnb/reports/exports/'.$export.'/download')->assertOk();
        $this->assertStringContainsString('58000', $download->getContent());
        $this->assertStringContainsString('Cà phê sữa', $download->getContent());
        $this->assertStringContainsString('attachment', $download->headers->get('Content-Disposition'));
        $this->command('shifts/'.$shift.'/close', [
            'counted_tenders' => [['payment_method_id' => $cash, 'counted_minor' => 158000]],
            'expected_version' => $this->version('fnb_shifts', $shift),
        ])->assertOk();
        $this->assertDatabaseHas('fnb_shifts', ['id' => $shift, 'variance_minor' => 0]);
    }

    public function test_disabled_module_and_foreign_terminal_are_rejected_even_for_system_owner(): void
    {
        $this->onboard();
        $this->withHeader('X-FNB-Terminal', '999999')->getJson('/admin/api/fnb/outlets/'.$this->outlet.'/pos')->assertNotFound();
        ModuleInstallation::query()->where('key', 'fnb-pos')->update(['status' => 'disabled']);
        $this->getJson('/admin/api/fnb/bootstrap')->assertNotFound();
    }

    public function test_menu_recipe_and_floor_configuration_preserve_versions_and_scope(): void
    {
        $this->onboard();
        $this->getJson('/admin/api/fnb/print-jobs/999999')->assertNotFound();
        $this->getJson('/admin/api/fnb/outlets/'.$this->outlet.'/settings')->assertOk();
        $this->getJson('/admin/api/fnb/outlets/'.$this->outlet.'/recipes')->assertOk();
        $item = $this->command('menu/items', [
            'code' => 'LATTE', 'name' => 'Latte', 'status' => 'active',
            'variant' => ['code' => 'M', 'name' => 'Vừa', 'base_price_minor' => 35000],
            'station_id' => DB::table('fnb_prep_stations')->value('id'),
        ])->assertOk()->json('data');
        $ingredient = $this->command('ingredients', ['code' => 'MILK', 'name' => 'Sữa', 'base_unit' => 'ml'])->assertOk()->json('data.id');
        $recipe = ['code' => 'LATTE', 'name' => 'Latte', 'variant_id' => $item['variants'][0]['id'],
            'expected_recipe_version' => 0, 'yield_quantity' => '1', 'yield_unit' => 'cup',
            'lines' => [['ingredient_id' => $ingredient, 'quantity' => '0.15', 'unit' => 'l', 'loss_rate' => '0']]];
        $recipeId = $this->command('recipes/publish', $recipe)->assertOk()->json('data.id');
        $this->assertEquals(150, DB::table('fnb_recipe_lines')->where('recipe_id', $recipeId)->value('base_quantity'));
        $this->command('recipes/publish', $recipe)->assertConflict()->assertJsonPath('code', 'FNB_STATE_CONFLICT');
        $recipe['expected_recipe_version'] = 1;
        $recipe['lines'][0]['quantity'] = '0.2';
        $this->command('recipes/publish', $recipe)->assertOk()->assertJsonPath('data.recipe_version', 2);
        $this->assertEquals(150, DB::table('fnb_recipe_lines')->where('recipe_id', $recipeId)->value('base_quantity'));
        $this->assertSame(1, DB::table('fnb_variant_recipes')->whereNotNull('current_slot')->count());
        $this->command('outlets/'.$this->outlet.'/tables', ['code' => 'INVALID', 'name' => 'Sai', 'capacity' => 4, 'service_area_id' => 999999])->assertNotFound();
        $area = $this->command('outlets/'.$this->outlet.'/areas', ['code' => 'TEST', 'name' => 'Khu thử nghiệm'])->assertOk()->json('data.id');
        $tableId = $this->command('outlets/'.$this->outlet.'/tables', ['code' => 'TEST01', 'name' => 'Bàn thử', 'capacity' => 4, 'service_area_id' => $area])->assertOk()->json('data.id');
        $table = DB::table('fnb_dining_tables')->where('id', $tableId)->first();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->putJson('/admin/api/fnb/outlets/'.$this->outlet.'/tables/'.$table->id,
            ['code' => $table->code, 'name' => $table->name, 'capacity' => 4, 'service_area_id' => $table->service_area_id,
                'status' => 'inactive', 'expected_version' => $table->version])->assertOk()->assertJsonPath('data.status', 'inactive');
    }

    public static function compensationCases(): array
    {
        return ['full_multi_tender' => [false], 'partial_then_serve' => [true]];
    }

    #[DataProvider('compensationCases')]
    public function test_paid_unfulfilled_compensation_refunds_multiple_tenders_and_reclassifies_stock_once(bool $partial): void
    {
        $this->onboard();
        $fnb = app(FnbService::class);
        $ctx = new FnbContext('website-main', $this->outlet, 1, $this->terminal);
        $item = $this->command('menu/items', ['code' => 'COMP', 'name' => 'Món bồi hoàn', 'status' => 'active',
            'variant' => ['code' => 'M', 'name' => 'Vừa', 'base_price_minor' => 29000],
            'station_id' => DB::table('fnb_prep_stations')->value('id')])->assertOk()->json('data');
        $variant = $item['variants'][0]['id'];
        $ingredient = $this->command('ingredients', ['code' => 'COFFEE', 'name' => 'Cà phê hạt', 'base_unit' => 'g'])->assertOk()->json('data.id');
        $this->command('recipes/publish', ['code' => 'COMP', 'name' => 'Định lượng', 'variant_id' => $variant,
            'expected_recipe_version' => 0, 'yield_quantity' => '1', 'yield_unit' => 'cup',
            'lines' => [['ingredient_id' => $ingredient, 'quantity' => '15', 'unit' => 'g']]])->assertOk();
        $fnb->openBusinessDay($ctx, now()->toDateString(), 'comp-day');
        $day = (int) DB::table('fnb_business_days')->value('id');
        $fnb->openShift($ctx, $day, 100000, 'comp-shift');
        $shift = (int) DB::table('fnb_shifts')->value('id');
        $fnb->openSession($ctx, ['business_day_id' => $day, 'service_type' => 'counter'], 'comp-session');
        $session = (int) DB::table('fnb_service_sessions')->value('id');
        $fnb->createOrder($ctx, $session, $shift, [], 'comp-order', $this->version('fnb_service_sessions', $session));
        $order = (int) DB::table('fnb_orders')->value('id');
        $fnb->addOrderLine($ctx, $order, ['variant_id' => $variant, 'quantity' => '1'], 'comp-line', $this->version('fnb_orders', $order));
        $fnb->submitOrder($ctx, $order, 'comp-submit', $this->version('fnb_orders', $order));
        $ticket = (int) DB::table('fnb_kitchen_tickets')->value('id');
        $kitchenLine = (int) DB::table('fnb_kitchen_ticket_lines')->value('id');
        $fnb->transitionKitchenLine($ctx, $kitchenLine, 'preparing', 'comp-prepare', $this->version('fnb_kitchen_tickets', $ticket));
        $fnb->createCheck($ctx, $session, [], 'comp-check', $this->version('fnb_service_sessions', $session));
        $check = (int) DB::table('fnb_checks')->value('id');
        $checkLine = (int) DB::table('fnb_check_lines')->value('id');
        $fnb->finalizeCheck($ctx, $check, 'comp-finalize', $this->version('fnb_checks', $check));
        $fnb->setSettlementPlan($ctx, $check, 'mixed', 'comp-plan', $this->version('fnb_checks', $check));
        foreach (['cash' => 20000, 'transfer' => 9000] as $kind => $amount) {
            $method = (int) DB::table('fnb_payment_methods')->where('kind', $kind)->value('id');
            $fnb->collectPayment($ctx, $check, $shift, ['payment_method_id' => $method, 'amount_minor' => $amount,
                'tendered_minor' => $amount, 'reference' => $kind === 'transfer' ? 'MANUAL-COMP-TEST' : null], 'comp-pay-'.$kind, $this->version('fnb_checks', $check));
        }
        $originalCheck = (array) DB::table('fnb_checks')->where('id', $check)->first();
        $payload = ['shift_id' => $shift, 'quantity' => $partial ? '0.25' : '1', 'expected_version' => $this->version('fnb_orders', $order), 'reason' => 'Không thể phục vụ'];
        $service = app(FnbCompensationService::class);
        $result = $service->compensate($ctx, $checkLine, $payload, 'comp-resolve');
        $this->assertSame('resolved', $result['resource']['status']);
        $this->assertCount($partial ? 1 : 2, $result['resource']['refund_ids']);
        $this->assertTrue($service->compensate($ctx, $checkLine, $payload, 'comp-resolve')['replayed']);
        $this->assertSame($originalCheck, (array) DB::table('fnb_checks')->where('id', $check)->first());
        $this->assertDatabaseCount('fnb_refunds', $partial ? 1 : 2);
        $this->assertEquals($partial ? 7250 : 29000, DB::table('fnb_refunds')->sum('amount_minor'));
        $this->assertDatabaseHas('fnb_order_lines', ['status' => $partial ? 'preparing' : 'cancelled_compensated', 'compensated_quantity' => $partial ? 0.25 : 1]);
        foreach (['sale', 'reversal', 'waste'] as $kind) {
            $consumption = (int) DB::table('fnb_stock_consumptions')->where('kind', $kind)->value('id');
            $this->assertSame(1, DB::table('fnb_stock_consumptions')->where('kind', $kind)->count());
            $this->assertEquals($kind === 'sale' || ! $partial ? 15 : 3.75, DB::table('fnb_stock_consumption_lines')->where('consumption_id', $consumption)->sum('quantity'));
        }
        $this->assertEquals($partial ? 112750 : 100000, DB::table('fnb_shifts')->where('id', $shift)->value('expected_cash_minor'));
        if ($partial) {
            foreach (['ready', 'served'] as $state) {
                $fnb->transitionKitchenLine($ctx, $kitchenLine, $state, 'comp-remainder-'.$state, $this->version('fnb_kitchen_tickets', $ticket));
            }
            $this->assertDatabaseHas('fnb_order_lines', ['status' => 'served', 'fulfilled_quantity' => 0.75, 'compensated_quantity' => 0.25]);
            $this->assertDatabaseHas('fnb_kitchen_ticket_lines', ['served_quantity' => 0.75, 'compensated_quantity' => 0.25]);
        }
    }

    private function onboard(): void
    {
        $payload = [
            'outlet' => ['code' => 'CAFE', 'name' => 'Quán Cafe', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND'],
            'terminal' => ['code' => 'POS-01', 'name' => 'Quầy 1', 'type' => 'pos'],
            'station' => ['code' => 'BAR', 'name' => 'Bar'],
            'payment_methods' => [['code' => 'CASH', 'name' => 'Tiền mặt', 'kind' => 'cash'], ['code' => 'TRANSFER', 'name' => 'Chuyển khoản', 'kind' => 'transfer']],
        ];
        $key = (string) Str::uuid();
        $challenge = $this->command('onboarding/presets/cafe', $payload, $key)->assertStatus(423);
        $this->assertDatabaseCount('fnb_outlets', 0);
        $proof = $this->postJson('/admin/api/fnb/reauth/proofs', [
            'action' => 'onboard', 'subject' => 'new', 'payload_hash' => $challenge->json('details.payload_hash'), 'password' => 'FnbPassword123!',
        ])->assertOk()->json('data.reauth_proof');
        $this->withHeader('X-FNB-Reauth-Proof', $proof);
        $this->command('onboarding/presets/cafe', $payload, $key)->assertOk();
        $this->withHeader('X-FNB-Reauth-Proof', '');
        $this->command('onboarding/presets/cafe', $payload, $key)->assertOk()->assertJsonPath('meta.replayed', true);
        $this->outlet = (int) DB::table('fnb_outlets')->value('id');
        $this->terminal = (int) DB::table('fnb_terminals')->value('id');
        $this->withHeader('X-FNB-Outlet', (string) $this->outlet)->withHeader('X-FNB-Terminal', (string) $this->terminal);
    }

    private function command(string $path, array $data, ?string $key = null)
    {
        return $this->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson('/admin/api/fnb/'.$path, $data);
    }

    private function version(string $table, int $id): int
    {
        return (int) DB::table($table)->where('id', $id)->value('version');
    }
}
