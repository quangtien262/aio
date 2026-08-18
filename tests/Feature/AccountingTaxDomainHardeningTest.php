<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Models\AcctDocumentEvent;
use App\Models\AcctItem;
use App\Models\AcctItemSource;
use App\Models\AcctOrganization;
use App\Models\AcctOrganizationWebsite;
use App\Models\AcctParty;
use App\Models\AcctTaxPeriod;
use App\Models\Admin;
use App\Models\CatalogProduct;
use App\Models\CmsService;
use App\Support\AccountingTax\AccountingDocumentService;
use App\Support\AccountingTax\AccountingItemSyncService;
use App\Support\AccountingTax\AccountingTaxReportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountingTaxDomainHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_does_not_create_an_organization_and_website_mapping_is_exclusive(): void
    {
        $owner = $this->bootAccounting();

        $this->getJson('/admin/api/accounting-tax/items')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('organization_id');
        $this->assertDatabaseCount('acct_organizations', 0);

        $first = $this->postJson('/admin/api/accounting-tax/organizations', [
            'name' => 'Legal Entity A',
            'tax_code' => '0101111111',
            'website_keys' => ['website-main'],
        ])->assertCreated();

        $this->assertTrue($first->json('data.is_default'));
        $this->assertSame(['website-main'], $first->json('data.website_keys'));

        $this->postJson('/admin/api/accounting-tax/organizations', [
            'name' => 'Legal Entity B',
            'tax_code' => '0102222222',
            'website_keys' => ['website-main'],
        ])->assertUnprocessable()->assertJsonValidationErrors('website_keys');

        $this->assertDatabaseCount('acct_organizations', 1);
        $this->assertSame($owner->id, Admin::SYSTEM_OWNER_ID);
    }

    public function test_source_sync_is_filtered_by_organization_websites_and_mapping_identity_is_org_scoped(): void
    {
        $this->bootAccounting();
        $manager = app(ModuleManager::class);
        $manager->install('catalog');
        $manager->enable('catalog');

        [$organizationA, $organizationB] = $this->organizationsWithWebsites();

        $secondaryProduct = CatalogProduct::query()->withoutGlobalScope('current_website')->create([
            'name' => 'Secondary Product',
            'slug' => 'secondary-product',
            'sku' => 'SECONDARY-001',
            'price' => '123.45',
            'stock' => 5,
            'website_key' => 'website-secondary',
            'is_active' => true,
        ]);
        CmsService::query()->withoutGlobalScope('current_website')->create([
            'title' => 'Secondary Service',
            'slug' => 'secondary-service',
            'status' => 'published',
            'website_key' => 'website-secondary',
        ]);

        $sync = app(AccountingItemSyncService::class);
        $sync->syncEnabledSources($organizationA->id);
        $sync->syncEnabledSources($organizationB->id);

        $this->assertFalse(AcctItemSource::query()
            ->where('organization_id', $organizationA->id)
            ->where('source_module', 'catalog')
            ->where('source_id', (string) $secondaryProduct->id)
            ->exists());
        $this->assertTrue(AcctItemSource::query()
            ->where('organization_id', $organizationB->id)
            ->where('source_module', 'catalog')
            ->where('source_id', (string) $secondaryProduct->id)
            ->exists());

        $itemA = AcctItem::query()->create([
            'organization_id' => $organizationA->id,
            'kind' => 'goods',
            'name' => 'Manual A',
            'unit' => 'pcs',
        ]);
        AcctItemSource::query()->create([
            'organization_id' => $organizationA->id,
            'accounting_item_id' => $itemA->id,
            'source_module' => 'legacy',
            'source_type' => 'legacy.item',
            'source_id' => 'same-id',
            'source_hash' => str_repeat('a', 64),
        ]);
        $itemB = AcctItem::query()->create([
            'organization_id' => $organizationB->id,
            'kind' => 'goods',
            'name' => 'Manual B',
            'unit' => 'pcs',
        ]);
        AcctItemSource::query()->create([
            'organization_id' => $organizationB->id,
            'accounting_item_id' => $itemB->id,
            'source_module' => 'legacy',
            'source_type' => 'legacy.item',
            'source_id' => 'same-id',
            'source_hash' => str_repeat('b', 64),
        ]);

        $this->assertSame(2, AcctItemSource::query()->where('source_id', 'same-id')->count());
    }

    public function test_document_uses_same_org_master_data_server_snapshots_and_decimal_rounding(): void
    {
        $maker = $this->bootAccounting();
        [$organizationA, $organizationB] = $this->organizationsWithWebsites();
        $partyA = $this->party($organizationA, 'Customer A');
        $partyB = $this->party($organizationB, 'Customer B');
        $itemA = $this->item($organizationA, 'Canonical Service');
        $itemB = $this->item($organizationB, 'Foreign Service');
        $service = app(AccountingDocumentService::class);
        $payload = $this->documentPayload($organizationA, $partyA, $itemA);

        try {
            $service->create([...$payload, 'party_id' => $partyB->id], $maker->id);
            $this->fail('Cross-organization party must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('party_id', $exception->errors());
        }

        try {
            $service->create([
                ...$payload,
                'idempotency_key' => 'cross-item',
                'lines' => [[...$payload['lines'][0], 'accounting_item_id' => $itemB->id]],
            ], $maker->id);
            $this->fail('Cross-organization item must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines.0.accounting_item_id', $exception->errors());
        }

        $document = $service->create($payload, $maker->id);
        $again = $service->create($payload, $maker->id);

        $this->assertSame($document->id, $again->id);
        $this->assertSame('0.30', $document->subtotal);
        $this->assertSame('0.03', $document->tax_total);
        $this->assertSame('0.33', $document->grand_total);
        $this->assertSame('Legal Entity A', $document->seller_snapshot['legal_name']);
        $this->assertSame('Customer A', $document->buyer_snapshot['legal_name']);
        $this->assertSame('Canonical Service', $document->lines->first()->name);
        $this->assertSame('Canonical Service', $document->lines->first()->snapshot['name']);
        $this->assertSame('untrusted client label', $document->lines->first()->snapshot['client_metadata']['name']);
        $this->assertNotEmpty($document->snapshot_hash);

        $foreignCurrency = $service->updateDraft($document, [
            'currency' => 'USD',
            'exchange_rate' => '25000.00000000',
        ], $maker->id, 1);
        $this->assertSame('USD', $foreignCurrency->currency);
        $this->assertSame('25000.00000000', $foreignCurrency->exchange_rate);
        $this->assertSame('8250.00', $foreignCurrency->base_grand_total);
    }

    public function test_state_machine_is_maker_checker_versioned_idempotent_and_audited(): void
    {
        $maker = $this->bootAccounting();
        $checker = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        [$organization] = $this->organizationsWithWebsites();
        $party = $this->party($organization, 'Customer');
        $item = $this->item($organization, 'Service');
        $service = app(AccountingDocumentService::class);
        $document = $service->create($this->documentPayload($organization, $party, $item), $maker->id);

        $autoNumbered = $service->create([
            ...$this->documentPayload($organization, $party, $item),
            'document_no' => null,
            'idempotency_key' => 'auto-numbered-document',
        ], $maker->id);
        $autoNumbered = $service->approve($autoNumbered, $checker->id, 1);
        $this->assertMatchesRegularExpression('/^NB-2026-\d{6}$/', $autoNumbered->document_no);

        $this->expectValidationError(fn () => $service->approve($document, $maker->id, 1), 'document');
        $this->expectValidationError(fn () => $service->post($document, $maker->id, 1), 'document');

        $approved = $service->approve($document, $checker->id, 1, 'approve-once');
        $approvedAgain = $service->approve($document, $checker->id, 1, 'approve-once');
        $this->assertSame(2, $approved->version);
        $this->assertSame(2, $approvedAgain->version);

        $this->expectValidationError(fn () => $service->post($approved, $maker->id, 1), 'version');
        $posted = $service->post($approved, $maker->id, 2, 'post-once');
        $this->assertSame('posted', $posted->workflow_status);
        $this->assertSame(3, $posted->version);

        $payment = $service->recordPayment($posted, [
            'amount' => '0.10',
            'idempotency_key' => 'payment-once',
        ], $maker->id, 3);
        $this->assertSame('0.10', $payment->amount);
        $this->assertSame('partial', $posted->fresh()->payment_status);

        $this->expectLogicException(fn () => $payment->update(['amount' => '0.20']));
        $this->expectLogicException(fn () => $posted->lines()->firstOrFail()->update(['name' => 'tampered']));
        $this->expectLogicException(function () use ($posted): void {
            $posted->forceFill(['grand_total' => '999.00'])->save();
        });

        $credit = $service->create([
            ...$this->documentPayload($organization, $party, $item),
            'document_type' => 'credit_note',
            'document_no' => 'CREDIT-0001',
            'original_document_id' => $posted->id,
            'correction_type' => 'adjustment',
            'idempotency_key' => 'credit-note-once',
        ], $maker->id);
        $this->assertSame(-1, $credit->effect_sign);

        $this->expectValidationError(fn () => $service->create([
            ...$this->documentPayload($organization, $party, $item),
            'document_no' => 'UNTRUSTED-SIGN',
            'idempotency_key' => 'untrusted-sign',
            'effect_sign' => -1,
        ], $maker->id), 'effect_sign');

        $reversal = $service->createReversal($posted->fresh(), $maker->id, 'Correction', 'REV-0001', 4, 'reverse-once');
        $this->assertSame(-1, $reversal->effect_sign);
        $this->assertSame('pending', $posted->fresh()->reversal_status);
        $reversal = $service->approve($reversal, $checker->id, 1, 'approve-reversal');
        $service->post($reversal, $maker->id, 2, 'post-reversal');
        $this->assertSame('reversed', $posted->fresh()->reversal_status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'accounting.document.posted',
            'target_id' => (string) $document->id,
        ]);

        $event = AcctDocumentEvent::query()->where('document_id', $document->id)->firstOrFail();
        $this->expectException(\LogicException::class);
        $event->update(['event_type' => 'tampered']);
    }

    public function test_posted_documents_cannot_be_deleted_directly_or_by_database_cascade(): void
    {
        $maker = $this->bootAccounting();
        $checker = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        [$organization] = $this->organizationsWithWebsites();
        $party = $this->party($organization, 'Customer');
        $item = $this->item($organization, 'Service');
        $service = app(AccountingDocumentService::class);
        $document = $service->create($this->documentPayload($organization, $party, $item), $maker->id);
        $document = $service->approve($document, $checker->id, 1);
        $document = $service->post($document, $maker->id, 2);

        try {
            $document->delete();
            $this->fail('Posted document model deletion must be rejected.');
        } catch (\LogicException) {
            $this->assertDatabaseHas('acct_documents', ['id' => $document->id]);
        }

        $this->expectException(QueryException::class);
        AcctOrganization::query()->whereKey($organization->id)->delete();
    }

    public function test_locked_period_blocks_mutations_and_tax_eligibility_requires_server_assessment(): void
    {
        $maker = $this->bootAccounting();
        $checker = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        [$organization] = $this->organizationsWithWebsites();
        $party = $this->party($organization, 'Supplier');
        $item = $this->item($organization, 'Input service');
        $service = app(AccountingDocumentService::class);
        $payload = [
            ...$this->documentPayload($organization, $party, $item),
            'direction' => 'inbound',
            'document_type' => 'tax_invoice',
            'document_no' => 'INPUT-0001',
        ];

        $this->expectValidationError(
            fn () => $service->create([...$payload, 'tax_eligibility' => 'eligible'], $maker->id),
            'tax_eligibility',
        );

        $document = $service->create($payload, $maker->id);
        $document = $service->approve($document, $checker->id, 1);
        $document = $service->post($document, $maker->id, 2);
        $document->forceFill(['legal_status' => 'accepted'])->saveTrusted();
        $eligible = $service->assessTaxEligibility(
            $document->fresh(),
            'eligible',
            'Provider XML and seller tax code validated',
            $checker->id,
            3,
        );
        $this->assertSame('eligible', $eligible->tax_eligibility);

        AcctTaxPeriod::query()->create([
            'organization_id' => $organization->id,
            'code' => '2026-08',
            'period_type' => 'monthly',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'base_currency' => 'VND',
            'status' => 'locked',
            'version' => 1,
        ]);
        AcctTaxPeriod::query()->create([
            'organization_id' => $organization->id,
            'code' => '2026-09',
            'period_type' => 'monthly',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'base_currency' => 'VND',
            'status' => 'open',
            'version' => 1,
        ]);

        $this->expectValidationError(
            fn () => $service->recordPayment($eligible, ['amount' => '0.10'], $maker->id, 4),
            'document_date',
        );
        $this->expectValidationError(
            fn () => $service->createReversal($eligible, $maker->id, 'Locked period correction', 'REV-LOCKED', 4),
            'document_date',
        );

        $reversal = $service->createReversal(
            $eligible,
            $maker->id,
            'Correction in next open period',
            'REV-OPEN',
            4,
            'reverse-open-period',
            '2026-09-01',
        );
        $this->assertSame('2026-09-01', $reversal->document_date->toDateString());
    }

    public function test_tax_report_uses_legal_output_assessed_input_and_blocks_incomplete_period_lock(): void
    {
        $maker = $this->bootAccounting();
        $checker = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        [$organization] = $this->organizationsWithWebsites();
        $party = $this->party($organization, 'Tax counterparty');
        $item = $this->item($organization, 'Tax service');
        $documents = app(AccountingDocumentService::class);

        $post = function (array $overrides) use ($documents, $organization, $party, $item, $maker, $checker) {
            $document = $documents->create([
                ...$this->documentPayload($organization, $party, $item),
                ...$overrides,
            ], $maker->id);
            $document = $documents->approve($document, $checker->id, 1);

            return $documents->post($document, $maker->id, 2);
        };

        $output = $post([
            'document_type' => 'tax_invoice',
            'document_no' => 'OUT-TAX-1',
            'idempotency_key' => 'out-tax-1',
        ]);
        $output->forceFill(['legal_status' => 'accepted'])->saveTrusted();

        $credit = $post([
            'document_type' => 'credit_note',
            'document_no' => 'OUT-CREDIT-1',
            'idempotency_key' => 'out-credit-1',
            'original_document_id' => $output->id,
            'correction_type' => 'adjustment',
        ]);
        $credit->forceFill(['legal_status' => 'accepted'])->saveTrusted();

        $input = $post([
            'direction' => 'inbound',
            'document_type' => 'tax_invoice',
            'document_no' => 'IN-TAX-1',
            'idempotency_key' => 'in-tax-1',
        ]);
        $input->forceFill(['legal_status' => 'accepted'])->saveTrusted();
        $documents->assessTaxEligibility($input->fresh(), 'eligible', 'XML hợp lệ', $checker->id, 3);

        $post([
            'document_type' => 'internal_invoice',
            'document_no' => 'INTERNAL-EXCLUDED',
            'idempotency_key' => 'internal-excluded',
        ])->forceFill(['legal_status' => 'accepted'])->saveTrusted();

        $pendingOutput = $post([
            'document_type' => 'tax_invoice',
            'document_no' => 'OUT-PENDING',
            'idempotency_key' => 'out-pending',
        ]);
        $unassessedInput = $post([
            'direction' => 'inbound',
            'document_type' => 'tax_invoice',
            'document_no' => 'IN-UNASSESSED',
            'idempotency_key' => 'in-unassessed',
        ]);

        $period = AcctTaxPeriod::query()->create([
            'organization_id' => $organization->id,
            'code' => '2026-08',
            'period_type' => 'monthly',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'base_currency' => 'VND',
            'status' => 'review',
            'version' => 1,
            'created_by' => $checker->id,
        ]);

        $this->postJson("/admin/api/accounting-tax/tax-periods/{$period->id}/transition", [
            'action' => 'lock',
        ])->assertUnprocessable()->assertJsonValidationErrors('period');

        $pendingOutput->forceFill(['legal_status' => 'accepted'])->saveTrusted();
        $documents->assessTaxEligibility(
            $unassessedInput->fresh(),
            'ineligible',
            'Thiếu bằng chứng khấu trừ',
            $checker->id,
            3,
        );

        $report = app(AccountingTaxReportService::class)->build(
            $organization->id,
            now()->setDate(2026, 8, 1),
            now()->setDate(2026, 8, 31),
            'tax',
        );
        $this->assertSame(4, $report['document_count']);
        $this->assertSame('0.03', $report['summary']['outbound_tax_exact']);
        $this->assertSame('0.03', $report['summary']['inbound_tax_exact']);
        $this->assertSame('0.00', $report['summary']['vat_payable_estimate_exact']);

        $this->postJson("/admin/api/accounting-tax/tax-periods/{$period->id}/transition", [
            'action' => 'lock',
        ])->assertOk()
            ->assertJsonPath('data.status', 'locked')
            ->assertJsonPath('data.report_snapshot.filing_ready', true)
            ->assertJsonPath('data.report_snapshot.document_count', 4);
    }

    private function bootAccounting(): Admin
    {
        $this->seed(DatabaseSeeder::class);
        $manager = app(ModuleManager::class);
        $manager->install('accounting-tax');
        $manager->enable('accounting-tax');
        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $this->actingAs($owner, 'admin');

        return $owner;
    }

    /** @return array{AcctOrganization, AcctOrganization} */
    private function organizationsWithWebsites(): array
    {
        $organizationA = AcctOrganization::query()->create([
            'name' => 'Legal Entity A',
            'legal_name' => 'Legal Entity A',
            'tax_code' => '0101111111',
            'default_currency' => 'VND',
            'is_default' => true,
            'status' => 'active',
        ]);
        $organizationB = AcctOrganization::query()->create([
            'name' => 'Legal Entity B',
            'legal_name' => 'Legal Entity B',
            'tax_code' => '0102222222',
            'default_currency' => 'VND',
            'status' => 'active',
        ]);
        AcctOrganizationWebsite::query()->create([
            'organization_id' => $organizationA->id,
            'website_key' => 'website-main',
            'is_primary' => true,
        ]);
        AcctOrganizationWebsite::query()->create([
            'organization_id' => $organizationB->id,
            'website_key' => 'website-secondary',
            'is_primary' => true,
        ]);

        return [$organizationA, $organizationB];
    }

    private function party(AcctOrganization $organization, string $name): AcctParty
    {
        return AcctParty::query()->create([
            'organization_id' => $organization->id,
            'type' => 'customer',
            'name' => $name,
            'tax_code' => '0312345678',
            'address' => 'Ho Chi Minh City',
        ]);
    }

    private function item(AcctOrganization $organization, string $name): AcctItem
    {
        return AcctItem::query()->create([
            'organization_id' => $organization->id,
            'kind' => 'service',
            'name' => $name,
            'sku' => 'SVC-'.$organization->id,
            'unit' => 'service',
            'default_price' => '0.10',
            'tax_category' => 'standard',
            'tax_rate' => '10.00',
            'status' => 'active',
        ]);
    }

    /** @return array<string,mixed> */
    private function documentPayload(AcctOrganization $organization, AcctParty $party, AcctItem $item): array
    {
        return [
            'organization_id' => $organization->id,
            'party_id' => $party->id,
            'direction' => 'outbound',
            'document_type' => 'internal_invoice',
            'document_no' => 'INT-'.$organization->id.'-0001',
            'document_date' => '2026-08-17',
            'currency' => 'VND',
            'website_key' => $organization->websites()->value('website_key'),
            'idempotency_key' => 'create-'.$organization->id,
            'lines' => [[
                'accounting_item_id' => $item->id,
                'name' => 'Client should not override this',
                'quantity' => '3.0000',
                'unit_price' => '0.10',
                'snapshot' => ['name' => 'untrusted client label'],
            ]],
        ];
    }

    private function expectValidationError(callable $operation, string $key): void
    {
        try {
            $operation();
            $this->fail("Expected validation error for {$key}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors());
        }
    }

    private function expectLogicException(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected immutable accounting record guard.');
        } catch (\LogicException) {
            $this->assertTrue(true);
        }
    }
}
