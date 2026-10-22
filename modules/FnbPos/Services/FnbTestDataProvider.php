<?php

namespace Modules\FnbPos\Services;

use App\Core\Modules\TestData\ModuleTestDataContext;
use App\Core\Modules\TestData\ModuleTestDataProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Isolated configuration fixtures; never publishes a menu or opens a sale. */
final class FnbTestDataProvider implements ModuleTestDataProvider
{
    public function preview(): array
    {
        return [
            'Một điểm bán thử riêng với quầy POS, trạm pha chế và hai bàn.',
            'Một thực đơn nháp và ba món mẫu đang tạm ngưng để không ảnh hưởng thực đơn đang bán.',
            'Phân công tài khoản hiện tại vào điểm bán thử; giữ nguyên quyền và cấu hình vận hành.',
        ];
    }

    public function create(ModuleTestDataContext $context): void
    {
        if (! DB::table('fnb_site_settings')->where('website_key', $context->websiteKey)
            ->where('is_active', true)->where('operational_state', 'active')->exists()) {
            throw ValidationException::withMessages([
                'module' => ['Hoàn tất khởi tạo và bật vận hành F&B cho website trước khi tạo dữ liệu thử nghiệm.'],
            ]);
        }

        $timestamps = ['created_at' => now(), 'updated_at' => now()];
        $website = ['website_key' => $context->websiteKey];
        $outletId = $context->insert('fnb_outlets', $website + [
            'public_id' => (string) Str::uuid(),
            'code' => $context->code('FNB'),
            'name' => 'Quán cafe thử nghiệm',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'currency' => 'VND',
            'status' => 'active',
            'settings' => json_encode(['cash_rounding_step_minor' => 500], JSON_THROW_ON_ERROR),
            'created_by' => $context->actor->id,
            'updated_by' => $context->actor->id,
            'version' => 1,
        ] + $timestamps);
        $scope = $website + ['outlet_id' => $outletId];

        $context->insert('fnb_terminals', $scope + [
            'public_id' => (string) Str::uuid(),
            'code' => $context->code('POS'),
            'name' => 'Quầy POS thử nghiệm',
            'type' => 'pos',
            'status' => 'active',
            'version' => 1,
        ] + $timestamps);
        $context->insert('fnb_outlet_staff', $scope + [
            'admin_id' => $context->actor->id,
            'is_active' => true,
            'is_default' => false,
            'assigned_by' => $context->actor->id,
            'version' => 1,
        ] + $timestamps);
        $stationId = $context->insert('fnb_prep_stations', $scope + [
            'code' => $context->code('BAR'),
            'name' => 'Bar thử nghiệm',
            'sla_seconds' => 600,
            'sort_order' => 0,
            'status' => 'active',
            'version' => 1,
        ] + $timestamps);
        $areaId = $context->insert('fnb_service_areas', $scope + [
            'code' => $context->code('AREA'),
            'name' => 'Khu vực thử nghiệm',
            'sort_order' => 0,
            'status' => 'active',
            'version' => 1,
        ] + $timestamps);
        foreach ([1, 2] as $number) {
            $context->insert('fnb_dining_tables', $scope + [
                'service_area_id' => $areaId,
                'code' => $context->code('TABLE'),
                'name' => 'Bàn thử '.$number,
                'capacity' => 4,
                'status' => 'available',
                'sort_order' => $number,
                'version' => 1,
            ] + $timestamps);
        }
        $context->insert('fnb_payment_methods', $scope + [
            'code' => $context->code('CASH'),
            'name' => 'Tiền mặt thử nghiệm',
            'kind' => 'cash',
            'requires_reference' => false,
            'sort_order' => 0,
            'status' => 'active',
            'version' => 1,
        ] + $timestamps);

        $menuId = $context->insert('fnb_menus', $website + [
            'code' => $context->code('MENU'),
            'name' => 'Thực đơn thử nghiệm · bản nháp',
            'status' => 'draft',
            'version' => 1,
        ] + $timestamps);
        $context->insert('fnb_menu_outlets', $scope + [
            'menu_id' => $menuId,
            'channel' => 'pos',
            'default_slot' => 'default',
            'status' => 'active',
            'version' => 1,
        ] + $timestamps);
        $categoryId = $context->insert('fnb_menu_categories', $website + [
            'menu_id' => $menuId,
            'code' => $context->code('DRINK'),
            'name' => 'Đồ uống thử nghiệm',
            'sort_order' => 0,
            'status' => 'active',
            'version' => 1,
        ] + $timestamps);

        foreach ([['Cà phê sữa thử nghiệm', 35000], ['Trà đào thử nghiệm', 45000], ['Cacao thử nghiệm', 40000]] as $sort => [$name, $price]) {
            $itemId = $context->insert('fnb_menu_items', $website + [
                'public_id' => (string) Str::uuid(),
                'code' => $context->code('ITEM'),
                'name' => $name,
                'description' => 'Dữ liệu thử nghiệm; chỉ bật và phát hành khi chủ động kiểm thử.',
                'item_type' => 'prepared',
                'tax_category' => 'standard',
                'tax_rate_bps' => 0,
                'tax_inclusive' => true,
                'status' => 'inactive',
                'version' => 1,
            ] + $timestamps);
            $variantId = $context->insert('fnb_item_variants', $website + [
                'item_id' => $itemId,
                'code' => $context->code('SIZE'),
                'name' => 'Tiêu chuẩn',
                'base_price_minor' => $price,
                'is_default' => true,
                'default_slot' => 'default',
                'sort_order' => 0,
                'status' => 'active',
                'version' => 1,
            ] + $timestamps);
            $context->insert('fnb_menu_category_items', $website + [
                'menu_id' => $menuId,
                'category_id' => $categoryId,
                'item_id' => $itemId,
                'sort_order' => $sort,
                'status' => 'active',
            ] + $timestamps);
            $context->insert('fnb_item_station_routes', $scope + [
                'item_id' => $itemId,
                'variant_id' => $variantId,
                'variant_key' => $variantId,
                'prep_station_id' => $stationId,
                'priority' => 0,
            ] + $timestamps);
        }
    }

    /** References without database foreign keys; other dependencies have native scoped FKs. */
    public function references(): array
    {
        return [
            ['table' => 'fnb_idempotency_requests', 'column' => 'outlet_id', 'foreign_table' => 'fnb_outlets', 'foreign_column' => 'id'],
            ['table' => 'fnb_idempotency_requests', 'column' => 'terminal_id', 'foreign_table' => 'fnb_terminals', 'foreign_column' => 'id'],
            ['table' => 'fnb_staff_role_scopes', 'column' => 'scope_key', 'foreign_table' => 'fnb_outlets', 'foreign_column' => 'id', 'where' => ['scope_type' => 'outlet']],
            ['table' => 'fnb_audit_scopes', 'column' => 'scope_value', 'foreign_table' => 'fnb_outlets', 'foreign_column' => 'id', 'where' => ['scope_type' => 'outlet']],
            ['table' => 'fnb_integration_item_mappings', 'column' => 'source_id', 'foreign_table' => 'fnb_item_variants', 'foreign_column' => 'id', 'where' => ['source_type' => 'variant']],
            ['table' => 'fnb_idempotency_requests', 'column' => 'resource_id', 'foreign_table' => 'fnb_menu_items', 'foreign_column' => 'id', 'where' => ['resource_type' => 'menu_item']],
            ['table' => 'fnb_outbox_events', 'column' => 'aggregate_id', 'foreign_table' => 'fnb_menu_items', 'foreign_column' => 'id', 'where' => ['aggregate_type' => 'menu_item']],
            ['table' => 'fnb_menu_publications', 'column' => 'snapshot->items->*->id', 'foreign_table' => 'fnb_menu_items', 'foreign_column' => 'id'],
            ['table' => 'fnb_menu_publications', 'column' => 'snapshot->items->*->variants->*->id', 'foreign_table' => 'fnb_item_variants', 'foreign_column' => 'id'],
            ['table' => 'fnb_menu_publications', 'column' => 'snapshot->categories->*->id', 'foreign_table' => 'fnb_menu_categories', 'foreign_column' => 'id'],
        ];
    }
}
