<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\AuditChainVerifier;
use App\Support\SiteContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\FnbBankService;
use Modules\FnbPos\Services\FnbChainReportService;
use Modules\FnbPos\Services\FnbCommandRunner;
use Modules\FnbPos\Services\FnbService;
use Tests\TestCase;

class FnbBankAndChainReportsTest extends TestCase
{
    use RefreshDatabase;

    private FnbContext $ctx;

    private FnbService $fnb;

    private Admin $owner;

    private array $shift;

    private array $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = Admin::findOrFail(1);
        $this->actingAs($this->owner, 'admin');
        $this->postJson('/admin/api/modules/fnb-pos/install')->assertOk();
        $this->postJson('/admin/api/modules/fnb-pos/enable')->assertOk();
        $this->fnb = app(FnbService::class);
        $website = app(SiteContext::class)->websiteKey();
        $preset = $this->fnb->onboard(new FnbContext($website, 0, 1), [
            'outlet' => ['code' => 'BANK', 'name' => 'Synthetic bank fixture', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND'],
            'terminal' => ['code' => 'POS', 'name' => 'POS', 'type' => 'pos'], 'station' => ['code' => 'BAR', 'name' => 'Bar'], 'table_count' => 0,
        ], (string) Str::uuid())['resource'];
        $this->ctx = new FnbContext($website, $preset['outlet']['id'], 1, $preset['terminal']['id']);
        $day = $this->fnb->openBusinessDay($this->ctx, now('Asia/Ho_Chi_Minh')->toDateString(), (string) Str::uuid())['resource'];
        $this->shift = $this->fnb->openShift($this->ctx, $day['id'], 0, (string) Str::uuid())['resource'];
        $this->session = $this->fnb->openSession($this->ctx, ['business_day_id' => $day['id'], 'service_type' => 'counter', 'guest_count' => 1], (string) Str::uuid())['resource'];
    }

    private function check(): array
    {
        $item = $this->fnb->saveCatalogItem($this->ctx, ['code' => 'COFFEE', 'name' => 'Coffee', 'station_id' => (int) DB::table('fnb_prep_stations')->value('id'),
            'variant' => ['code' => 'S', 'name' => 'Small', 'base_price_minor' => 30000]], (string) Str::uuid())['resource'];
        $order = $this->fnb->createOrder($this->ctx, $this->session['id'], $this->shift['id'], [], (string) Str::uuid(), $this->session['version'])['resource'];
        $order = $this->fnb->addOrderLine($this->ctx, $order['id'], ['variant_id' => $item['variants'][0]['id'], 'quantity' => '1'], (string) Str::uuid(), $order['version'])['resource'];
        $this->fnb->submitOrder($this->ctx, $order['id'], (string) Str::uuid(), $order['version']);
        $version = (int) DB::table('fnb_service_sessions')->where('id', $this->session['id'])->value('version');
        $check = $this->fnb->createCheck($this->ctx, $this->session['id'], [], (string) Str::uuid(), $version)['resource'];
        $check = $this->fnb->finalizeCheck($this->ctx, $check['id'], (string) Str::uuid(), $check['version'])['resource'];

        return $this->fnb->setSettlementPlan($this->ctx, $check['id'], 'single_non_cash', (string) Str::uuid(), $check['version'])['resource'];
    }

    public function test_tpb_qr_is_encrypted_scoped_idempotent_and_never_confirms_money(): void
    {
        $bank = app(FnbBankService::class);
        $account = $bank->saveAccount($this->ctx, ['account_number' => '12345678901', 'account_name' => 'SYNTHETIC TEST', 'enabled' => true], 'synthetic-bank-account')['resource'];
        $this->assertSame('*******8901', $account['account_number_masked']);
        $this->assertNotSame('12345678901', DB::table('fnb_bank_accounts')->value('account_number'));
        $check = $this->check();
        $intent = $bank->intent($this->ctx, $check['id'], ['amount_minor' => 30000], 'synthetic-bank-intent');
        $replay = $bank->intent($this->ctx, $check['id'], ['amount_minor' => 30000], 'synthetic-bank-intent');
        $this->assertSame($intent['resource'], $replay['resource']);
        $this->assertTrue($replay['replayed']);
        $this->assertStringContainsString('970423', $intent['resource']['qr_payload']);
        $this->assertStringContainsString('12345678901', $intent['resource']['qr_payload']);
        $this->assertSame('manual', $intent['resource']['confirmation_mode']);
        $this->assertSame('pending', $intent['resource']['status']);
        $this->assertDatabaseCount('fnb_bank_payment_intents', 1);
        $this->assertDatabaseCount('fnb_payments', 0);
        $this->assertSame('finalized', DB::table('fnb_checks')->where('id', $check['id'])->value('status'));
        $this->assertTrue(app(AuditChainVerifier::class)->verify()['valid']);
    }

    public function test_chain_report_matches_outlet_cash_flow_and_does_not_mix_foreign_outlets(): void
    {
        $check = $this->check();
        $method = DB::table('fnb_payment_methods')->where('kind', 'transfer')->value('id');
        $key = 'synthetic-transfer-payment';
        $this->fnb->collectPayment($this->ctx, $check['id'], $this->shift['id'], ['payment_method_id' => $method, 'amount_minor' => 30000, 'reference' => 'SYNTHETIC-REFERENCE'], $key, $check['version']);
        $foreign = (array) DB::table('fnb_outlets')->where('id', $this->ctx->outletId)->first();
        unset($foreign['id']);
        DB::table('fnb_outlets')->insert(array_replace($foreign, ['website_key' => 'foreign-website', 'public_id' => (string) Str::uuid(), 'currency' => 'USD']));
        $report = app(FnbChainReportService::class)->summary($this->owner, $this->ctx->websiteKey, [
            'scope' => 'chain', 'from' => now('Asia/Ho_Chi_Minh')->toDateString(), 'to' => now('Asia/Ho_Chi_Minh')->toDateString(),
        ]);
        $this->assertSame(30000, $report['totals']['gross_collected_minor']);
        $this->assertSame(30000, $report['totals']['closed_sales_minor']);
        $this->assertSame(1, $report['totals']['checks']);
        $this->assertSame([$this->ctx->outletId], array_column($report['outlets'], 'id'));
        $this->withHeaders(['X-FNB-Outlet' => (string) $this->ctx->outletId, 'X-FNB-Terminal' => (string) $this->ctx->terminalId])
            ->getJson('/admin/api/fnb/command-status?key='.$key)->assertOk()->assertJsonPath('data.status', 'committed');
        $operations = app(FnbChainReportService::class)->summary($this->owner, $this->ctx->websiteKey, [
            'scope' => 'outlet', 'outlet_id' => $this->ctx->outletId, 'report_type' => 'operations',
            'from' => now('Asia/Ho_Chi_Minh')->toDateString(), 'to' => now('Asia/Ho_Chi_Minh')->toDateString(),
        ]);
        $this->assertArrayNotHasKey('gross_collected_minor', $operations['totals']);
        $this->assertSame([], $operations['tenders']);
    }

    public function test_bank_http_config_denies_an_actor_without_hq_authority(): void
    {
        $actor = Admin::factory()->create(['must_change_password' => false, 'status' => 'active']);
        $this->actingAs($actor, 'admin')->withSession(['admin_auth_version' => $actor->auth_version])->withHeaders(['X-FNB-Outlet' => (string) $this->ctx->outletId])
            ->getJson('/admin/api/fnb/outlets/'.$this->ctx->outletId.'/bank-account')->assertForbidden();
    }

    public function test_hq_reports_keep_closed_outlet_history_and_queries_do_not_grow_with_fifty_outlets(): void
    {
        $check = $this->check();
        $method = DB::table('fnb_payment_methods')->where('kind', 'transfer')->value('id');
        $this->fnb->collectPayment($this->ctx, $check['id'], $this->shift['id'], ['payment_method_id' => $method, 'amount_minor' => 30000, 'reference' => 'TEST-HISTORY'], (string) Str::uuid(), $check['version']);
        $input = ['scope' => 'chain', 'from' => now('Asia/Ho_Chi_Minh')->toDateString(), 'to' => now('Asia/Ho_Chi_Minh')->toDateString()];
        $reports = app(FnbChainReportService::class);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $reports->summary($this->owner, $this->ctx->websiteKey, $input);
        $singleCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $template = (array) DB::table('fnb_outlets')->where('id', $this->ctx->outletId)->first();
        unset($template['id']);
        for ($i = 2; $i <= 50; $i++) {
            DB::table('fnb_outlets')->insert(array_replace($template, ['public_id' => (string) Str::ulid(), 'code' => 'QA-'.$i, 'name' => 'Synthetic outlet '.$i]));
        }
        DB::table('fnb_outlets')->where('id', $this->ctx->outletId)->update(['status' => 'inactive']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $report = $reports->summary($this->owner, $this->ctx->websiteKey, $input);
        $chainCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertCount(50, $report['outlets']);
        $this->assertSame(30000, $report['totals']['gross_collected_minor']);
        $this->assertSame('inactive', $report['outlets'][0]['status']);
        $this->assertLessThanOrEqual($singleCount, $chainCount, 'Report queries must stay constant across outlets.');
    }

    public function test_recovery_requires_operation_when_a_key_is_reused_across_commands(): void
    {
        $key = 'recovery-shared-key';
        $runner = app(FnbCommandRunner::class);
        foreach (['test.one', 'test.two'] as $operation) {
            $runner->run($this->ctx, $operation, [], $key, fn () => ['resource' => ['id' => 1]]);
        }
        $this->withHeaders(['X-FNB-Outlet' => (string) $this->ctx->outletId, 'X-FNB-Terminal' => (string) $this->ctx->terminalId]);
        $this->getJson('/admin/api/fnb/command-status?key='.$key)->assertConflict();
        $this->getJson('/admin/api/fnb/command-status?key='.$key.'&operation=test.one')->assertOk()->assertJsonPath('data.status', 'committed');
    }
}
