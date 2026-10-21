<?php

namespace Modules\Manufacturing\Services\Execution;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\Admin;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Inventory\Services\InventoryProductionService;
use Modules\Manufacturing\Services\ManufacturingData as D;
use Modules\Purchasing\Services\PurchasingProductionService;
use Modules\Purchasing\Services\PurchasingService;

final class MaterialService
{
    public function __construct(private readonly InventoryProductionService $inventory, private readonly ExecutionService $execution) {}

    public function run(object $factory, string $operation, ?string $resource, array $data, Admin $actor, string $key): array
    {
        if (str_starts_with($operation, 'procurement.')) {
            return $this->purchase($factory, $operation, $resource, $data, $actor, $key);
        }
        if ($operation === 'material.receive') {
            abort_unless($actor->canAccess('manufacturing.settings', 'global'), 403, 'Nhập tồn đầu kỳ cần quyền thiết lập.');
            $v = validator($data, ['code' => 'required|string|max:80', 'roll_code' => 'required|string|max:100',
                'quantity' => 'required', 'unit_cost' => 'required', 'reason' => 'required|string|min:5|max:1000'])->validate();
            $sku = $this->sku($factory, $v['code']);

            return $this->inventory->createRoll(['warehouse_id' => $factory->warehouse_id, 'sku_id' => $sku->id,
                'code' => $v['roll_code'], 'quantity' => (string) D::decimal($v['quantity']), 'unit_cost' => (string) D::decimal($v['unit_cost'], true),
                'lot_code' => $data['lot_code'] ?? null, 'secondary_quantity' => $data['secondary_quantity'] ?? null,
                'secondary_unit' => $data['secondary_unit'] ?? null, 'source_type' => 'manufacturing.opening', 'source_id' => $key,
                'metadata' => ['reason' => $v['reason'], 'shade' => $data['shade'] ?? null, 'width' => $data['width'] ?? null]], $key, $actor->id);
        }
        if ($operation === 'material.inspect') {
            $roll = $this->roll($factory, $resource ?? $data['material_id'] ?? '');

            return $this->inventory->inspectRoll(['roll_id' => $roll->public_id, 'expected_version' => $data['expected_version'] ?? null,
                'disposition' => match ($data['status'] ?? '') {
                    'accepted' => 'saleable', 'rejected' => 'rejected', default => 'quarantine'
                },
                'reason' => $data['notes'] ?? $data['reason'] ?? 'Kiểm nguyên liệu'], $key, $actor->id);
        }
        if ($operation === 'material.reserve') {
            $roll = $this->roll($factory, $resource ?? $data['material_id'] ?? '');
            $order = D::row('mfg_orders', (string) ($data['order_id'] ?? ''), $factory->company_id, $factory->id);
            abort_unless(in_array($order->status, ['released', 'in_progress', 'partially_shipped'], true), 409, 'Lệnh chưa được phát hành.');
            $sku = DB::table('inv_items')->where('id', $roll->item_id)->value('merchandise_sku_id');
            abort_unless(DB::table('mfg_material_requirements')->where('order_id', $order->id)->where('sku_id', $sku)->exists(), 422, 'Nguyên liệu không thuộc lệnh.');

            return $this->inventory->reserve(['warehouse_id' => $factory->warehouse_id, 'item_id' => $roll->item_id,
                'roll_id' => $roll->public_id, 'quantity' => (string) D::decimal($data['quantity'] ?? ''), 'order_ref' => $order->public_id], $key, $actor->id);
        }
        if (in_array($operation, ['material.issue', 'material.release'], true)) {
            $r = DB::table('inv_reservations')->where('public_id', $data['reservation_id'] ?? $resource ?? '')
                ->where('warehouse_id', $factory->warehouse_id)->where('purpose', 'production')->first();
            abort_unless($r && DB::table('mfg_orders')->where('public_id', $r->order_ref)->where('factory_id', $factory->id)->exists(), 404);
            $input = ['reservation_id' => $r->public_id, 'quantity' => (string) D::decimal($data['quantity'] ?? ''), 'expected_version' => $data['expected_version'] ?? null];

            $result = $operation === 'material.issue' ? $this->inventory->issue($input, $key, $actor->id) : $this->inventory->release($input, $key, $actor->id);
            if ($operation === 'material.issue') {
                $orderId = DB::table('mfg_orders')->where('public_id', $r->order_ref)->value('id');
                $skuId = DB::table('inv_items')->where('id', $r->item_id)->value('merchandise_sku_id');
                $requirement = DB::table('mfg_material_requirements')->where('order_id', $orderId)->where('sku_id', $skuId)->lockForUpdate()->first();
                abort_unless($requirement, 422, 'Vật tư không thuộc định mức lệnh.');
                DB::table('mfg_material_requirements')->where('id', $requirement->id)->update(['issued_quantity' => (string) BigDecimal::of((string) $requirement->issued_quantity)->plus($input['quantity']), 'updated_at' => now()]);
            }

            return $result;
        }
        if ($operation === 'material.return') {
            $issue = DB::table('inv_production_issues')->where('public_id', $data['issue_id'] ?? $resource ?? '')
                ->where('warehouse_id', $factory->warehouse_id)->lockForUpdate()->first();
            abort_unless($issue && DB::table('mfg_orders')->where('public_id', $issue->order_ref)->where('company_id', $factory->company_id)->exists(), 404);
            $bound = BigDecimal::zero();
            foreach (DB::table('mfg_bundle_materials as m')->join('mfg_bundles as b', 'b.id', '=', 'm.bundle_id')->where('m.issue_id', $issue->public_id)
                ->whereNotIn('b.status', ['merged', 'cancelled'])->get(['m.quantity', 'm.allocated_quantity']) as $allocation) {
                $bound = $bound->plus(BigDecimal::of((string) $allocation->quantity)->minus((string) $allocation->allocated_quantity));
            }
            $free = BigDecimal::of((string) $issue->quantity)->minus((string) $issue->returned_quantity)->minus((string) $issue->allocated_quantity)->minus($bound);
            abort_if(D::decimal($data['quantity'] ?? '')->isGreaterThan($free), 409, 'Vật tư đã gắn vào bó; đối soát cắt trước khi trả.');

            $result = $this->inventory->returnMaterial(['issue_id' => $issue->public_id, 'quantity' => $data['quantity'],
                'expected_version' => $data['expected_version'] ?? null], $key, $actor->id);
            $orderId = DB::table('mfg_orders')->where('public_id', $issue->order_ref)->value('id');
            $skuId = DB::table('inv_items')->where('id', $issue->item_id)->value('merchandise_sku_id');
            $requirement = DB::table('mfg_material_requirements')->where('order_id', $orderId)->where('sku_id', $skuId)->lockForUpdate()->first();
            abort_unless($requirement, 422);
            DB::table('mfg_material_requirements')->where('id', $requirement->id)->update(['returned_quantity' => (string) BigDecimal::of((string) $requirement->returned_quantity)->plus($data['quantity']), 'updated_at' => now()]);

            return $result;
        }
        if ($operation === 'material.stocktake') {
            abort_unless($actor->canAccess('inventory.stocktake.manage', 'global'), 403);

            return $this->inventory->stocktake(array_replace($data, ['warehouse_id' => $factory->warehouse_id]), $key, $actor->id);
        }
        abort(404);
    }

    private function purchase(object $factory, string $operation, ?string $resource, array $data, Admin $actor, string $key): array
    {
        $capabilities = app(ModuleCapabilityChecker::class);
        abort_unless($capabilities->has('purchasing', 'purchasing.production.requisitions.v1') && $capabilities->has('purchasing', 'purchasing.receipt_lines.identity.v1'), 503, 'Cần cài/nâng Purchasing với contract nguyên liệu FOB và identity receipt lines.');
        $service = app(PurchasingProductionService::class);
        $base = app(PurchasingService::class);
        if ($operation === 'procurement.create') {
            abort_unless($actor->canAccess('purchasing.order.create', 'global') && $actor->canAccess('inventory.receipt.manage', 'global'), 403);
            $v = validator($data, ['supplier_id' => 'nullable|integer', 'supplier_name' => 'nullable|string|max:255',
                'order_id' => 'nullable|uuid', 'source_revision' => 'nullable|integer|min:1', 'lines' => 'required|array|min:1|max:200'])->validate();
            $sourceOrder = ! empty($v['order_id']) ? D::row('mfg_orders', $v['order_id'], $factory->company_id, $factory->id, true) : null;
            if ($sourceOrder) {
                abort_unless(in_array($sourceOrder->status, ['draft', 'released', 'in_progress', 'partially_shipped'], true), 409, 'Lệnh không còn nhận đề nghị mua.');
                abort_unless((int) ($v['source_revision'] ?? 0) === (int) $sourceOrder->version, 409, 'MRP đã đổi phiên bản; tính lại trước khi mua.');
            }
            $supplier = $v['supplier_id'] ?? null;
            if (! $supplier) {
                abort_unless($actor->canAccess('purchasing.supplier.manage', 'global') && ! empty($v['supplier_name']), 403, 'Chọn nhà cung cấp hoặc có quyền tạo nhà cung cấp mới.');
                $s = $base->createSupplier(['code' => 'MFG-'.strtoupper(substr(hash('sha256', $key), 0, 16)), 'name' => $v['supplier_name']], $this->execution->child($key, 'supplier'), $actor->id);
                $supplier = $s['id'];
            }
            $lines = [];
            foreach ($v['lines'] as $line) {
                $sku = $this->sku($factory, $line['material_code'] ?? $line['code'] ?? $line['sku_id'] ?? '');
                if ($sourceOrder) {
                    $snapshot = D::json($sourceOrder->released_snapshot);
                    $bom = $snapshot['bom'] ?? D::json(D::row('mfg_style_revisions', (int) $sourceOrder->style_revision_id, $factory->company_id)->bom);
                    abort_unless(in_array((int) $sku->id, array_column($bom, 'sku_id'), true), 422, 'Nguyên liệu mua không thuộc BOM lệnh nguồn.');
                }
                $lines[] = ['sku_id' => $sku->id, 'quantity' => (string) D::decimal($line['quantity'] ?? ''), 'unit_cost' => (string) D::decimal($line['unit_cost'] ?? '', true)];
            }
            $warehouse = DB::table('inv_warehouses')->find($factory->warehouse_id);
            $req = $service->requisition(['warehouse_id' => $warehouse->id, 'catalog_id' => $warehouse->catalog_id,
                'order_ref' => $sourceOrder?->public_id ?? 'manufacturing:'.$factory->public_id.':'.$key,
                'source_revision' => $sourceOrder ? (string) $sourceOrder->version : '1', 'lines' => $lines], $this->execution->child($key, 'req'), $actor->id);

            return $service->createOrder(['warehouse_id' => $warehouse->id, 'catalog_id' => $warehouse->catalog_id,
                'supplier_id' => $supplier, 'requisition_id' => $req['public_id'], 'lines' => $lines], $this->execution->child($key, 'po'), $actor->id);
        }
        $po = DB::table('purchase_orders')->where('public_id', $resource ?? '')->where('warehouse_id', $factory->warehouse_id)->first();
        abort_unless($po, 404, 'Đơn mua không thuộc kho phân xưởng.');
        if ($operation === 'procurement.approve') {
            abort_unless($actor->canAccess('purchasing.order.approve', 'global'), 403);

            return $base->approveOrder($po->public_id, (int) ($data['expected_version'] ?? 0), $key, $actor->id);
        }
        if ($operation === 'procurement.receive') {
            $lines = [];
            foreach ($data['lines'] ?? [] as $line) {
                $lineId = $line['order_line_id'] ?? '';
                $source = DB::table('purchase_order_lines')->where(is_string($lineId) && Str::isUuid($lineId) ? 'public_id' : 'id', $lineId)->where('order_id', $po->id)->first();
                abort_unless($source, 422, 'Dòng nhận không thuộc đơn mua.');
                $lines[] = ['order_line_id' => $source->id, 'rolls' => $line['rolls'] ?? [[
                    'code' => $line['roll_code'] ?? '', 'quantity' => $line['quantity'] ?? '', 'lot_code' => $line['lot_code'] ?? null,
                    'secondary_quantity' => $line['secondary_quantity'] ?? null, 'secondary_unit' => $line['secondary_unit'] ?? null,
                    'metadata' => ['shade' => $line['shade'] ?? null, 'width' => $line['width'] ?? null],
                ]]];
            }

            return $service->receive(['order_id' => $po->public_id, 'expected_version' => $data['expected_version'] ?? null, 'lines' => $lines], $key, $actor->id);
        }
        if ($operation === 'procurement.return') {
            $receiptLineId = $data['receipt_line_id'] ?? '';
            $receiptLine = DB::table('purchase_receipt_lines as l')->join('purchase_receipts as r', 'r.id', '=', 'l.receipt_id')
                ->where(is_string($receiptLineId) && Str::isUuid($receiptLineId) ? 'l.public_id' : 'l.id', $receiptLineId)
                ->where('r.order_id', $po->id)->first(['l.id']);
            abort_unless($receiptLine, 404, 'Dòng trả không thuộc đơn mua trong phạm vi.');

            return $service->returnRoll(['receipt_line_id' => $receiptLine->id, 'quantity' => $data['quantity'] ?? '',
                'expected_version' => $data['expected_version'] ?? null, 'reason' => $data['reason'] ?? 'Trả NCC'], $key, $actor->id);
        }
        abort(404);
    }

    private function roll(object $factory, string $id): object
    {
        $roll = DB::table('inv_trace_units')->where('public_id', $id)->where('warehouse_id', $factory->warehouse_id)->first();
        abort_unless($roll, 404, 'Cuộn không thuộc kho đang quản trị.');

        return $roll;
    }

    private function sku(object $factory, int|string $code): object
    {
        $catalog = DB::table('inv_warehouses')->where('id', $factory->warehouse_id)->value('catalog_id');
        $sku = DB::table('merch_skus')->where('catalog_id', $catalog)->where(is_int($code) ? 'id' : 'sku', $code)->where('is_active', true)->first();
        abort_unless($sku, 422, 'Mã nguyên liệu chưa tồn tại trong danh mục doanh nghiệp.');

        return $sku;
    }
}
