<?php

namespace Tests\Feature;

use App\Models\AcctDocument;
use App\Models\AcctItem;
use App\Models\AcctOrganization;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\ModuleInstallation;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\RetailPos\Services\RetailAccountingBridge;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RetailAccountingBridgeTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    private array $branch;

    private array $sku;

    private array $sale;

    private AcctOrganization $organization;

    private AcctItem $accountingItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = Admin::query()->findOrFail(1);
        $this->owner->forceFill(['must_change_password' => false])->save();
        $this->actingAs($this->owner, 'admin')->withSession(['admin_auth_version' => $this->owner->auth_version]);
        foreach (['merchandise', 'inventory', 'retail-pos', 'accounting-tax'] as $key) {
            $this->postJson('/admin/api/modules/'.$key.'/install')->assertOk();
            $this->postJson('/admin/api/modules/'.$key.'/enable')->assertOk();
        }
        $this->branch = $this->command('setup', ['name' => 'Retail Accounting'])->assertOk()->json('data');
        $this->sku = $this->command('products', ['branch_id' => $this->branch['id'], 'sku' => 'FRESH', 'name' => 'Tên hàng tại thời điểm bán', 'base_unit' => 'kg', 'quantity_scale' => 6, 'price_minor' => '10001', 'opening_quantity' => '10', 'unit_cost' => '3000'])->assertOk()->json('data');
        $shift = $this->command('shifts', ['branch_id' => $this->branch['id'], 'terminal_id' => $this->branch['terminals'][0]['id'], 'opening_minor' => '0'])->assertOk()->json('data');
        $this->sale = $this->command('sales', ['branch_id' => $this->branch['id'], 'terminal_id' => $this->branch['terminals'][0]['id'], 'shift_id' => $shift['id'], 'lines' => [['sku_id' => $this->sku['id'], 'quantity' => '0.123456']]])->assertOk()->json('data');
        $this->sale = $this->command('sales/'.$this->sale['id'].'/complete', ['expected_version' => 1, 'tenders' => [['method' => 'cash', 'amount_minor' => $this->sale['total_minor']]]])->assertOk()->json('data');
        $this->organization = AcctOrganization::query()->create(['name' => 'Pháp nhân kế toán', 'legal_name' => 'Pháp nhân kế toán', 'default_currency' => 'VND', 'status' => 'active']);
        $this->accountingItem = AcctItem::query()->create(['organization_id' => $this->organization->id, 'name' => 'Tên master kế toán', 'sku' => 'ACCT-FRESH', 'kind' => 'goods', 'unit' => 'kg', 'tax_category' => 'not_declared', 'status' => 'active']);
    }

    public function test_sale_projects_exact_source_line_total_and_original_snapshot_to_internal_draft(): void
    {
        $bridge = app(RetailAccountingBridge::class);
        $bridge->configure($this->owner, $this->mapping());
        DB::table('merch_skus')->where('public_id', $this->sku['id'])->update(['name' => 'Tên master đã đổi']);
        $result = $bridge->reconcile($this->owner, $this->branch['id']);
        $this->assertSame(1, $result['processed']);
        $document = AcctDocument::query()->with('lines')->sole();
        $this->assertSame('draft', $document->workflow_status);
        $this->assertSame('internal_invoice', $document->document_type);
        $this->assertSame('outbound', $document->direction);
        $this->assertSame($this->sale['id'], $document->source_id);
        $this->assertSame((string) $this->sale['total_minor'].'.00', $document->grand_total);
        $this->assertNull($document->posted_at);
        $this->assertSame('retail_line_net_total', $document->metadata['projection_basis']);
        $line = $document->lines->sole();
        $this->assertSame('Tên hàng tại thời điểm bán', $line->name);
        $this->assertSame('not_declared', $line->tax_category);
        $this->assertTrue($line->snapshot['client_metadata']['requires_tax_classification']);
        $this->assertSame('1.0000', $line->quantity);
        $this->assertSame('0.123456', $line->snapshot['client_metadata']['retail_source']['quantity']);
        $this->assertSame($this->accountingItem->id, $line->snapshot['client_metadata']['retail_source']['accounting_item_id']);
        $this->assertDatabaseCount('retail_integration_links', 1);
        $this->assertSame('completed', DB::table('retail_sales')->value('status'));
    }

    public function test_replaying_delivery_keeps_one_document_even_after_lost_ack(): void
    {
        $bridge = app(RetailAccountingBridge::class);
        $bridge->configure($this->owner, $this->mapping());
        $bridge->reconcile($this->owner, $this->branch['id']);
        $id = AcctDocument::query()->sole()->id;
        DB::table('retail_outbox')->where('event_type', 'sale.completed')->update(['status' => 'pending', 'processed_at' => null]);
        $this->assertSame(1, $bridge->reconcile($this->owner, $this->branch['id'])['processed']);
        $this->assertSame(0, $bridge->reconcile($this->owner, $this->branch['id'])['processed']);
        $this->assertSame($id, AcctDocument::query()->sole()->id);
        $this->assertDatabaseCount('retail_integration_links', 1);
    }

    public function test_disabled_accounting_keeps_sale_completed_and_outbox_pending(): void
    {
        $bridge = app(RetailAccountingBridge::class);
        $bridge->configure($this->owner, $this->mapping());
        ModuleInstallation::query()->where('key', 'accounting-tax')->update(['status' => 'disabled']);
        $result = $bridge->reconcile($this->owner, $this->branch['id']);
        $this->assertSame('pending', $result['status']);
        $this->assertSame(0, $result['processed']);
        $this->assertDatabaseCount('acct_documents', 0);
        $this->assertSame('pending', DB::table('retail_outbox')->where('event_type', 'sale.completed')->value('status'));
        $this->assertSame('completed', DB::table('retail_sales')->value('status'));
        $this->assertFalse($bridge->status($this->owner, $this->branch['id'])['accounting']['available']);
    }

    public function test_missing_item_mapping_stays_actionable_without_creating_a_document(): void
    {
        $bridge = app(RetailAccountingBridge::class);
        $bridge->configure($this->owner, array_replace($this->mapping(), ['sku_mappings' => []]));
        $result = $bridge->reconcile($this->owner, $this->branch['id']);
        $this->assertSame(1, $result['blocked']);
        $this->assertSame('ACCOUNTING_BLOCKED_422', $result['errors'][0]['code']);
        $this->assertDatabaseCount('acct_documents', 0);
        $this->assertSame('pending', DB::table('retail_outbox')->where('event_type', 'sale.completed')->value('status'));
    }

    public function test_grant_auth_version_change_blocks_delivery_until_reauthorized(): void
    {
        $bridge = app(RetailAccountingBridge::class);
        $bridge->configure($this->owner, $this->mapping());
        DB::table('admins')->where('id', $this->owner->id)->increment('auth_version');
        $this->owner = $this->owner->fresh();
        $this->assertSame(1, $bridge->reconcile($this->owner, $this->branch['id'])['blocked']);
        $this->assertDatabaseCount('acct_documents', 0);
        $bridge->configure($this->owner, array_replace($this->mapping(), ['expected_version' => 1]));
        $this->assertSame(1, $bridge->reconcile($this->owner, $this->branch['id'])['processed']);
    }

    public function test_mapping_rejects_item_from_another_organization(): void
    {
        $other = AcctOrganization::query()->create(['name' => 'Khác', 'default_currency' => 'VND', 'status' => 'active']);
        $item = AcctItem::query()->create(['organization_id' => $other->id, 'name' => 'Khác', 'kind' => 'goods', 'status' => 'active']);
        $this->expectException(HttpException::class);
        app(RetailAccountingBridge::class)->configure($this->owner, array_replace($this->mapping(), ['sku_mappings' => [['sku_id' => $this->sku['id'], 'accounting_item_id' => $item->id]]]));
    }

    public function test_retail_integration_permission_does_not_grant_native_accounting_rights(): void
    {
        $admin = Admin::factory()->create(['must_change_password' => false, 'status' => 'active']);
        $role = Role::query()->create(['key' => 'retail.integration-local', 'name' => 'Retail only', 'status' => 'active']);
        $role->permissions()->sync(Permission::query()->where('key', 'retail.integration.manage')->pluck('id')->all());
        AdminRoleAssignment::query()->create(['admin_id' => $admin->id, 'role_id' => $role->id, 'scope_type' => 'global']);
        DB::table('retail_memberships')->insert(['admin_id' => $admin->id, 'chain_id' => DB::table('retail_chains')->where('public_id', $this->branch['chain_id'])->value('id'), 'branch_id' => $this->branch['database_id'], 'preset' => 'manager', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $admin = $admin->fresh();
        $bridge = app(RetailAccountingBridge::class);
        $this->assertSame([], $bridge->status($admin, $this->branch['id'])['accounting']['organizations']);
        try {
            $bridge->configure($admin, $this->mapping());
            $this->fail('Native Accounting permissions required.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertDatabaseCount('retail_accounting_mappings', 0);
    }

    public function test_http_mapping_and_reconcile_replay_return_one_internal_document(): void
    {
        $this->withHeader('Idempotency-Key', 'accounting-mapping-key-01')->putJson('/admin/api/retail/integrations/mapping', $this->mapping())->assertOk()->assertJsonPath('data.accounting.enabled', true);
        $body = ['branch_id' => $this->branch['id']];
        $first = $this->withHeader('Idempotency-Key', 'accounting-reconcile-key-01')->postJson('/admin/api/retail/integrations/reconcile', $body)->assertOk()->json('data');
        $again = $this->withHeader('Idempotency-Key', 'accounting-reconcile-key-01')->postJson('/admin/api/retail/integrations/reconcile', $body)->assertOk()->json('data');
        $this->assertSame($first, $again);
        $this->assertSame(1, $first['processed']);
        $this->assertDatabaseCount('acct_documents', 1);
        $this->getJson('/admin/api/retail/integrations?branch_id='.$this->branch['id'])->assertOk()->assertJsonPath('data.accounting.pending_sales', 0);
    }

    private function mapping(): array
    {
        return ['branch_id' => $this->branch['id'], 'organization_id' => $this->organization->id, 'enabled' => true, 'expected_version' => 0, 'sku_mappings' => [['sku_id' => $this->sku['id'], 'accounting_item_id' => $this->accountingItem->id]]];
    }

    private function command(string $path, array $body): TestResponse
    {
        return $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/admin/api/retail/'.$path, $body);
    }
}
