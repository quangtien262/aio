<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Merchandise\Services\MerchandiseService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MerchandiseModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ModuleManager::class)->install('merchandise');
        app(ModuleManager::class)->enable('merchandise');
    }

    public function test_packaging_and_leading_zero_barcode_resolve_one_shared_sku(): void
    {
        $s = app(MerchandiseService::class);
        $c = $s->createCatalog(['code' => 'CHAIN', 'name' => 'Chuỗi']);
        $sku = $s->saveSku($c['id'], ['sku' => 'COLA', 'name' => 'Cola', 'barcodes' => ['00001234'], 'packagings' => [
            ['code' => 'CASE', 'name' => 'Thùng 24', 'quantity_multiplier' => '24', 'barcode' => 'CASE-COLA'],
            ['code' => 'SIX', 'name' => 'Lốc 6', 'quantity_multiplier' => '6', 'barcode' => 'SIX-COLA'],
        ]]);
        $case = $s->resolveBarcode($c['id'], 'CASE-COLA');
        $this->assertSame($sku['id'], $case['id']);
        $this->assertSame('48.000000', $s->convertQuantity($sku['id'], '2', $case['packaging_id']));
        $this->assertSame('00001234', $s->lookup($c['id'], '00001234')[0]['barcode']);
        $this->assertSame('6.000000', $s->resolveBarcode($c['id'], 'SIX-COLA')['quantity_multiplier']);
    }

    public function test_barcode_conflict_rolls_back_sku_creation_without_changing_owner(): void
    {
        $s = app(MerchandiseService::class);
        $c = $s->createCatalog(['code' => 'C', 'name' => 'C']);
        $first = $s->saveSku($c['id'], ['sku' => 'A', 'name' => 'A', 'barcodes' => ['SHARED']]);
        try {
            $s->saveSku($c['id'], ['sku' => 'B', 'name' => 'B', 'barcodes' => ['SHARED']]);
            $this->fail('Conflict expected.');
        } catch (ValidationException) {
        }
        $this->assertSame(1, DB::table('merch_skus')->count());
        $this->assertSame($first['id'], $s->resolveBarcode($c['id'], 'SHARED')['id']);
    }

    public function test_import_preview_is_read_only_and_rejects_duplicate_input_atomically(): void
    {
        $s = app(MerchandiseService::class);
        $c = $s->createCatalog(['code' => 'C', 'name' => 'C']);
        $rows = [['sku' => 'A', 'name' => 'A', 'barcodes' => ['ONE']], ['sku' => 'B', 'name' => 'B', 'barcodes' => ['ONE']]];
        $preview = $s->previewImport($c['id'], $rows);
        $this->assertFalse($preview['valid']);
        $this->assertSame(2, $preview['errors'][0]['row']);
        $this->assertSame(0, DB::table('merch_skus')->count());
        try {
            $s->import($c['id'], $rows);
            $this->fail('Invalid file expected.');
        } catch (ValidationException) {
        }
        $this->assertSame(0, DB::table('merch_skus')->count());
    }

    public function test_weighted_quantity_is_exact_and_invalid_precision_is_rejected(): void
    {
        $s = app(MerchandiseService::class);
        $c = $s->createCatalog(['code' => 'C', 'name' => 'C']);
        $sku = $s->saveSku($c['id'], ['sku' => 'RICE', 'name' => 'Gạo', 'base_unit' => 'kg', 'quantity_scale' => 3]);
        $this->assertSame('0.375000', $s->convertQuantity($sku['id'], '0.375'));
        $this->expectException(ValidationException::class);
        $s->convertQuantity($sku['id'], '0.3751');
    }

    public function test_stale_updates_and_unit_changes_are_rejected(): void
    {
        $s = app(MerchandiseService::class);
        $c = $s->createCatalog(['code' => 'C', 'name' => 'C']);
        $sku = $s->saveSku($c['id'], ['sku' => 'A', 'name' => 'A']);
        try {
            $s->saveSku($c['id'], ['sku' => 'A', 'name' => 'Changed', 'expected_version' => 9], $sku['id']);
            $this->fail('Stale version expected.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->expectException(ValidationException::class);
        $s->saveSku($c['id'], ['sku' => 'A', 'name' => 'A', 'base_unit' => 'kg', 'expected_version' => 1], $sku['id']);
    }

    public function test_packaging_revision_preserves_previous_conversion_and_uses_new_barcode_mapping(): void
    {
        $s = app(MerchandiseService::class);
        $c = $s->createCatalog(['code' => 'C', 'name' => 'C']);
        $sku = $s->saveSku($c['id'], ['sku' => 'A', 'name' => 'A', 'packagings' => [['code' => 'CASE', 'name' => 'Case', 'quantity_multiplier' => '24', 'barcode' => 'BOX']]]);
        $old = $s->resolveBarcode($c['id'], 'BOX')['packaging_id'];
        $s->saveSku($c['id'], ['sku' => 'A', 'name' => 'A', 'expected_version' => 1, 'packagings' => [['code' => 'CASE', 'name' => 'Case', 'quantity_multiplier' => '12', 'barcode' => 'BOX']]], $sku['id']);
        $this->assertSame('12.000000', $s->resolveBarcode($c['id'], 'BOX')['quantity_multiplier']);
        $this->assertEquals('24.000000', DB::table('merch_packagings')->where('id', $old)->value('quantity_multiplier'));
        $this->assertSame(0, (int) DB::table('merch_packagings')->where('id', $old)->value('is_active'));
    }

    public function test_ean_checksum_is_validated_and_leading_zero_is_kept(): void
    {
        $s = app(MerchandiseService::class);
        $c = $s->createCatalog(['code' => 'C', 'name' => 'C']);
        $s->saveSku($c['id'], ['sku' => 'EAN', 'name' => 'EAN', 'barcodes' => [['barcode' => '0123456789012', 'format' => 'ean13']]]);
        $this->assertSame('0123456789012', $s->resolveBarcode($c['id'], '0123456789012')['barcode']);
        $this->expectException(ValidationException::class);
        $s->saveSku($c['id'], ['sku' => 'BAD', 'name' => 'Bad', 'barcodes' => [['barcode' => '0123456789013', 'format' => 'ean13']]]);
    }
}
