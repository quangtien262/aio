<?php

namespace Modules\Manufacturing\Services;

use App\Core\Modules\TestData\ModuleTestDataContext;
use App\Core\Modules\TestData\ModuleTestDataProvider;
use Illuminate\Support\Str;
use Modules\Inventory\Services\InventoryTestDataProvider;
use Modules\Merchandise\Services\MerchandiseTestDataProvider;

final class ManufacturingTestDataProvider implements ModuleTestDataProvider
{
    public function preview(): array
    {
        return [
            'Công ty, nhà máy, kho, phân xưởng, chuyền và ca TEST riêng; cấp native membership cho người tạo.',
            'Mã hàng may và mẫu PP nháp, BOM vải cùng SKU biến thể; chưa phát hành lệnh hoặc ghi tồn kho.',
        ];
    }

    public function references(): array
    {
        $references = (new InventoryTestDataProvider)->references();
        foreach (['mfg_commands', 'mfg_outbox', 'mfg_bundle_materials'] as $table) {
            $references[] = ['table' => $table, 'column' => 'company_id', 'foreign_table' => 'mfg_companies', 'foreign_column' => 'id'];
            $references[] = ['table' => $table, 'column' => 'factory_id', 'foreign_table' => 'mfg_factories', 'foreign_column' => 'id'];
        }
        $references[] = [
            'table' => 'mfg_documents', 'column' => 'resource_id', 'foreign_table' => 'mfg_styles',
            'foreign_column' => 'public_id', 'where' => ['resource_type' => 'style'],
        ];
        $references[] = ['table' => 'mfg_demands', 'column' => 'metadata->style_id', 'foreign_table' => 'mfg_styles', 'foreign_column' => 'id'];
        $references[] = ['table' => 'mfg_orders', 'column' => 'metadata->workshop_id', 'foreign_table' => 'mfg_workshops', 'foreign_column' => 'id'];
        $references[] = ['table' => 'mfg_orders', 'column' => 'metadata->line_id', 'foreign_table' => 'mfg_lines', 'foreign_column' => 'id'];

        return $references;
    }

    public function create(ModuleTestDataContext $context): void
    {
        $catalog = MerchandiseTestDataProvider::catalog($context, '[TEST] Danh mục xưởng may');
        $companyPublicId = (string) Str::uuid();
        $company = (int) $context->insert('mfg_companies', [
            'public_id' => $companyPublicId, 'code' => $context->code('DN'), 'name' => '[TEST] Doanh nghiệp may mẫu',
            'timezone' => 'Asia/Ho_Chi_Minh', 'status' => 'active', 'version' => 1,
            'settings' => ManufacturingData::encode(['catalog_id' => $catalog, 'legal_entity_ref' => 'mfg-company:'.$companyPublicId]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $warehouse = InventoryTestDataProvider::warehouse($context, $catalog, 'mfg-company:'.$companyPublicId, true);
        $factory = $this->row($context, 'mfg_factories', $company, null, [
            'code' => $context->code('NM'), 'name' => '[TEST] Nhà máy mẫu', 'timezone' => 'Asia/Ho_Chi_Minh',
            'warehouse_id' => $warehouse['id'], 'settings' => '{}', 'status' => 'active',
        ]);
        $this->row($context, 'mfg_memberships', $company, null, [
            'factory_id' => null, 'admin_id' => $context->actor->id, 'role' => 'manager', 'status' => 'active',
        ]);
        $workshop = $this->row($context, 'mfg_workshops', $company, $factory, [
            'code' => $context->code('PX'), 'name' => '[TEST] Phân xưởng may', 'status' => 'active',
        ]);
        $this->row($context, 'mfg_lines', $company, $factory, [
            'workshop_id' => $workshop, 'code' => $context->code('CM'), 'name' => '[TEST] Chuyền may mẫu',
            'capacity_minutes' => '2400.000000', 'status' => 'active',
        ]);
        $this->row($context, 'mfg_shifts', $company, $factory, [
            'code' => $context->code('CA'), 'name' => '[TEST] Ca ngày',
            'starts_at' => '08:00:00', 'ends_at' => '17:00:00', 'settings' => '{}', 'status' => 'active',
        ]);
        $fabric = MerchandiseTestDataProvider::sku($context, $catalog, '[TEST] Vải cotton', 'm', 'batch');
        $finished = MerchandiseTestDataProvider::sku($context, $catalog, '[TEST] Áo mẫu / Xanh / M', 'pcs', 'batch');
        InventoryTestDataProvider::item($context, $fabric);
        InventoryTestDataProvider::item($context, $finished);
        $styleCode = $context->code('MH');
        $set = (int) $context->insert('merch_variant_sets', [
            'public_id' => (string) Str::uuid(), 'catalog_id' => $catalog, 'code' => $styleCode, 'name' => '[TEST] Áo mẫu',
            'axes' => ManufacturingData::encode(['color' => ['Xanh'], 'size' => ['M']]),
            'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $attributes = ['color' => 'Xanh', 'size' => 'M'];
        $context->insert('merch_sku_variants', [
            'variant_set_id' => $set, 'sku_id' => $finished['id'],
            'attributes' => json_encode($attributes, JSON_THROW_ON_ERROR),
            'combination_hash' => hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR)),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $metadata = [
            'sizes' => ['M'], 'colors' => ['Xanh'], 'variant_set_id' => $set,
            'variants' => [ManufacturingData::encode(['Xanh', 'M']) => [
                'sku_id' => $finished['id'], 'sku' => $finished['sku'], 'size' => 'M', 'color' => 'Xanh',
            ]],
        ];
        $style = $this->row($context, 'mfg_styles', $company, null, [
            'code' => $styleCode, 'name' => '[TEST] Áo mẫu', 'status' => 'draft',
            'description' => 'Mã hàng thử nghiệm chưa duyệt kỹ thuật.', 'metadata' => ManufacturingData::encode($metadata),
        ]);
        $revision = $this->row($context, 'mfg_style_revisions', $company, null, [
            'style_id' => $style, 'revision' => 1, 'status' => 'draft',
            'bom' => ManufacturingData::encode([[
                'sku_id' => $fabric['id'], 'material_code' => $fabric['sku'], 'name' => $fabric['name'],
                'uom' => 'm', 'quantity' => '1.500000', 'allowance_percent' => '3.000000', 'size' => null, 'color' => null,
            ]]),
            'routing' => ManufacturingData::encode([
                ['code' => 'TEST-CUT', 'name' => 'Cắt', 'smv' => '2.000000'],
                ['code' => 'TEST-SEW', 'name' => 'May', 'smv' => '8.000000'],
            ]),
            'measurements' => '[]', 'attachments' => '[]', 'metadata' => ManufacturingData::encode($metadata),
        ]);
        $this->row($context, 'mfg_samples', $company, null, [
            'style_id' => $style, 'style_revision_id' => $revision, 'type' => 'pp', 'status' => 'draft',
            'notes' => '[TEST] Mẫu PP chờ kiểm tra và duyệt.',
        ]);
    }

    private function row(ModuleTestDataContext $context, string $table, int $company, ?int $factory, array $values): int
    {
        $scope = ['company_id' => $company];
        if ($factory !== null) {
            $scope['factory_id'] = $factory;
        }

        return (int) $context->insert($table, $values + $scope + [
            'public_id' => (string) Str::uuid(), 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
