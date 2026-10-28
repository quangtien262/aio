<?php

namespace Modules\Pharmacy\Services;

use App\Core\Modules\TestData\ModuleTestDataContext;
use App\Core\Modules\TestData\ModuleTestDataProvider;
use Illuminate\Support\Str;
use Modules\Inventory\Services\InventoryTestDataProvider;
use Modules\Merchandise\Services\MerchandiseTestDataProvider;
use Modules\Pharmacy\Services\Access\PharmacyAccessService;

final class PharmacyTestDataProvider implements ModuleTestDataProvider
{
    public function preview(): array
    {
        return ['Chuỗi, nhà thuốc, kho và quầy TEST riêng; chỉ cấp membership trong quyền Core của người tạo.', 'Một thuốc và giá nháp; không nhập tồn, cấp thuốc, thu tiền hoặc tạo qualification chuyên môn.', 'Một kết nối CSDL dược nháp không credentials; không gọi API/email/provider.'];
    }

    public function references(): array
    {
        $references = (new InventoryTestDataProvider)->references();
        foreach (['ph_commands', 'ph_outbox', 'ph_documents', 'ph_quality_cases', 'ph_recalls', 'ph_recall_tasks', 'ph_temperature_readings', 'ph_patients', 'ph_prescriptions', 'ph_dispensings', 'ph_sales', 'ph_shifts', 'ph_returns', 'ph_refunds', 'ph_receipts', 'ph_supplier_links', 'ph_purchase_links', 'ph_transfers', 'ph_stocktakes', 'ph_print_jobs', 'ph_import_batches', 'ph_saved_filters', 'ph_integration_attempts', 'ph_recovery_cases', 'ph_accounting_source_links', 'ph_accounting_mappings', 'ph_customer_mappings', 'ph_patient_customer_links', 'ph_work_tasks', 'ph_counseling_evidence', 'ph_cash_movements', 'ph_replenishment_proposals'] as $table) {
            $references[] = ['table' => $table, 'column' => 'chain_id', 'foreign_table' => 'ph_chains', 'foreign_column' => 'id'];
            if (! in_array($table, ['ph_recalls', 'ph_accounting_mappings', 'ph_customer_mappings'], true)) {
                $references[] = ['table' => $table, 'column' => 'pharmacy_id', 'foreign_table' => 'ph_pharmacies', 'foreign_column' => 'id'];
            }
        }
        foreach (['ph_prescription_lines', 'ph_dispensing_lines', 'ph_receipts', 'ph_prices'] as $table) {
            $references[] = ['table' => $table, 'column' => 'product_id', 'foreign_table' => 'ph_products', 'foreign_column' => 'id'];
        }
        $references[] = ['table' => 'ph_integration_attempts', 'column' => 'connection_id', 'foreign_table' => 'ph_connections', 'foreign_column' => 'id'];

        return $references;
    }

    public function create(ModuleTestDataContext $context): void
    {
        $catalog = MerchandiseTestDataProvider::catalog($context, '[TEST] Danh mục nhà thuốc');
        $chainUuid = (string) Str::uuid();
        $pharmacyUuid = (string) Str::uuid();
        $chain = $this->row($context, 'ph_chains', ['public_id' => $chainUuid, 'code' => $context->code('NT'), 'name' => '[TEST] Chuỗi nhà thuốc', 'catalog_id' => $catalog, 'legal_entity_ref' => 'pharmacy-test:'.$chainUuid, 'timezone' => 'Asia/Ho_Chi_Minh', 'settings' => '{}']);
        $warehouse = $context->insert('inv_warehouses', ['public_id' => (string) Str::uuid(), 'code' => $context->code('KHO'), 'name' => '[TEST] Kho nhà thuốc chưa có tồn', 'catalog_id' => $catalog, 'legal_entity_ref' => 'pharmacy-test:'.$chainUuid, 'timezone' => 'Asia/Ho_Chi_Minh', 'v2_managed' => true, 'production_managed' => false, 'managed_owner_module' => 'pharmacy', 'managed_owner_scope' => $pharmacyUuid, 'is_default' => false, 'is_active' => true]);
        $pharmacy = $this->row($context, 'ph_pharmacies', ['public_id' => $pharmacyUuid, 'chain_id' => $chain, 'code' => $context->code('CS'), 'name' => '[TEST] Nhà thuốc mẫu', 'warehouse_id' => $warehouse, 'legal_entity_ref' => 'pharmacy-test:'.$chainUuid, 'timezone' => 'Asia/Ho_Chi_Minh', 'facility_type' => 'pharmacy', 'license_number' => '[TEST] KHÔNG PHẢI GIẤY PHÉP', 'settings' => '{}']);
        $this->row($context, 'ph_counters', ['chain_id' => $chain, 'pharmacy_id' => $pharmacy, 'code' => 'TEST-POS', 'name' => '[TEST] Quầy mẫu']);
        $this->row($context, 'ph_memberships', ['chain_id' => $chain, 'pharmacy_id' => null, 'admin_id' => $context->actor->id, 'permissions' => PharmacyData::encode(app(PharmacyAccessService::class)->permissions($context->actor))]);
        $sku = MerchandiseTestDataProvider::sku($context, $catalog, '[TEST] Thuốc mẫu nháp', 'pcs', 'batch');
        $item = InventoryTestDataProvider::item($context, $sku);
        $product = $this->row($context, 'ph_products', ['chain_id' => $chain, 'sku_id' => $sku['id'], 'inventory_item_id' => $item, 'code' => $sku['sku'], 'name' => '[TEST] Thuốc mẫu nháp', 'registration_number' => '[TEST] CHƯA XÁC MINH', 'base_unit' => 'pcs', 'requires_prescription' => false, 'controlled' => false, 'cold_chain' => false, 'restricted_retail' => false, 'minimum_increment' => '1.000000', 'ingredients' => '[]', 'metadata' => '{"synthetic":true}', 'status' => 'draft']);
        $this->row($context, 'ph_prices', ['chain_id' => $chain, 'pharmacy_id' => $pharmacy, 'product_id' => $product, 'packaging_key' => 0, 'price_minor' => 1000, 'effective_at' => now(), 'status' => 'draft']);
        $this->row($context, 'ph_connections', ['chain_id' => $chain, 'kind' => 'drug_database', 'name' => '[TEST] Kết nối chưa cấu hình', 'settings' => '{"environment":"sandbox","transport_key":"unconfigured"}', 'status' => 'draft']);
    }

    private function row(ModuleTestDataContext $context, string $table, array $values): int
    {
        return (int) $context->insert($table, $values + ['public_id' => (string) Str::uuid(), 'status' => 'active', 'version' => 1]);
    }
}
