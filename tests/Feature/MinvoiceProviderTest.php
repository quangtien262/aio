<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Jobs\AccountingTax\ProcessEinvoiceTransmission;
use App\Jobs\AccountingTax\ScheduleMsmiInboundSyncs;
use App\Jobs\AccountingTax\SyncMsmiInboundInvoices;
use App\Models\AcctDocument;
use App\Models\AcctEinvoiceInbound;
use App\Models\AcctOrganization;
use App\Models\AcctParty;
use App\Models\AcctProviderConnection;
use App\Models\Admin;
use App\Support\AccountingTax\ModuleCapabilityService;
use App\Support\AccountingTax\Providers\EinvoiceTransmissionService;
use App\Support\AccountingTax\Providers\Exceptions\ProviderSafetyException;
use App\Support\AccountingTax\Providers\InboundInvoiceReviewService;
use App\Support\AccountingTax\Providers\MsmiInboundArtifactService;
use App\Support\AccountingTax\Providers\MsmiInboundSyncService;
use App\Support\AccountingTax\Providers\ProviderConnectionService;
use App\Support\AccountingTax\Providers\ProviderExecutionGuard;
use App\Support\AccountingTax\Providers\ProviderFactory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MinvoiceProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'accounting_einvoice.network_enabled' => true,
            'accounting_einvoice.production_enabled' => false,
            'accounting_einvoice.production.contract_version' => 'contract-test-v2',
            'accounting_einvoice.production.sandbox_health_max_age_hours' => 24,
        ]);
        Http::preventStrayRequests();

        $this->seed(DatabaseSeeder::class);
        $manager = app(ModuleManager::class);
        $manager->install('accounting-tax');
        $manager->enable('accounting-tax');
        $manager->install('minvoice-connector');
        $manager->enable('minvoice-connector');
    }

    public function test_connection_secrets_are_encrypted_and_production_has_independent_gates(): void
    {
        $organization = $this->organization();
        $service = app(ProviderConnectionService::class);
        $sandbox = $this->connection($organization, 'outbound', 'sandbox');
        $stored = (string) DB::table('acct_provider_connections')->where('id', $sandbox->id)->value('credentials');

        $this->assertStringNotContainsString('provider-password', $stored);
        $this->assertStringNotContainsString('provider-user', $stored);
        $this->assertSame('provider-password', $sandbox->credentials['password']);
        $this->assertSame('configured', $service->readiness($sandbox)['state']);

        Http::fake([
            'https://sandbox.minvoice.com.vn/api/Account/Login' => Http::response([
                'ok' => true,
                'code' => '00',
                'token' => 'sandbox-token',
            ]),
        ]);
        $service->test($sandbox);
        $sandbox->refresh();
        $this->assertSame('healthy', $service->readiness($sandbox)['state']);
        $this->assertNotNull($sandbox->sandbox_verified_at);
        $this->assertTrue(app(ModuleCapabilityService::class)
            ->accountingIntegrations($organization->id)['einvoice.minvoice.outbound.v1']['ready']);

        $production = $this->connection($organization, 'outbound', 'production');
        $service->allowProduction($production, 'ALLOW PRODUCTION', 'contract-test-v2');
        $production->refresh();
        $this->assertNotNull($production->production_allowed_at);

        $this->expectException(ProviderSafetyException::class);
        app(ProviderFactory::class)->outbound($production)->healthCheck();
    }

    public function test_scheduled_msmi_sync_is_opt_in_and_respects_global_network_gate(): void
    {
        Queue::fake();
        $organization = $this->organization();
        $connection = $this->verifiedConnection($organization, 'inbound');
        $settings = $connection->settings ?? [];
        $settings['scheduled_sync_enabled'] = true;
        $settings['sync_lookback_days'] = 14;
        $connection->forceFill(['settings' => $settings])->save();

        (new ScheduleMsmiInboundSyncs)->handle(app(ProviderExecutionGuard::class));
        Queue::assertPushed(SyncMsmiInboundInvoices::class, fn ($job): bool => $job->connectionId === $connection->id
            && $job->filters['date_from'] === now()->subDays(14)->toDateString());

        Queue::fake();
        config(['accounting_einvoice.network_enabled' => false]);
        (new ScheduleMsmiInboundSyncs)->handle(app(ProviderExecutionGuard::class));
        Queue::assertNothingPushed();
    }

    public function test_outbound_outbox_is_idempotent_and_stores_private_artifacts(): void
    {
        Queue::fake();
        Storage::fake('accounting_private');
        $organization = $this->organization();
        $connection = $this->verifiedConnection($organization, 'outbound');
        $document = $this->postedDocument($organization);
        $document->party->forceFill([
            'name' => 'Changed after posting',
            'tax_code' => '0999999999',
        ])->save();
        Http::fake([
            'https://sandbox.minvoice.com.vn/api/Account/Login' => Http::response([
                'ok' => true,
                'code' => '00',
                'token' => 'safe-token',
            ]),
            'https://sandbox.minvoice.com.vn/api/InvoiceApi78' => Http::response(
                $this->fixture('outbound-create-draft-response.json'),
            ),
        ]);

        $service = app(EinvoiceTransmissionService::class);
        $transmission = $service->enqueue($document, $connection, 'create_draft', ['series' => '1C26TAA']);
        $same = $service->enqueue($document, $connection, 'create_draft', ['series' => '1C26TAA']);

        $this->assertSame($transmission->id, $same->id);
        Queue::assertPushed(ProcessEinvoiceTransmission::class);
        $this->runTransmission($transmission->id);
        $transmission->refresh();

        $this->assertSame('succeeded', $transmission->status);
        $this->assertSame('provider-doc-100', $transmission->provider_document_id);
        $this->assertSame('waiting_sign', $transmission->legal_status);
        $this->assertSame('waiting_sign', $document->fresh()->legal_status);
        $this->assertSame('aio-sandbox-org-'.$organization->id.'-document-'.$document->id, data_get(
            $transmission->request_snapshot,
            'provider_operation_key',
        ));
        $this->assertSame('Customer Co', data_get(
            $transmission->request_snapshot,
            'payload.data.0.inv_buyerLegalName',
        ));
        $this->assertSame('0200000000', data_get(
            $transmission->request_snapshot,
            'payload.data.0.inv_buyerTaxCode',
        ));
        $this->assertSame('10', data_get(
            $transmission->request_snapshot,
            'payload.data.0.details.0.data.0.ma_thue',
        ));
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://sandbox.minvoice.com.vn/api/InvoiceApi78'
            && $request->hasHeader('Authorization', 'Bear safe-token'));

        Http::fake([
            'https://sandbox.minvoice.com.vn/api/InvoiceApi78/PrintInvoice*' => Http::response(
                '%PDF-1.7 safe fake',
                200,
                ['Content-Type' => 'application/pdf'],
            ),
        ]);
        $pdf = $service->enqueue($document, $connection, 'download_pdf');
        $this->runTransmission($pdf->id);
        $pdf->refresh();

        $this->assertSame('succeeded', $pdf->status);
        $this->assertNotNull($pdf->checksum);
        Storage::disk('accounting_private')->assertExists($pdf->pdf_path);
    }

    public function test_uncertain_create_is_reconciled_by_provider_operation_key_without_blind_retry(): void
    {
        Queue::fake();
        $organization = $this->organization();
        $connection = $this->verifiedConnection($organization, 'outbound');
        $document = $this->postedDocument($organization, 'INV-UNCERTAIN');
        Http::fake([
            'https://sandbox.minvoice.com.vn/api/Account/Login' => Http::response([
                'ok' => true,
                'code' => '00',
                'token' => 'safe-token',
            ]),
            'https://sandbox.minvoice.com.vn/api/InvoiceApi78' => Http::failedConnection('response lost'),
        ]);

        $source = app(EinvoiceTransmissionService::class)->enqueue(
            $document,
            $connection,
            'create_draft',
            ['series' => '1C26TAA'],
        );
        $this->runTransmission($source->id);
        $source->refresh();
        $this->assertSame('uncertain', $source->status);

        $reconciliation = $source->document->id
            ? $source->newQuery()->where('operation', 'sync_status')->where('document_id', $document->id)->firstOrFail()
            : null;
        $this->assertNotNull($reconciliation);

        Http::fake([
            'https://sandbox.minvoice.com.vn/api/InvoiceApi78/GetInfoInvoice*' => Http::response([
                'hoadon68_id' => 'provider-reconciled-1',
                'trang_thai' => 4,
            ]),
        ]);
        $this->runTransmission($reconciliation->id);

        $source->refresh();
        $this->assertSame('succeeded', $source->status);
        $this->assertSame('provider-reconciled-1', $source->provider_document_id);
    }

    public function test_issue_is_blocked_when_immutable_document_has_no_legal_tax_point(): void
    {
        Queue::fake();
        $organization = $this->organization();
        $connection = $this->verifiedConnection($organization, 'outbound');
        $document = $this->postedDocument($organization, 'INV-NO-TAX-POINT');
        AcctDocument::query()->whereKey($document->id)->update(['metadata' => null]);
        $document->refresh();
        $service = app(EinvoiceTransmissionService::class);
        $preview = $service->preview($document, $connection, '1C26TAA');

        $this->assertTrue(collect($preview['warnings'])->contains(
            fn (array $warning): bool => $warning['code'] === 'issuance_event_missing'
                && $warning['blocking'] === true,
        ));

        try {
            $service->enqueue($document, $connection, 'create_draft', ['series' => '1C26TAA']);
            $this->fail('Issuance without a legal tax point must be blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document', $exception->errors());
        }

        $this->assertDatabaseMissing('acct_einvoice_transmissions', ['document_id' => $document->id]);
        Queue::assertNothingPushed();
    }

    public function test_standard_issue_flow_rejects_internal_documents(): void
    {
        Queue::fake();
        $organization = $this->organization();
        $connection = $this->verifiedConnection($organization, 'outbound');
        $document = $this->postedDocument($organization, 'INTERNAL-001');
        AcctDocument::query()->whereKey($document->id)->update(['document_type' => 'internal_invoice']);
        $document->refresh();
        $service = app(EinvoiceTransmissionService::class);
        $preview = $service->preview($document, $connection, '1C26TAA');

        $this->assertTrue(collect($preview['warnings'])->contains(
            fn (array $warning): bool => $warning['code'] === 'unsupported_document_type'
                && $warning['blocking'] === true,
        ));

        try {
            $service->enqueue($document, $connection, 'create_draft', ['series' => '1C26TAA']);
            $this->fail('Internal documents must not enter the standard tax-invoice issuance flow.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document', $exception->errors());
        }

        $this->assertDatabaseMissing('acct_einvoice_transmissions', ['document_id' => $document->id]);
        Queue::assertNothingPushed();
    }

    public function test_msmi_sync_is_connection_scoped_and_creates_review_only_internal_draft(): void
    {
        Storage::fake('accounting_private');
        $organization = $this->organization();
        $connection = $this->verifiedConnection($organization, 'inbound');
        $payload = $this->msmiPayload();
        Http::fake([
            'https://sandbox.minvoice.com.vn/erp/qlhd-api/invoices?*' => Http::response($payload),
        ]);

        $result = app(MsmiInboundSyncService::class)->sync($connection, [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-17',
            'size' => 100,
        ]);
        $invoice = AcctEinvoiceInbound::query()->findOrFail($result['invoice_ids'][0]);

        $this->assertSame(1, $result['synced']);
        $this->assertCount(1, $invoice->lines);
        $this->assertCount(1, $invoice->vatBreakdowns);
        $this->assertSame('0100000000', $invoice->buyer_tax_code);

        $second = $this->verifiedConnection($organization, 'inbound', 'Second mSMI');
        app(MsmiInboundSyncService::class)->sync($second, [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-17',
        ]);
        $this->assertSame(2, AcctEinvoiceInbound::query()->where('provider_invoice_id', 'mongo-001')->count());

        $document = app(InboundInvoiceReviewService::class)->createInternalDraft($invoice, 991);
        $same = app(InboundInvoiceReviewService::class)->createInternalDraft($invoice->fresh(), 991);
        $this->assertSame($document->id, $same->id);
        $this->assertSame('MSMI-0300000000-C26TAA-000001', $document->document_no);
        $this->assertSame('draft', $document->workflow_status);
        $this->assertSame('not_assessed', $document->tax_eligibility);
        $this->assertSame('inbound', $document->direction);

        Http::fake([
            'https://sandbox.minvoice.com.vn/erp/qlhd-api/invoices/mongo-001/download/invoice.xml' => Http::response(
                '<Invoice id="mongo-001"/>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
            'https://sandbox.minvoice.com.vn/erp/qlhd-api/invoices/mongo-001/warning' => Http::response([
                'status' => 'warning',
                'origin_status' => 'warning',
                'xmlnven' => 1,
                'has_xml' => true,
            ]),
        ]);
        $invoice = app(MsmiInboundArtifactService::class)->download($invoice->fresh(), 'xml');
        $this->assertNotNull($invoice->xml_checksum);
        Storage::disk('accounting_private')->assertExists($invoice->xml_path);
        $invoice = app(MsmiInboundArtifactService::class)->checkWarning($invoice);
        $this->assertContains('xml_integrity_failed', $invoice->warnings);
    }

    public function test_unapproved_advanced_legal_operations_are_audited_without_network_calls(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        $organization = $this->organization();
        $connection = $this->verifiedConnection($organization, 'outbound');
        $original = $this->postedDocument($organization, 'INV-ORIGINAL');
        $correction = $this->postedDocument($organization, 'INV-ADJUST');
        AcctDocument::query()->whereKey($correction->id)->update([
            'original_document_id' => $original->id,
            'correction_type' => 'adjustment',
        ]);
        $correction->refresh();

        $transmission = app(EinvoiceTransmissionService::class)->enqueueBlockedLegalOperation(
            $correction,
            $connection,
            'adjust',
            ['series' => '1C26TAA', 'reason' => 'Điều chỉnh kiểm thử'],
        );

        $this->assertSame('blocked', $transmission->status);
        $this->assertFalse((bool) data_get($transmission->request_snapshot, 'provider_supported'));
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_authorization_prefix_cannot_inject_headers(): void
    {
        $this->expectException(ValidationException::class);

        app(ProviderConnectionService::class)->save([
            ...$this->connectionPayload($this->organization(), 'outbound', 'sandbox', 'Unsafe'),
            'settings' => ['authorization_prefix' => "Bear\r\nX-Injected: yes"],
        ], null, 1);
    }

    public function test_admin_connection_api_never_returns_secrets_and_global_gate_blocks_issue_queue(): void
    {
        Queue::fake();
        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $this->actingAs($owner, 'admin');
        $organization = $this->organization();
        $payload = $this->connectionPayload($organization, 'outbound', 'sandbox', 'API Connection');

        $response = $this->postJson('/admin/api/accounting-tax/minvoice/connections', $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', 'API Connection')
            ->assertJsonMissing(['password' => 'provider-password'])
            ->assertJsonMissing(['api_token' => 'static-msmi-token']);
        $connection = AcctProviderConnection::query()->findOrFail($response->json('data.id'));
        $connection->forceFill([
            'sandbox_verified_at' => now(),
            'healthy_at' => now(),
            'last_health_checked_at' => now(),
            'health_status' => 'healthy',
        ])->save();
        $document = $this->postedDocument($organization, 'INV-GATE');
        config(['accounting_einvoice.network_enabled' => false]);

        $this->postJson('/admin/api/accounting-tax/minvoice/documents/'.$document->id.'/create-draft', [
            'connection_id' => $connection->id,
            'series' => '1C26TAA',
        ])->assertStatus(409);

        $this->assertDatabaseMissing('acct_einvoice_transmissions', ['document_id' => $document->id]);
        Queue::assertNothingPushed();
    }

    private function organization(): AcctOrganization
    {
        return AcctOrganization::query()->create([
            'name' => 'AIO Tax Co',
            'legal_name' => 'AIO Tax Company Limited',
            'tax_code' => '0100000000',
            'address' => 'Ha Noi',
            'default_currency' => 'VND',
            'status' => 'active',
        ]);
    }

    private function connection(
        AcctOrganization $organization,
        string $channel,
        string $environment,
        string $name = 'Primary Minvoice',
    ): AcctProviderConnection {
        return app(ProviderConnectionService::class)->save(
            $this->connectionPayload($organization, $channel, $environment, $name),
            null,
            1,
        );
    }

    private function verifiedConnection(
        AcctOrganization $organization,
        string $channel,
        string $name = 'Primary Minvoice',
    ): AcctProviderConnection {
        $connection = $this->connection($organization, $channel, 'sandbox', $name);
        $connection->forceFill([
            'sandbox_verified_at' => now(),
            'healthy_at' => now(),
            'last_health_checked_at' => now(),
            'health_status' => 'healthy',
            'readiness_state' => 'healthy',
        ])->save();

        return $connection->fresh();
    }

    private function connectionPayload(
        AcctOrganization $organization,
        string $channel,
        string $environment,
        string $name,
    ): array {
        return [
            'organization_id' => $organization->id,
            'name' => $name,
            'channel' => $channel,
            'environment' => $environment,
            'base_url' => $environment === 'production'
                ? 'https://tenant.minvoice.com.vn'
                : 'https://sandbox.minvoice.com.vn',
            'credentials' => $channel === 'inbound'
                ? ['api_token' => 'static-msmi-token', 'tax_code' => '0100000000']
                : [
                    'username' => 'provider-user',
                    'password' => 'provider-password',
                    'ma_dvcs' => 'VP',
                    'tax_code' => '0100000000',
                ],
            'settings' => $channel === 'inbound'
                ? ['msmi_prefix' => '/erp/qlhd-api']
                : ['authorization_prefix' => 'Bear', 'default_series' => '1C26TAA'],
        ];
    }

    private function postedDocument(AcctOrganization $organization, string $number = 'INV-100'): AcctDocument
    {
        $party = AcctParty::query()->create([
            'organization_id' => $organization->id,
            'type' => 'customer',
            'name' => 'Customer Co',
            'tax_code' => '0200000000',
            'email' => 'buyer@example.test',
            'address' => 'Da Nang',
        ]);
        $document = AcctDocument::query()->create([
            'organization_id' => $organization->id,
            'party_id' => $party->id,
            'direction' => 'outbound',
            'document_type' => 'tax_invoice',
            'document_no' => $number,
            'document_date' => '2026-08-17',
            'currency' => 'VND',
            'base_currency' => 'VND',
            'exchange_rate' => '1',
            'workflow_status' => 'draft',
            'subtotal' => '100000.00',
            'discount_total' => '0.00',
            'tax_total' => '10000.00',
            'grand_total' => '110000.00',
            'base_subtotal' => '100000.00',
            'base_discount_total' => '0.00',
            'base_tax_total' => '10000.00',
            'base_grand_total' => '110000.00',
            'seller_snapshot' => [
                'legal_name' => $organization->legal_name,
                'tax_code' => $organization->tax_code,
                'address' => $organization->address,
            ],
            'buyer_snapshot' => [
                'legal_name' => $party->name,
                'tax_code' => $party->tax_code,
                'address' => $party->address,
                'email' => $party->email,
            ],
            'metadata' => ['service_completed_at' => '2026-08-17T10:00:00+07:00'],
            'created_by' => 1,
            'posted_by' => 2,
            'posted_at' => now(),
        ]);
        $document->lines()->create([
            'line_type' => 'item',
            'sort_order' => 0,
            'item_kind' => 'service',
            'name' => 'Implementation service',
            'sku' => 'SVC-001',
            'unit' => 'service',
            'quantity' => '1',
            'unit_price' => '100000',
            'line_subtotal' => '100000',
            'discount_amount' => '0',
            'tax_category' => 'standard',
            'tax_rate' => '10',
            'tax_base' => '100000',
            'tax_amount' => '10000',
            'line_total' => '110000',
            'snapshot' => ['tax_category' => 'standard'],
        ]);
        $document->forceFill(['workflow_status' => 'posted'])->save();

        return $document->fresh(['party', 'lines']);
    }

    private function runTransmission(int $id): void
    {
        app()->call([new ProcessEinvoiceTransmission($id), 'handle']);
    }

    private function msmiPayload(): array
    {
        return $this->fixture('msmi-invoices-page.json');
    }

    private function fixture(string $name): array
    {
        $contents = file_get_contents(base_path('tests/Fixtures/minvoice/'.$name));

        $this->assertNotFalse($contents, "Không đọc được fixture Minvoice/mSMI: {$name}");

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}
