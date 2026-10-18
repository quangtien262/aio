<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Models\AcctItem;
use App\Models\AcctOrganization;
use App\Models\AcctOrganizationWebsite;
use App\Models\Admin;
use App\Models\InvItem;
use App\Models\InvWarehouse;
use App\Support\InventoryStockService;
use App\Support\SiteContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Jobs\ReconcileFnbIntegration;
use Modules\FnbPos\Services\FnbChainService;
use Modules\FnbPos\Services\FnbCommandRunner;
use Modules\FnbPos\Services\FnbIntegrationService;
use Modules\FnbPos\Services\FnbOutboxService;
use Modules\FnbPos\Services\FnbService;
use Modules\FnbPos\Services\Security\FnbScopedStaffService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class FnbIntegrationAdapterTest extends TestCase
{
    use RefreshDatabase;

    private Admin $actor;

    private FnbContext $ctx;

    private FnbIntegrationService $integrations;

    private int $ingredient;

    private InvItem $stockItem;

    private InvWarehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actor = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $this->actingAs($this->actor, 'admin');
        $modules = app(ModuleManager::class);
        foreach (['cms', 'catalog', 'inventory', 'accounting-tax', 'fnb-pos'] as $module) {
            if (! DB::table('module_installations')->where('key', $module)->whereIn('status', ['installed', 'enabled', 'disabled'])->exists()) {
                $modules->install($module);
            }
            if (DB::table('module_installations')->where('key', $module)->value('status') !== 'enabled') {
                $modules->enable($module);
            }
        }
        $fnb = app(FnbService::class);
        $website = app(SiteContext::class)->websiteKey();
        $resources = $fnb->onboard(new FnbContext($website, 0, $this->actor->id), [
            'outlet' => ['code' => 'ADAPTER', 'name' => 'Adapter fixture', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency' => 'VND'],
            'terminal' => ['code' => 'POS-1', 'name' => 'POS1', 'type' => 'pos'], 'station' => ['code' => 'BAR', 'name' => 'Bar'], 'table_count' => 0,
        ], (string) Str::uuid())['resource'];
        $this->ctx = new FnbContext($website, $resources['outlet']['id'], $this->actor->id, $resources['terminal']['id']);
        $this->integrations = app(FnbIntegrationService::class);
        $this->ingredient = DB::table('fnb_ingredients')->insertGetId(['website_key' => $website, 'code' => 'MILK', 'name' => 'Milk', 'base_unit' => 'g', 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->warehouse = InvWarehouse::query()->where('is_active', true)->firstOrFail();
        $this->stockItem = InvItem::query()->create(['sku' => 'FNB-MILK', 'name' => 'Milk', 'unit' => 'g', 'costing_method' => 'fifo', 'track_batch' => false, 'track_serial' => false, 'is_active' => true]);
        app(InventoryStockService::class)->createOpeningBalance($this->stockItem->id, $this->warehouse->id, 10, 4, $this->actor->id);
    }

    private function configureStock(bool $automatic = false): array
    {
        $this->integrations->mapping($this->ctx, $this->actor, ['destination' => 'inventory', 'source_type' => 'ingredient', 'source_id' => $this->ingredient, 'destination_id' => $this->stockItem->id, 'unit' => 'g', 'factor' => '1.000000'], (string) Str::uuid(), true);

        return $this->integrations->mapping($this->ctx, $this->actor, ['destination' => 'inventory', 'enabled' => true, 'automatic' => $automatic, 'config' => ['warehouse_id' => $this->warehouse->id]], (string) Str::uuid())['resource'];
    }

    private function consumption(string $kind, string $quantity, ?int $sourceLine = null): array
    {
        $snapshot = ['marker' => (string) Str::uuid(), 'kind' => $kind];
        $id = DB::table('fnb_stock_consumptions')->insertGetId(['website_key' => $this->ctx->websiteKey, 'outlet_id' => $this->ctx->outletId, 'public_id' => (string) Str::uuid(),
            'source_type' => 'fixture', 'source_id' => (string) Str::uuid(), 'source_version' => 1, 'source_event_id' => (string) Str::uuid(), 'kind' => $kind,
            'status' => 'pending', 'idempotency_key' => (string) Str::uuid(), 'payload_snapshot' => json_encode($snapshot), 'payload_hash' => app(FnbCommandRunner::class)->fingerprint($snapshot), 'created_at' => now(), 'updated_at' => now()]);
        $line = DB::table('fnb_stock_consumption_lines')->insertGetId(['website_key' => $this->ctx->websiteKey, 'outlet_id' => $this->ctx->outletId, 'consumption_id' => $id, 'ingredient_id' => $this->ingredient,
            'source_consumption_line_id' => $sourceLine, 'quantity' => $quantity, 'base_unit' => 'g', 'created_at' => now(), 'updated_at' => now()]);

        return [$id, $line];
    }

    public function test_inventory_posts_sale_reversal_and_waste_once_with_original_lineage_and_cost(): void
    {
        $this->configureStock();
        [$sale, $source] = $this->consumption('sale', '1.000000');
        $this->assertSame(['delivered' => 1, 'failed' => 0], $this->integrations->reconcile($this->ctx, $this->actor, 'inventory'));
        $this->consumption('reversal', '0.500000', $source);
        $this->consumption('waste', '0.500000', $source);
        $this->assertSame(['delivered' => 2, 'failed' => 0], $this->integrations->reconcile($this->ctx, $this->actor, 'inventory'));
        $this->assertSame(['delivered' => 0, 'failed' => 0], $this->integrations->reconcile($this->ctx, $this->actor, 'inventory'));
        $this->assertEquals(9, DB::table('inv_stock_balances')->where('item_id', $this->stockItem->id)->value('quantity_on_hand'));
        $this->assertSame(3, DB::table('fnb_integration_stock_lines')->count());
        $this->assertSame(3, DB::table('fnb_outbox_deliveries')->where('status', 'delivered')->count());
        $this->assertSame(4, DB::table('inv_stock_documents')->count());
        $receipt = DB::table('inv_stock_documents')->where('reference', 'like', 'fnb-consumption:%')->where('type', 'receipt')->firstOrFail();
        $this->assertEquals(4, DB::table('inv_stock_document_lines')->where('document_id', $receipt->id)->value('unit_cost'));
    }

    public function test_precision_failure_rolls_back_stock_then_dead_letters_without_busy_retry(): void
    {
        $this->configureStock();
        [$id] = $this->consumption('sale', '0.000001');
        $this->assertSame(['delivered' => 0, 'failed' => 1], $this->integrations->reconcile($this->ctx, $this->actor, 'inventory'));
        $this->assertDatabaseCount('inv_stock_documents', 1);
        $this->assertDatabaseMissing('fnb_integration_stock_lines', ['consumption_line_id' => 1]);
        $this->assertDatabaseHas('fnb_stock_consumptions', ['id' => $id, 'status' => 'pending']);
        $this->assertSame(['delivered' => 0, 'failed' => 0], $this->integrations->reconcile($this->ctx, $this->actor, 'inventory'));
        DB::table('fnb_outbox_deliveries')->update(['attempts' => 9, 'available_at' => now()->subMinute()]);
        $this->integrations->reconcile($this->ctx, $this->actor, 'inventory');
        $this->assertDatabaseHas('fnb_outbox_deliveries', ['status' => 'dead_letter', 'attempts' => 10, 'lock_token' => null]);
        $this->assertDatabaseCount('inv_stock_documents', 1);
    }

    public function test_automation_is_opt_in_and_revoking_auth_identity_stops_the_worker(): void
    {
        Queue::fake();
        $mapping = $this->configureStock();
        [$id] = $this->consumption('sale', '1.000000');
        $this->artisan('fnb:dispatch-integrations')->assertExitCode(0);
        Queue::assertNothingPushed();
        $mapping = $this->integrations->mapping($this->ctx, $this->actor, ['destination' => 'inventory', 'enabled' => true, 'automatic' => true,
            'config' => ['warehouse_id' => $this->warehouse->id], 'expected_version' => $mapping['version']], (string) Str::uuid())['resource'];
        $this->artisan('fnb:dispatch-integrations')->assertExitCode(0);
        Queue::assertPushed(ReconcileFnbIntegration::class, fn ($job) => $job->mappingId === $mapping['id'] && $job->queue === 'fnb');
        $this->assertSame(['delivered' => 1, 'failed' => 0], $this->integrations->runAutomatic($mapping['id']));
        [$id] = $this->consumption('sale', '1.000000');
        $this->actor->forceFill(['auth_version' => $this->actor->auth_version + 1])->save();
        $this->assertSame(['delivered' => 0, 'failed' => 0], $this->integrations->runAutomatic($mapping['id']));
        $this->assertDatabaseHas('fnb_integration_mappings', ['id' => $mapping['id'], 'automatic' => false, 'automatic_paused_reason' => 'authorizing_identity_changed']);
        $this->assertDatabaseHas('fnb_stock_consumptions', ['id' => $id, 'status' => 'pending']);
        $this->assertDatabaseCount('inv_stock_documents', 2);
    }

    public function test_disabled_destination_or_stale_mapping_is_revalidated_before_delivery(): void
    {
        $this->configureStock();
        $this->consumption('sale', '1.000000');
        DB::table('inv_warehouses')->where('id', $this->warehouse->id)->update(['is_active' => false]);
        try {
            $this->integrations->reconcile($this->ctx, $this->actor, 'inventory');
            $this->fail('Inactive warehouse must reject.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertDatabaseCount('inv_stock_documents', 1);
        DB::table('inv_warehouses')->where('id', $this->warehouse->id)->update(['is_active' => true]);
        DB::table('module_installations')->where('key', 'inventory')->update(['status' => 'disabled']);
        try {
            $this->integrations->reconcile($this->ctx, $this->actor, 'inventory');
            $this->fail('Disabled module must reject.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertDatabaseCount('inv_stock_documents', 1);
    }

    public function test_accounting_creates_exact_gross_internal_draft_without_tax_approval_or_duplicate(): void
    {
        $organization = AcctOrganization::query()->create(['name' => 'Adapter legal entity', 'status' => 'active', 'default_currency' => 'VND']);
        AcctOrganizationWebsite::query()->create(['organization_id' => $organization->id, 'website_key' => $this->ctx->websiteKey, 'is_primary' => true]);
        $item = AcctItem::query()->create(['organization_id' => $organization->id, 'name' => 'Tea accounting', 'kind' => 'service', 'unit' => 'cup', 'tax_category' => 'standard', 'tax_rate' => 8, 'status' => 'active']);
        $fnb = app(FnbService::class);
        $menu = $fnb->saveCatalogItem($this->ctx, ['code' => 'TEA', 'name' => 'Tea', 'tax_rate_bps' => 800, 'tax_inclusive' => true,
            'station_id' => DB::table('fnb_prep_stations')->value('id'), 'variant' => ['code' => 'M', 'name' => 'Medium', 'base_price_minor' => 1001]], (string) Str::uuid())['resource'];
        $variant = $menu['variants'][0]['id'];
        $this->integrations->mapping($this->ctx, $this->actor, ['destination' => 'accounting-tax', 'source_type' => 'variant', 'source_id' => $variant, 'destination_id' => $item->id], (string) Str::uuid(), true);
        $this->integrations->mapping($this->ctx, $this->actor, ['destination' => 'accounting-tax', 'enabled' => true, 'config' => ['organization_id' => $organization->id]], (string) Str::uuid());
        $day = $fnb->openBusinessDay($this->ctx, '2026-10-18', (string) Str::uuid())['resource'];
        $shift = $fnb->openShift($this->ctx, $day['id'], 0, (string) Str::uuid())['resource'];
        $session = $fnb->openSession($this->ctx, ['business_day_id' => $day['id'], 'service_type' => 'counter'], (string) Str::uuid())['resource'];
        $order = $fnb->createOrder($this->ctx, $session['id'], $shift['id'], [], (string) Str::uuid(), $session['version'])['resource'];
        $order = $fnb->addOrderLine($this->ctx, $order['id'], ['variant_id' => $variant, 'quantity' => '3.000000'], (string) Str::uuid(), $order['version'])['resource'];
        $fnb->submitOrder($this->ctx, $order['id'], (string) Str::uuid(), $order['version']);
        $version = DB::table('fnb_service_sessions')->where('id', $session['id'])->value('version');
        $check = $fnb->createCheck($this->ctx, $session['id'], [], (string) Str::uuid(), $version)['resource'];
        $check = $fnb->finalizeCheck($this->ctx, $check['id'], (string) Str::uuid(), $check['version'])['resource'];
        $check = $fnb->setSettlementPlan($this->ctx, $check['id'], 'single_non_cash', (string) Str::uuid(), $check['version'])['resource'];
        $fnb->collectPayment($this->ctx, $check['id'], $shift['id'], ['payment_method_id' => DB::table('fnb_payment_methods')->where('kind', 'transfer')->value('id'), 'amount_minor' => 3003, 'reference' => 'LOCAL'], (string) Str::uuid(), $check['version']);
        $this->assertSame(['delivered' => 1, 'failed' => 0], $this->integrations->reconcile($this->ctx, $this->actor, 'accounting-tax'));
        $this->assertSame(['delivered' => 0, 'failed' => 0], $this->integrations->reconcile($this->ctx, $this->actor, 'accounting-tax'));
        $doc = DB::table('acct_documents')->where('source_module', 'fnb-pos')->firstOrFail();
        $this->assertSame('draft', $doc->workflow_status);
        $this->assertSame('internal_invoice', $doc->document_type);
        $this->assertEquals(3003, $doc->grand_total);
        $line = DB::table('acct_document_lines')->where('document_id', $doc->id)->firstOrFail();
        $this->assertSame('not_declared', $line->tax_category);
        $this->assertTrue(data_get(json_decode($line->snapshot, true), 'client_metadata.requires_tax_classification'));
        $this->assertEquals(3, data_get(json_decode($line->snapshot, true), 'client_metadata.source_quantity'));
        $this->assertSame(1, DB::table('acct_documents')->where('source_module', 'fnb-pos')->count());
    }

    public function test_checksum_handles_legacy_json_key_order_but_rejects_tampering(): void
    {
        $this->configureStock();
        [$id] = $this->consumption('sale', '1.000000');
        $legacy = ['check_id' => 123, 'check_no' => 'CHK-LEGACY', 'check_version' => 5, 'lines' => []];
        $encoded = json_encode($legacy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $reordered = array_reverse($legacy, true);
        DB::table('fnb_stock_consumptions')->where('id', $id)->update(['source_type' => 'check', 'payload_snapshot' => json_encode($reordered), 'payload_hash' => hash('sha256', $encoded)]);
        $this->assertSame(['delivered' => 1, 'failed' => 0], $this->integrations->reconcile($this->ctx, $this->actor, 'inventory'));
        [$tampered] = $this->consumption('waste', '1.000000');
        DB::table('fnb_stock_consumptions')->where('id', $tampered)->update(['payload_snapshot' => json_encode(['marker' => 'changed'])]);
        $this->assertSame(['delivered' => 0, 'failed' => 1], $this->integrations->reconcile($this->ctx, $this->actor, 'inventory'));
        $this->assertDatabaseCount('inv_stock_documents', 2);
        $this->assertDatabaseHas('fnb_stock_consumptions', ['id' => $tampered, 'status' => 'pending']);
    }

    public function test_expired_lease_recovers_but_final_attempt_crash_goes_to_dead_letter(): void
    {
        $this->configureStock();
        [$record] = $this->consumption('sale', '1.000000');
        $event = app(FnbOutboxService::class)->emit($this->ctx, 'stock_consumption', $record, 1, 'fnb.stock_consumption.recorded', [
            'consumption_id' => $record, 'kind' => 'sale', 'payload_hash' => DB::table('fnb_stock_consumptions')->where('id', $record)->value('payload_hash'),
        ]);
        DB::table('fnb_outbox_deliveries')->insert(['website_key' => $this->ctx->websiteKey, 'outlet_id' => $this->ctx->outletId, 'outbox_event_id' => $event['id'], 'destination' => 'inventory',
            'idempotency_key' => 'lease-fixture', 'status' => 'processing', 'attempts' => 1, 'max_attempts' => 10,
            'available_at' => now(), 'lease_expires_at' => now()->subMinute(), 'lock_token' => 'abandoned', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(['delivered' => 1, 'failed' => 0], $this->integrations->reconcile($this->ctx, $this->actor, 'inventory'));
        $this->assertDatabaseHas('fnb_outbox_deliveries', ['outbox_event_id' => $event['id'], 'attempts' => 2, 'status' => 'delivered', 'lock_token' => null]);
        DB::table('fnb_outbox_deliveries')->update(['status' => 'processing', 'attempts' => 10, 'lease_expires_at' => now()->subMinute()]);
        $this->integrations->reconcile($this->ctx, $this->actor, 'inventory');
        $this->assertDatabaseHas('fnb_outbox_deliveries', ['outbox_event_id' => $event['id'], 'status' => 'dead_letter', 'attempts' => 10]);
        $this->assertDatabaseCount('inv_stock_documents', 2);
    }

    public function test_hq_fnb_permission_does_not_grant_inventory_or_other_website_access(): void
    {
        $this->configureStock();
        $this->consumption('sale', '1.000000');
        $hq = Admin::factory()->create(['status' => 'active', 'is_active' => true, 'must_change_password' => false]);
        DB::transaction(fn () => app(FnbScopedStaffService::class)->assignHq($this->actor, $this->ctx->websiteKey, $hq, 'fnb-owner', null));
        $ctx = new FnbContext($this->ctx->websiteKey, $this->ctx->outletId, $hq->id);
        try {
            $this->integrations->reconcile($ctx, $hq, 'inventory');
            $this->fail('F&B owner cannot write inventory without inventory permissions.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $other = new FnbContext('website-other', $this->ctx->outletId, $this->actor->id);
        try {
            $this->integrations->reconcile($other, $this->actor, 'inventory');
            $this->fail('Cross-website outlet must reject.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
        $this->assertDatabaseCount('inv_stock_documents', 1);
    }

    public function test_http_reconciliation_consumes_reauth_in_transaction_and_replay_does_not_require_a_second_proof(): void
    {
        $this->configureStock();
        $this->consumption('sale', '1.000000');
        $this->actor->forceFill(['password' => 'AdapterPassword123!', 'must_change_password' => false])->save();
        $this->actingAs($this->actor, 'admin')->withSession(['admin_auth_version' => $this->actor->auth_version]);
        $this->withHeader('X-FNB-Outlet', (string) $this->ctx->outletId)->withHeader('Idempotency-Key', 'integration-reconcile-proof-test');
        $challenge = $this->postJson('/admin/api/fnb/chain/integrations/reconcile', ['destination' => 'inventory'])->assertStatus(423);
        $this->assertDatabaseCount('fnb_outbox_deliveries', 0);
        $proof = $this->postJson('/admin/api/fnb/reauth/proofs', ['action' => 'integration.reconcile', 'subject' => 'new',
            'payload_hash' => $challenge->json('details.payload_hash'), 'password' => 'AdapterPassword123!'])->assertOk()->json('data.reauth_proof');
        $this->withHeader('X-FNB-Reauth-Proof', $proof);
        $this->postJson('/admin/api/fnb/chain/integrations/reconcile', ['destination' => 'inventory'])->assertOk()->assertJsonPath('data.delivered', 1);
        $this->withHeader('X-FNB-Reauth-Proof', '');
        $this->postJson('/admin/api/fnb/chain/integrations/reconcile', ['destination' => 'inventory'])->assertOk()->assertJsonPath('data.delivered', 0);
        $this->assertSame(1, DB::table('admin_reauth_proofs')->whereNotNull('consumed_at')->count());
        $this->assertDatabaseCount('inv_stock_documents', 2);
        $this->withHeader('Idempotency-Key', 'integration-reconcile-new-command');
        $this->postJson('/admin/api/fnb/chain/integrations/reconcile', ['destination' => 'inventory'])->assertStatus(423);
    }

    public function test_item_mapping_change_pauses_automation_and_disabled_module_does_not_block_its_kill_switch(): void
    {
        $mapping = $this->configureStock(true);
        $itemMap = DB::table('fnb_integration_item_mappings')->firstOrFail();
        $this->integrations->mapping($this->ctx, $this->actor, ['destination' => 'inventory', 'source_type' => 'ingredient', 'source_id' => $this->ingredient,
            'destination_id' => $this->stockItem->id, 'unit' => 'g', 'factor' => '1.000000', 'expected_version' => $itemMap->version], (string) Str::uuid(), true);
        $this->assertDatabaseHas('fnb_integration_mappings', ['id' => $mapping['id'], 'automatic' => false, 'authorized_by' => null]);
        DB::table('module_installations')->where('key', 'inventory')->update(['status' => 'disabled']);
        $disabled = $this->integrations->mapping($this->ctx, $this->actor, ['destination' => 'inventory', 'enabled' => false,
            'config' => ['warehouse_id' => $this->warehouse->id], 'expected_version' => $mapping['version'] + 1], (string) Str::uuid())['resource'];
        $this->assertFalse((bool) $disabled['enabled']);
        $this->assertSame(['delivered' => 0, 'failed' => 0], $this->integrations->runAutomatic($mapping['id']));
    }

    public function test_accounting_currency_guard_never_guesses_foreign_minor_units_or_exchange_rates(): void
    {
        $organization = AcctOrganization::query()->create(['name' => 'Foreign fixture', 'status' => 'active', 'default_currency' => 'JPY']);
        AcctOrganizationWebsite::query()->create(['organization_id' => $organization->id, 'website_key' => $this->ctx->websiteKey]);
        DB::table('fnb_outlets')->where('id', $this->ctx->outletId)->update(['currency' => 'JPY']);
        try {
            $this->integrations->mapping($this->ctx, $this->actor, ['destination' => 'accounting-tax', 'enabled' => true, 'config' => ['organization_id' => $organization->id]], (string) Str::uuid());
            $this->fail('Foreign minor unit policy must not be inferred.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertDatabaseMissing('fnb_integration_mappings', ['destination' => 'accounting-tax']);
        $this->assertSame(0, DB::table('acct_documents')->where('source_module', 'fnb-pos')->count());
    }

    public function test_dispatch_limit_rotates_across_outlets_instead_of_starving_later_mappings(): void
    {
        Queue::fake();
        $first = $this->configureStock(true);
        $outlet = app(FnbChainService::class)->save(new FnbContext($this->ctx->websiteKey, 0, $this->actor->id), 'outlet', null, ['code' => 'SECOND', 'name' => 'Second fixture'], (string) Str::uuid())['resource']['outlet']['id'];
        $second = DB::table('fnb_integration_mappings')->insertGetId(['website_key' => $this->ctx->websiteKey, 'outlet_id' => $outlet, 'destination' => 'inventory',
            'enabled' => true, 'automatic' => true, 'authorized_by' => $this->actor->id, 'authorized_auth_version' => $this->actor->auth_version,
            'config' => json_encode(['warehouse_id' => $this->warehouse->id]), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->artisan('fnb:dispatch-integrations', ['--limit' => 1])->assertExitCode(0);
        $this->artisan('fnb:dispatch-integrations', ['--limit' => 1])->assertExitCode(0);
        Queue::assertPushed(ReconcileFnbIntegration::class, fn ($job) => $job->mappingId === $first['id']);
        Queue::assertPushed(ReconcileFnbIntegration::class, fn ($job) => $job->mappingId === $second);
    }
}
