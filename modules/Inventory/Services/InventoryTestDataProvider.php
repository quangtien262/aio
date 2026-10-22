<?php

namespace Modules\Inventory\Services;

use App\Core\Modules\TestData\ModuleTestDataContext;
use App\Core\Modules\TestData\ModuleTestDataProvider;
use Illuminate\Support\Str;
use Modules\Merchandise\Services\MerchandiseTestDataProvider;

final class InventoryTestDataProvider implements ModuleTestDataProvider
{
    public function preview(): array
    {
        return [
            'Một kho TEST riêng, vị trí lưu trữ và hàng hóa thuộc danh mục TEST mới.',
            'Một phiếu nhập nháp 10 sản phẩm; không ghi tồn kho, giá vốn hoặc sổ biến động.',
        ];
    }

    public function references(): array
    {
        $references = (new MerchandiseTestDataProvider)->references();
        foreach ([
            'inv_warehouses' => [
                'retail_branches' => ['warehouse_id'], 'purchase_orders' => ['warehouse_id'],
                'purchase_requisitions' => ['warehouse_id'], 'mfg_factories' => ['warehouse_id'],
                'acct_inventory_warehouse_mappings' => ['inventory_warehouse_id'],
                'fnb_integration_stock_lines' => ['warehouse_id'], 'inv_reservations' => ['warehouse_id'],
                'inv_transfers' => ['source_warehouse_id', 'destination_warehouse_id'],
                'inv_stocktakes' => ['warehouse_id'], 'inv_escrows' => ['warehouse_id'],
                'inv_trace_units' => ['warehouse_id'], 'inv_production_issues' => ['warehouse_id'],
                'inv_production_postings' => ['warehouse_id'],
            ],
            'inv_items' => [
                'purchase_order_lines' => ['inventory_item_id'], 'retail_sale_lines' => ['inventory_item_id'],
                'fnb_integration_stock_lines' => ['inventory_item_id'], 'inv_reservations' => ['item_id'],
                'inv_transfer_lines' => ['item_id'], 'inv_escrows' => ['item_id'],
                'inv_trace_units' => ['item_id'], 'inv_production_issues' => ['item_id'],
            ],
            'inv_locations' => [
                'inv_locations' => ['parent_id'], 'inv_cost_layers' => ['location_id'],
                'inv_production_issue_layers' => ['location_id'],
            ],
            'inv_stock_documents' => [
                'fnb_integration_stock_lines' => ['inventory_document_id'], 'inv_production_issues' => ['document_id'],
            ],
            'inv_stock_document_lines' => ['fnb_integration_stock_lines' => ['inventory_document_line_id']],
        ] as $foreignTable => $tables) {
            foreach ($tables as $table => $columns) {
                foreach ($columns as $column) {
                    $references[] = ['table' => $table, 'column' => $column, 'foreign_table' => $foreignTable, 'foreign_column' => 'id'];
                }
            }
        }
        $references[] = [
            'table' => 'fnb_integration_item_mappings', 'column' => 'destination_id',
            'foreign_table' => 'inv_items', 'foreign_column' => 'id', 'where' => ['destination' => 'inventory'],
        ];
        $references[] = [
            'table' => 'fnb_integration_mappings', 'column' => 'config->warehouse_id',
            'foreign_table' => 'inv_warehouses', 'foreign_column' => 'id', 'where' => ['destination' => 'inventory'],
        ];
        $references[] = [
            'table' => 'inv_production_postings', 'column' => 'outputs->*->item_id',
            'foreign_table' => 'inv_items', 'foreign_column' => 'id',
        ];

        return $references;
    }

    public function create(ModuleTestDataContext $context): void
    {
        $catalog = MerchandiseTestDataProvider::catalog($context, '[TEST] Danh mục kho');
        $sku = MerchandiseTestDataProvider::sku($context, $catalog, '[TEST] Hàng hóa kho');
        $warehouse = self::warehouse($context, $catalog, 'test-data:'.$context->batchId);
        $item = self::item($context, $sku);
        $document = $context->insert('inv_stock_documents', [
            'code' => $context->code('PN'), 'type' => 'receipt', 'status' => 'draft',
            'destination_warehouse_id' => $warehouse['id'], 'reference' => '[TEST] Phiếu nhập mẫu',
            'note' => 'Dữ liệu thử nghiệm: phiếu nháp, chưa ghi nhận tồn kho.',
            'created_by_admin_id' => $context->actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $context->insert('inv_stock_document_lines', [
            'document_id' => $document, 'item_id' => $item, 'quantity' => '10.000000',
            'unit_cost' => '25000.000000', 'destination_location_id' => $warehouse['location_id'],
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public static function warehouse(ModuleTestDataContext $context, int $catalog, string $legalReference, bool $production = false): array
    {
        $publicId = (string) Str::uuid();
        $id = (int) $context->insert('inv_warehouses', [
            'public_id' => $publicId, 'code' => $context->code('KHO'),
            'name' => $production ? '[TEST] Kho nhà máy' : '[TEST] Kho hàng mẫu',
            'catalog_id' => $catalog, 'legal_entity_ref' => $legalReference,
            'timezone' => 'Asia/Ho_Chi_Minh', 'v2_managed' => true, 'production_managed' => $production,
            'is_default' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $location = (int) $context->insert('inv_locations', [
            'warehouse_id' => $id, 'code' => 'TEST-STORAGE', 'name' => '[TEST] Kệ hàng mẫu',
            'type' => 'storage', 'is_default' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['id' => $id, 'public_id' => $publicId, 'location_id' => $location];
    }

    public static function item(ModuleTestDataContext $context, array $sku): int
    {
        return (int) $context->insert('inv_items', [
            'merchandise_sku_id' => $sku['id'], 'sku' => $sku['sku'], 'name' => $sku['name'],
            'unit' => $sku['unit'], 'costing_method' => 'fifo',
            'track_batch' => $sku['tracking_mode'] === 'batch', 'track_serial' => false,
            'sale_price' => '0', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
