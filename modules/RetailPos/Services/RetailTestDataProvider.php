<?php

namespace Modules\RetailPos\Services;

use App\Core\Modules\TestData\ModuleTestDataContext;
use App\Core\Modules\TestData\ModuleTestDataProvider;
use Illuminate\Support\Str;
use Modules\Inventory\Services\InventoryRuntimeState;
use Modules\Merchandise\Services\MerchandiseRuntimeState;

/** An isolated retail workspace with master data only; stock and money stay untouched. */
final class RetailTestDataProvider implements ModuleTestDataProvider
{
    public function preview(): array
    {
        return [
            'Một chuỗi TEST riêng, pháp nhân, danh mục hàng hóa và hai cửa hàng có quầy bán hàng.',
            'Ba hàng hóa có giá bán, đơn vị, mã vạch và một quy cách đóng gói mẫu.',
            'Hai khách hàng mẫu; không tạo tồn kho, ca bán hàng, đơn bán hoặc thanh toán.',
        ];
    }

    /** Scalar cross-module references which are not enforced by database FKs. */
    public function references(): array
    {
        $edges = [
            'merch_catalogs' => [
                ['retail_chains', 'catalog_id'], ['inv_warehouses', 'catalog_id'],
                ['purchase_orders', 'catalog_id'], ['purchase_requisitions', 'catalog_id'],
            ],
            'merch_skus' => [
                ['inv_items', 'merchandise_sku_id'], ['retail_assortments', 'sku_id'],
                ['retail_sale_lines', 'sku_id'], ['retail_accounting_item_links', 'sku_id'],
                ['purchase_order_lines', 'sku_id'], ['mfg_demand_lines', 'sku_id'],
                ['mfg_order_lines', 'sku_id'], ['mfg_material_requirements', 'sku_id'],
                ['mfg_bundles', 'sku_id'],
            ],
            'inv_items' => [
                ['retail_sale_lines', 'inventory_item_id'], ['purchase_order_lines', 'inventory_item_id'],
                ['fnb_integration_stock_lines', 'inventory_item_id'], ['inv_reservations', 'item_id'],
                ['inv_transfer_lines', 'item_id'], ['inv_escrows', 'item_id'],
                ['inv_trace_units', 'item_id'], ['inv_production_issues', 'item_id'],
            ],
            'inv_warehouses' => [
                ['retail_branches', 'warehouse_id'], ['purchase_orders', 'warehouse_id'],
                ['purchase_requisitions', 'warehouse_id'], ['mfg_factories', 'warehouse_id'],
                ['fnb_integration_stock_lines', 'warehouse_id'],
                ['acct_inventory_warehouse_mappings', 'inventory_warehouse_id'],
                ['inv_reservations', 'warehouse_id'], ['inv_transfers', 'source_warehouse_id'],
                ['inv_transfers', 'destination_warehouse_id'], ['inv_stocktakes', 'warehouse_id'],
                ['inv_escrows', 'warehouse_id'], ['inv_trace_units', 'warehouse_id'],
                ['inv_production_issues', 'warehouse_id'], ['inv_production_postings', 'warehouse_id'],
            ],
            'retail_branches' => [
                ['inv_warehouses', 'branch_id'], ['retail_memberships', 'branch_id'],
                ['retail_outbox', 'branch_id'], ['retail_integration_links', 'branch_id'],
            ],
            'retail_legal_entities' => [
                ['retail_sales', 'legal_entity_id'], ['retail_accounting_mappings', 'legal_entity_id'],
                ['inv_warehouses', 'legal_entity_ref', 'public_id'],
                ['purchase_orders', 'legal_entity_ref', 'public_id'],
                ['inv_transfers', 'legal_entity_ref', 'public_id'],
            ],
            'retail_customers' => [['retail_sales', 'customer_id']],
            'retail_chains' => [
                ['cust_bindings', 'scope_key', 'public_id'], ['cust_sources', 'scope_key', 'public_id'],
            ],
        ];
        $references = [];
        foreach ($edges as $parent => $consumers) {
            foreach ($consumers as $edge) {
                $references[] = [
                    'table' => $edge[0], 'column' => $edge[1],
                    'foreign_table' => $parent, 'foreign_column' => $edge[2] ?? 'id',
                ];
            }
        }

        return [
            ...$references,
            [
                'table' => 'merch_skus', 'columns' => ['catalog_id', 'base_unit'],
                'foreign_table' => 'merch_units', 'foreign_columns' => ['catalog_id', 'code'],
            ],
            [
                'table' => 'mfg_companies', 'column' => 'settings->catalog_id',
                'foreign_table' => 'merch_catalogs', 'foreign_column' => 'id',
            ],
            [
                'table' => 'purchase_requisitions', 'column' => 'lines->*->sku_id',
                'foreign_table' => 'merch_skus', 'foreign_column' => 'id',
            ],
            [
                'table' => 'mfg_style_revisions', 'column' => 'bom->*->sku_id',
                'foreign_table' => 'merch_skus', 'foreign_column' => 'id',
            ],
            [
                'table' => 'mfg_orders', 'column' => 'released_snapshot->bom->*->sku_id',
                'foreign_table' => 'merch_skus', 'foreign_column' => 'id',
            ],
            [
                'table' => 'fnb_integration_mappings', 'column' => 'config->warehouse_id',
                'foreign_table' => 'inv_warehouses', 'foreign_column' => 'id',
                'where' => ['destination' => 'inventory'],
            ],
            [
                'table' => 'fnb_integration_item_mappings', 'column' => 'destination_id',
                'foreign_table' => 'inv_items', 'foreign_column' => 'id',
                'where' => ['destination' => 'inventory', 'source_type' => 'ingredient'],
            ],
            [
                'table' => 'acct_documents', 'column' => 'metadata->branch_id',
                'foreign_table' => 'retail_branches', 'foreign_column' => 'public_id',
                'where' => ['source_module' => 'retail-pos'],
            ],
            [
                'table' => 'acct_documents', 'column' => 'metadata->legal_entity_id',
                'foreign_table' => 'retail_legal_entities', 'foreign_column' => 'id',
                'where' => ['source_module' => 'retail-pos'],
            ],
            [
                'table' => 'acct_documents', 'column' => 'metadata->source_lines->*->sku_id',
                'foreign_table' => 'merch_skus', 'foreign_column' => 'id',
                'where' => ['source_module' => 'retail-pos'],
            ],
        ];
    }

    public function create(ModuleTestDataContext $context): void
    {
        app(RetailRuntimeState::class)->guard();
        app(RetailService::class)->ready();
        app(InventoryRuntimeState::class)->guard();
        app(MerchandiseRuntimeState::class)->guard();

        $stamp = ['created_at' => now(), 'updated_at' => now()];
        $catalogId = $context->insert('merch_catalogs', $stamp + [
            'public_id' => (string) Str::uuid(),
            'code' => $context->code('RET-CAT'),
            'name' => 'Danh mục bán lẻ TEST',
            'is_active' => true,
            'version' => 1,
        ]);
        $legalPublicId = (string) Str::uuid();
        $legalId = $context->insert('retail_legal_entities', $stamp + [
            'public_id' => $legalPublicId,
            'name' => 'Đơn vị vận hành TEST',
        ]);
        $chainId = $context->insert('retail_chains', $stamp + [
            'public_id' => (string) Str::uuid(),
            'name' => $context->code('Chuoi-TEST'),
            'catalog_id' => $catalogId,
            'website_key' => $context->websiteKey,
        ]);
        $context->insert('retail_memberships', $stamp + [
            'admin_id' => $context->actor->id,
            'chain_id' => $chainId,
            'branch_id' => 0,
            'preset' => 'hq',
            'is_active' => true,
        ]);

        $branchIds = [];
        foreach (['Cửa hàng trung tâm TEST', 'Cửa hàng khu phố TEST'] as $index => $name) {
            // Warehouse -> branch is an optional soft reference. Insert the warehouse
            // first and link it from the branch so every owned row stays immutable.
            $warehouseId = $context->insert('inv_warehouses', $stamp + [
                'public_id' => (string) Str::uuid(),
                'code' => $context->code('RET-KHO'),
                'name' => 'Kho '.$name,
                'catalog_id' => $catalogId,
                'legal_entity_ref' => $legalPublicId,
                'timezone' => 'Asia/Ho_Chi_Minh',
                'v2_managed' => true,
                'is_default' => false,
                'is_active' => true,
            ]);
            $branchId = $context->insert('retail_branches', $stamp + [
                'public_id' => (string) Str::uuid(),
                'chain_id' => $chainId,
                'legal_entity_id' => $legalId,
                'warehouse_id' => $warehouseId,
                'code' => 'TEST-'.($index + 1),
                'name' => $name,
                'timezone' => 'Asia/Ho_Chi_Minh',
                'status' => 'active',
                'version' => 1,
            ]);
            $branchIds[] = $branchId;
            $context->insert('retail_terminals', $stamp + [
                'public_id' => (string) Str::uuid(),
                'branch_id' => $branchId,
                'code' => 'POS-TEST',
                'name' => 'Quầy bán hàng TEST',
                'status' => 'active',
            ]);
        }

        foreach ([['pcs', 'Cái / đơn vị'], ['kg', 'Kilôgam (kg)'], ['l', 'Lít (l)'], ['pack', 'Gói']] as [$code, $name]) {
            $context->insert('merch_units', $stamp + [
                'public_id' => (string) Str::uuid(),
                'catalog_id' => $catalogId,
                'code' => $code,
                'name' => $name,
                'is_active' => true,
                'version' => 1,
            ]);
        }

        foreach ([
            ['Nước khoáng TEST 500 ml', 'pcs', 10000],
            ['Bánh quy TEST', 'pack', 25000],
            ['Khăn giấy TEST', 'pack', 18000],
        ] as $index => [$name, $unit, $price]) {
            $sku = $context->code('HH');
            $skuId = $context->insert('merch_skus', $stamp + [
                'public_id' => (string) Str::uuid(),
                'catalog_id' => $catalogId,
                'sku' => $sku,
                'name' => $name,
                'base_unit' => $unit,
                'tracking_mode' => 'none',
                'requires_expiry' => false,
                'is_active' => true,
                'version' => 1,
            ]);
            // Retail's product read calls ensureItem(). Precreate the projection so
            // viewing these fixtures cannot create an untracked inventory record.
            $context->insert('inv_items', $stamp + [
                'merchandise_sku_id' => $skuId,
                'sku' => $sku,
                'name' => $name,
                'unit' => $unit,
                'sale_price' => 0,
                'costing_method' => 'fifo',
                'track_batch' => false,
                'track_serial' => false,
                'is_active' => true,
            ]);
            $context->insert('merch_barcodes', $stamp + [
                'catalog_id' => $catalogId,
                'sku_id' => $skuId,
                'barcode' => $context->code('BAR'),
                'format' => 'internal',
            ]);
            foreach ($branchIds as $branchId) {
                $context->insert('retail_assortments', $stamp + [
                    'branch_id' => $branchId,
                    'sku_id' => $skuId,
                    'price_minor' => $price,
                    'is_active' => true,
                    'version' => 1,
                ]);
            }
            if ($index === 0) {
                $packagingId = $context->insert('merch_packagings', $stamp + [
                    'public_id' => (string) Str::uuid(),
                    'sku_id' => $skuId,
                    'code' => 'LOC6',
                    'name' => 'Lốc 6 chai TEST',
                    'quantity_multiplier' => '6.000000',
                    'revision' => 1,
                    'is_active' => true,
                ]);
                $context->insert('merch_barcodes', $stamp + [
                    'catalog_id' => $catalogId,
                    'sku_id' => $skuId,
                    'packaging_id' => $packagingId,
                    'barcode' => $context->code('PACK'),
                    'format' => 'internal',
                ]);
            }
        }

        foreach (['Khách lẻ TEST', 'Khách thân thiết TEST'] as $name) {
            $context->insert('retail_customers', $stamp + [
                'public_id' => (string) Str::uuid(),
                'chain_id' => $chainId,
                'name' => $name,
                'status' => 'active',
                'version' => 1,
            ]);
        }
    }
}
