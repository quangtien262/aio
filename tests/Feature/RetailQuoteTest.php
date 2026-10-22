<?php

namespace Tests\Feature;

use App\Models\Admin;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Inventory\Services\InventoryV2Service;
use Tests\TestCase;

class RetailQuoteTest extends TestCase
{
    use RefreshDatabase;

    private array $branch;

    private array $shift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(1);
        $owner->forceFill(['must_change_password' => false])->save();
        $this->actingAs($owner, 'admin')->withSession(['admin_auth_version' => $owner->auth_version]);
        foreach (['merchandise', 'inventory', 'retail-pos'] as $key) {
            $this->postJson('/admin/api/modules/'.$key.'/install')->assertOk();
            $this->postJson('/admin/api/modules/'.$key.'/enable')->assertOk();
        }
        $this->travelTo(now()->startOfSecond());
        $this->branch = $this->postCommand('setup', ['name' => 'Chuỗi kiểm thử báo giá'])->assertOk()->json('data');
        $this->shift = $this->postCommand('shifts', ['branch_id' => $this->branch['id'], 'terminal_id' => $this->branch['terminals'][0]['id'], 'opening_minor' => '0'])->assertOk()->json('data');
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_expired_quote_is_rejected_at_boundary_without_collecting_money_or_reserving_stock(): void
    {
        $product = $this->product('EXPIRY');
        $sale = $this->sale([$product]);
        $this->assertSame(now()->addSeconds(300)->toIso8601String(), $sale['quote_expires_at']);
        $this->assertSame(1, (int) $sale['lines'][0]['price_version']);
        $this->travelTo(CarbonImmutable::parse($sale['quote_expires_at']));
        $body = $this->payment($sale, '10000') + ['quote_expires_at' => now()->addDay()->toIso8601String()];
        $this->postCommand('sales/'.$sale['id'].'/complete', $body, 'expired-quote-not-collected')->assertStatus(409)->assertJsonPath('message', 'Báo giá đã hết hạn; hủy giỏ hiện tại và tạo lại để xác nhận giá mới.');
        $this->assertUntouched($sale, [$product]);
        $this->assertDatabaseMissing('retail_commands', ['command_key' => 'expired-quote-not-collected']);

        $cancelled = $this->postCommand('sales/'.$sale['id'].'/cancel', ['expected_version' => $sale['version']])->assertOk()->json('data');
        $this->assertSame('cancelled', $cancelled['status']);
        $new = $this->sale([$product]);
        $this->postCommand('sales/'.$new['id'].'/complete', $this->payment($new, '10000'))->assertOk();
        $this->assertDatabaseCount('retail_tenders', 1);
        $this->assertSame('9.000000', $this->availability($product));
    }

    public function test_changed_price_requires_cancellation_and_explicit_confirmation_of_new_quote(): void
    {
        $product = $this->product('PRICE');
        $sale = $this->sale([$product]);
        $this->publishPrice($product, '12000', 1);
        $this->postCommand('sales/'.$sale['id'].'/complete', $this->payment($sale, '10000'))->assertStatus(409)->assertJsonPath('message', 'Bảng giá hoặc danh mục bán đã đổi; hủy giỏ hiện tại và tạo lại để xác nhận giá mới.');
        $this->assertUntouched($sale, [$product]);
        $line = DB::table('retail_sale_lines')->where('public_id', $sale['lines'][0]['id'])->first();
        $this->assertSame(10000, (int) $line->price_minor);
        $this->assertSame(1, (int) $line->price_version);

        $this->postCommand('sales/'.$sale['id'].'/cancel', ['expected_version' => $sale['version']])->assertOk();
        $new = $this->sale([$product]);
        $this->assertSame(12000, (int) $new['total_minor']);
        $this->assertSame(2, (int) $new['lines'][0]['price_version']);
        $this->postCommand('sales/'.$new['id'].'/complete', $this->payment($new, '12000'))->assertOk();
        $this->assertDatabaseCount('retail_tenders', 1);
        $this->assertSame(12000, (int) DB::table('retail_shifts')->value('expected_minor'));
    }

    public function test_republished_same_price_still_invalidates_old_revision(): void
    {
        $product = $this->product('REVISION');
        $sale = $this->sale([$product]);
        $this->publishPrice($product, '10000', 1);
        $this->postCommand('sales/'.$sale['id'].'/complete', $this->payment($sale, '10000'))->assertStatus(409);
        $this->assertUntouched($sale, [$product]);
    }

    public function test_stale_second_line_does_not_partially_post_first_line(): void
    {
        $first = $this->product('FIRST');
        $second = $this->product('SECOND');
        $sale = $this->sale([$first, $second]);
        $this->publishPrice($second, '12000', 1);
        $this->postCommand('sales/'.$sale['id'].'/complete', $this->payment($sale, '20000'))->assertStatus(409);
        $this->assertUntouched($sale, [$first, $second]);
    }

    public function test_successful_completion_replay_survives_expiry_and_price_changes(): void
    {
        $product = $this->product('REPLAY');
        $sale = $this->sale([$product]);
        $body = $this->payment($sale, '10000');
        $key = 'quote-lost-response-stable';
        $completed = $this->postCommand('sales/'.$sale['id'].'/complete', $body, $key)->assertOk()->json('data');
        $this->travelTo(CarbonImmutable::parse($sale['quote_expires_at'])->addMinute());
        $this->publishPrice($product, '12000', 1);
        $replayed = $this->postCommand('sales/'.$sale['id'].'/complete', $body, $key)->assertOk()->json('data');
        $this->assertSame($completed, $replayed);
        $this->assertSame('9.000000', $this->availability($product));
        $this->assertDatabaseCount('retail_tenders', 1);
        $this->assertDatabaseCount('retail_cash_movements', 1);
        $this->assertDatabaseCount('inv_reservations', 1);
        $this->assertSame(10000, (int) DB::table('retail_shifts')->value('expected_minor'));
    }

    public function test_delisted_assortment_cannot_be_completed_from_stored_quote(): void
    {
        $product = $this->product('DELISTED');
        $sale = $this->sale([$product]);
        DB::table('retail_assortments')->where('sku_id', $product['database_id'])->update(['is_active' => false]);
        $this->postCommand('sales/'.$sale['id'].'/complete', $this->payment($sale, '10000'))->assertStatus(409);
        $this->assertUntouched($sale, [$product]);
    }

    private function assertUntouched(array $sale, array $products): void
    {
        $row = DB::table('retail_sales')->where('public_id', $sale['id'])->first();
        $this->assertSame('priced', $row->status);
        $this->assertSame($sale['version'], (int) $row->version);
        $this->assertDatabaseCount('retail_tenders', 0);
        $this->assertDatabaseCount('retail_cash_movements', 0);
        $this->assertDatabaseCount('inv_reservations', 0);
        $this->assertDatabaseCount('inv_stock_documents', count($products));
        $this->assertDatabaseCount('inv_v2_operations', count($products));
        $this->assertDatabaseMissing('retail_outbox', ['event_type' => 'sale.completed']);
        $this->assertSame(0, (int) DB::table('retail_shifts')->value('expected_minor'));
        foreach ($products as $product) {
            $this->assertSame('10.000000', $this->availability($product));
        }
    }

    private function availability(array $product): string
    {
        return app(InventoryV2Service::class)->availability($this->branch['warehouse_id'], $product['inventory_item_id']);
    }

    private function product(string $code): array
    {
        $this->postCommand('products', ['branch_id' => $this->branch['id'], 'sku' => $code, 'name' => $code, 'base_unit' => 'pcs', 'price_minor' => '10000', 'opening_quantity' => '10', 'unit_cost' => '5000'])->assertOk();

        return $this->getJson('/admin/api/retail/products?branch_id='.$this->branch['id'].'&query='.$code)->assertOk()->json('data.0');
    }

    private function sale(array $products): array
    {
        return $this->postCommand('sales', ['branch_id' => $this->branch['id'], 'terminal_id' => $this->branch['terminals'][0]['id'], 'shift_id' => $this->shift['id'], 'lines' => array_map(fn ($product) => ['sku_id' => $product['id'], 'quantity' => '1'], $products)])->assertOk()->json('data');
    }

    private function payment(array $sale, string $amount): array
    {
        return ['expected_version' => $sale['version'], 'tenders' => [['method' => 'cash', 'amount_minor' => $amount]]];
    }

    private function publishPrice(array $product, string $price, int $version): void
    {
        $this->postCommand('assortments', ['branch_id' => $this->branch['id'], 'sku_id' => $product['id'], 'price_minor' => $price, 'expected_version' => $version])->assertOk()->assertJsonPath('data.version', $version + 1);
    }

    private function postCommand(string $path, array $data, ?string $key = null): TestResponse
    {
        return $this->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson('/admin/api/retail/'.$path, $data);
    }
}
