<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Core\Modules\TestData\ModuleTestDataService;
use App\Models\AcctDocument;
use App\Models\AcctItem;
use App\Models\AcctOrganization;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Role;
use App\Support\SiteContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\Customers\Services\CustomerService;
use Modules\Pharmacy\Jobs\SendPharmacyIntegration;
use Modules\Pharmacy\Services\Commands\PharmacyCommandService;
use Modules\Pharmacy\Services\Dispensing\DispensingService;
use Modules\Pharmacy\Services\Integrations\PharmacyAccountingAdapter;
use Modules\Pharmacy\Services\Integrations\PharmacyCustomerAdapter;
use Modules\Pharmacy\Services\Integrations\PharmacyIntegrationService;
use Modules\Pharmacy\Services\Integrations\PharmacyOutboxService;
use Modules\Pharmacy\Services\Integrations\PharmacyRecoveryService;
use Modules\Pharmacy\Services\Integrations\PharmacyTransport;
use Modules\Pharmacy\Services\Operations\PharmacyOperationsService;
use Modules\Pharmacy\Services\PharmacyData as D;
use Modules\Pharmacy\Services\Prescriptions\PatientService;
use Modules\Pharmacy\Services\Sales\PharmacySaleService;
use Modules\Pharmacy\Services\Setup\PharmacySetupService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PharmacyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    private object $pharmacy;

    private string $counter;

    // Providers must execute after COMMIT; tests intentionally use no wrapping transaction.
    protected function connectionsToTransact(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = Admin::query()->findOrFail(1);
        $this->owner->update(['must_change_password' => false]);
        foreach (['merchandise', 'inventory', 'pharmacy'] as $module) {
            app(ModuleManager::class)->install($module);
            app(ModuleManager::class)->enable($module);
        }
        $setup = app(PharmacySetupService::class)->dispatch($this->owner, 'setup', ['chain_name' => 'Synthetic integration chain', 'pharmacy_name' => 'Synthetic pharmacy', 'code' => 'PH-INT'], $this->key());
        $this->pharmacy = DB::table('ph_pharmacies')->where('public_id', $setup['id'])->first();
        $this->counter = $setup['pharmacy']['counter']['id'];
        SyntheticPharmacyTransport::$calls = [];
        SyntheticPharmacyTransport::$outcome = 'accepted';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // This class intentionally does not retain a transaction-owned in-memory PDO.
        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];
    }

    private function key(): string
    {
        return 'ph-test:'.Str::uuid();
    }

    private function command(string $action, array $input, ?string $key = null): array
    {
        return app(PharmacyIntegrationService::class)->dispatch($this->owner, $action, ['pharmacy_id' => $this->pharmacy->public_id] + $input, $key ?? $this->key());
    }

    private function fails(int $status, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected integration rejection');
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode(), $e->getMessage());
        }
    }

    private function registerTransport(): void
    {
        config(['pharmacy.network_enabled' => true, 'pharmacy.allowed_provider_hosts' => ['synthetic.invalid'], 'pharmacy.transports.synthetic' => ['class' => SyntheticPharmacyTransport::class, 'verified' => true, 'timeout_seconds' => 10, 'contract_versions' => ['synthetic.drug.v1', 'synthetic.erx.v1'], 'environments' => ['sandbox', 'production'], 'kinds' => ['drug_database', 'erx'], 'hosts' => ['synthetic.invalid']]]);
    }

    private function connection(array $extra = []): array
    {
        return $this->command('config.save', $extra + ['kind' => 'drug_database', 'name' => 'Synthetic provider contract fixture', 'contract_version' => 'synthetic.drug.v1', 'environment' => 'sandbox', 'transport_key' => 'synthetic', 'endpoint' => 'https://synthetic.invalid/api', 'credentials' => ['token' => 'SYNTHETIC-SECRET-TOKEN'], 'enabled' => true]);
    }

    private function product(): object
    {
        $r = app(PharmacySetupService::class)->dispatch($this->owner, 'product.save', ['pharmacy_id' => $this->pharmacy->public_id, 'name' => 'Synthetic original medicine', 'code' => 'SYN-'.Str::random(8), 'base_unit' => 'pcs', 'registration_number' => 'SYNTHETIC-REG', 'price_minor' => '1000'], $this->key());

        return DB::table('ph_products')->where('public_id', $r['id'])->first();
    }

    private function receipt(?object $product = null): array
    {
        $product ??= $this->product();

        return app(PharmacyOperationsService::class)->dispatch($this->owner, 'receipt.create', ['pharmacy_id' => $this->pharmacy->public_id, 'product_id' => $product->public_id, 'quantity' => '5', 'unit_cost' => '400', 'batch_code' => 'LOT-'.$product->id, 'expires_at' => now()->addYear()->toDateString()], $this->key());
    }

    private function queue(array $connection, array $source, string $type = 'receipt'): array
    {
        return $this->command('integration.queue', ['connection_id' => $connection['id'], 'source_type' => $type, 'source_id' => $source['id']]);
    }

    private function credential(): void
    {
        app(PharmacySetupService::class)->dispatch($this->owner, 'credential.save', ['pharmacy_id' => $this->pharmacy->public_id, 'admin_id' => $this->owner->id, 'license_number' => 'SYNTHETIC-NOT-A-LICENSE', 'abilities' => ['review', 'dispense', 'handover'], 'valid_from' => now()->subDay()->toDateTimeString(), 'valid_until' => now()->addYear()->toDateTimeString(), 'reason' => 'Synthetic qualification fixture'], $this->key());
    }

    private function prepared(?Admin $actor = null): array
    {
        $actor ??= $this->owner;
        $product = $this->product();
        $receipt = $this->receipt($product);
        $this->credential();
        app(PharmacyOperationsService::class)->dispatch($this->owner, 'receipt.inspect', ['pharmacy_id' => $this->pharmacy->public_id, 'receipt_id' => $receipt['id'], 'expected_version' => 1, 'decision' => 'release', 'reason' => 'Synthetic release'], $this->key());
        $d = app(DispensingService::class)->create($actor, ['pharmacy_id' => $this->pharmacy->public_id, 'mode' => 'otc', 'lines' => [['product_id' => $product->public_id, 'quantity' => '1']]], $this->key());

        return app(DispensingService::class)->prepare($actor, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'allocations' => [['line_id' => $d['lines'][0]['id'], 'batch_id' => $receipt['batch_id'], 'quantity' => '1']]], $this->key());
    }

    public function test_unconfigured_provider_is_blocked_and_credentials_and_envelope_stay_encrypted(): void
    {
        $c = $this->connection();
        $r = $this->receipt();
        $attempt = $this->queue($c, $r);
        $this->assertFalse($c['readiness']['ready']);
        $this->assertSame('TRANSPORT_NOT_REGISTERED', $attempt['error']);
        $this->assertSame('blocked', $attempt['status']);
        $this->assertSame(0, app(PharmacyIntegrationService::class)->dispatchDue());
        $this->assertArrayNotHasKey('encrypted_credentials', $c);
        $this->assertArrayNotHasKey('encrypted_request', $attempt);
        $stored = DB::table('ph_connections')->sole();
        $this->assertStringNotContainsString('SYNTHETIC-SECRET', $stored->encrypted_credentials);
        $this->assertSame('SYNTHETIC-SECRET-TOKEN', D::json(Crypt::decryptString($stored->encrypted_credentials))['token']);
        $this->assertStringNotContainsString($r['id'], DB::table('ph_integration_attempts')->value('encrypted_request'));
        $this->assertStringNotContainsString('SYNTHETIC-SECRET', json_encode(DB::table('ph_commands')->get()));
        $this->assertSame([], SyntheticPharmacyTransport::$calls);
    }

    public function test_registered_transport_records_actual_acceptance_after_commit_and_deduplicates_source(): void
    {
        $this->registerTransport();
        $c = $this->connection();
        $r = $this->receipt();
        $a = $this->queue($c, $r);
        $this->assertSame($a['id'], $this->queue($c, $r)['id']);
        $result = app(PharmacyIntegrationService::class)->process($a['id']);
        $this->assertSame('accepted', $result['status']);
        $this->assertSame('SYNTHETIC-ACK', $result['external_id']);
        $this->assertSame(0, SyntheticPharmacyTransport::$calls[0]['transaction']);
        $this->assertSame(10, SyntheticPharmacyTransport::$calls[0]['timeout']);
        $this->assertSame($a['event_id'], SyntheticPharmacyTransport::$calls[0]['event']);
        app(PharmacyIntegrationService::class)->process($a['id']);
        $this->assertCount(1, SyntheticPharmacyTransport::$calls);
        $this->assertDatabaseCount('ph_integration_attempts', 1);
    }

    public function test_uncertain_delivery_requires_lookup_of_same_event_even_after_source_changes(): void
    {
        $this->registerTransport();
        $c = $this->connection();
        $r = $this->receipt();
        $a = $this->queue($c, $r);
        SyntheticPharmacyTransport::$outcome = 'uncertain';
        app(PharmacyIntegrationService::class)->process($a['id']);
        $this->assertSame(0, app(PharmacyIntegrationService::class)->dispatchDue());
        DB::table('ph_receipts')->where('public_id', $r['id'])->update(['status' => 'rejected', 'version' => 2]);
        $stored = DB::table('ph_integration_attempts')->sole();
        $this->connection(['expected_version' => 1, 'credentials' => ['token' => 'SYNTHETIC-SECRET-TOKEN', 'rotation_marker' => 'v2']]);
        $retry = $this->command('integration.reconcile', ['attempt_id' => $a['id'], 'expected_version' => $stored->version]);
        $this->assertSame('pending', $retry['status']);
        SyntheticPharmacyTransport::$outcome = 'accepted';
        app(PharmacyIntegrationService::class)->process($a['id']);
        $this->assertSame(['send', 'reconcile'], array_column(SyntheticPharmacyTransport::$calls, 'operation'));
        $this->assertSame($a['event_id'], SyntheticPharmacyTransport::$calls[1]['event']);
        $this->assertSame('quarantine', SyntheticPharmacyTransport::$calls[1]['source_status']);
    }

    public function test_revoked_actor_changed_source_and_changed_connection_never_call_transport(): void
    {
        $this->registerTransport();
        $c = $this->connection();
        $r = $this->receipt();
        $a = $this->queue($c, $r);
        DB::table('admins')->where('id', $this->owner->id)->increment('auth_version');
        $this->assertSame('blocked', app(PharmacyIntegrationService::class)->process($a['id'])['status']);
        $this->owner = $this->owner->fresh();
        $r2 = $this->receipt();
        $a2 = $this->queue($c, $r2);
        DB::table('ph_receipts')->where('public_id', $r2['id'])->increment('version');
        $this->assertSame('blocked', app(PharmacyIntegrationService::class)->process($a2['id'])['status']);
        $r3 = $this->receipt();
        $a3 = $this->queue($c, $r3);
        DB::table('ph_connections')->where('public_id', $c['id'])->increment('version');
        $this->assertSame('blocked', app(PharmacyIntegrationService::class)->process($a3['id'])['status']);
        $this->assertSame([], SyntheticPharmacyTransport::$calls);
    }

    public function test_finalized_stock_is_not_reported_as_handed_over_and_erx_contract_is_distinct(): void
    {
        $this->registerTransport();
        $c = $this->connection();
        $d = $this->prepared();
        $d = app(DispensingService::class)->review($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'decision' => 'approved', 'reason' => 'Synthetic review'], $this->key());
        $shift = app(PharmacySaleService::class)->openShift($this->owner, ['pharmacy_id' => $this->pharmacy->public_id, 'counter_id' => $this->counter, 'opening_minor' => '0'], $this->key());
        $d = app(PharmacySaleService::class)->finalize($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'shift_id' => $shift['id'], 'tenders' => [['method' => 'cash', 'amount_minor' => '1000']]], $this->key());
        $this->fails(409, fn () => $this->queue($c, $d, 'dispensing'));
        $d = app(DispensingService::class)->handover($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'recipient_name' => 'Synthetic recipient'], $this->key());
        $this->assertSame('pending', $this->queue($c, $d, 'dispensing')['status']);
        $erx = $this->connection(['kind' => 'erx', 'contract_version' => 'synthetic.erx.v1']);
        $this->fails(422, fn () => $this->queue($erx, $this->receipt()));
        $this->assertSame('pending', $this->queue($erx, $d, 'dispensing')['status']);
    }

    public function test_network_production_allowlist_and_timeout_gates_and_expired_lease_are_fail_closed(): void
    {
        $this->registerTransport();
        config(['pharmacy.network_enabled' => false]);
        $c = $this->connection();
        $this->assertSame('NETWORK_DISABLED', $c['readiness']['reason']);
        config(['pharmacy.network_enabled' => true]);
        $chain = DB::table('ph_chains')->find($this->pharmacy->chain_id);
        $c = $this->connection(['environment' => 'production', 'expected_version' => 1, 'confirmation' => 'ENABLE PHARMACY PRODUCTION '.$chain->code]);
        $this->assertSame('PRODUCTION_DISABLED', $c['readiness']['reason']);
        $c = $this->connection(['expected_version' => 2, 'endpoint' => 'https://not-allowlisted.invalid']);
        $this->assertSame('ENDPOINT_NOT_ALLOWLISTED', $c['readiness']['reason']);
        config(['pharmacy.transports.synthetic.timeout_seconds' => 50]);
        $c = $this->connection(['expected_version' => 3]);
        $this->assertSame('TRANSPORT_TIMEOUT_NOT_BOUNDED', $c['readiness']['reason']);
        config(['pharmacy.transports.synthetic.timeout_seconds' => 10]);
        $c = $this->connection(['expected_version' => 4]);
        $a = $this->queue($c, $this->receipt());
        DB::table('ph_integration_attempts')->where('public_id', $a['id'])->update(['status' => 'processing', 'attempts' => 1, 'lease_token' => (string) Str::uuid(), 'lease_until' => now()->subMinute()]);
        $this->assertSame(0, app(PharmacyIntegrationService::class)->dispatchDue());
        $this->assertDatabaseHas('ph_integration_attempts', ['public_id' => $a['id'], 'status' => 'uncertain', 'error' => 'WORKER_LEASE_EXPIRED']);
        $this->assertSame([], SyntheticPharmacyTransport::$calls);
    }

    public function test_async_dispatch_is_bounded_and_audit_outbox_consumer_is_deduplicated(): void
    {
        $this->registerTransport();
        $c = $this->connection();
        $this->queue($c, $this->receipt());
        $this->queue($c, $this->receipt());
        Queue::fake();
        config(['queue.default' => 'database']);
        $this->assertSame(1, app(PharmacyIntegrationService::class)->dispatchDue(1));
        Queue::assertPushed(SendPharmacyIntegration::class, 1);
        $event = DB::transaction(fn () => app(PharmacyCommandService::class)->emit($this->pharmacy, 'pharmacy.synthetic.event', 'test', (string) Str::uuid(), 1));
        app(PharmacyOutboxService::class)->dispatchDue();
        $before = DB::table('audit_logs')->where('action', 'pharmacy.outbox.delivered')->count();
        DB::table('ph_outbox')->where('event_id', $event)->update(['status' => 'pending']);
        app(PharmacyOutboxService::class)->dispatchDue();
        $this->assertSame($before, DB::table('audit_logs')->where('action', 'pharmacy.outbox.delivered')->count());
        $this->assertDatabaseHas('ph_inbox', ['consumer' => 'pharmacy.audit.v1', 'event_id' => $event]);
    }

    public function test_expiration_releases_real_reservation_and_revoked_original_actor_needs_explicit_recovery(): void
    {
        $d = $this->prepared();
        $this->travel(16)->minutes();
        $this->assertSame(['resolved' => 1, 'manual_review' => 0], app(PharmacyRecoveryService::class)->recoverExpiring());
        $this->assertDatabaseHas('ph_dispensings', ['public_id' => $d['id'], 'status' => 'cancelled']);
        $this->assertEquals(0, DB::table('inv_stock_balances')->sum('quantity_reserved'));
        $this->travelBack();
        $d = $this->prepared();
        $this->travel(16)->minutes();
        DB::table('admins')->where('id', $this->owner->id)->increment('auth_version');
        $this->assertSame(['resolved' => 0, 'manual_review' => 1], app(PharmacyRecoveryService::class)->recoverExpiring());
        $case = DB::table('ph_recovery_cases')->where('status', 'manual_review')->sole();
        $this->assertSame(['resolved' => 0, 'manual_review' => 0], app(PharmacyRecoveryService::class)->recoverExpiring());
        $this->assertDatabaseHas('ph_dispensings', ['public_id' => $d['id'], 'status' => 'prepared']);
        $this->owner = $this->owner->fresh();
        $resolved = app(PharmacyRecoveryService::class)->dispatch($this->owner, 'recovery.resolve', ['pharmacy_id' => $this->pharmacy->public_id, 'case_id' => $case->public_id, 'expected_version' => $d['version'], 'reason' => 'Synthetic explicitly authorized recovery'], $this->key());
        $this->assertSame('resolved', $resolved['status']);
        $this->assertEquals(0, DB::table('inv_stock_balances')->sum('quantity_reserved'));
    }

    public function test_expiration_native_permission_failure_persists_manual_case_after_rollback(): void
    {
        $actor = Admin::factory()->create(['must_change_password' => false]);
        $permissions = ['pharmacy.view', 'pharmacy.dispensing.view', 'pharmacy.dispensing.manage'];
        $role = Role::query()->create(['key' => 'synthetic-recovery', 'name' => 'Synthetic recovery']);
        $role->permissions()->sync(DB::table('permissions')->whereIn('key', $permissions)->pluck('id'));
        AdminRoleAssignment::query()->create(['admin_id' => $actor->id, 'role_id' => $role->id, 'scope_type' => 'global']);
        DB::table('ph_memberships')->insert(['public_id' => (string) Str::uuid(), 'chain_id' => $this->pharmacy->chain_id, 'pharmacy_id' => $this->pharmacy->id, 'admin_id' => $actor->id, 'permissions' => json_encode($permissions), 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $d = $this->prepared($actor->fresh());
        DB::table('ph_memberships')->where('admin_id', $actor->id)->update(['status' => 'inactive']);
        $this->travel(16)->minutes();
        app(PharmacyRecoveryService::class)->recoverExpiring();
        $this->assertDatabaseHas('ph_recovery_cases', ['dispensing_id' => DB::table('ph_dispensings')->where('public_id', $d['id'])->value('id'), 'status' => 'manual_review', 'error_code' => 'NATIVE_PERMISSION_OR_STATE_CHANGED']);
        $this->assertEquals(1, DB::table('inv_stock_balances')->sum('quantity_reserved'));
    }

    public function test_internal_accounting_draft_requires_explicit_org_and_item_mapping_and_never_posts(): void
    {
        app(ModuleManager::class)->install('accounting-tax');
        app(ModuleManager::class)->enable('accounting-tax');
        $org = AcctOrganization::query()->create(['name' => 'Synthetic legal entity', 'legal_name' => 'Synthetic legal entity', 'default_currency' => 'VND', 'status' => 'active']);
        $item = AcctItem::query()->create(['organization_id' => $org->id, 'name' => 'Synthetic medicine', 'kind' => 'goods', 'unit' => 'pcs', 'tax_category' => 'not_declared', 'status' => 'active']);
        $d = $this->prepared();
        $d = app(DispensingService::class)->review($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'decision' => 'approved', 'reason' => 'Synthetic review'], $this->key());
        $shift = app(PharmacySaleService::class)->openShift($this->owner, ['pharmacy_id' => $this->pharmacy->public_id, 'counter_id' => $this->counter, 'opening_minor' => '0'], $this->key());
        $d = app(PharmacySaleService::class)->finalize($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'shift_id' => $shift['id'], 'tenders' => [['method' => 'cash', 'amount_minor' => '1000']]], $this->key());
        $sale = DB::table('ph_sales')->sole();
        $product = DB::table('ph_products')->sole();
        $adapter = app(PharmacyAccountingAdapter::class);
        $input = ['pharmacy_id' => $this->pharmacy->public_id, 'organization_id' => $org->id, 'source_type' => 'sale', 'source_id' => $sale->public_id];
        $this->fails(409, fn () => $adapter->dispatch($this->owner, 'accounting.draft', $input, $this->key()));
        $adapter->dispatch($this->owner, 'accounting.mapping.save', ['pharmacy_id' => $this->pharmacy->public_id, 'organization_id' => $org->id, 'item_mappings' => [['product_id' => $product->public_id, 'accounting_item_id' => $item->id]]], $this->key());
        $r = $adapter->dispatch($this->owner, 'accounting.draft', $input, $this->key());
        $this->assertSame($r['id'], $adapter->dispatch($this->owner, 'accounting.draft', $input, $this->key())['id']);
        $document = AcctDocument::query()->with('lines')->sole();
        $this->assertSame('draft', $document->workflow_status);
        $this->assertSame('internal_invoice', $document->document_type);
        $this->assertSame('1000.00', $document->grand_total);
        $this->assertNull($document->posted_at);
        $this->assertSame('Synthetic original medicine', $document->lines->sole()->name);
        $this->assertSame('not_declared', $document->lines->sole()->tax_category);
        $this->assertDatabaseCount('ph_accounting_source_links', 1);
        DB::table('ph_accounting_mappings')->update(['authorized_auth_version' => 999]);
        $this->fails(403, fn () => $adapter->dispatch($this->owner, 'accounting.draft', $input, $this->key()));
    }

    public function test_customers_mapping_requires_consent_and_shares_only_explicit_commercial_fields(): void
    {
        app(ModuleManager::class)->install('customers');
        app(ModuleManager::class)->enable('customers');
        $book = app(CustomerService::class)->createBook($this->owner, ['name' => 'Synthetic commercial book', 'code' => 'PH-COMMERCIAL'], $this->key());
        $adapter = app(PharmacyCustomerAdapter::class);
        $scope = ['pharmacy_id' => $this->pharmacy->public_id];
        app(SiteContext::class)->set(null, 'independent-pharmacy');
        $adapter->dispatch($this->owner, 'customer.mapping.save', $scope + ['website_key' => 'website-main', 'book_id' => $book['id']], $this->key());
        $this->assertSame('independent-pharmacy', app(SiteContext::class)->websiteKey());
        $patient = app(PatientService::class)->save($this->owner, $scope + ['name' => 'CLINICAL-PRIVATE-PATIENT', 'allergies' => ['CLINICAL-PRIVATE-ALLERGY'], 'processing_basis' => 'consent', 'processing_evidence' => 'Synthetic clinical consent'], $this->key());
        $customer = $adapter->dispatch($this->owner, 'customer.save', $scope + ['profile' => ['name' => 'Synthetic commercial buyer', 'phone' => '0901234567'], 'processing_consent' => true, 'policy_version' => 'synthetic-policy-v1', 'evidence' => 'Synthetic commercial profile consent; not marketing'], $this->key());
        $this->assertSame('independent-pharmacy', app(SiteContext::class)->websiteKey());
        $linkInput = $scope + ['patient_id' => $patient['id'], 'customer_id' => $customer['id']];
        $key = $this->key();
        $link = $adapter->dispatch($this->owner, 'customer.link', $linkInput, $key);
        $this->assertSame('active', $link['status']);
        $lookup = $adapter->dispatch($this->owner, 'customer.lookup', $scope + ['q' => 'Synthetic commercial'], $this->key());
        $this->assertCount(1, $lookup['items']);
        foreach (['cust_profiles', 'cust_contacts', 'cust_commands', 'cust_outbox_deliveries'] as $table) {
            $this->assertStringNotContainsString('CLINICAL-PRIVATE', json_encode(DB::table($table)->get()), $table);
        }
        $this->assertSame('CLINICAL-PRIVATE-PATIENT', D::json(Crypt::decryptString(DB::table('ph_patients')->sole()->encrypted_profile))['name']);
        DB::table('cust_consents')->update(['state' => 'withdrawn']);
        $this->assertSame([], $adapter->dispatch($this->owner, 'customer.lookup', $scope + ['q' => 'Synthetic commercial'], $this->key())['items']);
        $this->fails(409, fn () => $adapter->dispatch($this->owner, 'customer.link', $linkInput, $key));
        $this->assertSame('independent-pharmacy', app(SiteContext::class)->websiteKey());
    }

    public function test_customers_rejects_clinical_payload_and_restores_context_after_native_denial(): void
    {
        app(ModuleManager::class)->install('customers');
        app(ModuleManager::class)->enable('customers');
        $book = app(CustomerService::class)->createBook($this->owner, ['name' => 'Synthetic commercial book', 'code' => 'PH-COMMERCIAL'], $this->key());
        $adapter = app(PharmacyCustomerAdapter::class);
        $scope = ['pharmacy_id' => $this->pharmacy->public_id];
        $adapter->dispatch($this->owner, 'customer.mapping.save', $scope + ['website_key' => 'website-main', 'book_id' => $book['id']], $this->key());
        app(SiteContext::class)->set(null, 'independent-pharmacy');
        $this->fails(422, fn () => $adapter->dispatch($this->owner, 'customer.save', $scope + ['profile' => ['name' => 'Synthetic', 'allergies' => ['FORBIDDEN']], 'processing_consent' => true, 'policy_version' => 'v1', 'evidence' => 'Synthetic'], $this->key()));
        $this->assertDatabaseCount('cust_profiles', 0);
        $this->assertDatabaseCount('cust_consents', 0);
        DB::table('cust_books')->where('public_id', $book['id'])->update(['status' => 'archived']);
        $this->fails(404, fn () => $adapter->dispatch($this->owner, 'customer.lookup', $scope + ['q' => 'synthetic'], $this->key()));
        $this->assertSame('independent-pharmacy', app(SiteContext::class)->websiteKey());
    }

    public function test_old_customers_batch_cannot_be_deleted_through_a_real_mapping_while_pharmacy_is_disabled(): void
    {
        app(ModuleManager::class)->install('customers');
        app(ModuleManager::class)->enable('customers');
        $service = app(ModuleTestDataService::class);
        $batch = $service->create('customers', 'website-main', $this->owner, (string) Str::uuid());
        $book = DB::table('cust_books')->sole();
        // This predates the Pharmacy reference contract stored by new batches.
        DB::table('module_test_data_batches')->where('id', $batch['id'])->update(['soft_references' => '[]']);
        app(PharmacyCustomerAdapter::class)->dispatch($this->owner, 'customer.mapping.save', ['pharmacy_id' => $this->pharmacy->public_id, 'website_key' => 'website-main', 'book_id' => $book->public_id], $this->key());
        DB::table('module_installations')->where('key', 'pharmacy')->update(['status' => 'disabled']);
        DB::table('ph_runtime_states')->update(['operational_state' => 'disabled']);
        $this->fails(409, fn () => $service->remove('website-main', $this->owner, 'customers', $batch['id']));
        $this->assertDatabaseHas('cust_books', ['id' => $book->id]);
        $this->assertDatabaseCount('cust_profiles', 3);
        $this->assertDatabaseHas('module_test_data_batches', ['id' => $batch['id'], 'status' => 'active']);
    }

    public function test_synthetic_test_data_is_insert_only_draft_and_exact_cleanup_does_not_touch_real_scope(): void
    {
        $before = DB::table('ph_pharmacies')->count();
        $service = app(ModuleTestDataService::class);
        $key = (string) Str::uuid();
        $batch = $service->create('pharmacy', 'backoffice', $this->owner, $key);
        $this->assertSame($batch, $service->create('pharmacy', 'backoffice', $this->owner, $key));
        $this->assertSame($before + 1, DB::table('ph_pharmacies')->count());
        foreach (['inv_stock_movements', 'inv_cost_layers', 'ph_dispensings', 'ph_sales', 'ph_credentials', 'ph_patients'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        $this->assertSame('draft', DB::table('ph_products')->sole()->status);
        $this->assertSame('draft', DB::table('ph_connections')->sole()->status);
        $this->assertNull(DB::table('ph_connections')->sole()->encrypted_credentials);
        $service->remove('backoffice', $this->owner, 'pharmacy', $batch['id']);
        $this->assertSame($before, DB::table('ph_pharmacies')->count());
        $this->assertDatabaseHas('ph_pharmacies', ['public_id' => $this->pharmacy->public_id]);
    }
}

/** Test-only deterministic transport, never registered by production configuration. */
final class SyntheticPharmacyTransport implements PharmacyTransport
{
    public static array $calls = [];

    public static string $outcome = 'accepted';

    public function send(array $connection, array $envelope, array $credentials): array
    {
        return $this->result('send', $connection, $envelope, $credentials);
    }

    public function reconcile(array $connection, array $envelope, array $credentials): array
    {
        return $this->result('reconcile', $connection, $envelope, $credentials);
    }

    private function result(string $operation, array $connection, array $envelope, array $credentials): array
    {
        if (($credentials['token'] ?? '') !== 'SYNTHETIC-SECRET-TOKEN') {
            throw new \RuntimeException('Synthetic credential mismatch');
        }
        self::$calls[] = ['operation' => $operation, 'transaction' => DB::transactionLevel(), 'event' => $envelope['event_id'], 'source_status' => $envelope['source']['status'], 'timeout' => $connection['delivery_timeout_seconds']];

        return ['status' => self::$outcome, 'external_id' => self::$outcome === 'accepted' ? 'SYNTHETIC-ACK' : null, 'response_code' => 'SYNTHETIC'];
    }
}
