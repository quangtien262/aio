<?php

namespace Modules\Manufacturing\Services\Execution;

use App\Models\Admin;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Services\InventoryProductionService;
use Modules\Manufacturing\Services\Access\ManufacturingAccessService;
use Modules\Manufacturing\Services\ManufacturingData as D;

final class ExecutionService
{
    public function __construct(private readonly InventoryProductionService $inventory) {}

    public function cut(object $factory, array $data, Admin $actor, string $key): array
    {
        $v = validator($data, ['order_id' => 'required|uuid', 'code' => 'required|string|max:80', 'color' => 'required|string',
            'size' => 'required|string', 'quantity' => 'required', 'workshop_id' => 'required|uuid'])->validate();
        $order = D::row('mfg_orders', $v['order_id'], $factory->company_id, $factory->id, true);
        abort_unless(in_array($order->status, ['released', 'in_progress', 'partially_shipped'], true), 409, 'Lệnh chưa phát hành hoặc đã kết thúc.');
        $workshop = D::row('mfg_workshops', $v['workshop_id'], $factory->company_id, $factory->id);
        $line = DB::table('mfg_order_lines')->where('order_id', $order->id)->lockForUpdate()->get()->first(function ($row) use ($v): bool {
            $variant = D::json($row->variant);

            return $variant['color'] === $v['color'] && $variant['size'] === $v['size'];
        });
        abort_unless($line, 422, 'Màu/size không nằm trong lệnh.');
        $qty = D::pieces($v['quantity']);
        $cut = BigDecimal::zero();
        foreach (DB::table('mfg_bundles')->where('order_line_id', $line->id)->whereNotIn('status', ['cancelled', 'merged'])->get() as $prior) {
            $cut = $cut->plus((string) $prior->quantity)->minus(D::json($prior->metadata)['scrap_quantity'] ?? '0');
        }
        abort_if($cut->plus($qty)->isGreaterThan((string) $line->quantity), 409, 'Cắt vượt số lượng biến thể được giao.');
        abort_if(DB::table('mfg_bundles')->where('company_id', $factory->company_id)->where('code', $v['code'])->exists(), 422, 'Mã bó đã tồn tại.');
        $materials = $data['materials'] ?? [['material_id' => $data['material_id'] ?? '', 'consumed_quantity' => $data['consumed_quantity'] ?? '']];
        abort_unless(is_array($materials) && count($materials) > 0 && count($materials) <= 200, 422);
        $required = [];
        foreach (D::json($order->released_snapshot)['bom'] as $bom) {
            if ((! empty($bom['color']) && $bom['color'] !== $v['color']) || (! empty($bom['size']) && $bom['size'] !== $v['size'])) {
                continue;
            }
            $base = BigDecimal::of($bom['quantity'])->multipliedBy($qty);
            $allowance = $base->multipliedBy($bom['allowance_percent'] ?? '0')->dividedBy('100', 6, RoundingMode::HalfUp);
            $prior = $required[$bom['sku_id']] ?? ['base' => BigDecimal::zero(), 'maximum' => BigDecimal::zero()];
            $required[$bom['sku_id']] = ['base' => $prior['base']->plus($base), 'maximum' => $prior['maximum']->plus($base)->plus($allowance)];
        }
        $issues = [];
        $covered = [];
        foreach ($materials as $index => $material) {
            validator($material, ['issue_id' => 'nullable|uuid', 'material_id' => 'nullable|uuid', 'reservation_id' => 'nullable|uuid', 'consumed_quantity' => 'required'])->validate();
            abort_unless(count(array_filter([$material['issue_id'] ?? null, $material['material_id'] ?? null, $material['reservation_id'] ?? null])) === 1, 422, 'Mỗi dòng chọn đúng một nguồn: cuộn, giữ vật tư hoặc phiếu cấp.');
            $amount = (string) D::decimal($material['consumed_quantity'] ?? '');
            $newIssue = empty($material['issue_id']);
            if (! $newIssue) {
                $issued = DB::table('inv_production_issues')->where('public_id', $material['issue_id'])->where('warehouse_id', $factory->warehouse_id)
                    ->where('order_ref', $order->public_id)->lockForUpdate()->first();
                abort_unless($issued, 404, 'Phiếu cấp không thuộc lệnh và xưởng này.');
                $bound = BigDecimal::zero();
                foreach (DB::table('mfg_bundle_materials as a')->join('mfg_bundles as b', 'b.id', '=', 'a.bundle_id')->where('a.issue_id', $issued->public_id)
                    ->whereNotIn('b.status', ['merged', 'cancelled'])->get(['a.quantity', 'a.allocated_quantity']) as $allocation) {
                    $bound = $bound->plus(BigDecimal::of((string) $allocation->quantity)->minus((string) $allocation->allocated_quantity));
                }
                $free = BigDecimal::of((string) $issued->quantity)->minus((string) $issued->allocated_quantity)->minus((string) $issued->returned_quantity)->minus($bound);
                foreach ($issues as $assigned) {
                    if ($assigned['issue_id'] === $issued->public_id) {
                        $free = $free->minus($assigned['quantity']);
                    }
                }
                abort_if(BigDecimal::of($amount)->isGreaterThan($free), 409, 'Phiếu cấp không đủ phần chưa gắn vào bó.');
                $batchIds = DB::table('inv_production_issue_layers')->where('issue_id', $issued->id)->distinct()->pluck('batch_id');
                $roll = $batchIds->count() === 1 ? DB::table('inv_trace_units')->where('batch_id', $batchIds->first())->first() : null;
                $issue = (array) $issued;
                $itemId = $issued->item_id;
            } else {
                if (! empty($material['reservation_id'])) {
                    $held = DB::table('inv_reservations')->where('public_id', $material['reservation_id'])->where('warehouse_id', $factory->warehouse_id)
                        ->where('order_ref', $order->public_id)->where('purpose', 'production')->where('status', 'active')->lockForUpdate()->first();
                    abort_unless($held, 404, 'Giữ vật tư không thuộc lệnh.');
                    $hold = (array) $held;
                    $batchIds = DB::table('inv_reservation_allocations as a')->join('inv_stock_balances as b', 'b.id', '=', 'a.balance_id')
                        ->where('a.reservation_id', $held->id)->distinct()->pluck('b.batch_id');
                    $roll = $batchIds->count() === 1 ? DB::table('inv_trace_units')->where('batch_id', $batchIds->first())->first() : null;
                    $itemId = $held->item_id;
                } else {
                    $roll = DB::table('inv_trace_units')->where('public_id', $material['material_id'] ?? '')->where('warehouse_id', $factory->warehouse_id)->lockForUpdate()->first();
                    abort_unless($roll && $roll->disposition === 'saleable', 422, 'Cuộn phải thuộc kho xưởng và đã đạt kiểm nguyên liệu.');
                    $hold = $this->inventory->reserve(['warehouse_id' => $factory->warehouse_id, 'item_id' => $roll->item_id,
                        'roll_id' => $roll->public_id, 'quantity' => $amount, 'order_ref' => $order->public_id], $this->child($key, 'hold-'.$index), $actor->id);
                    $itemId = $roll->item_id;
                }
                $issue = $this->inventory->issue(['reservation_id' => $hold['public_id'], 'quantity' => $amount, 'expected_version' => $hold['version']], $this->child($key, 'issue-'.$index), $actor->id);
            }
            $sku = DB::table('inv_items')->where('id', $itemId)->value('merchandise_sku_id');
            abort_unless(isset($required[$sku]), 422, 'Nguyên liệu không thuộc BOM đã phát hành.');
            $issues[] = ['issue_id' => $issue['public_id'], 'quantity' => $amount, 'allocated_quantity' => '0.000000', 'roll_id' => $roll?->public_id, 'sku_id' => (int) $sku];
            $covered[$sku] = BigDecimal::of($covered[$sku] ?? '0')->plus($amount);
            $requirement = DB::table('mfg_material_requirements')->where('order_id', $order->id)->where('sku_id', $sku)->lockForUpdate()->first();
            abort_unless($requirement, 422);
            DB::table('mfg_material_requirements')->where('id', $requirement->id)->update([
                'issued_quantity' => (string) BigDecimal::of((string) $requirement->issued_quantity)->plus($newIssue ? $amount : '0'),
                'consumed_quantity' => (string) BigDecimal::of((string) $requirement->consumed_quantity)->plus($amount), 'updated_at' => now(),
            ]);
        }
        abort_if(array_diff_key($required, $covered) !== [], 422, 'Chưa khai báo đủ các nguyên liệu BOM cho lần cắt.');
        foreach ($covered as $skuId => $actual) {
            if ($actual->isLessThan($required[$skuId]['base']) || $actual->isGreaterThan($required[$skuId]['maximum'])) {
                validator($data, ['variance_reason' => 'required|string|min:5|max:2000'])->validate();
                app(ManufacturingAccessService::class)->factory($actor, (int) $factory->id, 'manufacturing.plan', true);
            }
        }
        $recut = null;
        if (! empty($data['recut_of'])) {
            validator($data, ['recut_of' => 'required|uuid', 'recut_reason' => 'required|string|min:5|max:2000'])->validate();
            $recut = D::row('mfg_bundles', $data['recut_of'], $factory->company_id, lock: true);
            abort_unless($recut->order_line_id === $line->id && BigDecimal::of(D::json($recut->metadata)['scrap_quantity'] ?? '0')->isPositive(), 422, 'Bó cắt bù cần tham chiếu bó cùng biến thể có lượng loại.');
        }
        $combined = [];
        foreach ($issues as $allocation) {
            $id = $allocation['issue_id'];
            if (isset($combined[$id])) {
                $combined[$id]['quantity'] = (string) BigDecimal::of($combined[$id]['quantity'])->plus($allocation['quantity']);
            } else {
                $combined[$id] = $allocation;
            }
        }
        $issues = array_values($combined);
        $routing = D::json($order->released_snapshot)['routing'];
        $bundle = D::insert('mfg_bundles', ['company_id' => $factory->company_id, 'factory_id' => $factory->id, 'order_id' => $order->id,
            'order_line_id' => $line->id, 'code' => $v['code'], 'sku_id' => $line->sku_id, 'variant' => $line->variant,
            'quantity' => $qty, 'operation_key' => $routing[0]['code'], 'workshop_id' => $workshop->id, 'status' => 'active',
            'metadata' => D::encode(['materials' => $issues, 'operation_index' => 0, 'routing_complete' => false, 'recut_of' => $recut?->public_id,
                'finished_quantity' => '0.000000', 'scrap_quantity' => '0.000000', 'cut_snapshot' => $data])]);
        D::syncBundleMaterials($bundle);
        DB::table('mfg_orders')->where('id', $order->id)->update(['status' => $order->status === 'partially_shipped' ? 'partially_shipped' : 'in_progress', 'version' => $order->version + 1, 'updated_at' => now()]);
        D::event($factory, 'cut.created', $order->id, $bundle->id, ['quantity' => $qty, 'source_ref' => $bundle->public_id], $actor->id);

        return D::resource($bundle);
    }

    public function split(object $factory, string $resource, array $data, Admin $actor): array
    {
        $b = D::row('mfg_bundles', $resource, $factory->company_id, $factory->id, true);
        abort_unless($b->status === 'active', 409, 'Chỉ tách bó đang ở xưởng.');
        $child = $this->splitRow($b, D::pieces($data['quantity'] ?? ''), $data['expected_version'] ?? null);
        D::event($factory, 'bundle.split', $b->order_id, $b->id, ['quantity' => $child->quantity, 'source_ref' => $child->public_id], $actor->id);

        return D::resource($child);
    }

    public function splitRow(object $bundle, string $quantity, mixed $version): object
    {
        $qty = D::decimal($quantity);
        $meta = D::json($bundle->metadata);
        abort_if(isset($meta['operation_targets'][$bundle->operation_key]), 409, 'Công đoạn đang ghi dở; hoàn tất trước khi tách bó.');
        $scrap = BigDecimal::of($meta['scrap_quantity'] ?? '0');
        $physical = BigDecimal::of((string) $bundle->quantity)->minus($scrap);
        abort_if($qty->isGreaterThanOrEqualTo((string) $bundle->quantity) || BigDecimal::of((string) $bundle->accepted_quantity)->isPositive()
            || $qty->isGreaterThan($physical) || BigDecimal::of($meta['qc_inspected'] ?? '0')->isPositive()
            || BigDecimal::of($meta['finished_quantity'] ?? '0')->isPositive(), 409, 'Chỉ tách phần còn ở công đoạn, chưa QC/thành phẩm.');
        $childMeta = $meta;
        $childMeta['scrap_quantity'] = '0.000000';
        $childMeta['operation_scrap'] = '0.000000';
        foreach ($meta['materials'] ?? [] as $i => $material) {
            // Scrap cost stays with the remaining good output, without losing input provenance.
            $part = $qty->isEqualTo($physical) ? BigDecimal::of($material['quantity'])
                : BigDecimal::of($material['quantity'])->multipliedBy($qty)->dividedBy($physical, 6, RoundingMode::Down);
            abort_unless($part->isPositive(), 422, 'Lượng quá nhỏ để tách allocation nguyên liệu.');
            $childMeta['materials'][$i]['quantity'] = (string) $part;
            $meta['materials'][$i]['quantity'] = (string) BigDecimal::of($material['quantity'])->minus($part);
        }
        $parentQuantity = BigDecimal::of((string) $bundle->quantity)->minus($qty);
        $parent = D::update('mfg_bundles', $bundle, ['quantity' => (string) $parentQuantity,
            'status' => $parentQuantity->isEqualTo($scrap) ? 'scrapped' : $bundle->status, 'metadata' => D::encode($meta)], $version);
        D::syncBundleMaterials($parent);
        $child = D::insert('mfg_bundles', ['company_id' => $bundle->company_id, 'factory_id' => $bundle->factory_id,
            'order_id' => $bundle->order_id, 'order_line_id' => $bundle->order_line_id, 'parent_id' => $bundle->id,
            'code' => substr($bundle->code, 0, 55).'-'.strtoupper(substr(bin2hex(random_bytes(5)), 0, 10)), 'sku_id' => $bundle->sku_id,
            'variant' => $bundle->variant, 'quantity' => (string) $qty, 'operation_key' => $bundle->operation_key,
            'workshop_id' => $bundle->workshop_id, 'line_id' => $bundle->line_id, 'status' => $bundle->status, 'metadata' => D::encode($childMeta)]);

        D::syncBundleMaterials($child);

        return $child;
    }

    public function merge(object $factory, string $resource, array $data, Admin $actor): array
    {
        $v = validator($data, ['source_bundle_id' => 'required|uuid', 'expected_version' => 'required|integer|min:1',
            'source_expected_version' => 'required|integer|min:1'])->validate();
        abort_if($resource === $v['source_bundle_id'], 422, 'Chọn hai bó khác nhau.');
        $rows = DB::table('mfg_bundles')->where('company_id', $factory->company_id)->where('factory_id', $factory->id)
            ->whereIn('public_id', [$resource, $v['source_bundle_id']])->orderBy('id')->lockForUpdate()->get()->keyBy('public_id');
        $target = $rows[$resource] ?? null;
        $source = $rows[$v['source_bundle_id']] ?? null;
        abort_unless($target && $source, 404);
        abort_unless($target->status === 'active' && $source->status === 'active'
            && $target->order_line_id === $source->order_line_id && $target->operation_key === $source->operation_key
            && $target->workshop_id === $source->workshop_id && $target->line_id === $source->line_id, 409, 'Chỉ gộp bó cùng biến thể, lệnh, vị trí và công đoạn.');
        $meta = D::json($target->metadata);
        $sourceMeta = D::json($source->metadata);
        foreach ([$target, $source] as $bundle) {
            $m = D::json($bundle->metadata);
            abort_if(isset($m['operation_targets'][$bundle->operation_key]) || BigDecimal::of((string) $bundle->accepted_quantity)->isPositive()
                || BigDecimal::of($m['scrap_quantity'] ?? '0')->isPositive() || BigDecimal::of($m['finished_quantity'] ?? '0')->isPositive(), 409, 'Bó đã xử lý dở, QC hoặc có hao hụt cần đối soát riêng.');
        }
        abort_unless(($meta['operation_index'] ?? 0) === ($sourceMeta['operation_index'] ?? 0), 409, 'Hai bó khác tiến độ routing.');
        $meta['materials'] = array_merge($meta['materials'] ?? [], $sourceMeta['materials'] ?? []);
        $combined = [];
        foreach ($meta['materials'] as $allocation) {
            $id = $allocation['issue_id'];
            if (isset($combined[$id])) {
                $combined[$id]['quantity'] = (string) BigDecimal::of($combined[$id]['quantity'])->plus($allocation['quantity']);
            } else {
                $combined[$id] = $allocation;
            }
        }
        $meta['materials'] = array_values($combined);
        $meta['merged_from'][] = $source->public_id;
        $target = D::update('mfg_bundles', $target, ['quantity' => (string) BigDecimal::of((string) $target->quantity)->plus((string) $source->quantity),
            'metadata' => D::encode($meta)], $v['expected_version']);
        D::update('mfg_bundles', $source, ['status' => 'merged', 'metadata' => D::metadata($source, ['merged_into' => $target->public_id])], $v['source_expected_version']);
        D::syncBundleMaterials($target);
        D::event($factory, 'bundle.merged', $target->order_id, $target->id, ['quantity' => $source->quantity, 'source_ref' => $source->public_id], $actor->id);

        return D::resource($target);
    }

    public function operation(object $factory, string $resource, array $data, Admin $actor): array
    {
        $b = D::row('mfg_bundles', $resource, $factory->company_id, $factory->id, true);
        abort_unless($b->status === 'active', 409, 'Bó đang đi đường, ở đối tác hoặc đã kết thúc.');
        $meta = D::json($b->metadata);
        abort_if($meta['routing_complete'] ?? false, 409, 'Đã hoàn tất routing.');
        $order = D::row('mfg_orders', (int) $b->order_id, $factory->company_id);
        $routing = D::json($order->released_snapshot)['routing'];
        $index = (int) ($meta['operation_index'] ?? 0);
        $op = $routing[$index] ?? null;
        abort_unless($op && ($data['operation_code'] ?? $b->operation_key) === $op['code'], 422, 'Công đoạn không đúng routing đã phát hành.');
        $qty = D::pieces($data['quantity'] ?? '');
        $good = D::pieces($data['good_quantity'] ?? $qty, true);
        $rework = D::pieces($data['rework_quantity'] ?? '0', true);
        $scrap = D::pieces($data['scrap_quantity'] ?? '0', true);
        abort_unless(BigDecimal::of($good)->plus($rework)->plus($scrap)->isEqualTo($qty), 422, 'Tốt + sửa + loại phải bằng lượng xử lý.');
        $existing = DB::table('mfg_operations')->where('bundle_id', $b->id)->where('operation_key', $op['code'])->pluck('quantity');
        $done = BigDecimal::zero();
        foreach ($existing as $n) {
            $done = $done->plus($n);
        }
        $target = BigDecimal::of($meta['operation_targets'][$op['code']] ?? BigDecimal::of((string) $b->quantity)->minus($meta['scrap_quantity'] ?? '0'));
        $meta['operation_targets'][$op['code']] = (string) $target;
        abort_if($done->plus($qty)->isGreaterThan($target), 409, 'Ghi vượt lượng còn lại tại công đoạn.');
        abort_if($rework !== '0.000000', 422, 'Ghi hàng cần sửa qua kiểm chất lượng để có phiếu sửa và tái kiểm.');
        D::insert('mfg_operations', ['company_id' => $factory->company_id, 'factory_id' => $factory->id,
            'bundle_id' => $b->id, 'order_id' => $order->id, 'operation_key' => $op['code'], 'workshop_id' => $b->workshop_id,
            'quantity' => $qty, 'accepted_quantity' => $good, 'rejected_quantity' => $scrap, 'rework_quantity' => $rework,
            'completed_at' => now(), 'recorded_by' => $actor->id, 'snapshot' => D::encode(['routing' => $op, 'work_minutes' => (string) D::decimal($data['work_minutes'] ?? '0', true)]), 'status' => 'completed']);
        $meta['scrap_quantity'] = (string) BigDecimal::of($meta['scrap_quantity'] ?? '0')->plus($scrap);
        if ($done->plus($qty)->isEqualTo($target)) {
            $meta['operation_index'] = $index + 1;
            $meta['routing_complete'] = ! isset($routing[$index + 1]);
        }
        $b = D::update('mfg_bundles', $b, ['operation_key' => $routing[$meta['operation_index']]['code'] ?? 'final_qc',
            'metadata' => D::encode($meta)], $data['expected_version'] ?? null);
        D::event($factory, 'operation.recorded', $order->id, $b->id, ['quantity' => $qty, 'operation' => $op['code']], $actor->id);

        return D::resource($b);
    }

    public function handover(object $factory, string $resource, array $data, Admin $actor): array
    {
        $b = D::row('mfg_bundles', $resource, $factory->company_id, $factory->id, true);
        abort_unless($b->status === 'active', 409, 'Bó không sẵn sàng bàn giao.');
        $target = D::row('mfg_workshops', (string) ($data['to_workshop_id'] ?? ''), $factory->company_id);
        app(ManufacturingAccessService::class)->factory($actor, (int) $target->factory_id, 'manufacturing.execute', true);
        abort_if(! empty($data['to_operation']) && $data['to_operation'] !== $b->operation_key, 422, 'Bàn giao không được bỏ qua routing.');
        $qty = D::pieces($data['quantity'] ?? '');
        $bMeta = D::json($b->metadata);
        abort_if(BigDecimal::of($qty)->isGreaterThan(BigDecimal::of((string) $b->quantity)->minus($bMeta['scrap_quantity'] ?? '0')), 422, 'Bàn giao vượt lượng còn lại sau loại.');
        abort_if(BigDecimal::of((string) $b->accepted_quantity)->isPositive() || BigDecimal::of($bMeta['finished_quantity'] ?? '0')->isPositive(), 409, 'Hàng đã QC/thành phẩm cần luồng điều chuyển kho riêng.');
        if (BigDecimal::of($qty)->isLessThan((string) $b->quantity)) {
            $b = $this->splitRow($b, $qty, $data['expected_version'] ?? null);
        } else {
            abort_unless((int) ($data['expected_version'] ?? 0) === (int) $b->version, 409, 'Phiên bản bó đã đổi.');
        }
        $handover = D::insert('mfg_handovers', ['company_id' => $factory->company_id, 'factory_id' => $factory->id,
            'bundle_id' => $b->id, 'from_factory_id' => $factory->id, 'to_factory_id' => $target->factory_id,
            'quantity' => $qty, 'dispatched_by' => $actor->id, 'dispatched_at' => now(), 'status' => 'in_transit',
            'metadata' => D::encode(['to_workshop_id' => $target->id])]);
        D::update('mfg_bundles', $b, ['status' => 'in_transit'], $b->version);
        D::event($factory, 'handover.dispatched', $b->order_id, $b->id, ['quantity' => $qty, 'source_ref' => $handover->public_id], $actor->id);

        return D::resource($handover);
    }

    public function receiveHandover(object $factory, string $resource, array $data, Admin $actor): array
    {
        $h = D::row('mfg_handovers', $resource, $factory->company_id, lock: true);
        abort_unless((int) $h->to_factory_id === (int) $factory->id && $h->status === 'in_transit', 409, 'Phiếu không chờ nhận tại xưởng này.');
        $b = D::row('mfg_bundles', (int) $h->bundle_id, $factory->company_id, lock: true);
        $qty = D::pieces($data['quantity'] ?? '');
        abort_if(BigDecimal::of((string) $h->received_quantity)->plus($qty)->isGreaterThan((string) $h->quantity), 409, 'Nhận vượt phần còn đang đi.');
        $received = BigDecimal::of((string) $h->received_quantity)->plus($qty);
        $isFull = $received->isEqualTo((string) $h->quantity);
        if (BigDecimal::of($qty)->isLessThan((string) $b->quantity)) {
            $b = $this->splitRow($b, $qty, $b->version);
        }
        $b = D::update('mfg_bundles', $b, ['factory_id' => $factory->id, 'workshop_id' => D::json($h->metadata)['to_workshop_id'], 'status' => 'active'], $b->version);
        D::syncBundleMaterials($b);
        $h = D::update('mfg_handovers', $h, ['received_quantity' => (string) $received, 'received_by' => $actor->id,
            'received_at' => now(), 'status' => $isFull ? 'received' : 'in_transit'], $data['expected_version'] ?? null);
        D::event($factory, 'handover.received', $b->order_id, $b->id, ['quantity' => $qty, 'source_ref' => $h->public_id], $actor->id);

        return D::resource($h);
    }

    public function child(string $key, string $suffix): string
    {
        return 'mfg:'.hash('sha256', $key.'|'.$suffix);
    }
}
