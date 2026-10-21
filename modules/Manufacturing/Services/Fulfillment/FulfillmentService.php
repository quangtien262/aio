<?php

namespace Modules\Manufacturing\Services\Fulfillment;

use App\Models\Admin;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Services\InventoryProductionService;
use Modules\Manufacturing\Services\Execution\ExecutionService;
use Modules\Manufacturing\Services\ManufacturingData as D;

final class FulfillmentService
{
    public function __construct(private readonly InventoryProductionService $inventory, private readonly ExecutionService $execution) {}

    public function finish(object $factory, string $resource, array $data, Admin $actor, string $key): array
    {
        $b = D::row('mfg_bundles', $resource, $factory->company_id, $factory->id, true);
        $meta = D::json($b->metadata);
        abort_unless($b->status === 'active' && ($meta['routing_complete'] ?? false), 409, 'Bó còn công đoạn, hold hoặc đang ngoài xưởng.');
        $remaining = BigDecimal::of((string) $b->accepted_quantity)->minus($meta['finished_quantity'] ?? '0');
        $qty = D::pieces($data['quantity'] ?? (string) $remaining);
        abort_if(BigDecimal::of($qty)->isGreaterThan($remaining), 409, 'Nhập vượt lượng đã đạt QC còn lại.');
        $order = D::row('mfg_orders', (int) $b->order_id, $factory->company_id, lock: true);
        $line = D::row('mfg_order_lines', (int) $b->order_line_id, $factory->company_id, lock: true);
        abort_if(BigDecimal::of((string) $line->completed_quantity)->plus($qty)->isGreaterThan((string) $line->quantity), 409, 'Thành phẩm vượt lượng lệnh.');
        $materials = [];
        foreach ($meta['materials'] as $i => $allocation) {
            $free = BigDecimal::of($allocation['quantity'])->minus($allocation['allocated_quantity'] ?? '0');
            $take = BigDecimal::of($qty)->isEqualTo($remaining) ? $free : $free->multipliedBy($qty)->dividedBy($remaining, 6, RoundingMode::Down);
            abort_unless($take->isPositive(), 409, 'Allocation nguyên liệu quá nhỏ hoặc đã quyết toán.');
            $materials[] = ['issue_id' => $allocation['issue_id'], 'quantity' => (string) $take];
            $meta['materials'][$i]['allocated_quantity'] = (string) BigDecimal::of($allocation['allocated_quantity'] ?? '0')->plus($take);
        }
        $conversion = BigDecimal::zero();
        foreach (DB::table('mfg_cost_entries')->where('order_id', $order->id)->where('status', 'approved')->whereIn('type', ['labor', 'overhead', 'subcontract'])->pluck('amount') as $amount) {
            $conversion = $conversion->plus((string) $amount);
        }
        $planned = BigDecimal::zero();
        foreach (DB::table('mfg_order_lines')->where('order_id', $order->id)->pluck('quantity') as $q) {
            $planned = $planned->plus((string) $q);
        }
        $conversionShare = $conversion->multipliedBy($qty)->dividedBy($planned, 6, RoundingMode::Down);
        $post = $this->inventory->postOutput(['warehouse_id' => $factory->warehouse_id, 'order_ref' => $order->public_id,
            'materials' => $materials, 'conversion_cost' => (string) $conversionShare,
            'outputs' => [['sku_id' => $b->sku_id, 'quantity' => $qty, 'code' => 'FG-'.substr(hash('sha256', $key), 0, 25)]]], $this->execution->child($key, 'fg'), $actor->id);
        foreach ($post['outputs'] as $output) {
            $roll = $this->inventory->roll($output['roll_id']);
            $this->inventory->inspectRoll(['roll_id' => $output['roll_id'], 'expected_version' => $roll['version'],
                'disposition' => 'saleable', 'reason' => 'Final QC '.$b->public_id], $this->execution->child($key, 'qc-'.$output['roll_id']), $actor->id);
            $meta['finished_lots'][] = ['roll_id' => $output['roll_id'], 'quantity' => $qty, 'packed_quantity' => '0.000000',
                'posting_id' => $post['public_id'], 'cost' => $output['allocated_cost']];
        }
        $meta['finished_quantity'] = (string) BigDecimal::of($meta['finished_quantity'] ?? '0')->plus($qty);
        $b = D::update('mfg_bundles', $b, ['metadata' => D::encode($meta)], $data['expected_version'] ?? null);
        D::syncBundleMaterials($b);
        DB::table('mfg_order_lines')->where('id', $line->id)->update(['completed_quantity' => (string) BigDecimal::of((string) $line->completed_quantity)->plus($qty), 'updated_at' => now()]);
        D::event($factory, 'finished.received', $order->id, $b->id, ['quantity' => $qty, 'source_ref' => $post['public_id']], $actor->id);

        return D::resource($b);
    }

    public function pack(object $factory, array $data, Admin $actor): array
    {
        $v = validator($data, ['order_id' => 'required|uuid', 'code' => 'required|string|max:70', 'items' => 'required|array|min:1|max:200'])->validate();
        $order = D::row('mfg_orders', $v['order_id'], $factory->company_id, lock: true);
        abort_unless(in_array($order->status, ['in_progress', 'released', 'partially_shipped'], true), 409, 'Lệnh không ở trạng thái đóng gói.');
        $contents = [];
        $quantity = BigDecimal::zero();
        $seen = [];
        foreach ($v['items'] as $input) {
            $b = D::row('mfg_bundles', (string) ($input['bundle_id'] ?? ''), $factory->company_id, $factory->id, true);
            abort_unless((int) $b->order_id === (int) $order->id && $b->status === 'active' && ! isset($seen[$b->id]), 422, 'Bó không hợp lệ hoặc bị trùng trong carton.');
            $seen[$b->id] = true;
            $meta = D::json($b->metadata);
            $qty = D::decimal(D::pieces($input['quantity'] ?? ''));
            $left = $qty;
            foreach ($meta['finished_lots'] ?? [] as $i => $lot) {
                $free = BigDecimal::of($lot['quantity'])->minus($lot['packed_quantity']);
                $take = BigDecimal::min($free, $left);
                if (! $take->isPositive()) {
                    continue;
                }
                $contents[] = ['bundle_id' => $b->public_id, 'order_line_id' => $b->order_line_id, 'sku_id' => $b->sku_id,
                    'variant' => D::json($b->variant), 'roll_id' => $lot['roll_id'], 'quantity' => (string) $take];
                $meta['finished_lots'][$i]['packed_quantity'] = (string) BigDecimal::of($lot['packed_quantity'])->plus($take);
                $left = $left->minus($take);
                if ($left->isZero()) {
                    break;
                }
            }
            abort_unless($left->isZero(), 409, 'Không đủ thành phẩm đã nhập để đóng gói.');
            D::update('mfg_bundles', $b, ['metadata' => D::encode($meta)], $b->version);
            $quantity = $quantity->plus($qty);
        }
        abort_if(DB::table('mfg_cartons')->where('company_id', $factory->company_id)->where('code', $v['code'])->exists(), 422, 'Mã carton đã tồn tại.');
        $carton = D::insert('mfg_cartons', ['company_id' => $factory->company_id, 'factory_id' => $factory->id, 'order_id' => $order->id,
            'code' => $v['code'], 'contents' => D::encode($contents), 'quantity' => (string) $quantity, 'status' => 'packed']);
        $shipment = D::insert('mfg_shipments', ['company_id' => $factory->company_id, 'factory_id' => $factory->id,
            'order_id' => $order->id, 'code' => 'SHP-'.$v['code'], 'lines' => D::encode($contents),
            'carton_ids' => D::encode([$carton->id]), 'quantity' => (string) $quantity,
            'destination_snapshot' => D::encode($data['destination'] ?? D::json($order->buyer_snapshot)), 'status' => 'packed']);
        foreach ($contents as $entry) {
            D::insert('mfg_shipment_lines', ['company_id' => $factory->company_id, 'factory_id' => $factory->id,
                'shipment_id' => $shipment->id, 'order_line_id' => $entry['order_line_id'], 'carton_id' => $carton->id, 'quantity' => $entry['quantity'], 'status' => 'packed']);
        }
        D::event($factory, 'shipment.packed', $order->id, null, ['quantity' => (string) $quantity, 'source_ref' => $shipment->public_id], $actor->id);

        return D::resource($shipment);
    }

    public function dispatch(object $factory, string $resource, array $data, Admin $actor, string $key): array
    {
        $shipment = D::row('mfg_shipments', $resource, $factory->company_id, $factory->id, true);
        abort_unless($shipment->status === 'packed', 409, 'Lô giao không còn chờ xuất.');
        $order = D::row('mfg_orders', (int) $shipment->order_id, $factory->company_id, lock: true);
        foreach (D::json($shipment->lines) as $i => $entry) {
            $b = D::row('mfg_bundles', $entry['bundle_id'], $factory->company_id, $factory->id, true);
            abort_unless($b->status === 'active', 409, 'Có bó đang bị giữ; xử lý trước khi giao.');
            $roll = $this->inventory->roll($entry['roll_id']);
            abort_unless((int) $roll['warehouse_id'] === (int) $factory->warehouse_id && $roll['disposition'] === 'saleable', 409, 'Thành phẩm không đủ điều kiện xuất.');
            $hold = $this->inventory->reserve(['warehouse_id' => $factory->warehouse_id, 'item_id' => $roll['item_id'], 'roll_id' => $roll['public_id'],
                'quantity' => $entry['quantity'], 'order_ref' => 'shipment:'.$shipment->public_id], $this->execution->child($key, 'ship-hold-'.$i), $actor->id);
            $this->inventory->issue(['reservation_id' => $hold['public_id'], 'quantity' => $entry['quantity'], 'expected_version' => $hold['version']], $this->execution->child($key, 'ship-issue-'.$i), $actor->id);
            $line = D::row('mfg_order_lines', (int) $entry['order_line_id'], $factory->company_id, lock: true);
            $after = BigDecimal::of((string) $line->shipped_quantity)->plus($entry['quantity']);
            abort_if($after->isGreaterThan((string) $line->completed_quantity) || $after->isGreaterThan((string) $line->quantity), 409, 'Giao vượt thành phẩm hoặc cam kết.');
            DB::table('mfg_order_lines')->where('id', $line->id)->update(['shipped_quantity' => (string) $after, 'updated_at' => now()]);
            if ($line->demand_line_id) {
                $demand = DB::table('mfg_demand_lines')->where('id', $line->demand_line_id)->lockForUpdate()->first();
                DB::table('mfg_demand_lines')->where('id', $demand->id)->update(['fulfilled_quantity' => (string) BigDecimal::of((string) $demand->fulfilled_quantity)->plus($entry['quantity']), 'updated_at' => now()]);
            }
        }
        foreach (D::json($shipment->carton_ids) as $id) {
            $c = D::row('mfg_cartons', (int) $id, $factory->company_id, $factory->id, true);
            D::update('mfg_cartons', $c, ['status' => 'shipped'], $c->version);
        }
        $shipment = D::update('mfg_shipments', $shipment, ['status' => 'shipped', 'shipped_at' => now(),
            'metadata' => D::metadata($shipment, ['reference' => mb_substr((string) ($data['reference'] ?? ''), 0, 255)])], $data['expected_version'] ?? null);
        $all = DB::table('mfg_order_lines')->where('order_id', $order->id)->get()->every(fn ($l) => BigDecimal::of((string) $l->shipped_quantity)->isEqualTo((string) $l->quantity));
        DB::table('mfg_orders')->where('id', $order->id)->update(['status' => $all ? ((D::json($order->metadata)['cost_closed'] ?? false) ? 'closed' : 'shipped') : 'partially_shipped', 'version' => $order->version + 1, 'updated_at' => now()]);
        D::event($factory, 'shipment.dispatched', $order->id, null, ['quantity' => $shipment->quantity, 'source_ref' => $shipment->public_id], $actor->id);

        return D::resource($shipment);
    }

    public function cancel(object $factory, string $resource, array $data, Admin $actor): array
    {
        $v = validator($data, ['reason' => 'required|string|min:5|max:2000', 'expected_version' => 'required|integer|min:1'])->validate();
        $shipment = D::row('mfg_shipments', $resource, $factory->company_id, $factory->id, true);
        abort_unless($shipment->status === 'packed', 409, 'Chỉ hủy lô chưa xuất kho.');
        foreach (D::json($shipment->lines) as $entry) {
            $b = D::row('mfg_bundles', $entry['bundle_id'], $factory->company_id, $factory->id, true);
            $meta = D::json($b->metadata);
            $found = false;
            foreach ($meta['finished_lots'] ?? [] as $index => $lot) {
                if ($lot['roll_id'] !== $entry['roll_id']) {
                    continue;
                }
                $remaining = BigDecimal::of($lot['packed_quantity'])->minus($entry['quantity']);
                abort_if($remaining->isNegative(), 409, 'Số lượng đóng gói cần đối soát.');
                $meta['finished_lots'][$index]['packed_quantity'] = (string) $remaining;
                $found = true;
                break;
            }
            abort_unless($found, 409, 'Không tìm thấy lô thành phẩm gốc.');
            D::update('mfg_bundles', $b, ['metadata' => D::encode($meta)], $b->version);
        }
        foreach (D::json($shipment->carton_ids) as $id) {
            $carton = D::row('mfg_cartons', (int) $id, $factory->company_id, $factory->id, true);
            D::update('mfg_cartons', $carton, ['status' => 'cancelled'], $carton->version);
        }
        DB::table('mfg_shipment_lines')->where('shipment_id', $shipment->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
        $shipment = D::update('mfg_shipments', $shipment, ['status' => 'cancelled', 'metadata' => D::metadata($shipment, ['cancel_reason' => $v['reason'], 'cancelled_by' => $actor->id])], $v['expected_version']);
        D::event($factory, 'shipment.cancelled', $shipment->order_id, null, ['source_ref' => $shipment->public_id, 'reason' => $v['reason']], $actor->id);

        return D::resource($shipment);
    }
}
