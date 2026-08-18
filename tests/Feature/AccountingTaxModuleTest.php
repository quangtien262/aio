<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Core\Modules\ModuleRegistry;
use App\Models\AcctDocument;
use App\Models\AcctItem;
use App\Models\AcctOrganization;
use App\Models\AcctOrganizationWebsite;
use App\Models\AcctParty;
use App\Models\Admin;
use App\Models\CatalogProduct;
use App\Models\ModuleInstallation;
use App\Models\Order;
use App\Models\Permission;
use App\Support\AccountingTax\AccountingDocumentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountingTaxModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_hardening_migration_resumes_after_mysql_style_partial_failure(): void
    {
        $defaultConnection = DB::getDefaultConnection();
        config()->set('database.connections.accounting_migration_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('accounting_migration_test');
        DB::setDefaultConnection('accounting_migration_test');

        try {
            $baseMigration = require base_path(
                'modules/AccountingTax/database/migrations/2026_08_16_000001_create_accounting_tax_tables.php',
            );
            $baseMigration->up();

            // MySQL commits DDL implicitly. Reproduce the observed state where the
            // first ALTER succeeded but dropping the FK-supporting unique index did not.
            Schema::table('acct_organizations', function (Blueprint $table): void {
                $table->string('default_slot', 20)->nullable();
                $table->unique('default_slot', 'acct_organizations_default_slot_unique');
            });

            $hardeningMigration = require base_path(
                'modules/AccountingTax/database/migrations/2026_08_17_000001_harden_accounting_tax_domain.php',
            );
            $hardeningMigration->up();
            $hardeningMigration->up();

            $websiteIndexes = collect(Schema::getIndexes('acct_organization_websites'))->keyBy('name');

            $this->assertFalse($websiteIndexes->has('acct_org_websites_org_website_unique'));
            $this->assertSame(
                ['organization_id', 'is_primary'],
                $websiteIndexes->get('acct_org_websites_org_primary_idx')['columns'] ?? null,
            );
            $this->assertTrue($websiteIndexes->get('acct_org_websites_website_unique')['unique'] ?? false);
            $this->assertTrue(Schema::hasColumn('acct_documents', 'snapshot_hash'));
            $this->assertTrue(Schema::hasTable('acct_document_payments'));
        } finally {
            DB::setDefaultConnection($defaultConnection);
            DB::disconnect('accounting_migration_test');
        }
    }

    public function test_accounting_tax_module_installs_schema_permissions_and_capabilities(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = app(ModuleManager::class);
        $manager->install('accounting-tax');
        $manager->enable('accounting-tax');

        $module = app(ModuleRegistry::class)->find('accounting-tax');

        $this->assertSame('enabled', $module['status']);
        $this->assertArrayHasKey('accounting.documents.manage.v1', $module['provides']);
        $this->assertDatabaseHas('permissions', [
            'key' => 'accounting.document.post',
            'module_key' => 'accounting-tax',
            'is_active' => true,
        ]);
        $this->assertTrue(\Schema::hasTable('acct_documents'));
        $this->assertTrue(\Schema::hasTable('acct_item_sources'));
    }

    public function test_minvoice_connector_requires_accounting_tax_lifecycle_first(): void
    {
        $this->seed(DatabaseSeeder::class);
        $manager = app(ModuleManager::class);

        try {
            $manager->install('minvoice-connector');
            $this->fail('Minvoice connector should require AccountingTax first.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Kế toán', $exception->errors()['module'][0]);
        }

        $manager->install('accounting-tax');
        $manager->enable('accounting-tax');
        $manager->install('minvoice-connector');
        $manager->enable('minvoice-connector');

        $this->assertSame('enabled', ModuleInstallation::query()->where('key', 'minvoice-connector')->value('status'));
        $this->assertDatabaseHas('permissions', ['key' => 'minvoice.inbound.sync', 'module_key' => 'minvoice-connector']);
    }

    public function test_sync_sources_respects_enabled_module_capabilities_not_table_presence(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = app(ModuleManager::class);
        $manager->install('accounting-tax');
        $manager->enable('accounting-tax');

        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $this->actingAs($owner, 'admin');

        $organization = AcctOrganization::query()->create([
            'name' => 'AIO Trading',
            'is_default' => true,
            'status' => 'active',
        ]);
        AcctOrganizationWebsite::query()->create([
            'organization_id' => $organization->id,
            'website_key' => 'website-main',
            'is_primary' => true,
        ]);

        $this->postJson('/admin/api/accounting-tax/items/sync-sources')
            ->assertOk()
            ->assertJsonPath('data.synced.inventory_enabled', false)
            ->assertJsonPath('data.synced.catalog', 0)
            ->assertJsonPath('data.synced.cms_services', 3);

        $this->assertSame(3, AcctItem::query()->where('kind', 'service')->count());
        $this->assertSame(0, AcctItem::query()->where('kind', 'goods')->count());

        $manager->install('catalog');
        $manager->enable('catalog');
        $catalogProductCount = CatalogProduct::query()->count();

        $this->postJson('/admin/api/accounting-tax/items/sync-sources')
            ->assertOk()
            ->assertJsonPath('data.synced.inventory_enabled', false)
            ->assertJsonPath('data.synced.catalog', $catalogProductCount);

        $this->assertSame($catalogProductCount, AcctItem::query()->where('kind', 'goods')->count());
        $this->assertDatabaseMissing('module_installations', ['key' => 'inventory', 'status' => 'enabled']);
    }

    public function test_admin_can_create_approve_post_internal_invoice_with_line_snapshot(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = app(ModuleManager::class);
        $manager->install('accounting-tax');
        $manager->enable('accounting-tax');

        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);
        $this->actingAs($owner, 'admin');

        $organization = AcctOrganization::query()->create([
            'name' => 'AIO Trading',
            'tax_code' => '0100000000',
            'is_default' => true,
            'status' => 'active',
        ]);
        $party = AcctParty::query()->create([
            'organization_id' => $organization->id,
            'type' => 'customer',
            'name' => 'AIO Customer',
        ]);
        $permissionCount = Permission::query()->where('module_key', 'accounting-tax')->count();
        $this->assertGreaterThan(5, $permissionCount);

        $response = $this->postJson('/admin/api/accounting-tax/documents', [
            'organization_id' => $organization->id,
            'direction' => 'outbound',
            'party_id' => $party->id,
            'document_type' => 'internal_invoice',
            'document_no' => 'INT-0001',
            'document_date' => '2026-08-16',
            'lines' => [[
                'line_type' => 'item',
                'item_kind' => 'service',
                'name' => 'Implementation service',
                'unit' => 'service',
                'quantity' => 2,
                'unit_price' => 100000,
                'tax_rate' => 10,
                'snapshot' => ['source' => 'manual'],
            ]],
        ])->assertCreated();

        $documentId = $response->json('data.id');

        $this->assertEquals(200000.0, $response->json('data.subtotal'));
        $this->assertEquals(20000.0, $response->json('data.tax_total'));
        $this->assertEquals(220000.0, $response->json('data.grand_total'));
        $this->assertSame('manual', $response->json('data.lines.0.snapshot.client_metadata.source'));

        $checker = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        app(AccountingDocumentService::class)->approve(
            AcctDocument::query()->findOrFail($documentId),
            $checker->id,
        );

        $this->postJson("/admin/api/accounting-tax/documents/{$documentId}/post")
            ->assertOk()
            ->assertJsonPath('data.workflow_status', 'posted');

        $this->getJson('/admin/api/accounting-tax/reports/summary?organization_id='.$organization->id)
            ->assertOk()
            ->assertJsonPath('data.summary.outbound_count', 1)
            ->assertJsonPath('data.summary.outbound_tax', 20000);

        $this->assertSame('posted', AcctDocument::query()->findOrFail($documentId)->workflow_status);
    }

    public function test_order_conversion_creates_an_idempotent_review_draft_without_optional_item_mapping(): void
    {
        $this->seed(DatabaseSeeder::class);
        $manager = app(ModuleManager::class);
        $manager->install('accounting-tax');
        $manager->enable('accounting-tax');
        $this->actingAs(Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID), 'admin');

        $organization = AcctOrganization::query()->create([
            'name' => 'Order Legal Entity',
            'legal_name' => 'Order Legal Entity',
            'tax_code' => '0101234000',
            'is_default' => true,
            'status' => 'active',
        ]);
        AcctOrganizationWebsite::query()->create([
            'organization_id' => $organization->id,
            'website_key' => 'website-main',
            'is_primary' => true,
        ]);
        $order = Order::query()->create([
            'order_code' => 'ORDER-TO-ACCOUNTING-1',
            'website_key' => 'website-main',
            'status' => 'placed',
            'customer_name' => 'Khách hàng từ đơn',
            'customer_phone' => '0900000000',
            'customer_email' => 'order@example.test',
            'delivery_address' => 'Hà Nội',
            'payment_method' => 'cod',
            'payment_label' => 'Thanh toán khi nhận hàng',
            'subtotal' => '125000.00',
            'item_count' => 1,
            'placed_at' => now(),
        ]);
        $order->items()->create([
            'catalog_product_id' => null,
            'product_name' => 'Mặt hàng chưa phân loại thuế',
            'product_slug' => 'mat-hang-chua-phan-loai',
            'sku' => 'ORDER-LINE-1',
            'unit_price' => '125000.00',
            'quantity' => 1,
            'line_total' => '125000.00',
        ]);

        $first = $this->postJson("/admin/api/accounting-tax/orders/{$order->id}/draft", [
            'organization_id' => $organization->id,
        ])->assertCreated()
            ->assertJsonPath('data.workflow_status', 'draft')
            ->assertJsonPath('data.tax_eligibility', 'not_assessed')
            ->assertJsonPath('data.lines.0.tax_category', 'not_declared')
            ->assertJsonPath('data.metadata.requires_tax_classification', true);

        $second = $this->postJson("/admin/api/accounting-tax/orders/{$order->id}/draft", [
            'organization_id' => $organization->id,
        ])->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('acct_documents', 1);

        $document = AcctDocument::query()->findOrFail($first->json('data.id'));
        $checker = Admin::factory()->create(['status' => 'active', 'is_active' => true]);
        try {
            app(AccountingDocumentService::class)->approve($document, $checker->id, 1);
            $this->fail('Order draft must require an explicit tax classification review.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines', $exception->errors());
        }

        $document = app(AccountingDocumentService::class)->updateDraft($document, [
            'document_date' => '2026-08-17',
            'lines' => [[
                'line_type' => 'item',
                'item_kind' => 'goods',
                'name' => 'Mặt hàng đã phân loại thuế',
                'sku' => 'ORDER-LINE-1',
                'unit' => 'pcs',
                'quantity' => '1.0000',
                'unit_price' => '125000.00',
                'tax_category' => 'standard',
                'tax_rate' => '10.00',
            ]],
        ], Admin::SYSTEM_OWNER_ID, 1);
        $document = app(AccountingDocumentService::class)->approve($document, $checker->id, 2);
        $this->assertSame('approved', $document->workflow_status);
    }
}
