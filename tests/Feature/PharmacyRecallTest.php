<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Pharmacy\Services\PharmacyInventoryService;
use Modules\Pharmacy\Services\PharmacyService;
use Modules\Pharmacy\Services\Quality\PharmacyRecallService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PharmacyRecallTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    private array $pharmacy;

    private array $chain;

    private array $product;

    private array $receipt;

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
        $setup = $this->command('setup', ['chain_name' => 'Synthetic recall chain', 'pharmacy_name' => 'Synthetic recall site', 'code' => 'RECALL']);
        $this->pharmacy = $setup['pharmacy'];
        $this->chain = $setup['chain'];
        $this->credential($this->owner, $this->pharmacy);
        $this->product = $this->command('product.save', ['pharmacy_id' => $this->pharmacy['id'], 'name' => 'Synthetic recall medicine', 'code' => 'RECALL-MED', 'base_unit' => 'vien', 'price_minor' => '1000']);
        $this->receipt = $this->command('receipt.create', ['pharmacy_id' => $this->pharmacy['id'], 'product_id' => $this->product['id'], 'quantity' => '3', 'unit_cost' => '487', 'batch_code' => 'RECALL-LOT', 'expires_at' => now()->addYear()->toDateString()]);
        $this->command('receipt.inspect', ['pharmacy_id' => $this->pharmacy['id'], 'receipt_id' => $this->receipt['id'], 'expected_version' => $this->receipt['version'], 'decision' => 'release', 'reason' => 'Synthetic inspection']);
    }

    public function test_chain_trace_has_business_refs_without_clinical_identity_or_cost_and_scope_is_live(): void
    {
        $this->product = $this->command('product.save', ['pharmacy_id' => $this->pharmacy['id'], 'product_id' => $this->product['id'], 'expected_version' => $this->product['version'], 'name' => 'Synthetic recall medicine', 'code' => 'RECALL-MED', 'base_unit' => 'vien', 'price_minor' => '1000', 'requires_prescription' => true]);
        $patient = $this->command('patient.save', ['pharmacy_id' => $this->pharmacy['id'], 'name' => 'SECRET-RECALL-PATIENT', 'allergies' => ['SECRET-ALLERGY'], 'processing_basis' => 'consent', 'processing_evidence' => 'SECRET-CONSENT']);
        $rx = $this->command('prescription.save', ['pharmacy_id' => $this->pharmacy['id'], 'patient_id' => $patient['id'], 'reference' => 'SYNTHETIC-RECALL-RX', 'issued_at' => now()->toDateString(), 'prescriber_name' => 'SECRET-PRESCRIBER', 'facility_name' => 'SECRET-FACILITY', 'original_reference' => 'paper:SECRET', 'lines' => [['product_id' => $this->product['id'], 'quantity' => '1', 'instructions' => 'SECRET-INSTRUCTIONS']]]);
        $rx = $this->command('prescription.verify', ['prescription_id' => $rx['id'], 'expected_version' => $rx['version'], 'verification_method' => 'manual_review', 'reason' => 'Synthetic verification']);
        $d = $this->command('dispensing.create', ['pharmacy_id' => $this->pharmacy['id'], 'patient_id' => $patient['id'], 'prescription_id' => $rx['id'], 'mode' => 'rx', 'lines' => [['product_id' => $this->product['id'], 'quantity' => '1']]]);
        $d = $this->command('dispensing.prepare', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'allocations' => [['line_id' => $d['lines'][0]['id'], 'batch_id' => $this->receipt['batch_id'], 'quantity' => '1']]]);
        $d = $this->command('dispensing.review', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'decision' => 'approved', 'reason' => 'Synthetic review']);
        $shift = $this->command('shift.open', ['pharmacy_id' => $this->pharmacy['id'], 'counter_id' => $this->pharmacy['counter']['id'], 'opening_minor' => '0']);
        $d = $this->command('dispensing.finalize', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'shift_id' => $shift['id'], 'tenders' => [['method' => 'cash', 'amount_minor' => '1000']]]);
        $recall = $this->recall();
        $trace = app(PharmacyRecallService::class)->show($this->owner, $recall['id']);
        $this->assertSame('2.000000', $trace['totals']['on_hand']);
        $this->assertSame('1.000000', $trace['totals']['pending_handover']);
        $this->assertSame($d['id'], $trace['dispensings'][0]['id']);
        $this->assertSame($this->receipt['id'], $trace['receipts'][0]['id']);
        foreach (['SECRET-', 'patient_id', 'unit_cost', 'total_cost', 'encrypted_snapshot'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, json_encode($trace));
        }
        $siteOnly = $this->member(['pharmacy.view', 'pharmacy.recall.manage']);
        $this->rejected(403, fn () => app(PharmacyRecallService::class)->show($siteOnly, $recall['id']));
        $task = DB::table('ph_recall_tasks')->where('recall_id', DB::table('ph_recalls')->where('public_id', $recall['id'])->value('id'))->first();
        $this->rejected(403, fn () => app(PharmacyRecallService::class)->reconcile($siteOnly, ['pharmacy_id' => $this->pharmacy['id'], 'task_id' => $task->public_id, 'expected_version' => $task->version, 'physical_quantity' => '2', 'reason' => 'No professional authority'], (string) Str::uuid()));
        $this->rejected(409, fn () => $this->close($trace));
        $this->rejected(409, fn () => $this->command('dispensing.handover', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'recipient_name' => 'SECRET-RECIPIENT']));
        $this->command('dispensing.return', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'lines' => [['line_id' => $d['lines'][0]['id'], 'quantity' => '1']], 'reason' => 'Synthetic return before handover']);
        $this->command('quality.destroy', ['pharmacy_id' => $this->pharmacy['id'], 'batch_id' => $this->receipt['batch_id'], 'quantity' => '3', 'reason' => 'Synthetic documented destruction']);
        $this->reconcile($recall['id'], $this->pharmacy, '0');
        $this->assertSame('closed', $this->close(app(PharmacyRecallService::class)->show($this->owner, $recall['id']))['status']);
        $this->assertDatabaseHas('ph_dispense_claims', ['status' => 'committed_pending_handover', 'quantity' => '1.000000']);
        $this->assertDatabaseCount('ph_refunds', 0);
    }

    public function test_discrepancy_blocks_close_and_completed_case_retains_authoritative_hold(): void
    {
        $recall = $this->recall();
        $result = $this->reconcile($recall['id'], $this->pharmacy, '2');
        $this->assertSame('discrepancy', $result['status']);
        $this->rejected(409, fn () => $this->close(app(PharmacyRecallService::class)->show($this->owner, $recall['id'])));
        $this->command('quality.destroy', ['pharmacy_id' => $this->pharmacy['id'], 'batch_id' => $this->receipt['batch_id'], 'quantity' => '3', 'reason' => 'Synthetic documented destruction']);
        $this->reconcile($recall['id'], $this->pharmacy, '0');
        $trace = app(PharmacyRecallService::class)->show($this->owner, $recall['id']);
        $key = (string) Str::uuid();
        $payload = $this->closeInput($trace);
        DB::table('ph_credentials')->where('admin_id', $this->owner->id)->update(['status' => 'inactive']);
        $this->rejected(403, fn () => app(PharmacyRecallService::class)->close($this->owner, $payload, $key));
        DB::table('ph_credentials')->where('admin_id', $this->owner->id)->update(['status' => 'active']);
        $closed = app(PharmacyRecallService::class)->close($this->owner, $payload, $key);
        $this->assertSame('closed', $closed['status']);
        $this->assertTrue(app(PharmacyRecallService::class)->close($this->owner, $payload, $key)['replayed']);
        $this->assertDatabaseHas('inv_stock_holds', ['reason_code' => 'recall', 'status' => 'active']);
        $p = DB::table('ph_pharmacies')->where('public_id', $this->pharmacy['id'])->first();
        $product = DB::table('ph_products')->where('public_id', $this->product['id'])->first();
        $this->rejected(409, fn () => app(PharmacyInventoryService::class)->assertEligible($this->owner, $p, $product, $this->receipt['batch_id']));
        $newSite = $this->command('pharmacy.create', ['chain_id' => $this->chain['id'], 'name' => 'Site created after case closure', 'code' => 'AFTER-CLOSED']);
        $newRow = DB::table('ph_pharmacies')->where('public_id', $newSite['id'])->first();
        $this->assertDatabaseHas('ph_recall_tasks', ['recall_id' => DB::table('ph_recalls')->where('public_id', $recall['id'])->value('id'), 'pharmacy_id' => $newRow->id, 'status' => 'permanent_hold']);
        $this->rejected(409, fn () => app(PharmacyInventoryService::class)->assertEligible($this->owner, $newRow, $product, $this->receipt['batch_id']));
        $this->assertSame('closed', app(PharmacyRecallService::class)->show($this->owner, $recall['id'])['status']);
        foreach (['ph_quality_cases', 'ph_recalls', 'ph_commands', 'audit_logs', 'ph_outbox'] as $table) {
            $this->assertStringNotContainsString('SECRET-RECONCILIATION', json_encode(DB::table($table)->get()));
            $this->assertStringNotContainsString('SECRET-CONSUMER-FOLLOWUP', json_encode(DB::table($table)->get()));
        }
        $this->assertDatabaseCount('ph_refunds', 0);
        $this->assertDatabaseCount('ph_dispense_claims', 0);
    }

    public function test_in_transit_ledger_and_stale_site_reconciliation_block_closure(): void
    {
        $target = $this->command('pharmacy.create', ['chain_id' => $this->chain['id'], 'name' => 'Synthetic recall target', 'code' => 'TARGET']);
        $this->credential($this->owner, $target);
        $checker = $this->member(['pharmacy.view', 'pharmacy.transfer.manage'], true);
        $transfer = $this->command('transfer.create', ['pharmacy_id' => $this->pharmacy['id'], 'destination_pharmacy_id' => $target['id'], 'reason' => 'Synthetic transfer before recall', 'lines' => [['product_id' => $this->product['id'], 'quantity' => '1', 'batch_id' => $this->receipt['batch_id']]]]);
        $transfer = $this->command('transfer.approve', ['pharmacy_id' => $this->pharmacy['id'], 'transfer_id' => $transfer['id'], 'expected_version' => $transfer['version']], $checker);
        $transfer = $this->command('transfer.dispatch', ['pharmacy_id' => $this->pharmacy['id'], 'transfer_id' => $transfer['id'], 'expected_version' => $transfer['version']]);
        $recall = $this->recall();
        $trace = app(PharmacyRecallService::class)->show($this->owner, $recall['id']);
        $this->assertSame('1.000000', $trace['totals']['in_transit']);
        $this->assertSame($transfer['id'], $trace['transfers'][0]['id']);
        $this->command('quality.destroy', ['pharmacy_id' => $this->pharmacy['id'], 'batch_id' => $this->receipt['batch_id'], 'quantity' => '2', 'reason' => 'Synthetic source destruction']);
        $this->reconcile($recall['id'], $this->pharmacy, '0');
        $this->reconcile($recall['id'], $target, '0');
        $this->rejected(409, fn () => $this->close(app(PharmacyRecallService::class)->show($this->owner, $recall['id'])));
        $this->command('transfer.receive', ['pharmacy_id' => $target['id'], 'transfer_id' => $transfer['id'], 'expected_version' => $transfer['version']], $checker);
        $this->command('quality.destroy', ['pharmacy_id' => $target['id'], 'batch_id' => $this->receipt['batch_id'], 'quantity' => '1', 'reason' => 'Synthetic destination destruction']);
        $this->rejected(409, fn () => $this->close(app(PharmacyRecallService::class)->show($this->owner, $recall['id'])));
        $this->reconcile($recall['id'], $this->pharmacy, '0');
        $this->reconcile($recall['id'], $target, '0');
        $this->assertSame('closed', $this->close(app(PharmacyRecallService::class)->show($this->owner, $recall['id']))['status']);
    }

    private function recall(): array
    {
        return $this->command('recall.create', ['pharmacy_id' => $this->pharmacy['id'], 'batch_id' => $this->receipt['batch_id'], 'reference' => 'SYNTHETIC-RECALL', 'reason' => 'Synthetic regulatory recall']);
    }

    private function reconcile(string $recallId, array $site, string $quantity): array
    {
        $task = DB::table('ph_recall_tasks')->where('recall_id', DB::table('ph_recalls')->where('public_id', $recallId)->value('id'))->where('pharmacy_id', DB::table('ph_pharmacies')->where('public_id', $site['id'])->value('id'))->first();

        return app(PharmacyRecallService::class)->reconcile($this->owner, ['pharmacy_id' => $site['id'], 'task_id' => $task->public_id, 'expected_version' => $task->version, 'physical_quantity' => $quantity, 'reason' => 'SECRET-RECONCILIATION'], (string) Str::uuid());
    }

    private function closeInput(array $trace): array
    {
        return ['recall_id' => $trace['id'], 'expected_version' => $trace['version'], 'reason' => 'Synthetic closure', 'consumer_followup_evidence' => 'SECRET-CONSUMER-FOLLOWUP: qualified staff documented manual followup or confirmed no consumers exposed', 'consumer_followup_completed' => true];
    }

    private function close(array $trace): array
    {
        return app(PharmacyRecallService::class)->close($this->owner, $this->closeInput($trace), (string) Str::uuid());
    }

    private function credential(Admin $actor, array $site): void
    {
        $this->command('credential.save', ['pharmacy_id' => $site['id'], 'admin_id' => $actor->id, 'license_number' => 'SYNTHETIC', 'abilities' => ['review', 'dispense', 'handover'], 'valid_from' => now()->subDay()->toDateTimeString(), 'valid_until' => now()->addYear()->toDateTimeString(), 'reason' => 'Synthetic qualification']);
    }

    private function command(string $action, array $input, ?Admin $actor = null): array
    {
        return app(PharmacyService::class)->command($actor ?? $this->owner, $action, $input, (string) Str::uuid());
    }

    private function member(array $permissions, bool $wide = false): Admin
    {
        $admin = Admin::factory()->create(['must_change_password' => false]);
        $role = Role::query()->create(['name' => 'Recall fixture', 'key' => 'ph-recall-'.$admin->id]);
        $role->permissions()->sync(Permission::query()->whereIn('key', $permissions)->pluck('id'));
        AdminRoleAssignment::query()->create(['admin_id' => $admin->id, 'role_id' => $role->id, 'scope_type' => 'global']);
        $p = DB::table('ph_pharmacies')->where('public_id', $this->pharmacy['id'])->first();
        DB::table('ph_memberships')->insert(['public_id' => (string) Str::uuid(), 'chain_id' => $p->chain_id, 'pharmacy_id' => $wide ? null : $p->id, 'admin_id' => $admin->id, 'permissions' => json_encode($permissions), 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return $admin->fresh();
    }

    private function rejected(int $status, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected recall gate rejection.');
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode(), $e->getMessage());
        }
    }
}
