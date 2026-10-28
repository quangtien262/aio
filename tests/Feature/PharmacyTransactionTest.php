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
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Merchandise\Services\MerchandiseService;
use Modules\Pharmacy\Services\Dispensing\DispensingService;
use Modules\Pharmacy\Services\Operations\PharmacyOperationsService;
use Modules\Pharmacy\Services\PharmacyInventoryService;
use Modules\Pharmacy\Services\Prescriptions\PatientService;
use Modules\Pharmacy\Services\Prescriptions\PrescriptionService;
use Modules\Pharmacy\Services\Sales\PharmacySaleService;
use Modules\Pharmacy\Services\Setup\PharmacySetupService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PharmacyTransactionTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    private object $pharmacy;

    private string $counter;

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
        $setup = $this->setupCommand('setup', ['chain_name' => 'Pharmacy transaction tests', 'pharmacy_name' => 'Nhà thuốc thử', 'code' => 'TEST']);
        $this->pharmacy = DB::table('ph_pharmacies')->where('public_id', $setup['pharmacy']['id'])->first();
        $this->counter = $setup['pharmacy']['counter']['id'];
        $this->setupCommand('credential.save', ['pharmacy_id' => $this->pharmacy->public_id, 'admin_id' => $this->owner->id, 'license_number' => 'TEST-CREDENTIAL', 'abilities' => ['review', 'dispense', 'handover'], 'valid_from' => now()->subDay()->toDateTimeString(), 'valid_until' => now()->addYear()->toDateTimeString(), 'reason' => 'Synthetic qualification fixture']);
    }

    public function test_otc_cash_stock_custody_return_quarantine_refund_and_shift_reconcile(): void
    {
        $product = $this->product('OTC', '1000');
        $batch = $this->receive($product, '10');
        $d = $this->prepared($product, $batch, '3');
        $d = $this->reviewed($d);
        $shift = $this->shift();
        $input = ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'shift_id' => $shift['id'], 'tenders' => [['method' => 'cash', 'amount_minor' => '3000', 'tendered_minor' => '5000']]];
        $key = $this->key();
        $d = app(PharmacySaleService::class)->finalize($this->owner, $input, $key);
        $replay = app(PharmacySaleService::class)->finalize($this->owner, $input, $key);
        $this->assertTrue($replay['replayed']);
        $this->assertSame('finalized', $d['status']);
        $this->assertSame('pending', $d['handover_status']);
        $this->assertDatabaseCount('ph_sales', 1);
        $this->assertDatabaseCount('ph_tenders', 1);
        $this->assertEquals('7.000000', app(InventoryV2Service::class)->availability($this->pharmacy->warehouse_id, $product->inventory_item_id));
        $d = app(DispensingService::class)->handover($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'recipient_name' => 'Synthetic recipient'], $this->key());
        $this->assertSame('handed_over', $d['status']);
        $d = app(PharmacySaleService::class)->returnSale($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'lines' => [['line_id' => $d['lines'][0]['id'], 'quantity' => '1']], 'reason' => 'Synthetic return'], $this->key());
        $this->assertSame('quarantine', $d['disposition']);
        $this->assertSame(1000, $d['return_amount_minor']);
        $this->assertEquals('7.000000', app(InventoryV2Service::class)->availability($this->pharmacy->warehouse_id, $product->inventory_item_id));
        $this->assertEquals('1', DB::table('inv_stock_balances')->join('inv_locations', 'inv_locations.id', '=', 'inv_stock_balances.location_id')->where('inv_stock_balances.warehouse_id', $this->pharmacy->warehouse_id)->where('inv_locations.type', 'quarantine')->sum('quantity_on_hand'));
        $d = app(PharmacySaleService::class)->refund($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'return_id' => $d['return_id'], 'shift_id' => $shift['id'], 'amount_minor' => '1000', 'method' => 'cash', 'reason' => 'Synthetic refund'], $this->key());
        $this->assertSame(1000, $d['refunded_minor']);
        $closed = app(PharmacySaleService::class)->closeShift($this->owner, ['shift_id' => $shift['id'], 'expected_version' => 1, 'closing_minor' => '2000'], $this->key());
        $this->assertSame(2000, $closed['expected_minor']);
        $this->assertSame(0, $closed['difference_minor']);
    }

    public function test_rx_partial_claims_include_finalized_not_handed_over_and_do_not_restore_after_return(): void
    {
        $product = $this->product('RX', '1200', ['requires_prescription' => true]);
        $batch = $this->receive($product, '20');
        $rx = $this->prescription($product, '5');
        $d = $this->prepared($product, $batch, '3', $rx);
        $d = $this->reviewed($d);
        $shift = $this->shift();
        $d = app(PharmacySaleService::class)->finalize($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'shift_id' => $shift['id'], 'tenders' => [['method' => 'cash', 'amount_minor' => '3600']]], $this->key());
        $this->assertDatabaseHas('ph_dispense_claims', ['status' => 'committed_pending_handover']);
        $candidate = $this->draft($product, '3', $rx);
        $this->rejected(409, fn () => $this->prepare($candidate, $batch, '3'));
        $this->assertEquals('3', DB::table('ph_dispense_claims')->where('status', 'committed_pending_handover')->sum('quantity'));
        $d = app(PharmacySaleService::class)->returnSale($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'lines' => [['line_id' => $d['lines'][0]['id'], 'quantity' => '1']], 'reason' => 'Not handed over'], $this->key());
        $this->rejected(409, fn () => $this->prepare($candidate, $batch, '3'));
        $this->assertSame('committed_pending_handover', DB::table('ph_dispense_claims')->value('status'));
        $remaining = $this->prepared($product, $batch, '2', $rx);
        $this->assertSame('prepared', $remaining['status']);
        $this->assertSame('0.000000', app(PrescriptionService::class)->show($this->owner, $rx['id'])['lines'][0]['remaining_quantity']);
    }

    public function test_cashier_without_credentials_can_finalize_current_professional_review_but_cannot_review_or_handover(): void
    {
        $product = $this->product('SPLIT', '1000');
        $batch = $this->receive($product, '5');
        $d = $this->reviewed($this->prepared($product, $batch, '1'));
        $cashier = $this->member(['pharmacy.view', 'pharmacy.dispensing.view', 'pharmacy.sale.create', 'pharmacy.sale.view', 'pharmacy.payment.collect', 'pharmacy.shift.manage']);
        $shift = $this->shift($cashier);
        $d = app(PharmacySaleService::class)->finalize($cashier, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'shift_id' => $shift['id'], 'tenders' => [['method' => 'cash', 'amount_minor' => '1000']]], $this->key());
        $this->assertSame('finalized', $d['status']);
        $this->rejected(403, fn () => app(DispensingService::class)->handover($cashier, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'recipient_name' => 'Someone'], $this->key()));
    }

    public function test_review_is_invalidated_by_patient_change_and_live_credential_revocation(): void
    {
        $product = $this->product('RX-LIVE', '1000', ['requires_prescription' => true]);
        $batch = $this->receive($product, '5');
        $rx = $this->prescription($product, '3');
        $d = $this->reviewed($this->prepared($product, $batch, '1', $rx));
        $shift = $this->shift();
        DB::table('ph_patients')->where('public_id', $rx['patient_id'])->increment('version');
        $this->rejected(409, fn () => app(DispensingService::class)->assertPrintable($this->owner, $this->pharmacy, DB::table('ph_dispensings')->where('public_id', $d['id'])->first()));
        $finalize = function () use (&$d, $shift): array {
            return app(PharmacySaleService::class)->finalize($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'shift_id' => $shift['id'], 'tenders' => [['method' => 'cash', 'amount_minor' => '1000']]], $this->key());
        };
        $this->rejected(409, $finalize);
        $d = $this->reviewed($d);
        DB::table('ph_credentials')->update(['status' => 'inactive']);
        $this->rejected(403, $finalize);
        $this->assertDatabaseCount('ph_sales', 0);
        $this->assertDatabaseCount('ph_tenders', 0);
        $this->assertEquals('5', DB::table('inv_stock_balances')->where('warehouse_id', $this->pharmacy->warehouse_id)->sum('quantity_on_hand'));
    }

    public function test_controlled_cold_chain_and_rx_otc_bypass_are_blocked(): void
    {
        foreach ([['controlled' => true], ['cold_chain' => true], ['requires_prescription' => true]] as $i => $flags) {
            $product = $this->product('BLOCK-'.$i, '1000', $flags);
            $this->rejected($i < 2 ? 409 : 422, fn () => $this->draft($product, '1'));
        }
        $this->assertDatabaseCount('ph_dispensings', 0);
    }

    public function test_packaging_revision_blocks_unpaid_sale_without_changing_paid_base_snapshot(): void
    {
        $product = $this->product('PACK-REVISION', '1000', ['packagings' => [['code' => 'BOX', 'name' => 'Box 10', 'quantity_multiplier' => '10']]]);
        $pack = DB::table('merch_packagings')->where('sku_id', $product->sku_id)->where('is_active', true)->first();
        $this->setupCommand('price.save', ['pharmacy_id' => $this->pharmacy->public_id, 'product_id' => $product->public_id, 'packaging_id' => $pack->id, 'price_minor' => '10000']);
        $batch = $this->receive($product, '20');
        $draft = fn (int $packId) => app(DispensingService::class)->create($this->owner, ['pharmacy_id' => $this->pharmacy->public_id, 'mode' => 'otc', 'lines' => [['product_id' => $product->public_id, 'packaging_id' => $packId, 'quantity' => '1']]], $this->key());
        $d = $this->reviewed($this->prepare($draft($pack->id), $batch, '10'));
        $this->assertTrue(BigDecimal::of($d['lines'][0]['quantity'])->isEqualTo('10'));
        $this->assertTrue(BigDecimal::of($d['lines'][0]['sale_quantity'])->isEqualTo('1'));
        $this->assertSame('Box 10', $d['lines'][0]['sale_unit']);
        $revise = function (string $name, string $factor) use ($product): array {
            $sku = DB::table('merch_skus')->find($product->sku_id);

            return app(MerchandiseService::class)->saveSku($sku->catalog_id, ['sku' => $sku->sku, 'name' => $sku->name, 'base_unit' => $sku->base_unit, 'tracking_mode' => $sku->tracking_mode, 'requires_expiry' => true, 'expected_version' => $sku->version, 'packagings' => [['code' => 'BOX', 'name' => $name, 'quantity_multiplier' => $factor]]], $sku->id);
        };
        $revised = $revise('Box 20', '20');
        $this->assertSame((int) $product->version, (int) DB::table('ph_products')->where('id', $product->id)->value('version'));
        $shift = $this->shift();
        $this->rejected(409, fn () => app(PharmacySaleService::class)->finalize($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'shift_id' => $shift['id'], 'tenders' => [['method' => 'cash', 'amount_minor' => '10000']]], $this->key()));
        $this->assertDatabaseCount('ph_sales', 0);
        $this->assertDatabaseCount('ph_tenders', 0);
        $this->assertTrue(BigDecimal::of((string) DB::table('inv_stock_balances')->where('warehouse_id', $this->pharmacy->warehouse_id)->sum('quantity_on_hand'))->isEqualTo('20'));
        app(DispensingService::class)->cancel($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'reason' => 'Refresh changed packaging'], $this->key());
        $newPack = $revised['packagings'][0];
        $this->setupCommand('price.save', ['pharmacy_id' => $this->pharmacy->public_id, 'product_id' => $product->public_id, 'packaging_id' => $newPack['id'], 'price_minor' => '20000']);
        $d = $this->reviewed($this->prepare($draft($newPack['id']), $batch, '20'));
        $d = app(PharmacySaleService::class)->finalize($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'shift_id' => $shift['id'], 'tenders' => [['method' => 'cash', 'amount_minor' => '20000']]], $this->key());
        $revise('Box 30', '30');
        $d = app(DispensingService::class)->handover($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'recipient_name' => 'Synthetic recipient'], $this->key());
        $this->assertTrue(BigDecimal::of($d['lines'][0]['quantity'])->isEqualTo('20'));
        $this->assertSame('20.000000', $d['lines'][0]['quantity_multiplier']);
        $this->assertSame('Box 20', $d['lines'][0]['sale_unit']);
    }

    public function test_quantity_mismatch_rolls_back_all_reservations_and_cancel_releases_expired_claims(): void
    {
        $product = $this->product('ROLLBACK', '1000');
        $batch = $this->receive($product, '5');
        $d = $this->draft($product, '2');
        $this->rejected(422, fn () => $this->prepare($d, $batch, '1'));
        $this->assertDatabaseCount('ph_dispensing_allocations', 0);
        $d = $this->prepare($d, $batch, '2');
        $this->travel(16)->minutes();
        $this->rejected(409, fn () => $this->reviewed($d));
        $d = app(DispensingService::class)->cancel($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'reason' => 'Expired preparation'], $this->key());
        $this->assertSame('cancelled', $d['status']);
        $this->assertEquals('0', DB::table('inv_stock_balances')->where('warehouse_id', $this->pharmacy->warehouse_id)->sum('quantity_reserved'));
    }

    public function test_sensitive_profile_is_encrypted_and_not_exposed_in_journal_audit_or_outbox(): void
    {
        $patient = $this->patient();
        $this->assertStringNotContainsString('SECRET-PATIENT', DB::table('ph_patients')->value('encrypted_profile'));
        $this->assertStringNotContainsString('SECRET-PATIENT', json_encode(DB::table('ph_commands')->get()));
        $this->assertStringNotContainsString('SECRET-PATIENT', json_encode(DB::table('audit_logs')->get()));
        $this->assertStringNotContainsString('SECRET-PATIENT', json_encode(DB::table('ph_outbox')->get()));
        $reader = $this->member(['pharmacy.view', 'pharmacy.patient.view']);
        $list = app(PatientService::class)->list($reader, ['pharmacy_id' => $this->pharmacy->public_id]);
        $this->assertArrayNotHasKey('allergies', $list['records'][0]);
        $this->rejected(403, fn () => app(PatientService::class)->show($reader, $patient['id']));
        $this->assertSame('SECRET-PATIENT', app(PatientService::class)->show($this->owner, $patient['id'])['name']);
    }

    public function test_phone_lookup_is_exact_scoped_masked_and_never_merges_duplicate_patients(): void
    {
        $input = ['pharmacy_id' => $this->pharmacy->public_id, 'name' => 'Synthetic duplicate one', 'phone' => '0912 345 678', 'date_of_birth' => '1990-01-01', 'allergies' => ['SECRET-LOOKUP-ALLERGY'], 'processing_basis' => 'consent', 'processing_evidence' => 'Synthetic lookup consent'];
        $one = app(PatientService::class)->save($this->owner, $input, $this->key());
        $two = app(PatientService::class)->save($this->owner, array_replace($input, ['name' => 'Synthetic duplicate two', 'phone' => '+84 (912) 345-678']), $this->key());
        $reader = $this->member(['pharmacy.view', 'pharmacy.patient.view']);
        $key = $this->key();
        $lookup = ['pharmacy_id' => $this->pharmacy->public_id, 'phone' => '+84912345678'];
        $result = app(PatientService::class)->lookup($reader, $lookup, $key);
        $this->assertEqualsCanonicalizing([$one['id'], $two['id']], array_column($result['records'], 'id'));
        $this->assertFalse($result['meta']['auto_merge']);
        $this->assertSame('+84••••••678', $result['records'][0]['phone_masked']);
        $this->assertArrayNotHasKey('date_of_birth', $result['records'][0]);
        $this->assertArrayNotHasKey('allergies', $result['records'][0]);
        $this->assertArrayNotHasKey('phone', $result['records'][0]);
        $this->assertSame(64, strlen(DB::table('ph_patients')->where('public_id', $one['id'])->value('phone_lookup')));
        $replay = app(PatientService::class)->lookup($reader, array_replace($lookup, ['phone' => '0912 345 678']), $key);
        $this->assertTrue($replay['replayed']);
        $this->assertArrayNotHasKey('records', $replay);
        foreach (['ph_commands', 'ph_outbox', 'audit_logs'] as $table) {
            $this->assertStringNotContainsString('0912345678', json_encode(DB::table($table)->get()));
            $this->assertStringNotContainsString('+84912345678', json_encode(DB::table($table)->get()));
        }
        $origin = $this->pharmacy;
        $target = $this->setupCommand('pharmacy.create', ['chain_id' => DB::table('ph_chains')->where('id', $origin->chain_id)->value('public_id'), 'name' => 'Phone lookup target', 'code' => 'PHONE-TARGET']);
        $this->rejected(403, fn () => app(PatientService::class)->lookup($reader, ['pharmacy_id' => $target['id'], 'phone' => '0912345678'], $this->key()));
        $this->assertSame([], app(PatientService::class)->lookup($this->owner, ['pharmacy_id' => $target['id'], 'phone' => '0912345678'], $this->key())['records']);
        $targetPatient = app(PatientService::class)->save($this->owner, array_replace($input, ['pharmacy_id' => $target['id'], 'name' => 'Synthetic target patient']), $this->key());
        $this->assertSame([$targetPatient['id']], array_column(app(PatientService::class)->lookup($this->owner, ['pharmacy_id' => $target['id'], 'phone' => '0912345678'], $this->key())['records'], 'id'));
        $this->assertDatabaseCount('ph_patients', 3);
        $otherChain = $this->setupCommand('setup', ['chain_name' => 'Other phone lookup chain', 'pharmacy_name' => 'Other chain site', 'code' => 'OTHERPHONE']);
        $otherPatient = app(PatientService::class)->save($this->owner, array_replace($input, ['pharmacy_id' => $otherChain['pharmacy']['id']]), $this->key());
        $this->assertNotSame(DB::table('ph_patients')->where('public_id', $one['id'])->value('phone_lookup'), DB::table('ph_patients')->where('public_id', $otherPatient['id'])->value('phone_lookup'));
        $this->rejected(403, fn () => app(PatientService::class)->lookup($reader, ['pharmacy_id' => $otherChain['pharmacy']['id'], 'phone' => '0912345678'], $this->key()));
        DB::table('ph_memberships')->where('admin_id', $reader->id)->update(['status' => 'inactive']);
        $this->rejected(403, fn () => app(PatientService::class)->lookup($reader, $lookup, $key));
        try {
            app(PatientService::class)->lookup($this->owner, ['pharmacy_id' => $origin->public_id, 'phone' => '0912'], $this->key());
            $this->fail('Partial phone must not be searchable.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('phone', $e->errors());
        }
    }

    public function test_phone_index_updates_and_previous_application_key_supports_rotation(): void
    {
        $original = config('app.key');
        $previous = config('app.previous_keys');
        $keyA = 'base64:'.base64_encode(str_repeat('A', 32));
        $keyB = 'base64:'.base64_encode(str_repeat('B', 32));
        $reset = function (string $key, array $old): void {
            config(['app.key' => $key, 'app.previous_keys' => $old]);
            app()->forgetInstance('encrypter');
            Crypt::clearResolvedInstance('encrypter');
        };
        try {
            $reset($keyA, []);
            $input = ['pharmacy_id' => $this->pharmacy->public_id, 'name' => 'Synthetic rotation patient', 'phone' => '0912345678', 'processing_basis' => 'consent', 'processing_evidence' => 'Synthetic consent'];
            $patient = app(PatientService::class)->save($this->owner, $input, $this->key());
            $oldIndex = DB::table('ph_patients')->where('public_id', $patient['id'])->value('phone_lookup');
            $reset($keyB, [$keyA]);
            $lookup = ['pharmacy_id' => $this->pharmacy->public_id, 'phone' => '+84912345678'];
            $this->assertSame($patient['id'], app(PatientService::class)->lookup($this->owner, $lookup, $this->key())['records'][0]['id']);
            $patient = app(PatientService::class)->save($this->owner, $input + ['patient_id' => $patient['id'], 'expected_version' => 1], $this->key());
            $this->assertNotSame($oldIndex, DB::table('ph_patients')->where('public_id', $patient['id'])->value('phone_lookup'));
            $reset($keyB, []);
            $this->assertSame($patient['id'], app(PatientService::class)->lookup($this->owner, $lookup, $this->key())['records'][0]['id']);
            $patient = app(PatientService::class)->save($this->owner, array_replace($input, ['patient_id' => $patient['id'], 'expected_version' => $patient['version'], 'phone' => '0987654321']), $this->key());
            $this->assertSame([], app(PatientService::class)->lookup($this->owner, $lookup, $this->key())['records']);
            $this->assertSame($patient['id'], app(PatientService::class)->lookup($this->owner, ['pharmacy_id' => $this->pharmacy->public_id, 'phone' => '0987654321'], $this->key())['records'][0]['id']);
            app(PatientService::class)->save($this->owner, array_replace($input, ['patient_id' => $patient['id'], 'expected_version' => $patient['version'], 'phone' => null]), $this->key());
            $this->assertNull(DB::table('ph_patients')->where('public_id', $patient['id'])->value('phone_lookup'));
        } finally {
            $reset($original, $previous);
        }
    }

    public function test_transaction_lists_apply_nonclinical_search_local_dates_status_and_real_pagination(): void
    {
        $this->travelTo(now()->setTimezone('UTC')->setDate(2026, 10, 21)->setTime(16, 30));
        $product = $this->product('FILTERS', '1000');
        $rxA = $this->prescription($product, '1');
        $batch = $this->receive($product, '2');
        $dA = $this->reviewed($this->prepared($product, $batch, '1'));
        $shiftA = $this->shift();
        $saleA = app(PharmacySaleService::class)->finalize($this->owner, ['dispensing_id' => $dA['id'], 'expected_version' => $dA['version'], 'shift_id' => $shiftA['id'], 'tenders' => [['method' => 'cash', 'amount_minor' => '1000']]], $this->key());
        app(PharmacySaleService::class)->closeShift($this->owner, ['shift_id' => $shiftA['id'], 'expected_version' => 1, 'closing_minor' => '1000'], $this->key());
        $this->travel(1)->hours();
        $rxB = $this->prescription($product, '1');
        $dB = $this->reviewed($this->prepared($product, $batch, '1'));
        $shiftB = $this->shift();
        $saleB = app(PharmacySaleService::class)->finalize($this->owner, ['dispensing_id' => $dB['id'], 'expected_version' => $dB['version'], 'shift_id' => $shiftB['id'], 'tenders' => [['method' => 'cash', 'amount_minor' => '1000']]], $this->key());
        $filter = ['pharmacy_id' => $this->pharmacy->public_id, 'from' => '2026-10-22', 'to' => '2026-10-22', 'per_page' => 1];
        $sales = app(PharmacySaleService::class)->listSales($this->owner, $filter + ['q' => $dB['id'], 'status' => 'paid']);
        $this->assertSame([$saleB['sale']['id']], array_column($sales['records'], 'id'));
        $this->assertSame(['page' => 1, 'per_page' => 1, 'total' => 1], $sales['meta']);
        $this->assertSame([], app(PharmacySaleService::class)->listSales($this->owner, $filter + ['q' => $saleA['sale']['id']])['records']);
        $this->assertSame([$shiftB['id']], array_column(app(PharmacySaleService::class)->listShifts($this->owner, $filter + ['status' => 'open', 'q' => 'POS-01'])['records'], 'id'));
        $this->assertSame([$rxB['id']], array_column(app(PrescriptionService::class)->list($this->owner, $filter + ['status' => 'verified'])['records'], 'id'));
        $this->assertSame([], app(PrescriptionService::class)->list($this->owner, $filter + ['q' => $rxA['id']])['records']);
        $this->assertSame([$dB['id']], array_column(app(DispensingService::class)->list($this->owner, $filter + ['status' => 'finalized'])['records'], 'id'));
        $this->assertSame([], app(DispensingService::class)->list($this->owner, $filter + ['q' => 'SECRET-PATIENT'])['records']);
        $page2 = app(DispensingService::class)->list($this->owner, ['pharmacy_id' => $this->pharmacy->public_id, 'per_page' => 1, 'page' => 2]);
        $this->assertSame([$dA['id']], array_column($page2['records'], 'id'));
        $this->assertSame(2, $page2['meta']['total']);
        try {
            app(PatientService::class)->list($this->owner, ['pharmacy_id' => $this->pharmacy->public_id, 'q' => 'SECRET-PATIENT']);
            $this->fail('Patient names must not use URL search.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_clinical_review_projection_requires_read_permissions_and_historical_identity_is_frozen(): void
    {
        $product = $this->product('RX-PROJECTION', '1000', ['requires_prescription' => true]);
        $batch = $this->receive($product, '1');
        $rx = $this->prescription($product, '1');
        $d = $this->reviewed($this->prepared($product, $batch, '1', $rx));
        $this->assertSame('Synthetic instruction', $d['lines'][0]['instructions']);
        $this->assertSame('Synthetic doctor', $rx['prescriber_name']);
        $reader = $this->member(['pharmacy.view', 'pharmacy.dispensing.view', 'pharmacy.patient.view']);
        $redacted = app(DispensingService::class)->show($reader, $d['id']);
        $this->assertSame('SECRET-PATIENT', $redacted['patient_display_name']);
        $this->assertNull($redacted['clinical_patient']);
        $this->assertNull($redacted['lines'][0]['instructions']);
        $shift = $this->shift();
        $d = app(PharmacySaleService::class)->finalize($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'shift_id' => $shift['id'], 'tenders' => [['method' => 'cash', 'amount_minor' => '1000']]], $this->key());
        app(PatientService::class)->save($this->owner, ['pharmacy_id' => $this->pharmacy->public_id, 'patient_id' => $rx['patient_id'], 'expected_version' => 1, 'name' => 'Changed current profile', 'processing_basis' => 'consent', 'processing_evidence' => 'Synthetic update'], $this->key());
        $historical = app(DispensingService::class)->show($this->owner, $d['id']);
        $this->assertSame('SECRET-PATIENT', $historical['patient_display_name']);
        // All available stock was issued; eligibility at handover must not require another stock balance.
        $this->assertSame('handed_over', app(DispensingService::class)->handover($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'recipient_name' => 'Synthetic recipient'], $this->key())['status']);
    }

    public function test_replay_reauthorizes_revoked_native_permission_and_expected_version_conflicts(): void
    {
        $actor = $this->member(['pharmacy.view', 'pharmacy.patient.manage']);
        $input = ['pharmacy_id' => $this->pharmacy->public_id, 'name' => 'SECRET-PATIENT', 'processing_basis' => 'consent', 'processing_evidence' => 'Synthetic consent'];
        $key = $this->key();
        app(PatientService::class)->save($actor, $input, $key);
        DB::table('ph_memberships')->where('admin_id', $actor->id)->update(['permissions' => json_encode(['pharmacy.view'])]);
        $this->rejected(403, fn () => app(PatientService::class)->save($actor, $input, $key));
        $this->assertDatabaseCount('ph_patients', 1);
        $product = $this->product('VERSION', '1000');
        $batch = $this->receive($product, '5');
        $d = $this->prepare($this->draft($product, '1'), $batch, '1');
        $this->rejected(409, fn () => app(DispensingService::class)->review($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => 1, 'decision' => 'approved', 'reason' => 'Stale'], $this->key()));
    }

    public function test_cross_branch_partial_fill_uses_explicit_consent_and_one_chain_claim_aggregate(): void
    {
        $product = $this->product('RX-CHAIN', '1000', ['requires_prescription' => true]);
        $origin = $this->pharmacy;
        $batch = $this->receive($product, '5');
        $rx = $this->prescription($product, '5');
        $d = $this->reviewed($this->prepared($product, $batch, '3', $rx));
        $shift = $this->shift();
        app(PharmacySaleService::class)->finalize($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'shift_id' => $shift['id'], 'tenders' => [['method' => 'cash', 'amount_minor' => '3000']]], $this->key());
        $target = $this->setupCommand('pharmacy.create', ['chain_id' => DB::table('ph_chains')->where('id', $origin->chain_id)->value('public_id'), 'name' => 'Target synthetic site', 'code' => 'TARGET']);
        $this->pharmacy = DB::table('ph_pharmacies')->where('public_id', $target['id'])->first();
        $this->setupCommand('credential.save', ['pharmacy_id' => $target['id'], 'admin_id' => $this->owner->id, 'license_number' => 'TARGET-SYNTHETIC', 'abilities' => ['review', 'dispense', 'handover'], 'valid_from' => now()->subDay()->toDateTimeString(), 'valid_until' => now()->addYear()->toDateTimeString(), 'reason' => 'Synthetic target fixture']);
        $this->setupCommand('price.save', ['pharmacy_id' => $target['id'], 'product_id' => $product->public_id, 'price_minor' => '1000']);
        $batch = $this->receive($product, '5');
        $this->rejected(403, fn () => $this->draft($product, '2', $rx));
        $permit = app(PrescriptionService::class)->transfer($this->owner, ['prescription_id' => $rx['id'], 'expected_version' => $rx['version'], 'target_pharmacy_id' => $target['id'], 'consented_at' => now()->toIso8601String(), 'consent_evidence' => 'SECRET-CROSS-BRANCH-CONSENT'], $this->key());
        $this->assertSame($rx['id'], app(PrescriptionService::class)->list($this->owner, ['pharmacy_id' => $target['id']])['records'][0]['id']);
        $reader = $this->member(['pharmacy.view', 'pharmacy.dispensing.manage', 'pharmacy.dispensing.view', 'pharmacy.prescription.view', 'pharmacy.prescription.transfer', 'pharmacy.patient.view', 'pharmacy.patient.sensitive']);
        $this->assertSame($rx['id'], app(PrescriptionService::class)->show($reader, $rx['id'], $target['id'])['id']);
        $this->rejected(403, fn () => app(PrescriptionService::class)->show($reader, $rx['id']));
        $over = $this->draft($product, '3', $rx);
        $this->rejected(409, fn () => $this->prepare($over, $batch, '3'));
        $remaining = $this->prepared($product, $batch, '2', $rx);
        $this->assertSame('prepared', $remaining['status']);
        $remaining = $this->reviewed($remaining);
        $cashier = $this->member(['pharmacy.view', 'pharmacy.dispensing.view', 'pharmacy.sale.create', 'pharmacy.payment.collect', 'pharmacy.shift.manage']);
        $this->counter = $target['counter']['id'];
        $targetShift = $this->shift($cashier);
        $remaining = app(PharmacySaleService::class)->finalize($cashier, ['dispensing_id' => $remaining['id'], 'expected_version' => $remaining['version'], 'shift_id' => $targetShift['id'], 'tenders' => [['method' => 'cash', 'amount_minor' => '2000']]], $this->key());
        $this->assertSame('finalized', $remaining['status']);
        $this->assertNull($remaining['patient_display_name']);
        $this->assertDatabaseCount('ph_prescriptions', 1);
        $this->assertDatabaseCount('ph_patients', 1);
        $this->assertStringNotContainsString('SECRET-CROSS-BRANCH-CONSENT', json_encode(DB::table('ph_prescription_transfers')->get()));
        $this->assertStringNotContainsString('SECRET-CROSS-BRANCH-CONSENT', json_encode(DB::table('ph_commands')->get()));
        app(PrescriptionService::class)->revokeTransfer($this->owner, ['transfer_id' => $permit['id'], 'expected_version' => $permit['version'], 'reason' => 'Synthetic withdrawal'], $this->key());
        $this->rejected(403, fn () => app(PrescriptionService::class)->show($reader, $rx['id'], $target['id']));
        $redacted = app(DispensingService::class)->show($reader, $remaining['id']);
        $this->assertNull($redacted['patient_display_name']);
        $this->assertNull($redacted['lines'][0]['instructions']);
        $grant = ['prescription_id' => $rx['id'], 'expected_version' => $rx['version'], 'target_pharmacy_id' => $target['id'], 'consented_at' => now()->toIso8601String(), 'consent_evidence' => 'Synthetic time-bounded consent'];
        $short = app(PrescriptionService::class)->transfer($this->owner, $grant + ['expires_at' => now()->addMinute()->toIso8601String()], $this->key());
        $this->travel(2)->minutes();
        $this->rejected(403, fn () => app(PrescriptionService::class)->show($reader, $rx['id'], $target['id']));
        $grant['consented_at'] = now()->toIso8601String();
        app(PrescriptionService::class)->transfer($this->owner, $grant, $this->key());
        app(PatientService::class)->save($this->owner, ['pharmacy_id' => $origin->public_id, 'patient_id' => $rx['patient_id'], 'expected_version' => 1, 'name' => 'New unshared patient profile', 'processing_basis' => 'consent', 'processing_evidence' => 'Synthetic profile update'], $this->key());
        $this->rejected(403, fn () => app(PrescriptionService::class)->show($reader, $rx['id'], $target['id']));
        $this->assertSame([], app(PrescriptionService::class)->list($reader, ['pharmacy_id' => $target['id']])['records']);
    }

    public function test_second_stock_commit_failure_rolls_back_first_issue_sale_tenders_and_outbox(): void
    {
        $product = $this->product('MULTI', '1000');
        $batch = $this->receive($product, '5');
        $d = $this->draft($product, '2');
        $d = app(DispensingService::class)->prepare($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'allocations' => [
            ['line_id' => $d['lines'][0]['id'], 'batch_id' => $batch, 'quantity' => '1'],
            ['line_id' => $d['lines'][0]['id'], 'batch_id' => $batch, 'quantity' => '1'],
        ]], $this->key());
        $d = $this->reviewed($d);
        $allocation = DB::table('ph_dispensing_allocations')->orderByDesc('id')->first();
        app(PharmacyInventoryService::class)->release($this->owner, $this->pharmacy, $allocation->reservation_id, $this->key());
        $shift = $this->shift();
        $beforeOutbox = DB::table('ph_outbox')->count();
        $this->rejected(409, fn () => app(PharmacySaleService::class)->finalize($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'shift_id' => $shift['id'], 'tenders' => [['method' => 'cash', 'amount_minor' => '2000']]], $this->key()));
        $this->assertDatabaseCount('ph_sales', 0);
        $this->assertDatabaseCount('ph_tenders', 0);
        $this->assertSame($beforeOutbox, DB::table('ph_outbox')->count());
        $this->assertEquals('5', DB::table('inv_stock_balances')->where('warehouse_id', $this->pharmacy->warehouse_id)->sum('quantity_on_hand'));
        $this->assertDatabaseMissing('inv_stock_documents', ['type' => 'issue']);
    }

    public function test_same_product_rx_lines_require_explicit_identity_and_preserve_instructions(): void
    {
        $product = $this->product('DISTINCT', '1000', ['requires_prescription' => true]);
        $patient = $this->patient();
        $rx = app(PrescriptionService::class)->create($this->owner, ['pharmacy_id' => $this->pharmacy->public_id, 'patient_id' => $patient['id'], 'reference' => $this->key(), 'issued_at' => now()->toDateString(), 'prescriber_name' => 'Doctor', 'facility_name' => 'Facility', 'original_reference' => 'paper:fixture', 'lines' => [
            ['product_id' => $product->public_id, 'quantity' => '2', 'instructions' => 'MORNING-PRIVATE'],
            ['product_id' => $product->public_id, 'quantity' => '3', 'instructions' => 'EVENING-PRIVATE'],
        ]], $this->key());
        $this->rejected(422, fn () => $this->draft($product, '1', $rx));
        $d = app(DispensingService::class)->create($this->owner, ['pharmacy_id' => $this->pharmacy->public_id, 'mode' => 'rx', 'prescription_id' => $rx['id'], 'lines' => [
            ['product_id' => $product->public_id, 'prescription_line_id' => $rx['lines'][0]['id'], 'quantity' => '1'],
            ['product_id' => $product->public_id, 'prescription_line_id' => $rx['lines'][1]['id'], 'quantity' => '1'],
        ]], $this->key());
        $this->assertCount(2, $d['lines']);
        $this->assertNotEquals($d['lines'][0]['id'], $d['lines'][1]['id']);
        $this->assertNotEquals($d['lines'][0]['prescription_line_id'], $d['lines'][1]['prescription_line_id']);
        $this->assertStringNotContainsString('MORNING-PRIVATE', json_encode(DB::table('ph_dispensing_lines')->get()));
    }

    public function test_return_and_cash_refund_caps_hold_across_new_keys_and_versions(): void
    {
        $product = $this->product('CAP', '1000');
        $batch = $this->receive($product, '5');
        $d = $this->reviewed($this->prepared($product, $batch, '1'));
        $shift = $this->shift();
        $d = app(PharmacySaleService::class)->finalize($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'shift_id' => $shift['id'], 'tenders' => [['method' => 'cash', 'amount_minor' => '1000']]], $this->key());
        $this->rejected(422, fn () => app(PharmacySaleService::class)->returnSale($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'lines' => [['line_id' => $d['lines'][0]['id'], 'quantity' => '2']], 'reason' => 'Over return'], $this->key()));
        $d = app(PharmacySaleService::class)->returnSale($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'lines' => [['line_id' => $d['lines'][0]['id'], 'quantity' => '1']], 'reason' => 'Return all'], $this->key());
        $this->assertSame('returned', $d['status']);
        $this->rejected(422, fn () => app(PharmacySaleService::class)->refund($this->owner, ['dispensing_id' => $d['id'], 'return_id' => $d['return_id'], 'expected_version' => $d['version'], 'shift_id' => $shift['id'], 'method' => 'cash', 'amount_minor' => '1001', 'reason' => 'Over refund'], $this->key()));
        $this->assertDatabaseCount('ph_refunds', 0);
        $this->assertDatabaseCount('ph_return_lines', 1);
    }

    private function setupCommand(string $action, array $input): array
    {
        return app(PharmacySetupService::class)->dispatch($this->owner, $action, $input, $this->key());
    }

    private function product(string $code, string $price, array $extra = []): object
    {
        $result = $this->setupCommand('product.save', $extra + ['pharmacy_id' => $this->pharmacy->public_id, 'name' => $code, 'code' => $code, 'base_unit' => 'vien', 'registration_number' => 'SYNTHETIC-'.$code, 'price_minor' => $price]);

        return DB::table('ph_products')->where('public_id', $result['id'])->first();
    }

    private function receive(object $product, string $quantity): int
    {
        $result = app(PharmacyOperationsService::class)->dispatch($this->owner, 'receipt.create', ['pharmacy_id' => $this->pharmacy->public_id, 'product_id' => $product->public_id, 'quantity' => $quantity, 'unit_cost' => '400', 'batch_code' => 'BATCH-'.$product->id, 'expires_at' => now()->addYear()->toDateString()], $this->key());
        app(PharmacyOperationsService::class)->dispatch($this->owner, 'receipt.inspect', ['pharmacy_id' => $this->pharmacy->public_id, 'receipt_id' => $result['id'], 'expected_version' => $result['version'], 'decision' => 'release', 'reason' => 'Synthetic inspected lot'], $this->key());

        return (int) $result['batch_id'];
    }

    private function patient(): array
    {
        return app(PatientService::class)->save($this->owner, ['pharmacy_id' => $this->pharmacy->public_id, 'name' => 'SECRET-PATIENT', 'allergies' => ['SECRET-ALLERGY'], 'processing_basis' => 'consent', 'processing_evidence' => 'Synthetic explicit consent'], $this->key());
    }

    private function prescription(object $product, string $quantity): array
    {
        $patient = $this->patient();
        $rx = app(PrescriptionService::class)->create($this->owner, ['pharmacy_id' => $this->pharmacy->public_id, 'patient_id' => $patient['id'], 'reference' => $this->key(), 'issued_at' => now()->toDateString(), 'prescriber_name' => 'Synthetic doctor', 'facility_name' => 'Synthetic facility', 'original_reference' => 'paper:synthetic-location', 'lines' => [['product_id' => $product->public_id, 'quantity' => $quantity, 'instructions' => 'Synthetic instruction']]], $this->key());

        return app(PrescriptionService::class)->verify($this->owner, ['prescription_id' => $rx['id'], 'expected_version' => $rx['version'], 'verification_method' => 'manual_review', 'reason' => 'Synthetic original checked'], $this->key());
    }

    private function draft(object $product, string $quantity, ?array $rx = null): array
    {
        return app(DispensingService::class)->create($this->owner, ['pharmacy_id' => $this->pharmacy->public_id, 'mode' => $rx ? 'rx' : 'otc', 'prescription_id' => $rx['id'] ?? null, 'lines' => [['product_id' => $product->public_id, 'quantity' => $quantity]]], $this->key());
    }

    private function prepare(array $d, int $batch, string $quantity): array
    {
        return app(DispensingService::class)->prepare($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'allocations' => [['line_id' => $d['lines'][0]['id'], 'batch_id' => $batch, 'quantity' => $quantity]]], $this->key());
    }

    private function prepared(object $product, int $batch, string $quantity, ?array $rx = null): array
    {
        return $this->prepare($this->draft($product, $quantity, $rx), $batch, $quantity);
    }

    private function reviewed(array $d): array
    {
        return app(DispensingService::class)->review($this->owner, ['dispensing_id' => $d['id'], 'expected_version' => $d['version'], 'decision' => 'approved', 'reason' => 'Synthetic professional review'], $this->key());
    }

    private function shift(?Admin $actor = null): array
    {
        return app(PharmacySaleService::class)->openShift($actor ?? $this->owner, ['pharmacy_id' => $this->pharmacy->public_id, 'counter_id' => $this->counter, 'opening_minor' => '0'], $this->key());
    }

    private function member(array $permissions): Admin
    {
        $admin = Admin::factory()->create(['must_change_password' => false]);
        $role = Role::query()->create(['name' => 'Pharmacy fixture', 'key' => 'ph-fixture-'.$admin->id]);
        $role->permissions()->sync(Permission::query()->whereIn('key', $permissions)->pluck('id'));
        AdminRoleAssignment::query()->create(['admin_id' => $admin->id, 'role_id' => $role->id, 'scope_type' => 'global']);
        DB::table('ph_memberships')->insert(['public_id' => (string) Str::uuid(), 'admin_id' => $admin->id, 'chain_id' => $this->pharmacy->chain_id, 'pharmacy_id' => $this->pharmacy->id, 'permissions' => json_encode($permissions), 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return $admin->fresh();
    }

    private function rejected(int $status, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected rejected transaction.');
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode(), $e->getMessage());
        }
    }

    private function key(): string
    {
        return (string) Str::uuid();
    }
}
