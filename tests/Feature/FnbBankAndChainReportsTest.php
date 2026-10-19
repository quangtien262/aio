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

    private function check(bool $plan = true): array
    {
        $item = $this->fnb->saveCatalogItem($this->ctx, ['code' => 'COFFEE', 'name' => 'Coffee', 'station_id' => (int) DB::table('fnb_prep_stations')->value('id'),
            'variant' => ['code' => 'S', 'name' => 'Small', 'base_price_minor' => 30000]], (string) Str::uuid())['resource'];
        $order = $this->fnb->createOrder($this->ctx, $this->session['id'], $this->shift['id'], [], (string) Str::uuid(), $this->session['version'])['resource'];
        $order = $this->fnb->addOrderLine($this->ctx, $order['id'], ['variant_id' => $item['variants'][0]['id'], 'quantity' => '1'], (string) Str::uuid(), $order['version'])['resource'];
        $this->fnb->submitOrder($this->ctx, $order['id'], (string) Str::uuid(), $order['version']);
        $version = (int) DB::table('fnb_service_sessions')->where('id', $this->session['id'])->value('version');
        $check = $this->fnb->createCheck($this->ctx, $this->session['id'], [], (string) Str::uuid(), $version)['resource'];
        $check = $this->fnb->finalizeCheck($this->ctx, $check['id'], (string) Str::uuid(), $check['version'])['resource'];

        return $plan ? $this->fnb->setSettlementPlan($this->ctx, $check['id'], 'single_non_cash', (string) Str::uuid(), $check['version'])['resource'] : $check;
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

    public function test_multiple_bank_accounts_have_one_default_and_use_the_selected_bank_bin(): void
    {
        $bank = app(FnbBankService::class);
        $first = $bank->saveAccount($this->ctx, ['bank_code' => 'TPB', 'account_number' => '12345678901', 'account_name' => 'TEST TPB', 'enabled' => true], 'bank-first', create: true)['resource'];
        $this->assertTrue($first['is_default']);
        $second = $bank->saveAccount($this->ctx, ['bank_code' => 'VCB', 'account_number' => '98765432100', 'account_name' => 'TEST VCB', 'enabled' => true, 'is_default' => true], 'bank-second', create: true)['resource'];
        $this->assertTrue($second['is_default']);
        $this->assertSame($second['id'], $bank->account($this->ctx)['id']);
        $this->assertSame(1, DB::table('fnb_bank_accounts')->where('default_slot', 'default')->count());
        $this->assertSame(2, (int) DB::table('fnb_bank_accounts')->where('id', $first['id'])->value('version'));
        $check = $this->check();
        $intent = $bank->intent($this->ctx, $check['id'], ['amount_minor' => 30000], 'vcb-intent')['resource'];
        $this->assertSame('VCB', $intent['bank_code']);
        $this->assertSame($second['id'], $intent['account_id']);
        $this->assertStringContainsString('970436', $intent['qr_payload']);
        $this->assertStringContainsString('98765432100', $intent['qr_payload']);
        $selected = $bank->intent($this->ctx, $check['id'], ['amount_minor' => 30000, 'account_id' => $first['id']], 'tpb-selected')['resource'];
        $this->assertSame('TPB', $selected['bank_code']);
        $this->assertStringContainsString('970423', $selected['qr_payload']);
        $bank->saveAccount($this->ctx, ['account_name' => 'TEST VCB NEW', 'account_number' => '', 'enabled' => false, 'expected_version' => $second['version']], 'disable-vcb', $second['id']);
        $this->assertSame($first['id'], $bank->account($this->ctx)['id']);
        $this->assertSame($intent, $bank->intent($this->ctx, $check['id'], ['amount_minor' => 30000], 'vcb-intent')['resource']);
        $this->assertDatabaseCount('fnb_payments', 0);
    }

    public function test_qr_preview_keeps_settlement_mode_unlocked_and_rejects_unavailable_accounts(): void
    {
        $bank = app(FnbBankService::class);
        $account = $bank->saveAccount($this->ctx, ['bank_code' => 'MB', 'account_number' => '12345678901', 'account_name' => 'TEST MB', 'enabled' => true], 'mb-account', create: true)['resource'];
        $check = $this->check(false);
        $intent = $bank->intent($this->ctx, $check['id'], ['amount_minor' => 30000, 'account_id' => $account['id']], 'preview-intent')['resource'];
        $this->assertSame('MB', $intent['bank_code']);
        $this->assertStringContainsString('970422', $intent['qr_payload']);
        $this->assertNull(DB::table('fnb_checks')->where('id', $check['id'])->value('settlement_mode'));
        $this->assertSame($check['version'], (int) DB::table('fnb_checks')->where('id', $check['id'])->value('version'));
        $cash = $this->fnb->setSettlementPlan($this->ctx, $check['id'], 'single_cash', (string) Str::uuid(), $check['version']);
        $this->assertSame('single_cash', $cash['resource']['settlement_mode']);
        $this->assertDatabaseCount('fnb_payments', 0);
        $this->expectException(\Modules\FnbPos\Domain\FnbConflictException::class);
        $bank->intent($this->ctx, $check['id'], ['amount_minor' => 30000], 'no-qr-cash-plan');
    }

    public function test_bank_account_http_crud_is_versioned_and_payment_options_are_masked(): void
    {
        $owner = $this->owner->fresh();
        $owner->forceFill(['must_change_password' => false])->save();
        $this->actingAs($owner, 'admin')->withSession(['admin_auth_version' => $owner->auth_version])->withHeaders(['X-FNB-Terminal' => (string) $this->ctx->terminalId]);
        $path = '/admin/api/fnb/outlets/'.$this->ctx->outletId.'/bank-accounts';
        // Sensitive configuration still requires the existing reauthentication challenge.
        $this->withHeader('Idempotency-Key', 'bank-http-create')->postJson($path, ['bank_code' => 'VCB', 'account_number' => '12345678901', 'account_name' => 'TEST BANK', 'enabled' => true])->assertStatus(423);
        $bank = app(FnbBankService::class);
        $account = $bank->saveAccount($this->ctx, ['bank_code' => 'VCB', 'account_number' => '12345678901', 'account_name' => 'TEST BANK', 'enabled' => true], 'bank-create-options', create: true)['resource'];
        $this->getJson($path)->assertOk()->assertJsonCount(1, 'data.accounts');
        $options = $this->getJson($path.'/payment-options')->assertOk();
        $this->assertStringNotContainsString('12345678901', $options->getContent());
        $options->assertJsonPath('data.accounts.0.is_default', true)->assertJsonPath('data.accounts.0.bank_name', 'Vietcombank');
        try {
            $bank->saveAccount($this->ctx, ['account_name' => 'TEST NEW', 'enabled' => true, 'expected_version' => 99], 'stale-bank', $account['id']);
            $this->fail('Stale account version must fail.');
        } catch (\Modules\FnbPos\Domain\FnbConflictException) { $this->assertSame('TEST BANK', $bank->account($this->ctx)['account_name']); }
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
