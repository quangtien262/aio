<?php

namespace Modules\Purchasing\Services;

use App\Core\Modules\TestData\ModuleTestDataContext;
use App\Core\Modules\TestData\ModuleTestDataProvider;
use Illuminate\Support\Str;
use Modules\Inventory\Services\InventoryTestDataProvider;
use Modules\Merchandise\Services\MerchandiseTestDataProvider;

final class PurchasingTestDataProvider implements ModuleTestDataProvider
{
    public function preview(): array
    {
        return [
            'Một nhà cung cấp TEST và một đơn mua nháp 20 sản phẩm.',
            'Danh mục, SKU và kho riêng; chưa duyệt, nhận hàng, thanh toán hoặc phát sinh công nợ.',
        ];
    }

    public function references(): array
    {
        return [...(new InventoryTestDataProvider)->references(), [
            'table' => 'purchase_requisitions', 'column' => 'purchase_order_id',
            'foreign_table' => 'purchase_orders', 'foreign_column' => 'id',
        ]];
    }

    public function create(ModuleTestDataContext $context): void
    {
        $catalog = MerchandiseTestDataProvider::catalog($context, '[TEST] Danh mục mua hàng');
        $sku = MerchandiseTestDataProvider::sku($context, $catalog, '[TEST] Hàng hóa đặt mua');
        $warehouse = InventoryTestDataProvider::warehouse($context, $catalog, 'test-data:'.$context->batchId);
        $item = InventoryTestDataProvider::item($context, $sku);
        $supplier = $context->insert('purchase_suppliers', [
            'public_id' => (string) Str::uuid(), 'code' => $context->code('NCC'),
            'name' => '[TEST] Nhà cung cấp mẫu', 'email' => 'supplier@example.invalid',
            'address' => '[TEST] Địa chỉ minh họa', 'payment_terms_days' => 15, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $order = $context->insert('purchase_orders', [
            'public_id' => (string) Str::uuid(), 'code' => $context->code('PO'),
            'supplier_id' => $supplier, 'warehouse_id' => $warehouse['id'], 'catalog_id' => $catalog,
            'legal_entity_ref' => 'test-data:'.$context->batchId, 'status' => 'draft', 'currency' => 'VND',
            'created_by_admin_id' => $context->actor->id, 'expected_at' => now()->addDays(7)->toDateString(),
            'due_at' => now()->addDays(15)->toDateString(), 'note' => '[TEST] Đơn mua thử nghiệm chưa duyệt.',
            'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $context->insert('purchase_order_lines', [
            'public_id' => (string) Str::uuid(), 'order_id' => $order,
            'sku_id' => $sku['id'], 'inventory_item_id' => $item,
            'sku_snapshot' => $sku['sku'], 'name_snapshot' => $sku['name'], 'unit_snapshot' => $sku['unit'],
            'quantity_ordered' => '20.000000', 'quantity_received' => '0', 'quantity_returned' => '0',
            'unit_cost' => '25000.000000', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
