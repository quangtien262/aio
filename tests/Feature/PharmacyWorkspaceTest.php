<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Pharmacy\Services\Dispensing\DispensingService;
use Modules\Pharmacy\Services\Documents\PharmacyDocumentService;
use Modules\Pharmacy\Services\PharmacyInventoryService;
use Modules\Pharmacy\Services\PharmacyService;
use Modules\Pharmacy\Services\Read\PharmacyReadService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PharmacyWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    private array $pharmacy;

    private array $chain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = Admin::query()->findOrFail(1);
        $this->owner->update(['must_change_password' => false]);
        foreach (['merchandise', 'inventory', 'pharmacy'] as $module) {
            app(ModuleManager::class)->install($module);
            app(ModuleManager::class)->enable($module);
        }
        $setup = $this->command('setup', ['chain_name' => 'Workspace synthetic chain', 'pharmacy_name' => 'Workspace synthetic site', 'code' => 'WORKSPACE']);
        $this->pharmacy = $setup['pharmacy'];
        $this->chain = $setup['chain'];
        $this->command('credential.save', ['pharmacy_id' => $this->pharmacy['id'], 'admin_id' => $this->owner->id, 'license_number' => 'SYNTHETIC', 'abilities' => ['review', 'dispense', 'handover'], 'valid_from' => now()->subDay()->toDateTimeString(), 'valid_until' => now()->addYear()->toDateTimeString(), 'reason' => 'Synthetic fixture']);
    }

    public function test_http_bootstrap_command_read_headers_and_replay_status(): void
    {
        $this->actingAs($this->owner, 'admin');
        $this->getJson('/admin/api/pharmacy/bootstrap')->assertOk()->assertJsonPath('data.current_pharmacy_id', $this->pharmacy['id'])->assertJsonPath('data.capabilities.controlled', false)->assertHeader('Cache-Control', 'no-store, private');
        $key = (string) Str::uuid();
        $payload = ['action' => 'patient.save', 'pharmacy_id' => $this->pharmacy['id'], 'name' => 'Synthetic HTTP patient', 'phone' => '0912345678', 'processing_basis' => 'consent', 'processing_evidence' => 'Synthetic evidence'];
        $first = $this->postJson('/admin/api/pharmacy/commands', $payload, ['Idempotency-Key' => $key])->assertOk();
        $this->postJson('/admin/api/pharmacy/commands', $payload, ['Idempotency-Key' => $key])->assertOk()->assertJsonPath('data.replayed', true);
        $this->getJson('/admin/api/pharmacy/commands/'.$key)->assertOk();
        $this->getJson('/admin/api/pharmacy/patients/'.$first->json('data.id').'?pharmacy_id='.$this->pharmacy['id'])->assertOk()->assertJsonPath('data.name', 'Synthetic HTTP patient');
        $this->postJson('/admin/api/pharmacy/commands', ['action' => 'patient.lookup', 'pharmacy_id' => $this->pharmacy['id'], 'phone' => '+84912345678'], ['Idempotency-Key' => (string) Str::uuid()])->assertOk()->assertJsonPath('data.records.0.id', $first->json('data.id'))->assertJsonPath('data.records.0.phone_masked', '+84••••••678');
        $this->postJson('/admin/api/pharmacy/commands', $payload)->assertStatus(422);
        $this->assertDatabaseCount('ph_patients', 1);
    }

    public function test_read_sections_use_real_tables_and_safe_capability_flags(): void
    {
        $reader = app(PharmacyReadService::class);
        foreach (['patients', 'prescriptions', 'dispensings', 'sales', 'shifts', 'products', 'prices', 'inventory', 'lots', 'receipts', 'transfers', 'stocktakes', 'purchasing', 'suppliers', 'quality', 'reports', 'operations', 'integrations', 'settings', 'pharmacies', 'counters'] as $kind) {
            $this->assertIsArray($reader->read($this->owner, $kind, ['pharmacy_id' => $this->pharmacy['id']]), $kind);
        }
    }

    public function test_receipt_quarantine_inspection_hold_and_release_change_inventory_eligibility(): void
    {
        $product = $this->product();
        $receipt = $this->command('receipt.create', ['pharmacy_id' => $this->pharmacy['id'], 'product_id' => $product['id'], 'quantity' => '10', 'unit_cost' => '250', 'batch_code' => 'LOT-WORKSPACE', 'expires_at' => now()->addYear()->toDateString()]);
        $this->assertSame('quarantine', $receipt['status']);
        $warehouse = DB::table('ph_pharmacies')->where('public_id', $this->pharmacy['id'])->value('warehouse_id');
        $this->assertSame('0.000000', app(InventoryV2Service::class)->availability($warehouse, $product['inventory_item_id']));
        $receipt = $this->command('receipt.inspect', ['pharmacy_id' => $this->pharmacy['id'], 'receipt_id' => $receipt['id'], 'expected_version' => $receipt['version'], 'decision' => 'release', 'reason' => 'Synthetic inspection']);
        $this->assertSame('released', $receipt['status']);
        $this->assertSame('10.000000', app(InventoryV2Service::class)->availability($warehouse, $product['inventory_item_id']));
        $case = $this->command('quality.hold', ['pharmacy_id' => $this->pharmacy['id'], 'batch_id' => $receipt['batch_id'], 'reason' => 'Synthetic hold']);
        $this->assertSame('0.000000', app(InventoryV2Service::class)->availability($warehouse, $product['inventory_item_id']));
        $this->command('quality.release', ['pharmacy_id' => $this->pharmacy['id'], 'hold_id' => $case['hold']['public_id'], 'expected_version' => $case['hold']['version'], 'reason' => 'Synthetic resolution']);
        $this->assertSame('10.000000', app(InventoryV2Service::class)->availability($warehouse, $product['inventory_item_id']));
    }

    public function test_chain_recall_creates_scoped_tasks_and_cannot_be_manually_released(): void
    {
        $product = $this->product();
        $receipt = $this->command('receipt.create', ['pharmacy_id' => $this->pharmacy['id'], 'product_id' => $product['id'], 'quantity' => '10', 'unit_cost' => '250', 'batch_code' => 'LOT-RECALL', 'expires_at' => now()->addYear()->toDateString()]);
        $other = $this->command('pharmacy.create', ['chain_id' => $this->chain['id'], 'name' => 'Second synthetic site', 'code' => 'SECOND']);
        $recall = $this->command('recall.create', ['pharmacy_id' => $this->pharmacy['id'], 'batch_id' => $receipt['batch_id'], 'reference' => 'SYNTHETIC-RECALL', 'reason' => 'Synthetic recall']);
        $this->assertSame(2, DB::table('ph_recall_tasks')->where('recall_id', DB::table('ph_recalls')->where('public_id', $recall['id'])->value('id'))->count());
        $this->assertSame(2, DB::table('inv_stock_holds')->where('reason_code', 'recall')->count());
        $this->assertNotSame($this->pharmacy['id'], $other['id']);
        $hold = DB::table('inv_stock_holds')->where('reason_code', 'recall')->where('warehouse_id', DB::table('ph_pharmacies')->where('public_id', $this->pharmacy['id'])->value('warehouse_id'))->first();
        $this->rejected(409, fn () => $this->command('quality.release', ['pharmacy_id' => $this->pharmacy['id'], 'hold_id' => $hold->public_id, 'expected_version' => $hold->version, 'reason' => 'Must not bypass recall']));
        $new = $this->command('pharmacy.create', ['chain_id' => $this->chain['id'], 'name' => 'Site after recall', 'code' => 'AFTER-RECALL']);
        $newRow = DB::table('ph_pharmacies')->where('public_id', $new['id'])->first();
        $this->assertDatabaseHas('inv_stock_holds', ['warehouse_id' => $newRow->warehouse_id, 'batch_id' => $receipt['batch_id'], 'reason_code' => 'recall', 'status' => 'active']);
        $this->rejected(409, fn () => app(PharmacyInventoryService::class)->assertEligible($this->owner, $newRow, DB::table('ph_products')->where('public_id', $product['id'])->first(), $receipt['batch_id']));
    }

    public function test_private_upload_download_scope_revocation_expiry_and_checksum(): void
    {
        Storage::fake('pharmacy_private');
        $actor = $this->member(['pharmacy.view', 'pharmacy.documents.manage', 'pharmacy.patient.sensitive']);
        $this->actingAs($actor, 'admin');
        $file = UploadedFile::fake()->createWithContent('evidence.pdf', "%PDF-1.4\nSYNTHETIC-PRIVATE-DOCUMENT\n%%EOF");
        $created = $this->post('/admin/api/pharmacy/documents', ['pharmacy_id' => $this->pharmacy['id'], 'kind' => 'evidence', 'file' => $file], ['Accept' => 'application/json', 'Idempotency-Key' => (string) Str::uuid()])->assertOk();
        $id = $created->json('data.id');
        $row = DB::table('ph_documents')->where('public_id', $id)->first();
        $this->assertStringNotContainsString('SYNTHETIC-PRIVATE-DOCUMENT', Storage::disk('pharmacy_private')->get($row->path));
        $this->getJson('/admin/api/pharmacy/documents/'.$id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        DB::table('ph_memberships')->where('admin_id', $actor->id)->update(['status' => 'inactive']);
        $this->getJson('/admin/api/pharmacy/documents/'.$id)->assertForbidden();
        $this->actingAs($this->owner, 'admin');
        DB::table('ph_documents')->where('id', $row->id)->update(['checksum' => str_repeat('0', 64)]);
        $this->getJson('/admin/api/pharmacy/documents/'.$id)->assertStatus(409);
        DB::table('ph_documents')->where('id', $row->id)->update(['checksum' => $row->checksum, 'expires_at' => now()->subMinute()]);
        $this->getJson('/admin/api/pharmacy/documents/'.$id)->assertStatus(410);
        auth('admin')->logout();
        $this->getJson('/admin/api/pharmacy/documents/'.$id)->assertUnauthorized();
    }

    public function test_print_jobs_are_private_reprints_without_stock_or_payment_side_effects(): void
    {
        Storage::fake('pharmacy_private');
        $product = $this->product();
        $receipt = $this->command('receipt.create', ['pharmacy_id' => $this->pharmacy['id'], 'product_id' => $product['id'], 'quantity' => '10', 'unit_cost' => '250', 'batch_code' => 'LOT-PRINT', 'expires_at' => now()->addYear()->toDateString()]);
        $this->command('receipt.inspect', ['pharmacy_id' => $this->pharmacy['id'], 'receipt_id' => $receipt['id'], 'expected_version' => $receipt['version'], 'decision' => 'release', 'reason' => 'Synthetic inspection']);
        $patient = $this->command('patient.save', ['pharmacy_id' => $this->pharmacy['id'], 'name' => 'SECRET-RECEIPT-PATIENT', 'processing_basis' => 'consent', 'processing_evidence' => 'Synthetic consent']);
        $d = $this->command('dispensing.create', ['pharmacy_id' => $this->pharmacy['id'], 'patient_id' => $patient['id'], 'mode' => 'otc', 'lines' => [['product_id' => $product['id'], 'quantity' => '2', 'instructions' => 'Synthetic usage']]]);
        $d = $this->command('dispensing.prepare', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'allocations' => [['line_id' => $d['lines'][0]['id'], 'batch_id' => $receipt['batch_id'], 'quantity' => '2']]]);
        $d = $this->command('dispensing.review', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'decision' => 'approved', 'reason' => 'Synthetic professional review']);
        $label = $this->command('print.create', ['pharmacy_id' => $this->pharmacy['id'], 'kind' => 'dispensing-label', 'source_id' => $d['id']]);
        $this->assertStringContainsString('Synthetic usage', app(PharmacyDocumentService::class)->download($this->owner, $label['id'], true)->getContent());
        $shift = $this->command('shift.open', ['pharmacy_id' => $this->pharmacy['id'], 'counter_id' => $this->pharmacy['counter']['id'], 'opening_minor' => '0']);
        $d = $this->command('dispensing.finalize', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'shift_id' => $shift['id'], 'tenders' => [['method' => 'cash', 'amount_minor' => '2000']]]);
        $first = $this->command('print.create', ['pharmacy_id' => $this->pharmacy['id'], 'kind' => 'receipt', 'source_id' => $d['sale']['id']]);
        $again = $this->command('print.create', ['pharmacy_id' => $this->pharmacy['id'], 'kind' => 'receipt', 'source_id' => $d['sale']['id']]);
        $this->assertNotSame($first['id'], $again['id']);
        $this->assertStringContainsString('2,000 VND', app(PharmacyDocumentService::class)->download($this->owner, $again['id'], true)->getContent());
        $cashier = $this->member(['pharmacy.view', 'pharmacy.sale.view', 'pharmacy.documents.manage']);
        $cashReceipt = app(PharmacyService::class)->command($cashier, 'print.create', ['pharmacy_id' => $this->pharmacy['id'], 'kind' => 'receipt', 'source_id' => $d['sale']['id']], (string) Str::uuid());
        $cashBody = app(PharmacyDocumentService::class)->download($cashier, $cashReceipt['id'], true)->getContent();
        $this->assertStringContainsString('2,000 VND', $cashBody);
        $this->assertStringNotContainsString('SECRET-RECEIPT-PATIENT', $cashBody);
        $this->assertStringNotContainsString('Synthetic usage', $cashBody);
        $this->assertDatabaseCount('ph_sales', 1);
        $this->assertDatabaseCount('ph_tenders', 1);
        $warehouse = DB::table('ph_pharmacies')->where('public_id', $this->pharmacy['id'])->value('warehouse_id');
        $this->assertSame('8.000000', app(InventoryV2Service::class)->availability($warehouse, $product['inventory_item_id']));
        $this->assertSame('finalized', app(DispensingService::class)->show($this->owner, $d['id'])['status']);
    }

    public function test_native_workspace_requires_login_and_password_change_before_sensitive_access(): void
    {
        $this->get('/admin/modules/pharmacy')->assertRedirect();
        $this->getJson('/admin/api/pharmacy/bootstrap')->assertUnauthorized();
        $this->owner->update(['must_change_password' => true]);
        $this->actingAs($this->owner->fresh(), 'admin');
        $this->getJson('/admin/api/pharmacy/bootstrap')->assertOk()->assertJsonPath('data.must_change_password', true)->assertJsonPath('data.pharmacies', []);
        $this->getJson('/admin/api/pharmacy/patients?pharmacy_id='.$this->pharmacy['id'])->assertForbidden();
        $this->assertNull(session('admin.site_id'));
    }

    public function test_transfer_approval_dispatch_and_receipt_preserve_quarantine_and_scope(): void
    {
        $product = $this->product();
        $receipt = $this->command('receipt.create', ['pharmacy_id' => $this->pharmacy['id'], 'product_id' => $product['id'], 'quantity' => '10', 'unit_cost' => '250', 'batch_code' => 'LOT-TRANSFER', 'expires_at' => now()->addYear()->toDateString()]);
        $this->command('receipt.inspect', ['pharmacy_id' => $this->pharmacy['id'], 'receipt_id' => $receipt['id'], 'expected_version' => $receipt['version'], 'decision' => 'release', 'reason' => 'Synthetic inspection']);
        $target = $this->command('pharmacy.create', ['chain_id' => $this->chain['id'], 'name' => 'Transfer target', 'code' => 'TRANSFER-TARGET']);
        $checker = $this->member(['pharmacy.view', 'pharmacy.transfer.manage']);
        $membership = (array) DB::table('ph_memberships')->where('admin_id', $checker->id)->first();
        unset($membership['id']);
        DB::table('ph_memberships')->insert(array_replace($membership, ['public_id' => (string) Str::uuid(), 'pharmacy_id' => DB::table('ph_pharmacies')->where('public_id', $target['id'])->value('id')]));
        $transfer = $this->command('transfer.create', ['pharmacy_id' => $this->pharmacy['id'], 'destination_pharmacy_id' => $target['id'], 'reason' => 'Synthetic replenishment', 'lines' => [['product_id' => $product['id'], 'quantity' => '3', 'batch_id' => $receipt['batch_id']]]]);
        $this->assertSame('draft', $transfer['status']);
        $advance = ['pharmacy_id' => $this->pharmacy['id'], 'transfer_id' => $transfer['id'], 'expected_version' => $transfer['version']];
        $this->rejected(403, fn () => $this->command('transfer.approve', $advance));
        $this->rejected(409, fn () => $this->command('transfer.dispatch', $advance));
        $transfer = app(PharmacyService::class)->command($checker, 'transfer.approve', $advance, (string) Str::uuid());
        $transfer = $this->command('transfer.dispatch', array_replace($advance, ['expected_version' => $transfer['version']]));
        $this->assertSame('dispatched', $transfer['status']);
        $transfer = app(PharmacyService::class)->command($checker, 'transfer.receive', ['pharmacy_id' => $target['id'], 'transfer_id' => $transfer['id'], 'expected_version' => $transfer['version']], (string) Str::uuid());
        $this->assertSame('received', $transfer['status']);
        $targetId = DB::table('ph_pharmacies')->where('public_id', $target['id'])->value('id');
        $this->assertDatabaseHas('ph_receipts', ['pharmacy_id' => $targetId, 'batch_id' => $receipt['batch_id'], 'status' => 'quarantine']);
        $targetWarehouse = DB::table('ph_pharmacies')->where('id', $targetId)->value('warehouse_id');
        $this->assertSame('0.000000', app(InventoryV2Service::class)->availability($targetWarehouse, $product['inventory_item_id']));
    }

    public function test_stocktake_requires_checker_and_posts_immutable_adjustment(): void
    {
        $product = $this->product();
        $receipt = $this->command('receipt.create', ['pharmacy_id' => $this->pharmacy['id'], 'product_id' => $product['id'], 'quantity' => '10', 'unit_cost' => '250', 'batch_code' => 'LOT-COUNT', 'expires_at' => now()->addYear()->toDateString()]);
        $this->command('receipt.inspect', ['pharmacy_id' => $this->pharmacy['id'], 'receipt_id' => $receipt['id'], 'expected_version' => $receipt['version'], 'decision' => 'release', 'reason' => 'Synthetic inspection']);
        $count = $this->command('stocktake.create', ['pharmacy_id' => $this->pharmacy['id'], 'reason' => 'Synthetic stocktake']);
        $listed = app(PharmacyReadService::class)->read($this->owner, 'stocktakes', ['pharmacy_id' => $this->pharmacy['id']]);
        $this->assertNotEmpty($listed['records'][0]['counts']);
        $stocktakeId = DB::table('inv_stocktakes')->where('public_id', $count['inventory_stocktake_id'])->value('id');
        $counts = DB::table('inv_stocktake_lines')->where('stocktake_id', $stocktakeId)->get()->map(fn ($line) => ['balance_id' => $line->balance_id, 'quantity' => (float) $line->expected_quantity > 0 ? '9' : '0'])->all();
        $input = ['pharmacy_id' => $this->pharmacy['id'], 'stocktake_id' => $count['id'], 'expected_version' => $count['version'], 'counts' => $counts];
        $this->rejected(403, fn () => $this->command('stocktake.post', $input));
        $checker = $this->member(['pharmacy.view', 'pharmacy.stocktake.manage']);
        $this->command('credential.save', ['pharmacy_id' => $this->pharmacy['id'], 'admin_id' => $checker->id, 'license_number' => 'SYNTHETIC-CHECKER', 'abilities' => ['review'], 'valid_from' => now()->subDay()->toDateTimeString(), 'valid_until' => now()->addYear()->toDateTimeString(), 'reason' => 'Synthetic fixture']);
        $posted = app(PharmacyService::class)->command($checker, 'stocktake.post', $input, (string) Str::uuid());
        $this->assertSame('posted', $posted['status']);
        $this->assertDatabaseHas('inv_stock_movements', ['item_id' => $product['inventory_item_id'], 'type' => 'adjustment', 'quantity_delta' => '-1.000000']);
    }

    public function test_export_download_rechecks_underlying_native_resource_permission(): void
    {
        Storage::fake('pharmacy_private');
        $actor = $this->member(['pharmacy.view', 'pharmacy.export', 'pharmacy.inventory.view']);
        $export = app(PharmacyService::class)->command($actor, 'export.create', ['pharmacy_id' => $this->pharmacy['id'], 'resource' => 'inventory'], (string) Str::uuid());
        $this->assertSame(200, app(PharmacyDocumentService::class)->download($actor, $export['id'])->getStatusCode());
        DB::table('ph_memberships')->where('admin_id', $actor->id)->update(['permissions' => json_encode(['pharmacy.view', 'pharmacy.export'])]);
        $this->rejected(403, fn () => app(PharmacyDocumentService::class)->download($actor, $export['id']));
    }

    public function test_old_print_job_cannot_return_label_from_replaced_and_rereviewed_lines(): void
    {
        Storage::fake('pharmacy_private');
        $product = $this->product();
        $receipt = $this->command('receipt.create', ['pharmacy_id' => $this->pharmacy['id'], 'product_id' => $product['id'], 'quantity' => '10', 'unit_cost' => '250', 'batch_code' => 'LOT-STALE-LABEL', 'expires_at' => now()->addYear()->toDateString()]);
        $this->command('receipt.inspect', ['pharmacy_id' => $this->pharmacy['id'], 'receipt_id' => $receipt['id'], 'expected_version' => $receipt['version'], 'decision' => 'release', 'reason' => 'Synthetic inspection']);
        $d = $this->command('dispensing.create', ['pharmacy_id' => $this->pharmacy['id'], 'mode' => 'otc', 'lines' => [['product_id' => $product['id'], 'quantity' => '1', 'instructions' => 'Obsolete instructions']]]);
        $prepareAndReview = function (array $d, string $quantity) use ($receipt): array {
            $d = $this->command('dispensing.prepare', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'allocations' => [['line_id' => $d['lines'][0]['id'], 'batch_id' => $receipt['batch_id'], 'quantity' => $quantity]]]);

            return $this->command('dispensing.review', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'decision' => 'approved', 'reason' => 'Synthetic recheck']);
        };
        $d = $prepareAndReview($d, '1');
        $label = $this->command('print.create', ['pharmacy_id' => $this->pharmacy['id'], 'kind' => 'dispensing-label', 'source_id' => $d['id']]);
        $d = $this->command('dispensing.lines', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'lines' => [['product_id' => $product['id'], 'quantity' => '2', 'instructions' => 'Current approved instructions']]]);
        $prepareAndReview($d, '2');
        $this->rejected(409, fn () => app(PharmacyDocumentService::class)->download($this->owner, $label['id'], true));
    }

    public function test_unaccepted_controlled_and_cold_chain_stock_stays_quarantined(): void
    {
        foreach (['controlled', 'cold_chain'] as $flag) {
            $product = $this->command('product.save', ['pharmacy_id' => $this->pharmacy['id'], 'name' => 'Synthetic blocked '.$flag, 'code' => 'BLOCKED-'.$flag, 'base_unit' => 'vien', 'price_minor' => '1000', $flag => true]);
            $receipt = $this->command('receipt.create', ['pharmacy_id' => $this->pharmacy['id'], 'product_id' => $product['id'], 'quantity' => '2', 'unit_cost' => '250', 'batch_code' => 'LOT-BLOCKED-'.$flag, 'expires_at' => now()->addYear()->toDateString()]);
            $this->rejected(409, fn () => $this->command('receipt.inspect', ['pharmacy_id' => $this->pharmacy['id'], 'receipt_id' => $receipt['id'], 'expected_version' => $receipt['version'], 'decision' => 'release', 'reason' => 'Must remain blocked']));
            $this->assertDatabaseHas('ph_receipts', ['public_id' => $receipt['id'], 'status' => 'quarantine']);
        }
    }

    private function product(): array
    {
        return $this->command('product.save', ['pharmacy_id' => $this->pharmacy['id'], 'name' => 'Synthetic workspace medicine', 'code' => 'MED-WORKSPACE', 'base_unit' => 'vien', 'price_minor' => '1000']);
    }

    private function command(string $action, array $input): array
    {
        return app(PharmacyService::class)->command($this->owner, $action, $input, (string) Str::uuid());
    }

    private function member(array $permissions): Admin
    {
        $admin = Admin::factory()->create(['must_change_password' => false]);
        $role = Role::query()->create(['name' => 'Workspace fixture', 'key' => 'ph-workspace-'.$admin->id]);
        $role->permissions()->sync(Permission::query()->whereIn('key', $permissions)->pluck('id'));
        AdminRoleAssignment::query()->create(['admin_id' => $admin->id, 'role_id' => $role->id, 'scope_type' => 'global']);
        DB::table('ph_memberships')->insert(['public_id' => (string) Str::uuid(), 'admin_id' => $admin->id, 'chain_id' => DB::table('ph_pharmacies')->where('public_id', $this->pharmacy['id'])->value('chain_id'), 'pharmacy_id' => DB::table('ph_pharmacies')->where('public_id', $this->pharmacy['id'])->value('id'), 'permissions' => json_encode($permissions), 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return $admin->fresh();
    }

    private function rejected(int $status, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected rejected operation.');
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode(), $e->getMessage());
        }
    }
}
