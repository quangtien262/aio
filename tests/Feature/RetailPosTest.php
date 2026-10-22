<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ModuleInstallation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Inventory\Services\InventoryV2Service;
use Tests\TestCase;

class RetailPosTest extends TestCase
{
    use RefreshDatabase;

    private array $branch;

    private Admin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = Admin::query()->findOrFail(1);
        $this->owner->forceFill(['must_change_password' => false])->save();
        $this->actingAs($this->owner, 'admin')->withSession(['admin_auth_version' => $this->owner->auth_version]);
        foreach (['merchandise', 'inventory', 'retail-pos'] as $key) {
            $this->postJson('/admin/api/modules/'.$key.'/install')->assertOk();
            $this->postJson('/admin/api/modules/'.$key.'/enable')->assertOk();
        }
        $this->branch = $this->postCommand('setup', ['name' => 'Chuỗi thử nghiệm'])->assertOk()->json('data');
    }

    public function test_product_sku_is_generated_when_blank_and_replayed_without_creating_duplicates(): void
    {
        $this->product('HH000002', '0', '15000');
        foreach (['missing', '', null, '   '] as $index => $value) {
            $data = ['branch_id' => $this->branch['id'], 'name' => 'Hàng tự tạo '.$index, 'price_minor' => '15000'];
            if ($value !== 'missing') {
                $data['sku'] = $value;
            }
            $key = 'auto-sku-product-'.$index;
            $product = $this->postCommand('products', $data, $key)->assertOk()->json('data');
            $this->assertMatchesRegularExpression('/^HH\d{6,}$/', $product['sku']);
            $this->assertNotSame('HH000002', $product['sku']);
            $this->postCommand('products', $data, $key)->assertOk()->assertJsonPath('data.id', $product['id'])->assertJsonPath('data.sku', $product['sku']);
            $this->getJson('/admin/api/retail/products?branch_id='.$this->branch['id'].'&query='.$product['sku'])->assertOk()->assertJsonPath('data.0.sku', $product['sku']);
        }
        $this->assertDatabaseCount('merch_skus', 5);
        $this->assertSame(5, DB::table('merch_skus')->distinct()->count('sku'));
        $this->postCommand('products', ['branch_id' => $this->branch['id'], 'sku' => 'HH000002', 'name' => 'Trùng mã', 'price_minor' => '10000'])->assertUnprocessable();
        $this->postCommand('products', ['branch_id' => $this->branch['id'], 'sku' => 'INVALID SKU', 'name' => 'Sai mã', 'price_minor' => '10000'])->assertUnprocessable();
    }

    public function test_cash_sale_commits_stock_tender_and_replays_after_lost_response(): void
    {
        $product = $this->product('COLA', '24', '15000');
        $shift = $this->shift();
        $sale = $this->sale($product, $shift, '6');
        $body = ['expected_version' => $sale['version'], 'tenders' => [['method' => 'cash', 'amount_minor' => '90000', 'tendered_minor' => '100000']]];
        $key = 'retail-confirm-lost-response';
        $first = $this->postCommand('sales/'.$sale['id'].'/complete', $body, $key)->assertOk()->json('data');
        $again = $this->postCommand('sales/'.$sale['id'].'/complete', $body, $key)->assertOk()->json('data');
        $this->assertSame($first, $again);
        $this->assertSame('completed', $first['status']);
        $this->assertSame('18.000000', app(InventoryV2Service::class)->availability($this->branch['warehouse_id'], $product['inventory_item_id']));
        $this->assertDatabaseCount('retail_tenders', 1);
        $this->assertDatabaseCount('retail_cash_movements', 1);
        $this->assertSame(90000, (int) DB::table('retail_shifts')->value('expected_minor'));
        $this->assertSame(10000, (int) DB::table('retail_tenders')->value('change_minor'));
    }

    public function test_product_opening_stock_and_unit_cost_require_integers(): void
    {
        $body = ['branch_id' => $this->branch['id'], 'name' => 'Hàng nhập đầu kỳ', 'price_minor' => '10000', 'opening_quantity' => '2', 'unit_cost' => '15000'];
        $this->postCommand('products', array_replace($body, ['opening_quantity' => '2.5']))->assertUnprocessable();
        $this->postCommand('products', array_replace($body, ['unit_cost' => '15000.5']))->assertUnprocessable();
        $this->assertDatabaseCount('merch_skus', 0);
        $product = $this->postCommand('products', $body)->assertOk()->json('data');
        $this->assertArrayNotHasKey('quantity_scale', $product);
        $this->assertSame('2.000000', app(InventoryV2Service::class)->availability($this->branch['warehouse_id'], DB::table('inv_items')->where('merchandise_sku_id', $product['database_id'] ?? DB::table('merch_skus')->where('public_id', $product['id'])->value('id'))->value('id')));
    }

    public function test_unit_crud_is_versioned_replay_safe_and_protects_used_unit_codes(): void
    {
        $base = '/admin/api/retail/units';
        $initial = $this->getJson($base.'?branch_id='.$this->branch['id'])->assertOk()->json('data');
        $this->assertSame(['pcs', 'kg', 'l', 'pack'], array_column($initial, 'code'));
        $body = ['branch_id' => $this->branch['id'], 'code' => 'box', 'name' => 'Hộp'];
        $unit = $this->postCommand('units', $body, 'unit-create-test')->assertOk()->json('data');
        $this->postCommand('units', $body, 'unit-create-test')->assertOk()->assertJsonPath('data.public_id', $unit['public_id']);
        $path = $base.'/'.$unit['public_id'];
        $edit = $body + ['expected_version' => 1];
        $edit['name'] = 'Hộp hàng';
        $this->putJson($path, $edit, ['Idempotency-Key' => 'unit-update-test'])->assertOk()->assertJsonPath('data.version', 2);
        $this->putJson($path, $edit, ['Idempotency-Key' => 'unit-update-test'])->assertOk();
        $this->putJson($path, $edit, ['Idempotency-Key' => 'unit-update-stale'])->assertConflict();
        $this->postCommand('units', $body)->assertUnprocessable();
        $this->putJson($path, array_replace($edit, ['expected_version' => 2, 'code' => 'case']), ['Idempotency-Key' => 'unit-change-code'])->assertUnprocessable();
        $remove = ['branch_id' => $this->branch['id'], 'expected_version' => 2];
        $this->deleteJson($path, $remove, ['Idempotency-Key' => 'unit-archive-test'])->assertOk();
        $this->deleteJson($path, $remove, ['Idempotency-Key' => 'unit-archive-test'])->assertOk();
        $this->postCommand('products', ['branch_id' => $this->branch['id'], 'name' => 'Hộp thử', 'base_unit' => 'box', 'price_minor' => '1000'])->assertUnprocessable();
        $this->putJson($path, array_replace($edit, ['expected_version' => 3, 'is_active' => true]), ['Idempotency-Key' => 'unit-restore-test'])->assertOk();
        $this->postCommand('products', ['branch_id' => $this->branch['id'], 'name' => 'Hộp thử', 'base_unit' => 'box', 'price_minor' => '1000'])->assertOk();
        $this->deleteJson($path, array_replace($remove, ['expected_version' => 4]), ['Idempotency-Key' => 'unit-archive-used'])->assertUnprocessable();
        $this->putJson($path, array_replace($edit, ['expected_version' => 4, 'name' => 'Hộp mới']), ['Idempotency-Key' => 'unit-rename-used'])->assertOk();
        $listed = $this->getJson($base.'?branch_id='.$this->branch['id'])->assertOk()->json('data');
        $this->assertTrue(collect($listed)->firstWhere('code', 'box')['in_use']);
        $other = $this->postCommand('setup', ['name' => 'Chuỗi khác'])->assertOk()->json('data');
        $this->putJson($path, array_replace($edit, ['branch_id' => $other['id'], 'expected_version' => 5]), ['Idempotency-Key' => 'unit-cross-catalog'])->assertNotFound();
        $this->postCommand('units', array_replace($body, ['branch_id' => $other['id']]))->assertOk();
        $this->assertDatabaseCount('merch_units', 10);
    }

    public function test_insufficient_stock_rolls_back_every_financial_effect(): void
    {
        $p = $this->product('LAST', '1', '10000');
        $shift = $this->shift();
        $one = $this->sale($p, $shift, '1');
        $two = $this->sale($p, $shift, '1');
        $this->complete($one, '10000')->assertOk();
        $this->complete($two, '10000')->assertStatus(409);
        $this->assertSame('priced', DB::table('retail_sales')->where('public_id', $two['id'])->value('status'));
        $this->assertDatabaseCount('retail_tenders', 1);
        $this->assertSame(10000, (int) DB::table('retail_shifts')->value('expected_minor'));
    }

    public function test_partial_returns_use_original_allocation_and_never_refund_twice(): void
    {
        $p = $this->product('RETURN', '10', '10001');
        $shift = $this->shift();
        $sale = $this->sale($p, $shift, '3');
        $sale = $this->postCommand('sales/'.$sale['id'].'/complete', ['expected_version' => 1, 'discount_minor' => '100', 'reason' => 'Ưu đãi kiểm thử', 'tenders' => [['method' => 'cash', 'amount_minor' => '29903']]])->assertOk()->json('data');
        $line = $sale['lines'][0];
        $return = $this->postCommand('sales/'.$sale['id'].'/returns', ['expected_version' => 2, 'reason' => 'Khách trả hàng', 'lines' => [['sale_line_id' => $line['id'], 'quantity' => '1', 'disposition' => 'saleable']]])->assertOk()->json('data');
        $this->assertSame(9967, (int) $return['amount_minor']);
        $body = ['shift_id' => $shift['id'], 'method' => 'cash'];
        $this->postCommand('returns/'.$return['id'].'/refund', $body, 'refund-same-key-01')->assertOk();
        $this->postCommand('returns/'.$return['id'].'/refund', $body, 'refund-same-key-01')->assertOk();
        $this->postCommand('returns/'.$return['id'].'/refund', $body)->assertStatus(409);
        $this->assertSame(19936, (int) DB::table('retail_shifts')->value('expected_minor'));
        $this->postCommand('sales/'.$sale['id'].'/returns', ['expected_version' => 3, 'reason' => 'Trả quá số lượng', 'lines' => [['sale_line_id' => $line['id'], 'quantity' => '3', 'disposition' => 'saleable']]])->assertStatus(409);
        $this->assertDatabaseCount('retail_returns', 1);
    }

    public function test_quantity_and_money_validation_are_exact_for_weighted_goods(): void
    {
        $p = $this->product('RICE', '10', '20000', 6);
        $shift = $this->shift();
        $sale = $this->sale($p, $shift, '0.375');
        $this->complete($sale, '7500')->assertOk();
        $this->assertSame('9.625000', app(InventoryV2Service::class)->availability($this->branch['warehouse_id'], $p['inventory_item_id']));
        $this->postCommand('sales', ['branch_id' => $this->branch['id'], 'terminal_id' => $this->branch['terminals'][0]['id'], 'shift_id' => $shift['id'], 'lines' => [['sku_id' => $p['id'], 'quantity' => '0.0000001']]])->assertUnprocessable();
    }

    public function test_same_key_changed_payload_does_not_create_another_sale(): void
    {
        $p = $this->product('KEY', '3', '10000');
        $shift = $this->shift();
        $data = ['branch_id' => $this->branch['id'], 'terminal_id' => $this->branch['terminals'][0]['id'], 'shift_id' => $shift['id'], 'lines' => [['sku_id' => $p['id'], 'quantity' => '1']]];
        $this->postCommand('sales', $data, 'intent-is-stable-001')->assertOk();
        $data['lines'][0]['quantity'] = '2';
        $this->postCommand('sales', $data, 'intent-is-stable-001')->assertStatus(409);
        $this->assertDatabaseCount('retail_sales', 1);
    }

    public function test_dependency_disable_never_falls_back_to_catalog_stock(): void
    {
        $p = $this->product('DEPENDENCY', '10', '10000');
        $shift = $this->shift();
        $sale = $this->sale($p, $shift, '1');
        ModuleInstallation::query()->where('key', 'inventory')->update(['status' => 'disabled']);
        $this->complete($sale, '10000')->assertStatus(503);
        $this->assertDatabaseCount('retail_tenders', 0);
    }

    public function test_stocktake_freezes_sale_and_cannot_be_self_approved(): void
    {
        $p = $this->product('COUNT', '10', '10000');
        $shift = $this->shift();
        $sale = $this->sale($p, $shift, '1');
        $take = $this->postCommand('stocktakes', ['branch_id' => $this->branch['id']])->assertOk()->json('data');
        $this->complete($sale, '10000')->assertStatus(409);
        $this->postCommand('stocktakes/'.$take['id'].'/post', ['counts' => []])->assertForbidden();
    }

    public function test_module_disable_requires_closed_shift_to_be_reconciled(): void
    {
        $shift = $this->shift();
        $this->postJson('/admin/api/modules/retail-pos/disable')->assertUnprocessable();
        $closed = $this->postCommand('shifts/'.$shift['id'].'/close', ['expected_version' => $shift['version'], 'counted_minor' => '0'])->assertOk()->json('data');
        $this->postJson('/admin/api/modules/retail-pos/disable')->assertUnprocessable();
        $this->assertDatabaseHas('module_installations', ['key' => 'retail-pos', 'status' => 'enabled']);
        $this->postCommand('shifts/'.$shift['id'].'/reconcile', ['expected_version' => $closed['version']])->assertOk();
        $this->postJson('/admin/api/modules/retail-pos/disable')->assertOk();
        $this->assertDatabaseHas('module_installations', ['key' => 'retail-pos', 'status' => 'disabled']);
    }

    private function postCommand(string $path, array $data, ?string $key = null): TestResponse
    {
        return $this->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson('/admin/api/retail/'.$path, $data);
    }

    private function product(string $sku, string $qty, string $price, int $scale = 0): array
    {
        $p = $this->postCommand('products', ['branch_id' => $this->branch['id'], 'sku' => $sku, 'name' => $sku, 'base_unit' => $scale ? 'kg' : 'pcs', 'price_minor' => $price, 'opening_quantity' => $qty, 'unit_cost' => '5000'])->assertOk()->json('data');
        $rows = $this->getJson('/admin/api/retail/products?branch_id='.$this->branch['id'].'&query='.$sku)->assertOk()->json('data');

        return $rows[0];
    }

    private function shift(): array
    {
        return $this->postCommand('shifts', ['branch_id' => $this->branch['id'], 'terminal_id' => $this->branch['terminals'][0]['id'], 'opening_minor' => '0'])->assertOk()->json('data');
    }

    private function sale(array $p, array $s, string $quantity): array
    {
        return $this->postCommand('sales', ['branch_id' => $this->branch['id'], 'terminal_id' => $this->branch['terminals'][0]['id'], 'shift_id' => $s['id'], 'lines' => [['sku_id' => $p['id'], 'quantity' => $quantity]]])->assertOk()->json('data');
    }

    private function complete(array $sale, string $amount): TestResponse
    {
        return $this->postCommand('sales/'.$sale['id'].'/complete', ['expected_version' => $sale['version'], 'tenders' => [['method' => 'cash', 'amount_minor' => $amount]]]);
    }
}
