<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use App\Models\AcctItem;
use App\Models\AcctOrganization;
use App\Models\Admin;
use App\Models\AdminRoleAssignment;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Pos\PosNumbers;
use App\Support\SiteContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Customers\Services\CustomerService;
use Modules\Pharmacy\Services\Catalog\PharmacyBarcode;
use Modules\Pharmacy\Services\PharmacyData;
use Modules\Pharmacy\Services\PharmacyService;
use Modules\Pharmacy\Services\Read\PharmacyReadService;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class PharmacyAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    private array $p;

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
        $setup = $this->command('setup', ['chain_name' => 'Synthetic administration', 'pharmacy_name' => 'Synthetic pharmacy', 'code' => 'ADMIN-TEST']);
        $this->p = $setup['pharmacy'];
        $this->chain = $setup['chain'];
    }

    public function test_import_preview_conflicts_and_atomic_commit_with_replay(): void
    {
        $row = ['name' => 'Synthetic medicine', 'code' => 'IMPORT-1', 'base_unit' => 'vien', 'price_minor' => '100'];
        $preview = $this->command('import.preview', ['rows' => [$row], 'source_reference' => 'SYNTHETIC-SOURCE']);
        $this->assertTrue($preview['can_commit']);
        $this->assertStringNotContainsString('Synthetic medicine', DB::table('ph_import_batches')->value('encrypted_rows'));
        $key = (string) Str::uuid();
        $input = ['import_id' => $preview['id'], 'expected_version' => 1];
        $applied = $this->command('import.commit', $input, $key);
        $this->assertSame(1, $applied['created_count']);
        $this->assertTrue($this->command('import.commit', $input, $key)['replayed']);
        $this->assertDatabaseCount('ph_products', 1);
        $conflict = $this->command('import.preview', ['rows' => [$row, $row], 'source_reference' => 'SYNTHETIC-DUPLICATE']);
        $this->assertFalse($conflict['can_commit']);
        $this->assertCount(2, $conflict['errors']);
        $this->assertRejected(409, fn () => $this->command('import.commit', ['import_id' => $conflict['id'], 'expected_version' => 1]));
        $rows = [array_replace($row, ['code' => 'ATOMIC-1']), array_replace($row, ['code' => 'ATOMIC-2'])];
        $preview = $this->command('import.preview', ['rows' => $rows, 'source_reference' => 'SYNTHETIC-ATOMIC']);
        $this->command('product.save', $rows[1]);
        $this->assertRejected(409, fn () => $this->command('import.commit', ['import_id' => $preview['id'], 'expected_version' => 1]));
        $this->assertDatabaseMissing('ph_products', ['code' => 'ATOMIC-1']);
        $this->assertDatabaseHas('ph_import_batches', ['public_id' => $preview['id'], 'status' => 'preview']);
    }

    public function test_multi_pharmacy_price_publish_rolls_back_if_one_version_is_stale(): void
    {
        $product = $this->command('product.save', ['name' => 'Synthetic price', 'code' => 'PRICE', 'base_unit' => 'vien', 'price_minor' => '100']);
        $other = $this->command('pharmacy.create', ['chain_id' => $this->chain['id'], 'name' => 'Second synthetic', 'code' => 'PRICE-2']);
        $this->command('price.save', ['pharmacy_id' => $other['id'], 'product_id' => $product['id'], 'price_minor' => '100']);
        $input = ['product_id' => $product['id'], 'price_minor' => '150', 'targets' => [['pharmacy_id' => $this->p['id'], 'expected_version' => 1], ['pharmacy_id' => $other['id'], 'expected_version' => 2]]];
        $this->assertRejected(409, fn () => $this->command('price.publish', $input));
        $this->assertSame([100, 100], DB::table('ph_prices')->orderBy('id')->pluck('price_minor')->map(fn ($n) => (int) $n)->all());
        $input['targets'][1]['expected_version'] = 1;
        $publication = $this->command('price.publish', $input);
        $this->assertSame('applied', $publication['status']);
        $this->assertCount(2, $publication['acknowledgements']);
        $this->assertSame([150, 150], DB::table('ph_prices')->orderBy('id')->pluck('price_minor')->map(fn ($n) => (int) $n)->all());
    }

    public function test_gs1_parser_and_catalog_scan_never_auto_confirm_allocation(): void
    {
        $parser = app(PharmacyBarcode::class);
        $parsed = $parser->parse(']d201095060001343521727123110LOT-SYNTHETIC');
        $this->assertSame('2027-12-31', $parsed['expires_at']);
        $this->assertSame('LOT-SYNTHETIC', $parsed['batch_code']);
        $this->assertTrue($parser->parse('09506000134352')['requires_lot_confirmation']);
        $this->assertRejected(422, fn () => $parser->parse('(01)09506000134352(17)271200(10)LOT'));
        $this->assertRejected(422, fn () => $parser->parse('(01)09506000134353(10)LOT'));
        $this->assertRejected(422, fn () => $parser->parse('(01)09506000134352(10)A(10)B'));
        $this->command('product.save', ['name' => 'Synthetic barcode', 'code' => 'BARCODE', 'base_unit' => 'vien', 'price_minor' => '100', 'barcode' => '09506000134352']);
        $scan = app(PharmacyReadService::class)->read($this->owner, 'scan', ['pharmacy_id' => $this->p['id'], 'code' => '(01)09506000134352(10)LOT']);
        $this->assertTrue($scan['matched']);
        $this->assertFalse($scan['allocation_confirmed']);
        $this->assertSame([], $scan['lots']);
        $this->command('product.save', ['name' => 'Synthetic unrelated suffix', 'code' => 'SUFFIX', 'base_unit' => 'vien', 'price_minor' => '100', 'barcode' => '036000291452']);
        $unrelated = app(PharmacyReadService::class)->read($this->owner, 'scan', ['pharmacy_id' => $this->p['id'], 'code' => '17036000291452']);
        $this->assertFalse($unrelated['matched']);
        $this->assertSame([], $unrelated['records']);
    }

    public function test_saved_filter_strips_sensitive_search_and_uses_optimistic_version(): void
    {
        $filter = $this->command('filter.save', ['resource' => 'inventory', 'name' => 'Synthetic filter', 'filters' => ['status' => 'active', 'q' => 'PERSON SECRET', 'patient_id' => 'PRIVATE']]);
        $this->assertSame(['status' => 'active'], $filter['filters']);
        $this->assertRejected(409, fn () => $this->command('filter.save', ['resource' => 'inventory', 'name' => 'Synthetic filter', 'filters' => ['status' => 'inactive']]));
    }

    public function test_packaging_scan_returns_the_active_pack_price_and_conversion_without_guessing_base_price(): void
    {
        $product = $this->command('product.save', ['name' => 'Synthetic packaged medicine', 'code' => 'PACK-SCAN', 'base_unit' => 'vien', 'price_minor' => '100', 'packagings' => [['code' => 'HOP', 'name' => 'Hộp 10 viên', 'quantity_multiplier' => '10', 'barcode' => '09506000134352']]]);
        $pack = DB::table('merch_packagings')->first();
        $this->command('price.save', ['product_id' => $product['id'], 'packaging_id' => $pack->id, 'price_minor' => '900']);
        $reader = app(PharmacyReadService::class);
        $scan = $reader->read($this->owner, 'scan', ['pharmacy_id' => $this->p['id'], 'code' => '09506000134352']);
        $record = $scan['records'][0];
        $this->assertEquals($pack->id, $record['packaging_id']);
        $this->assertSame('Hộp 10 viên', $record['packaging_name']);
        $this->assertSame('HOP', $record['packaging_code']);
        $this->assertEquals(10, $record['quantity_multiplier']);
        $this->assertEquals(900, $record['price_minor']);
        $this->assertFalse($scan['allocation_confirmed']);
        $catalog = $reader->read($this->owner, 'products', ['pharmacy_id' => $this->p['id']]);
        $this->assertEquals($pack->id, $catalog['records'][0]['packagings'][0]['id']);
        DB::table('merch_packagings')->where('id', $pack->id)->update(['is_active' => false]);
        $this->assertFalse($reader->read($this->owner, 'scan', ['pharmacy_id' => $this->p['id'], 'code' => '09506000134352'])['matched']);
    }

    public function test_pos_rounding_bounds_and_allocation_preserve_total(): void
    {
        $this->assertSame(1, PosNumbers::amount('0.5', 1));
        $this->assertSame([34, 33, 33], PosNumbers::allocate(100, [1, 1, 1]));
        $this->assertSame('0.123456', PosNumbers::quantity('0.123456'));
        foreach (range(1, 40) as $total) {
            $this->assertSame($total, array_sum(PosNumbers::allocate($total, [1, 3, 9])));
        }
    }

    public function test_chain_report_and_navigation_only_project_native_permissions(): void
    {
        $other = $this->command('pharmacy.create', ['chain_id' => $this->chain['id'], 'name' => 'Restricted second site', 'code' => 'REPORT-2']);
        $reader = Admin::query()->create(['name' => 'Synthetic reader', 'username' => 'synthetic-reader', 'email' => 'reader@example.test', 'password' => 'SyntheticPassword123!', 'is_active' => true, 'status' => 'active', 'auth_version' => 1, 'must_change_password' => false]);
        $role = Role::query()->create(['name' => 'Synthetic report role', 'key' => 'synthetic-report']);
        $role->permissions()->sync(Permission::query()->whereIn('key', ['pharmacy.view', 'pharmacy.report.view', 'pharmacy.patient.view'])->pluck('id'));
        AdminRoleAssignment::query()->create(['admin_id' => $reader->id, 'role_id' => $role->id, 'scope_type' => 'global']);
        foreach ([[$this->p, ['pharmacy.view', 'pharmacy.report.view']], [$other, ['pharmacy.view']]] as [$site, $permissions]) {
            PharmacyData::insert('ph_memberships', ['chain_id' => $site['chain_id'], 'pharmacy_id' => DB::table('ph_pharmacies')->where('public_id', $site['id'])->value('id'), 'admin_id' => $reader->id, 'permissions' => json_encode($permissions)]);
        }
        $service = app(PharmacyReadService::class);
        $report = $service->read($reader, 'reports', ['pharmacy_id' => $this->p['id']]);
        $this->assertSame([$this->p['id']], array_column($report['sites'], 'id'));
        $bootstrap = $service->bootstrap($reader, $this->p['id']);
        $this->assertNotContains('pharmacy.patient.view', $bootstrap['navigation_permissions']);
        $this->assertContains('pharmacy.report.view', $bootstrap['navigation_permissions']);
        DB::table('ph_chains')->where('public_id', $this->chain['id'])->update(['status' => 'inactive']);
        $this->assertRejected(404, fn () => $service->read($reader, 'reports', ['pharmacy_id' => $this->p['id']]));
    }

    public function test_optional_integration_options_preserve_native_book_and_organization_authority(): void
    {
        $reader = app(PharmacyReadService::class);
        $scope = ['pharmacy_id' => $this->p['id']];
        $this->assertFalse($reader->read($this->owner, 'customer-books', $scope)['available']);
        $this->assertFalse($reader->read($this->owner, 'accounting-organizations', $scope)['available']);
        foreach (['customers', 'accounting-tax'] as $module) {
            app(ModuleManager::class)->install($module);
            app(ModuleManager::class)->enable($module);
        }
        $context = app(SiteContext::class);
        $context->set(null, 'website-main');
        $book = app(CustomerService::class)->createBook($this->owner, ['name' => 'Synthetic adapter book', 'code' => 'SYNTHETIC'], (string) Str::uuid());
        $org = AcctOrganization::query()->create(['name' => 'Synthetic org', 'legal_name' => 'Synthetic org', 'default_currency' => 'VND', 'status' => 'active']);
        $item = AcctItem::query()->create(['organization_id' => $org->id, 'name' => 'Synthetic mapping item', 'kind' => 'goods', 'unit' => 'vien', 'tax_category' => 'not_declared', 'status' => 'active']);
        $this->assertSame($book['id'], $reader->read($this->owner, 'customer-books', $scope)['records'][0]['id']);
        $this->assertSame('website-main', $context->websiteKey());
        $this->assertSame($org->id, $reader->read($this->owner, 'accounting-organizations', $scope)['records'][0]['id']);
        $this->assertEquals($item->id, $reader->read($this->owner, 'accounting-items', $scope + ['organization_id' => $org->id])['records'][0]->id);
        $limited = Admin::factory()->create(['must_change_password' => false]);
        $role = Role::query()->create(['name' => 'Synthetic adapters role', 'key' => 'synthetic-adapters']);
        $keys = ['pharmacy.view', 'pharmacy.integrations.manage'];
        $role->permissions()->sync(Permission::query()->whereIn('key', $keys)->pluck('id'));
        AdminRoleAssignment::query()->create(['admin_id' => $limited->id, 'role_id' => $role->id, 'scope_type' => 'global']);
        PharmacyData::insert('ph_memberships', ['chain_id' => $this->p['chain_id'], 'pharmacy_id' => DB::table('ph_pharmacies')->where('public_id', $this->p['id'])->value('id'), 'admin_id' => $limited->id, 'permissions' => json_encode($keys)]);
        $limited = $limited->fresh();
        $this->assertSame([], $reader->read($limited, 'customer-books', $scope)['records']);
        $this->assertSame([], $reader->read($limited, 'accounting-organizations', $scope)['records']);
        $this->assertRejected(403, fn () => $reader->read($limited, 'accounting-items', $scope + ['organization_id' => $org->id]));
        $this->assertSame('website-main', $context->websiteKey());
    }

    public function test_native_reader_uses_local_day_boundaries_once_for_delegated_and_generic_lists(): void
    {
        $expected = [];
        foreach (['2026-10-21 16:59:59', '2026-10-21 17:00:00', '2026-10-22 16:59:59', '2026-10-22 17:00:00'] as $index => $utc) {
            $patient = $this->command('patient.save', ['name' => 'Synthetic boundary '.$index, 'processing_basis' => 'consent', 'processing_evidence' => 'Synthetic date-boundary consent']);
            DB::table('ph_patients')->where('public_id', $patient['id'])->update(['created_at' => $utc]);
            $product = $this->command('product.save', ['name' => 'Synthetic boundary '.$index, 'code' => 'BOUNDARY-'.$index, 'base_unit' => 'vien', 'price_minor' => '100']);
            DB::table('ph_products')->where('public_id', $product['id'])->update(['created_at' => $utc]);
            if (in_array($index, [1, 2], true)) {
                $expected['patients'][] = $patient['id'];
                $expected['products'][] = $product['id'];
            }
        }
        $reader = app(PharmacyReadService::class);
        foreach (['patients', 'products'] as $kind) {
            $rows = $reader->read($this->owner, $kind, ['pharmacy_id' => $this->p['id'], 'from' => '2026-10-22', 'to' => '2026-10-22'])['records'];
            $this->assertEqualsCanonicalizing($expected[$kind], array_column($rows, 'id'));
            $utcRows = $reader->read($this->owner, $kind, ['pharmacy_id' => $this->p['id'], 'from' => '2026-10-21T17:00:00Z', 'to' => '2026-10-22T16:59:59Z'])['records'];
            $this->assertEqualsCanonicalizing($expected[$kind], array_column($utcRows, 'id'));
        }
    }

    private function command(string $action, array $input, ?string $key = null): array
    {
        return app(PharmacyService::class)->command($this->owner, $action, $input + ['pharmacy_id' => $this->p['id'] ?? null], $key ?? (string) Str::uuid());
    }

    private function assertRejected(int $status, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected rejection '.$status);
        } catch (HttpExceptionInterface $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
    }
}
