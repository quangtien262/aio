<?php

namespace Modules\Merchandise\Services;

use App\Core\Modules\TestData\ModuleTestDataContext;
use App\Core\Modules\TestData\ModuleTestDataProvider;
use Illuminate\Support\Str;

final class MerchandiseTestDataProvider implements ModuleTestDataProvider
{
    public function preview(): array
    {
        return [
            'Danh mục hàng hóa TEST riêng, hai đơn vị tính và một SKU.',
            'Một quy cách thùng 10 sản phẩm và mã vạch nội bộ; không dùng hàng hóa thật.',
        ];
    }

    public function references(): array
    {
        $references = [];
        foreach (['inv_warehouses', 'purchase_orders', 'purchase_requisitions', 'retail_chains'] as $table) {
            $references[] = ['table' => $table, 'column' => 'catalog_id', 'foreign_table' => 'merch_catalogs', 'foreign_column' => 'id'];
        }
        $references[] = ['table' => 'inv_items', 'column' => 'merchandise_sku_id', 'foreign_table' => 'merch_skus', 'foreign_column' => 'id'];
        foreach (['purchase_order_lines', 'retail_assortments', 'retail_sale_lines', 'retail_accounting_item_links', 'mfg_demand_lines', 'mfg_order_lines', 'mfg_material_requirements', 'mfg_bundles'] as $table) {
            $references[] = ['table' => $table, 'column' => 'sku_id', 'foreign_table' => 'merch_skus', 'foreign_column' => 'id'];
        }
        $references[] = ['table' => 'merch_skus', 'columns' => ['catalog_id', 'base_unit'], 'foreign_table' => 'merch_units', 'foreign_columns' => ['catalog_id', 'code']];
        $references[] = ['table' => 'mfg_companies', 'column' => 'settings->catalog_id', 'foreign_table' => 'merch_catalogs', 'foreign_column' => 'id'];
        $references[] = ['table' => 'mfg_styles', 'column' => 'metadata->variant_set_id', 'foreign_table' => 'merch_variant_sets', 'foreign_column' => 'id'];
        $references[] = ['table' => 'mfg_style_revisions', 'column' => 'metadata->variant_set_id', 'foreign_table' => 'merch_variant_sets', 'foreign_column' => 'id'];
        foreach ([
            'mfg_styles' => ['metadata->variants->*->sku_id'],
            'mfg_style_revisions' => ['metadata->variants->*->sku_id', 'bom->*->sku_id'],
            'mfg_orders' => ['released_snapshot->bom->*->sku_id', 'released_snapshot->variants->*->sku_id'],
            'purchase_requisitions' => ['lines->*->sku_id'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                $references[] = ['table' => $table, 'column' => $column, 'foreign_table' => 'merch_skus', 'foreign_column' => 'id'];
            }
        }

        return $references;
    }

    public function create(ModuleTestDataContext $context): void
    {
        $catalog = self::catalog($context, '[TEST] Danh mục hàng hóa');
        $sku = self::sku($context, $catalog, '[TEST] Sản phẩm mẫu');
        $packaging = $context->insert('merch_packagings', [
            'public_id' => (string) Str::uuid(), 'sku_id' => $sku['id'],
            'code' => 'BOX-10', 'name' => '[TEST] Thùng 10 sản phẩm',
            'quantity_multiplier' => '10.000000', 'revision' => 1, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $context->insert('merch_barcodes', [
            'catalog_id' => $catalog, 'sku_id' => $sku['id'], 'packaging_id' => $packaging,
            'barcode' => $context->code('BAR'), 'format' => 'internal',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** New dependency fixtures always belong to the initiating module's batch. */
    public static function catalog(ModuleTestDataContext $context, string $name): int
    {
        $catalog = (int) $context->insert('merch_catalogs', [
            'public_id' => (string) Str::uuid(), 'code' => $context->code('CAT'),
            'name' => $name, 'is_active' => true, 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['pcs' => 'Cái', 'm' => 'Mét'] as $code => $label) {
            $context->insert('merch_units', [
                'public_id' => (string) Str::uuid(), 'catalog_id' => $catalog,
                'code' => $code, 'name' => $label, 'is_active' => true, 'version' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $catalog;
    }

    public static function sku(ModuleTestDataContext $context, int $catalog, string $name, string $unit = 'pcs', string $tracking = 'none'): array
    {
        $code = $context->code('SKU');
        $id = (int) $context->insert('merch_skus', [
            'public_id' => (string) Str::uuid(), 'catalog_id' => $catalog, 'sku' => $code,
            'name' => $name, 'base_unit' => $unit, 'tracking_mode' => $tracking,
            'requires_expiry' => false, 'is_active' => true, 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['id' => $id, 'sku' => $code, 'name' => $name, 'unit' => $unit, 'tracking_mode' => $tracking];
    }
}
