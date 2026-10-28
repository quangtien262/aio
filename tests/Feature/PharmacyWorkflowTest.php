<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Permission;
use App\Models\Role;
use Brick\Math\BigDecimal;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Pharmacy\Services\PharmacyService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PharmacyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    private array $pharmacy;

    private array $product;

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
        $setup = $this->command('setup', ['chain_name' => 'Synthetic workflow chain', 'pharmacy_name' => 'Synthetic workflow site', 'code' => 'WORKFLOW']);
        $this->pharmacy = $setup['pharmacy'];
        $this->credential($this->owner);
        $this->product = $this->command('product.save', ['pharmacy_id' => $this->pharmacy['id'], 'name' => 'Synthetic workflow medicine', 'code' => 'WORKFLOW-MED', 'base_unit' => 'vien', 'price_minor' => '1000']);
    }

    public function test_assignment_requires_live_scope_and_professional_authority_and_parties_stay_private(): void
    {
        $d = $this->command('dispensing.create', ['pharmacy_id' => $this->pharmacy['id'], 'mode' => 'otc', 'lines' => [['product_id' => $this->product['id'], 'quantity' => '1']]]);
        $reviewer = $this->member(['pharmacy.view', 'pharmacy.dispensing.review']);
        $assignment = fn () => $this->command('dispensing.assign', ['pharmacy_id' => $this->pharmacy['id'], 'dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'assigned_to' => $reviewer->id, 'stage' => 'review']);
        $this->rejected(403, $assignment);
        $this->credential($reviewer);
        $d = $assignment();
        $this->assertSame($reviewer->id, $d['task']['assigned_to']);
        DB::table('ph_memberships')->where('admin_id', $reviewer->id)->update(['status' => 'inactive']);
        $this->rejected(403, fn () => $this->command('dispensing.assign', ['pharmacy_id' => $this->pharmacy['id'], 'dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'assigned_to' => $reviewer->id, 'stage' => 'review']));
        $d = $this->command('dispensing.parties', ['pharmacy_id' => $this->pharmacy['id'], 'dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'buyer_name' => 'SECRET-BUYER', 'payer_name' => 'SECRET-PAYER', 'recipient_name' => 'SECRET-RECIPIENT', 'relationship' => 'Synthetic caregiver', 'authority_evidence' => 'SECRET-AUTHORITY']);
        $this->assertStringNotContainsString('SECRET-BUYER', DB::table('ph_dispensings')->where('public_id', $d['id'])->value('encrypted_snapshot'));
        $this->assertStringNotContainsString('SECRET-AUTHORITY', json_encode(DB::table('ph_commands')->get()));
        $receipt = $this->receive('2');
        $line = DB::table('ph_dispensing_lines')->where('dispensing_id', DB::table('ph_dispensings')->where('public_id', $d['id'])->value('id'))->first();
        $d = $this->command('dispensing.prepare', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'allocations' => [['line_id' => $line->public_id, 'batch_id' => $receipt['batch_id'], 'quantity' => '1']]]);
        $this->rejected(409, fn () => $this->command('dispensing.parties', ['pharmacy_id' => $this->pharmacy['id'], 'dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'relationship' => 'Changed', 'authority_evidence' => 'New authority']));
    }

    public function test_counseling_and_failed_handover_evidence_never_restore_claim_or_reverse_money(): void
    {
        $this->product = $this->command('product.save', ['pharmacy_id' => $this->pharmacy['id'], 'product_id' => $this->product['id'], 'expected_version' => $this->product['version'], 'name' => 'Synthetic workflow medicine', 'code' => 'WORKFLOW-MED', 'base_unit' => 'vien', 'price_minor' => '1000', 'requires_prescription' => true]);
        $d = $this->paid('1', '2', true);
        $d = $this->command('dispensing.counsel', ['pharmacy_id' => $this->pharmacy['id'], 'dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'reason' => 'SECRET-COUNSELING']);
        $d = $this->command('dispensing.handover-failed', ['pharmacy_id' => $this->pharmacy['id'], 'dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'reason' => 'SECRET-FAILED-DELIVERY', 'recipient_name' => 'SECRET-RECIPIENT']);
        $this->assertSame('finalized', $d['status']);
        $this->assertSame('pending', $d['handover_status']);
        $this->assertDatabaseHas('ph_dispense_claims', ['status' => 'committed_pending_handover']);
        $this->assertDatabaseCount('ph_sales', 1);
        $this->assertDatabaseCount('ph_tenders', 1);
        $this->assertDatabaseCount('ph_returns', 0);
        $this->assertDatabaseCount('ph_refunds', 0);
        $this->assertDatabaseCount('ph_counseling_evidence', 2);
        foreach (['ph_counseling_evidence', 'ph_custody_events', 'ph_commands', 'ph_outbox', 'audit_logs'] as $table) {
            $this->assertStringNotContainsString('SECRET-', json_encode(DB::table($table)->get()), $table);
        }
    }

    public function test_cash_drawer_movements_require_own_open_shift_and_versions_and_reconcile_close(): void
    {
        $shift = $this->command('shift.open', ['pharmacy_id' => $this->pharmacy['id'], 'counter_id' => $this->pharmacy['counter']['id'], 'opening_minor' => '1000']);
        $input = ['pharmacy_id' => $this->pharmacy['id'], 'shift_id' => $shift['id'], 'expected_version' => 1, 'direction' => 'in', 'amount_minor' => '500', 'reason' => 'SECRET-CASH-REASON'];
        $this->command('shift.cash-movement', $input);
        $this->rejected(409, fn () => $this->command('shift.cash-movement', $input));
        $other = $this->member(['pharmacy.view', 'pharmacy.shift.manage']);
        $this->rejected(403, fn () => $this->command('shift.cash-movement', array_replace($input, ['expected_version' => 2]), $other));
        $this->command('shift.cash-movement', array_replace($input, ['expected_version' => 2, 'direction' => 'out', 'amount_minor' => '200']));
        $closed = $this->command('shift.close', ['shift_id' => $shift['id'], 'expected_version' => 3, 'closing_minor' => '1300']);
        $this->assertSame(1300, $closed['expected_minor']);
        $this->assertSame(0, $closed['difference_minor']);
        $this->assertStringNotContainsString('SECRET-CASH-REASON', json_encode(DB::table('ph_cash_movements')->get()));
    }

    public function test_replenishment_accounts_for_partial_purchase_receipts_without_counting_quarantine_as_available(): void
    {
        app(ModuleManager::class)->install('purchasing');
        app(ModuleManager::class)->enable('purchasing');
        $d = $this->paid('15', '20');
        $this->command('dispensing.handover', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'recipient_name' => 'Synthetic recipient']);
        $supplier = $this->command('supplier.save', ['pharmacy_id' => $this->pharmacy['id'], 'name' => 'Synthetic licensed supplier', 'code' => 'WORKFLOW-NCC', 'license_number' => 'SYNTHETIC', 'valid_until' => now()->addYear()->toDateTimeString()]);
        $order = $this->command('purchasing.create', ['pharmacy_id' => $this->pharmacy['id'], 'supplier_id' => $supplier['supplier']['public_id'], 'lines' => [['product_id' => $this->product['id'], 'quantity' => '10', 'unit_cost' => '200']]]);
        $checker = $this->member(['pharmacy.view', 'pharmacy.purchasing.manage', 'purchasing.order.approve']);
        $order = $this->command('purchasing.approve', ['pharmacy_id' => $this->pharmacy['id'], 'order_id' => $order['id'], 'expected_version' => $order['version']], $checker);
        $order = $this->command('purchasing.receive', ['pharmacy_id' => $this->pharmacy['id'], 'order_id' => $order['id'], 'expected_version' => $order['version'], 'lines' => [['order_line_id' => $order['order']['lines'][0]['public_id'], 'quantity' => '3', 'batch_code' => 'WORKFLOW-PARTIAL', 'expires_at' => now()->addYear()->toDateString()]]]);
        $this->assertSame('partially_received', $order['status']);
        $proposal = $this->command('replenishment.propose', ['pharmacy_id' => $this->pharmacy['id'], 'coverage_days' => 30, 'product_ids' => [$this->product['id']]]);
        $line = $proposal['lines'][0];
        foreach (['available' => '5', 'sold_30_days' => '15', 'on_order' => '7', 'proposed_quantity' => '3'] as $field => $expected) {
            $this->assertTrue(BigDecimal::of($line[$field])->isEqualTo($expected), $field);
        }
    }

    private function receive(string $quantity): array
    {
        $receipt = $this->command('receipt.create', ['pharmacy_id' => $this->pharmacy['id'], 'product_id' => $this->product['id'], 'quantity' => $quantity, 'unit_cost' => '200', 'batch_code' => 'WORKFLOW-LOT', 'expires_at' => now()->addYear()->toDateString()]);
        $this->command('receipt.inspect', ['pharmacy_id' => $this->pharmacy['id'], 'receipt_id' => $receipt['id'], 'expected_version' => $receipt['version'], 'decision' => 'release', 'reason' => 'Synthetic inspection']);

        return $receipt;
    }

    private function paid(string $quantity, string $stock, bool $rx = false): array
    {
        $receipt = $this->receive($stock);
        $prescription = null;
        if ($rx) {
            $patient = $this->command('patient.save', ['pharmacy_id' => $this->pharmacy['id'], 'name' => 'Synthetic workflow patient', 'processing_basis' => 'consent', 'processing_evidence' => 'Synthetic patient consent']);
            $prescription = $this->command('prescription.save', ['pharmacy_id' => $this->pharmacy['id'], 'patient_id' => $patient['id'], 'reference' => 'SYNTHETIC-RX', 'issued_at' => now()->toDateString(), 'prescriber_name' => 'Synthetic prescriber', 'facility_name' => 'Synthetic facility', 'original_reference' => 'paper:synthetic', 'lines' => [['product_id' => $this->product['id'], 'quantity' => $quantity, 'instructions' => 'Synthetic instructions']]]);
            $prescription = $this->command('prescription.verify', ['prescription_id' => $prescription['id'], 'expected_version' => $prescription['version'], 'verification_method' => 'manual_review', 'reason' => 'Synthetic check']);
        }
        $d = $this->command('dispensing.create', ['pharmacy_id' => $this->pharmacy['id'], 'mode' => $rx ? 'rx' : 'otc', 'prescription_id' => $prescription['id'] ?? null, 'lines' => [['product_id' => $this->product['id'], 'quantity' => $quantity]]]);
        $d = $this->command('dispensing.prepare', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'allocations' => [['line_id' => $d['lines'][0]['id'], 'batch_id' => $receipt['batch_id'], 'quantity' => $quantity]]]);
        $d = $this->command('dispensing.review', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'decision' => 'approved', 'reason' => 'Synthetic review']);
        $shift = $this->command('shift.open', ['pharmacy_id' => $this->pharmacy['id'], 'counter_id' => $this->pharmacy['counter']['id'], 'opening_minor' => '0']);

        return $this->command('dispensing.finalize', ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'shift_id' => $shift['id'], 'tenders' => [['method' => 'cash', 'amount_minor' => BigDecimal::of($quantity)->multipliedBy(1000)->toInt()]]]);
    }

    private function credential(Admin $actor): void
    {
        $this->command('credential.save', ['pharmacy_id' => $this->pharmacy['id'], 'admin_id' => $actor->id, 'license_number' => 'SYNTHETIC', 'abilities' => ['review', 'dispense', 'handover'], 'valid_from' => now()->subDay()->toDateTimeString(), 'valid_until' => now()->addYear()->toDateTimeString(), 'reason' => 'Synthetic qualification']);
    }

    private function command(string $action, array $input, ?Admin $actor = null): array
    {
        return app(PharmacyService::class)->command($actor ?? $this->owner, $action, $input, (string) Str::uuid());
    }

    private function member(array $permissions): Admin
    {
        $admin = Admin::factory()->create(['must_change_password' => false]);
        $role = Role::query()->create(['name' => 'Workflow fixture', 'key' => 'ph-workflow-'.$admin->id]);
        $role->permissions()->sync(Permission::query()->whereIn('key', $permissions)->pluck('id'));
        AdminRoleAssignment::query()->create(['admin_id' => $admin->id, 'role_id' => $role->id, 'scope_type' => 'global']);
        $p = DB::table('ph_pharmacies')->where('public_id', $this->pharmacy['id'])->first();
        DB::table('ph_memberships')->insert(['public_id' => (string) Str::uuid(), 'admin_id' => $admin->id, 'chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'permissions' => json_encode($permissions), 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return $admin->fresh();
    }

    private function rejected(int $status, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected rejected workflow.');
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode(), $e->getMessage());
        }
    }
}
