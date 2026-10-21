<?php

namespace Modules\Inventory\Services;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\Admin;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Internal company-owned production boundary. Native callers MUST authorize actor,
 * order, factory and warehouse before each call, including command replay. This is
 * not an unscoped HTTP API. Inventory owns physical quantity and stock valuation.
 */
class InventoryProductionService
{
    public function __construct(private readonly InventoryV2Service $stock) {}

    public function enableWarehouse(array $input, string $key, int $actor): array
    {
        return $this->command('warehouse.enable', $input, $key, $actor, function () use ($input): array {
            $w = DB::table('inv_warehouses')->where('id', $input['warehouse_id'] ?? 0)->where('is_active', true)->lockForUpdate()->first();
            abort_unless($w && $w->catalog_id && $w->legal_entity_ref && $w->v2_managed, 422, 'Kho chưa có catalog/pháp nhân hợp lệ.');
            if (! $w->production_managed) {
                abort_if(DB::table('inv_stock_balances')->where('warehouse_id', $w->id)->where(fn ($q) => $q->where('quantity_on_hand', '!=', 0)->orWhere('quantity_reserved', '!=', 0))->exists(), 409, 'Kho còn tồn; cần cutover có đối soát, không chuyển ownership ngầm.');
                DB::table('inv_warehouses')->where('id', $w->id)->update(['production_managed' => true, 'updated_at' => now()]);
            }

            return (array) DB::table('inv_warehouses')->find($w->id);
        });
    }

    public function createRoll(array $input, string $key, int $actor): array
    {
        return $this->command('roll.receive', $input, $key, $actor, function () use ($input, $key, $actor): array {
            $v = validator($input, ['warehouse_id' => 'required|integer', 'code' => 'required|string|max:100', 'quantity' => 'required', 'unit_cost' => 'required', 'lot_code' => 'nullable|string|max:100', 'secondary_unit' => 'nullable|string|max:24', 'secondary_quantity' => 'nullable', 'disposition' => 'sometimes|in:quarantine,saleable,damaged', 'source_type' => 'sometimes|string|max:80', 'source_id' => 'sometimes|string|max:191', 'metadata' => 'sometimes|array'])->validate();
            $w = $this->warehouse((int) $v['warehouse_id']);
            abort_if(isset($input['stock_owner_ref']) && $input['stock_owner_ref'] !== $w->legal_entity_ref, 422, 'Chỉ hỗ trợ nguyên liệu thuộc pháp nhân vận hành; CMT chưa mở.');
            $itemId = isset($input['item_id']) ? (int) $input['item_id'] : $this->stock->ensureItem((int) ($input['sku_id'] ?? 0));
            $item = DB::table('inv_items')->find($itemId);
            abort_unless($item, 422);
            $qty = $this->number($v['quantity'], true);
            $secondary = isset($v['secondary_quantity']) ? $this->number($v['secondary_quantity'], true) : null;
            abort_unless(($secondary === null) === empty($v['secondary_unit']), 422, 'Đơn vị phụ và số đo phụ phải có cùng nhau.');
            abort_if(DB::table('inv_trace_units')->where('code', $v['code'])->exists(), 409, 'Mã cuộn đã tồn tại.');
            $public = (string) Str::uuid();
            $receipt = $this->stock->receive(['warehouse_id' => $w->id, 'item_id' => $itemId, 'quantity' => (string) $qty, 'unit_cost' => (string) $this->number($v['unit_cost']), 'batch_code' => 'TRACE-'.$public, 'expires_at' => $input['expires_at'] ?? null, 'disposition' => $v['disposition'] ?? 'quarantine', 'source_id' => $v['source_id'] ?? $key, 'legal_entity_ref' => $w->legal_entity_ref], $this->child($key, 'receipt'), $actor);
            $id = DB::table('inv_trace_units')->insertGetId(['public_id' => $public, 'code' => $v['code'], 'warehouse_id' => $w->id, 'item_id' => $itemId, 'batch_id' => $receipt['batch_id'], 'lot_code' => $v['lot_code'] ?? null, 'stock_owner_ref' => $w->legal_entity_ref, 'primary_unit' => $item->unit, 'quantity_received' => (string) $qty, 'secondary_unit' => $v['secondary_unit'] ?? null, 'secondary_received' => $secondary === null ? null : (string) $secondary, 'secondary_on_hand' => $secondary === null ? null : (string) $secondary, 'disposition' => $v['disposition'] ?? 'quarantine', 'version' => 1, 'metadata' => json_encode($v['metadata'] ?? [], JSON_THROW_ON_ERROR), 'source_type' => $v['source_type'] ?? 'production.receipt', 'source_id' => $v['source_id'] ?? $key, 'created_at' => now(), 'updated_at' => now()]);
            $this->measurement($id, 'receipt', $qty, $secondary, $key, $actor);

            return $this->roll($public) + ['receipt' => $receipt];
        });
    }

    public function inspectRoll(array $input, string $key, int $actor): array
    {
        return $this->command('roll.inspect', $input, $key, $actor, function () use ($input, $key, $actor): array {
            $r = $this->lockedRoll((string) ($input['roll_id'] ?? ''));
            $this->version($r, $input);
            $w = $this->warehouse($r->warehouse_id);
            $status = $input['disposition'] ?? null;
            abort_unless(in_array($status, ['saleable', 'quarantine', 'damaged', 'rejected'], true), 422);
            abort_unless(! empty($input['reason']) && mb_strlen((string) $input['reason']) <= 1000, 422, 'Cần lý do/kết quả kiểm.');
            abort_if(DB::table('inv_stock_balances')->where('warehouse_id', $w->id)->where('batch_id', $r->batch_id)->where('quantity_reserved', '>', 0)->exists(), 409, 'Đang giữ cuộn; đối soát lệnh trước đổi trạng thái.');
            $location = $this->location($w->id, $status);
            $balances = DB::table('inv_stock_balances')->where('warehouse_id', $w->id)->where('batch_id', $r->batch_id)->lockForUpdate()->get();
            $target = $this->balance($w->id, $r->item_id, $r->batch_id, $location);
            foreach ($balances as $b) {
                if ($b->id === $target->id || $this->number($b->quantity_on_hand)->isZero()) {
                    continue;
                }
                $qty = $this->number($b->quantity_on_hand);
                $current = DB::table('inv_stock_balances')->where('id', $target->id)->value('quantity_on_hand');
                DB::table('inv_stock_balances')->where('id', $target->id)->update(['quantity_on_hand' => (string) $this->number($current)->plus($qty), 'updated_at' => now()]);
                DB::table('inv_stock_balances')->where('id', $b->id)->update(['quantity_on_hand' => '0', 'updated_at' => now()]);
                DB::table('inv_cost_layers')->where('warehouse_id', $w->id)->where('batch_id', $r->batch_id)->where('location_id', $b->location_id)->where('quantity_remaining', '>', 0)->update(['location_id' => $location, 'updated_at' => now()]);
            }
            DB::table('inv_trace_units')->where('id', $r->id)->update(['disposition' => $status, 'version' => $r->version + 1, 'updated_at' => now()]);
            $this->measurement($r->id, 'quality.'.$status, BigDecimal::zero(), null, $key.':'.$input['reason'], $actor);

            return $this->roll($r->public_id);
        });
    }

    public function reserve(array $input, string $key, int $actor): array
    {
        return $this->command('reserve', $input, $key, $actor, function () use ($input, $key, $actor): array {
            $w = $this->warehouse((int) ($input['warehouse_id'] ?? 0));
            $order = $this->order($input);
            $roll = ! empty($input['roll_id']) ? $this->lockedRoll($input['roll_id']) : null;
            abort_if($roll && ($roll->warehouse_id !== $w->id || $roll->item_id !== (int) ($input['item_id'] ?? 0)), 422);
            $result = $this->stock->reserve($w->id, (int) ($input['item_id'] ?? 0), (string) $this->number($input['quantity'] ?? '', true), $order, $this->child($key, 'hold'), $actor, $roll?->batch_id);
            DB::table('inv_reservations')->where('id', $result['id'])->update(['purpose' => 'production', 'order_ref' => $order, 'expires_at' => now()->addYears(10), 'version' => 1]);

            return (array) DB::table('inv_reservations')->find($result['id']);
        });
    }

    public function splitRoll(array $input, string $key, int $actor): array
    {
        return $this->command('roll.split', $input, $key, $actor, function () use ($input, $key, $actor): array {
            $r = $this->lockedRoll((string) ($input['roll_id'] ?? ''));
            $this->version($r, $input);
            $this->warehouse($r->warehouse_id);
            $quantity = $this->number($input['quantity'] ?? '', true);
            $before = $this->number($this->roll($r->public_id)['quantity_on_hand']);
            abort_unless($quantity->isLessThan($before), 422, 'Tách cuộn phải nhỏ hơn lượng cuộn gốc.');
            abort_unless(! empty($input['code']) && mb_strlen($input['code']) <= 100, 422);
            $secondary = $r->secondary_on_hand === null ? null : (isset($input['secondary_quantity']) ? $this->number($input['secondary_quantity'], true) : $this->number($r->secondary_on_hand)->multipliedBy($quantity)->dividedBy($before, 6, RoundingMode::HalfUp));
            abort_if($secondary !== null && $secondary->isGreaterThan((string) $r->secondary_on_hand), 422);
            $beforeLayers = $this->traceLayers($r);
            $withdraw = $this->stock->withdrawProductionRoll(['warehouse_id' => $r->warehouse_id, 'item_id' => $r->item_id, 'batch_id' => $r->batch_id, 'quantity' => (string) $quantity, 'source_id' => 'roll-split:'.$r->public_id], $this->child($key, 'withdraw'), $actor);
            $origins = $this->consumedOrigins($beforeLayers);
            app(InventoryValuationService::class)->reversePhysicalConsumptions(DB::table('inv_valuation_consumptions')->where('operation_id', $withdraw['operation_id'])->get(), 'roll-split:'.$key);
            $baseCost = BigDecimal::zero();
            foreach ($origins as $origin) {
                $baseCost = $baseCost->plus(BigDecimal::of($origin['quantity'])->multipliedBy($origin['unit_cost']));
            }
            $unitCost = $baseCost->dividedBy($quantity, 6, RoundingMode::HalfUp);
            $child = $this->createRoll(['warehouse_id' => $r->warehouse_id, 'item_id' => $r->item_id, 'quantity' => (string) $quantity, 'unit_cost' => (string) $unitCost, 'code' => $input['code'], 'lot_code' => $r->lot_code, 'secondary_quantity' => $secondary === null ? null : (string) $secondary, 'secondary_unit' => $r->secondary_unit, 'expires_at' => DB::table('inv_batches')->where('id', $r->batch_id)->value('expires_at'), 'disposition' => $r->disposition === 'rejected' ? 'quarantine' : $r->disposition, 'source_type' => 'trace.split', 'source_id' => $r->public_id, 'metadata' => ['parent_roll_id' => $r->public_id, 'stock_operation_id' => $withdraw['operation_id']]], $this->child($key, 'child'), $actor);
            $this->linkTargets($origins, [app(InventoryValuationService::class)->receiptLayer($child['receipt']['document_id'])], 'roll.split', $key);
            if ($r->disposition === 'rejected') {
                $child = $this->inspectRoll(['roll_id' => $child['public_id'], 'expected_version' => 1, 'disposition' => 'rejected', 'reason' => 'Preserve rejected parent roll disposition'], $this->child($key, 'quality'), $actor);
            }
            DB::table('inv_trace_units')->where('id', $r->id)->update(['secondary_on_hand' => $secondary === null ? null : (string) $this->number($r->secondary_on_hand)->minus($secondary), 'version' => $r->version + 1, 'updated_at' => now()]);
            $this->measurement($r->id, 'split', $quantity->negated(), $secondary?->negated(), $child['public_id'], $actor);

            return ['parent' => $this->roll($r->public_id), 'child' => $child, 'stock_operation_id' => $withdraw['operation_id'], 'total_cost' => $withdraw['total_cost']];
        });
    }

    /** Whole physical rolls travel with their identity; partial moves split first. */
    public function dispatchRoll(array $input, string $key, int $actor): array
    {
        return $this->command('roll.dispatch', $input, $key, $actor, function () use ($input, $key, $actor): array {
            $r = $this->lockedRoll((string) ($input['roll_id'] ?? ''));
            $this->version($r, $input);
            $this->warehouse($r->warehouse_id);
            $target = $this->warehouse((int) ($input['destination_warehouse_id'] ?? 0));
            abort_unless($r->disposition === 'saleable', 409, 'Cuộn phải qua QC trước khi bàn giao.');
            $payload = $this->roll($r->public_id);
            abort_if($this->number($payload['quantity_reserved'])->isPositive(), 409, 'Cuộn còn được giữ cho lệnh.');
            $quantity = $this->number($payload['quantity_on_hand'], true);
            $beforeLayers = $this->traceLayers($r);
            $consumptionStart = (int) (DB::table('inv_valuation_consumptions')->max('id') ?? 0);
            $transfer = $this->stock->dispatchTransfer(['source_warehouse_id' => $r->warehouse_id, 'destination_warehouse_id' => $target->id, 'lines' => [['item_id' => $r->item_id, 'batch_id' => $r->batch_id, 'quantity' => (string) $quantity]]], $this->child($key, 'transfer'), $actor);
            $metadata = json_decode($r->metadata, true) ?: [];
            $origins = $this->consumedOrigins($beforeLayers);
            $baseCost = BigDecimal::zero();
            foreach ($origins as $origin) {
                $baseCost = $baseCost->plus(BigDecimal::of($origin['quantity'])->multipliedBy($origin['unit_cost']));
            }
            DB::table('inv_transfer_lines')->where('transfer_id', $transfer['id'])->update(['base_unit_cost' => (string) $baseCost->dividedBy($quantity, 6, RoundingMode::HalfUp)]);
            app(InventoryValuationService::class)->reversePhysicalConsumptions(DB::table('inv_valuation_consumptions')->where('id', '>', $consumptionStart)->whereIn('cost_layer_id', $beforeLayers->pluck('id'))->get(), 'roll-transfer:'.$key);
            $metadata['transfer_origins'] = $origins;
            $metadata['active_transfer_id'] = $transfer['public_id'];
            DB::table('inv_trace_units')->where('id', $r->id)->update(['disposition' => 'in_transit', 'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'version' => $r->version + 1, 'updated_at' => now()]);
            $this->measurement($r->id, 'dispatch', $quantity, $r->secondary_on_hand === null ? null : $this->number($r->secondary_on_hand), $transfer['public_id'], $actor);

            return $transfer + ['roll_id' => $r->public_id];
        });
    }

    public function receiveRoll(array $input, string $key, int $actor): array
    {
        return $this->command('roll.receive_transfer', $input, $key, $actor, function () use ($input, $key, $actor): array {
            $r = $this->lockedRoll((string) ($input['roll_id'] ?? ''));
            $this->version($r, $input);
            $metadata = json_decode($r->metadata, true) ?: [];
            $transfer = DB::table('inv_transfers')->where('public_id', $metadata['active_transfer_id'] ?? '')->where('status', 'dispatched')->lockForUpdate()->first();
            abort_unless($r->disposition === 'in_transit' && $transfer, 409);
            $this->warehouse($transfer->destination_warehouse_id);
            $lines = DB::table('inv_transfer_lines')->where('transfer_id', $transfer->id)->get()->map(fn ($l) => ['line_id' => $l->id, 'quantity' => (string) $l->quantity, 'disposition' => 'quarantine'])->all();
            $lastLayer = (int) (DB::table('inv_cost_layers')->max('id') ?? 0);
            $received = $this->stock->receiveTransfer($transfer->public_id, ['expected_version' => $transfer->version, 'lines' => $lines], $this->child($key, 'receive'), $actor);
            $targets = DB::table('inv_cost_layers')->where('warehouse_id', $transfer->destination_warehouse_id)->where('batch_id', $r->batch_id)->where('id', '>', $lastLayer)->orderBy('id')->get()->all();
            $this->linkTargets($metadata['transfer_origins'] ?? [], $targets, 'roll.transfer', $transfer->public_id);
            unset($metadata['active_transfer_id'], $metadata['transfer_origins']);
            DB::table('inv_trace_units')->where('id', $r->id)->update(['warehouse_id' => $transfer->destination_warehouse_id, 'disposition' => 'quarantine', 'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'version' => $r->version + 1, 'updated_at' => now()]);
            $this->measurement($r->id, 'receive_transfer', $this->number(DB::table('inv_transfer_lines')->where('transfer_id', $transfer->id)->sum('quantity')), $r->secondary_on_hand === null ? null : $this->number($r->secondary_on_hand), $transfer->public_id, $actor);

            return $received + ['roll' => $this->roll($r->public_id)];
        });
    }

    public function issue(array $input, string $key, int $actor): array
    {
        return $this->command('issue', $input, $key, $actor, function () use ($input, $key, $actor): array {
            $r = $this->reservation($input);
            $this->warehouse($r->warehouse_id);
            $qty = $this->number($input['quantity'] ?? '', true);
            abort_if($qty->isGreaterThan((string) $r->quantity), 409, 'Cấp vượt lượng còn giữ.');
            $layers = DB::table('inv_cost_layers')->where('warehouse_id', $r->warehouse_id)->where('item_id', $r->item_id)->orderBy('id')->lockForUpdate()->get();
            $valuationBefore = [];
            foreach ($layers as $layer) {
                $valuationBefore[$layer->id] = app(InventoryValuationService::class)->remaining($layer->id);
            }
            $childId = $this->splitReservation($r, $qty, $actor);
            $posted = $this->stock->commitReservation($childId, $this->child($key, 'issue'), $actor);
            $id = DB::table('inv_production_issues')->insertGetId(['public_id' => (string) Str::uuid(), 'order_ref' => $r->order_ref, 'reservation_id' => $r->id, 'warehouse_id' => $r->warehouse_id, 'item_id' => $r->item_id, 'document_id' => $posted['document_id'], 'quantity' => (string) $qty, 'total_cost' => $posted['total_cost'], 'version' => 1, 'actor_id' => $actor, 'created_at' => now(), 'updated_at' => now()]);
            $consumed = [];
            foreach ($layers as $layer) {
                $after = DB::table('inv_cost_layers')->where('id', $layer->id)->value('quantity_remaining');
                $take = $this->number($layer->quantity_remaining)->minus($after);
                if ($take->isPositive()) {
                    $consumed[] = [$layer, $take];
                }
            }
            $secondary = [];
            foreach ($consumed as [$layer, $take]) {
                $batch = $layer->batch_id ?? 0;
                $secondary[$batch] = ($secondary[$batch] ?? BigDecimal::zero())->plus($take);
            }
            foreach ($secondary as $batch => $taken) {
                $trace = DB::table('inv_trace_units')->where('batch_id', $batch)->lockForUpdate()->first();
                if ($trace && $trace->secondary_on_hand !== null) {
                    $before = $this->number(DB::table('inv_stock_balances')->where('batch_id', $batch)->sum('quantity_on_hand'))->plus($taken);
                    $amount = $taken->isEqualTo($before) ? $this->number($trace->secondary_on_hand) : $this->number($trace->secondary_on_hand)->multipliedBy($taken)->dividedBy($before, 6, RoundingMode::HalfUp);
                    DB::table('inv_trace_units')->where('id', $trace->id)->update(['secondary_on_hand' => (string) $this->number($trace->secondary_on_hand)->minus($amount), 'version' => $trace->version + 1, 'updated_at' => now()]);
                    $this->measurement($trace->id, 'issue', $taken->negated(), $amount->negated(), $key, $actor);
                    $secondary[$batch] = ['quantity' => $taken, 'secondary' => $amount, 'remaining_primary' => $taken, 'remaining_secondary' => $amount];
                } else {
                    $secondary[$batch] = null;
                }
            }
            $unassignedCost = $this->number($posted['total_cost']);
            foreach ($consumed as $costIndex => [$layer, $take]) {
                $actualCost = $take->multipliedBy((string) $layer->unit_cost)->plus($valuationBefore[$layer->id]->minus(app(InventoryValuationService::class)->remaining($layer->id)));
                $actualCost = $costIndex === array_key_last($consumed) ? $unassignedCost : $actualCost->toScale(6, RoundingMode::HalfUp);
                $unassignedCost = $unassignedCost->minus($actualCost);
                $effectiveUnitCost = $actualCost->dividedBy($take, 6, RoundingMode::HalfUp);
                $batchKey = $layer->batch_id ?? 0;
                $s = $secondary[$batchKey];
                $secondaryPart = $s ? ($take->isEqualTo($s['remaining_primary']) ? $s['remaining_secondary'] : $s['secondary']->multipliedBy($take)->dividedBy($s['quantity'], 6, RoundingMode::HalfUp)) : null;
                if ($s) {
                    $secondary[$batchKey]['remaining_primary'] = $s['remaining_primary']->minus($take);
                    $secondary[$batchKey]['remaining_secondary'] = $s['remaining_secondary']->minus($secondaryPart);
                }
                $components = DB::table('inv_valuation_consumptions')->where('operation_id', $posted['operation_id'])->where('cost_layer_id', $layer->id)->get()->map(fn ($row) => ['adjustment_id' => $row->adjustment_id, 'cost_layer_id' => $row->cost_layer_id, 'amount' => (string) $row->amount, 'quantity' => (string) $row->quantity])->all();
                DB::table('inv_production_issue_layers')->insert(['issue_id' => $id, 'cost_layer_id' => $layer->id, 'batch_id' => $layer->batch_id, 'location_id' => $layer->location_id, 'quantity' => (string) $take, 'unit_cost' => (string) $effectiveUnitCost, 'total_cost' => (string) $actualCost, 'valuation_components' => json_encode($components, JSON_THROW_ON_ERROR), 'allocated_cost' => '0', 'returned_cost' => '0', 'secondary_quantity' => $secondaryPart === null ? null : (string) $secondaryPart]);
            }

            return $this->issuePayload($id);
        });
    }

    public function release(array $input, string $key, int $actor): array
    {
        return $this->command('release', $input, $key, $actor, function () use ($input): array {
            $r = $this->reservation($input);
            $this->warehouse($r->warehouse_id);
            $remaining = $this->number($input['quantity'] ?? $r->quantity, true);
            abort_if($remaining->isGreaterThan((string) $r->quantity), 409);
            foreach (DB::table('inv_reservation_allocations')->where('reservation_id', $r->id)->orderBy('balance_id')->lockForUpdate()->get() as $a) {
                $take = BigDecimal::min($remaining, (string) $a->quantity);
                $b = DB::table('inv_stock_balances')->where('id', $a->balance_id)->lockForUpdate()->first();
                DB::table('inv_reservation_allocations')->where('id', $a->id)->update(['quantity' => (string) $this->number($a->quantity)->minus($take)]);
                DB::table('inv_stock_balances')->where('id', $b->id)->update(['quantity_reserved' => (string) $this->number($b->quantity_reserved)->minus($take), 'updated_at' => now()]);
                $remaining = $remaining->minus($take);
                if ($remaining->isZero()) {
                    break;
                }
            }
            abort_unless($remaining->isZero(), 409);
            $after = $this->number($r->quantity)->minus($input['quantity'] ?? $r->quantity);
            DB::table('inv_reservations')->where('id', $r->id)->update(['quantity' => (string) $after, 'status' => $after->isZero() ? 'released' : 'active', 'version' => $r->version + 1, 'updated_at' => now()]);

            return (array) DB::table('inv_reservations')->find($r->id);
        });
    }

    public function returnMaterial(array $input, string $key, int $actor): array
    {
        return $this->command('material.return', $input, $key, $actor, function () use ($input, $key, $actor): array {
            $issue = DB::table('inv_production_issues')->where('public_id', $input['issue_id'] ?? '')->lockForUpdate()->first();
            abort_unless($issue, 404);
            $this->version($issue, $input);
            $this->warehouse($issue->warehouse_id);
            $qty = $this->number($input['quantity'] ?? '', true);
            $remaining = $qty;
            $cost = BigDecimal::zero();
            $receipts = [];
            foreach ($this->issueLayers($issue->id) as $layer) {
                $available = $this->number($layer->quantity)->minus((string) $layer->returned_quantity)->minus((string) $layer->allocated_quantity);
                $take = BigDecimal::min($remaining, $available);
                if (! $take->isPositive()) {
                    continue;
                }
                $trace = DB::table('inv_trace_units')->where('batch_id', $layer->batch_id)->first();
                $secondary = $layer->secondary_quantity === null ? null : $this->number($layer->secondary_quantity)->multipliedBy((string) $this->number($layer->returned_quantity)->plus($take))->dividedBy((string) $layer->quantity, 6, RoundingMode::HalfUp)->minus((string) $this->number($layer->secondary_quantity)->multipliedBy((string) $layer->returned_quantity)->dividedBy((string) $layer->quantity, 6, RoundingMode::HalfUp));
                $actualReturnCost = $this->layerCostShare($layer, $take);
                $origin = DB::table('inv_cost_layers')->find($layer->cost_layer_id);
                $remnant = $this->createRoll(['warehouse_id' => $issue->warehouse_id, 'item_id' => $issue->item_id, 'quantity' => (string) $take, 'unit_cost' => (string) $origin->unit_cost, 'code' => 'RET-'.substr(hash('sha256', $key.':'.$layer->id), 0, 24), 'lot_code' => $trace?->lot_code, 'expires_at' => DB::table('inv_batches')->where('id', $layer->batch_id)->value('expires_at'), 'secondary_quantity' => $secondary === null ? null : (string) $secondary, 'secondary_unit' => $trace?->secondary_unit, 'disposition' => 'quarantine', 'source_type' => 'production.material_return', 'source_id' => $issue->public_id, 'metadata' => ['parent_roll_id' => $trace?->public_id, 'source_cost_layer_id' => $layer->cost_layer_id, 'source_issue_layer_id' => $layer->id]], $this->child($key, 'return-'.$layer->id), $actor);
                $targetLayer = app(InventoryValuationService::class)->receiptLayer($remnant['receipt']['document_id']);
                $transferredValue = app(InventoryValuationService::class)->link((int) $layer->cost_layer_id, (int) $targetLayer->id, $take, 'material.return', $key);
                app(InventoryValuationService::class)->reverseComponents(json_decode($layer->valuation_components ?? '[]', true), $this->number($layer->returned_quantity), $take, $this->number($layer->quantity), 'material-return:'.$key);
                $receipts[] = $remnant['receipt'] + ['roll_id' => $remnant['public_id'], 'source_cost_layer_id' => $layer->cost_layer_id];
                DB::table('inv_production_issue_layers')->where('id', $layer->id)->update(['returned_quantity' => (string) $this->number($layer->returned_quantity)->plus($take), 'returned_cost' => $this->money($this->number($layer->returned_cost)->plus($actualReturnCost))]);
                $cost = $cost->plus((string) $take->multipliedBy($origin->unit_cost))->plus($transferredValue);
                $remaining = $remaining->minus($take);
                if ($remaining->isZero()) {
                    break;
                }
            }
            abort_unless($remaining->isZero(), 409, 'Lượng trả vượt phần chưa phân bổ giá thành.');
            DB::table('inv_production_issues')->where('id', $issue->id)->update(['returned_quantity' => (string) $this->number($issue->returned_quantity)->plus($qty), 'version' => $issue->version + 1, 'updated_at' => now()]);
            $id = DB::table('inv_production_returns')->insertGetId(['public_id' => (string) Str::uuid(), 'issue_id' => $issue->id, 'quantity' => (string) $qty, 'total_cost' => $this->money($cost), 'receipts' => json_encode($receipts, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);

            return (array) DB::table('inv_production_returns')->find($id);
        });
    }

    public function postOutput(array $input, string $key, int $actor): array
    {
        return $this->command('output.post', $input, $key, $actor, function () use ($input, $key, $actor): array {
            $w = $this->warehouse((int) ($input['warehouse_id'] ?? 0));
            $order = $this->order($input);
            $materials = $input['materials'] ?? array_map(fn ($id) => ['issue_id' => $id], $input['issue_ids'] ?? []);
            abort_unless(is_array($materials) && count($materials) > 0 && count($materials) <= 300, 422);
            $materialCost = BigDecimal::zero();
            $slices = [];
            $seen = [];
            usort($materials, fn ($a, $b) => strcmp($a['issue_id'] ?? '', $b['issue_id'] ?? ''));
            foreach ($materials as $m) {
                abort_if(isset($seen[$m['issue_id'] ?? '']), 422, 'Issue trùng trong lần nhập thành phẩm.');
                $seen[$m['issue_id'] ?? ''] = true;
                $issue = DB::table('inv_production_issues')->where('public_id', $m['issue_id'] ?? '')->where('order_ref', $order)->lockForUpdate()->first();
                abort_unless($issue, 404, 'Issue không thuộc lệnh.');
                $source = $this->warehouse($issue->warehouse_id);
                abort_unless($source->legal_entity_ref === $w->legal_entity_ref && $source->catalog_id === $w->catalog_id, 422, 'Output khác pháp nhân/catalog của đầu vào.');
                $available = $this->number($issue->quantity)->minus((string) $issue->returned_quantity)->minus((string) $issue->allocated_quantity);
                $qty = isset($m['quantity']) ? $this->number($m['quantity'], true) : $available;
                abort_unless($qty->isPositive() && $qty->isLessThanOrEqualTo($available), 409, 'Issue đã phân bổ hoặc không đủ lượng.');
                $remaining = $qty;
                foreach ($this->issueLayers($issue->id) as $layer) {
                    $free = $this->number($layer->quantity)->minus((string) $layer->returned_quantity)->minus((string) $layer->allocated_quantity);
                    $take = BigDecimal::min($remaining, $free);
                    if (! $take->isPositive()) {
                        continue;
                    }
                    $amount = $this->layerCostShare($layer, $take);
                    DB::table('inv_production_issue_layers')->where('id', $layer->id)->update(['allocated_quantity' => (string) $this->number($layer->allocated_quantity)->plus($take), 'allocated_cost' => $this->money($this->number($layer->allocated_cost)->plus($amount))]);
                    $materialCost = $materialCost->plus($amount);
                    $slices[] = ['issue_id' => $issue->public_id, 'issue_layer_id' => $layer->id, 'cost_layer_id' => $layer->cost_layer_id, 'quantity' => (string) $take, 'unit_cost' => $layer->unit_cost, 'cost' => $this->money($amount)];
                    $remaining = $remaining->minus($take);
                    if ($remaining->isZero()) {
                        break;
                    }
                }
                abort_unless($remaining->isZero(), 409);
                DB::table('inv_production_issues')->where('id', $issue->id)->update(['allocated_quantity' => (string) $this->number($issue->allocated_quantity)->plus($qty), 'version' => $issue->version + 1, 'updated_at' => now()]);
            }
            $conversion = $this->number($input['conversion_cost'] ?? '0');
            $scrap = $this->number($input['scrap_cost'] ?? '0');
            $total = $materialCost->plus($conversion)->toScale(6, RoundingMode::HalfUp);
            abort_if($scrap->isGreaterThan($total), 422, 'Giá trị phế vượt tổng chi phí.');
            $rows = $input['outputs'] ?? [];
            abort_unless(is_array($rows) && count($rows) > 0 && count($rows) <= 300, 422);
            $weights = [];
            $weightTotal = BigDecimal::zero();
            foreach ($rows as $i => $row) {
                abort_if(count($rows) > 1 && ! isset($row['cost_weight']), 422, 'Nhiều output cần tỷ trọng cost tường minh, không cộng số lượng khác đơn vị.');
                $weights[$i] = $this->number($row['cost_weight'] ?? '1', true);
                $weightTotal = $weightTotal->plus($weights[$i]);
            }
            $left = $total->minus($scrap);
            $outputs = [];
            $rounding = BigDecimal::zero();
            foreach ($rows as $i => $row) {
                $share = $i === array_key_last($rows) ? $left : $total->minus($scrap)->multipliedBy($weights[$i])->dividedBy($weightTotal, 6, RoundingMode::Down);
                $left = $left->minus($share);
                $qty = $this->number($row['quantity'] ?? '', true);
                $unitCost = $share->dividedBy($qty, 6, RoundingMode::HalfUp);
                $receipt = $this->createRoll(array_replace($row, ['warehouse_id' => $w->id, 'code' => $row['code'] ?? 'OUT-'.substr(hash('sha256', $key.':'.$i), 0, 24), 'unit_cost' => (string) $unitCost, 'disposition' => 'quarantine', 'source_type' => 'production.output', 'source_id' => $order]), $this->child($key, 'output-'.$i), $actor);
                $variance = $share->minus((string) $qty->multipliedBy($unitCost));
                $rounding = $rounding->plus($variance);
                $outputs[] = ['roll_id' => $receipt['public_id'], 'item_id' => $receipt['item_id'], 'quantity' => (string) $qty, 'allocated_cost' => (string) $share, 'unit_cost' => (string) $unitCost, 'rounding_variance' => $this->money($variance), 'receipt' => $receipt['receipt']];
            }
            $id = DB::table('inv_production_postings')->insertGetId(['public_id' => (string) Str::uuid(), 'order_ref' => $order, 'warehouse_id' => $w->id, 'material_cost' => $this->money($materialCost), 'conversion_cost' => (string) $conversion, 'scrap_cost' => (string) $scrap, 'rounding_variance' => $this->money($rounding), 'materials' => json_encode($slices, JSON_THROW_ON_ERROR), 'outputs' => json_encode($outputs, JSON_THROW_ON_ERROR), 'actor_id' => $actor, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($outputs as $i => $output) {
                $variance = BigDecimal::of($output['rounding_variance']);
                if (! $variance->isZero()) {
                    $valuation = app(InventoryValuationService::class);
                    $event = $valuation->createEvent($id, 'output.rounding', $this->child($key, 'rounding-'.$i), $variance, 'Exact output cost allocation residual', $actor, hash('sha256', $key.':'.$i));
                    $valuation->allocate($event->id, $valuation->receiptLayer($output['receipt']['document_id'])->id, $variance);
                }
            }

            return array_replace((array) DB::table('inv_production_postings')->find($id), ['materials' => $slices, 'outputs' => $outputs, 'total_cost' => (string) $total]);
        });
    }

    public function adjustOutputCost(array $input, string $key, int $actor): array
    {
        return $this->command('output.adjust_cost', $input, $key, $actor, function () use ($input, $actor): array {
            $v = validator($input, ['posting_id' => 'required|uuid', 'amount' => 'required', 'source_id' => 'required|string|max:191', 'reason' => 'required|string|max:1000', 'output_weights' => 'sometimes|array|max:300'])->validate();
            $posting = DB::table('inv_production_postings')->where('public_id', $v['posting_id'])->lockForUpdate()->first();
            abort_unless($posting, 404);
            $this->warehouse($posting->warehouse_id);
            abort_unless(preg_match('/^-?(?:0|[1-9]\d{0,17})(?:\.\d{1,6})?$/D', (string) $v['amount']), 422, 'Điều chỉnh phải là decimal 24,6.');
            $amount = BigDecimal::of((string) $v['amount'])->toScale(6);
            $weightsInput = $v['output_weights'] ?? [];
            ksort($weightsInput);
            $fingerprint = hash('sha256', json_encode([$posting->public_id, (string) $amount, $v['reason'], $weightsInput], JSON_THROW_ON_ERROR));
            $valuation = app(InventoryValuationService::class);
            $old = DB::table('inv_valuation_events')->where('posting_id', $posting->id)->where('source_type', 'late_conversion')->where('source_id', $v['source_id'])->first();
            if ($old) {
                abort_unless(hash_equals($old->fingerprint, $fingerprint), 409, 'Nguồn chi phí đã có điều chỉnh khác.');

                return array_replace($valuation->event($old->id), ['posting_id' => $posting->public_id, 'posting_database_id' => $posting->id, 'posting_version' => $posting->version, 'source_replayed' => true]);
            }
            if (isset($input['expected_version'])) {
                $this->version($posting, $input);
            }
            $outputs = json_decode($posting->outputs, true, flags: JSON_THROW_ON_ERROR);
            if ($weightsInput !== []) {
                $expected = array_column($outputs, 'roll_id');
                sort($expected);
                abort_unless(array_keys($weightsInput) === $expected, 422, 'Tỷ trọng phải phủ đúng từng output, không thừa/thiếu identity.');
            }
            $totalWeight = BigDecimal::zero();
            $weights = [];
            $totalOriginal = BigDecimal::zero();
            foreach ($outputs as $i => $output) {
                $weight = $weightsInput[$output['roll_id']] ?? $output['allocated_cost'];
                if (count($outputs) === 1 && BigDecimal::of((string) $weight)->isZero()) {
                    $weight = '1';
                }
                $weights[$i] = $this->number($weight, true);
                $totalWeight = $totalWeight->plus($weights[$i]);
                $totalOriginal = $totalOriginal->plus($output['allocated_cost']);
            }
            $previous = BigDecimal::zero();
            foreach (DB::table('inv_valuation_events')->where('posting_id', $posting->id)->where('source_type', 'late_conversion')->pluck('amount') as $delta) {
                $previous = $previous->plus((string) $delta);
            }
            abort_if($totalOriginal->plus($previous)->plus($amount)->isNegative(), 422, 'Điều chỉnh vượt giá trị output.');
            // An in-transit physical roll has no receiving cost layer yet. Complete
            // its custody handover before changing valuation, rather than guessing COGS.
            $layerIds = [];
            foreach ($outputs as $output) {
                $layerIds[] = $valuation->receiptLayer($output['receipt']['document_id'])->id;
            }
            $seen = [];
            while ($layerIds !== []) {
                $layerId = array_pop($layerIds);
                if (isset($seen[$layerId])) {
                    continue;
                }
                $seen[$layerId] = true;
                abort_if(count($seen) > 10000, 409, 'Genealogy cần xử lý theo lô.');
                $layer = DB::table('inv_cost_layers')->find($layerId);
                abort_if(DB::table('inv_trace_units')->where('batch_id', $layer->batch_id)->where('disposition', 'in_transit')->exists(), 409, 'Nhận xong cuộn đang đi trước khi điều chỉnh giá trị.');
                foreach (DB::table('inv_cost_layer_links')->where('origin_layer_id', $layerId)->pluck('target_layer_id') as $target) {
                    $layerIds[] = (int) $target;
                }
            }
            $event = $valuation->createEvent($posting->id, 'late_conversion', $v['source_id'], $amount, $v['reason'], $actor, $fingerprint);
            $remaining = $amount;
            foreach ($outputs as $i => $output) {
                $share = $i === array_key_last($outputs) ? $remaining : $amount->multipliedBy($weights[$i])->dividedBy($totalWeight, 6, RoundingMode::HalfUp);
                $remaining = $remaining->minus($share);
                $valuation->allocate($event->id, $valuation->receiptLayer($output['receipt']['document_id'])->id, $share);
            }
            DB::table('inv_production_postings')->where('id', $posting->id)->update(['version' => $posting->version + 1, 'updated_at' => now()]);

            return array_replace($valuation->event($event->id), ['posting_id' => $posting->public_id, 'posting_database_id' => $posting->id, 'posting_version' => $posting->version + 1]);
        });
    }

    public function stocktake(array $input, string $key, int $actor): array
    {
        return $this->command('stocktake', $input, $key, $actor, function () use ($input, $key, $actor): array {
            $w = $this->warehouse((int) ($input['warehouse_id'] ?? 0), allowCounting: true);
            if (empty($input['stocktake_id'])) {
                return $this->stock->beginStocktake($w->id, $this->child($key, 'begin'), $actor, true);
            }
            $take = DB::table('inv_stocktakes')->where('public_id', $input['stocktake_id'])->where('warehouse_id', $w->id)->first();
            abort_unless($take, 404);
            $result = $this->stock->postStocktake($take->public_id, $input['counts'] ?? [], $this->child($key, 'post'), $actor);
            $totals = [];
            foreach ($input['counts'] ?? [] as $count) {
                $balance = DB::table('inv_stock_balances')->find($count['balance_id']);
                $trace = $balance ? DB::table('inv_trace_units')->where('batch_id', $balance->batch_id)->lockForUpdate()->first() : null;
                if ($trace && $trace->secondary_on_hand !== null) {
                    $primary = $this->number($count['quantity']);
                    abort_unless(isset($count['secondary_quantity']) || $primary->isZero(), 422, 'Kiểm kê cuộn hai đơn vị cần số đo phụ tường minh.');
                    $secondary = $this->number($count['secondary_quantity'] ?? '0');
                    abort_if($primary->isZero() && ! $secondary->isZero(), 422, 'Không có lượng chính thì lượng phụ phải bằng 0.');
                    $totals[$trace->id] ??= ['trace' => $trace, 'primary' => BigDecimal::zero(), 'secondary' => BigDecimal::zero()];
                    $totals[$trace->id]['primary'] = $totals[$trace->id]['primary']->plus($primary);
                    $totals[$trace->id]['secondary'] = $totals[$trace->id]['secondary']->plus($secondary);
                }
            }
            foreach ($totals as $id => $entry) {
                DB::table('inv_trace_units')->where('id', $id)->update(['secondary_on_hand' => (string) $entry['secondary'], 'version' => $entry['trace']->version + 1, 'updated_at' => now()]);
                $this->measurement($id, 'stocktake', $entry['primary'], $entry['secondary'], $take->public_id, $actor);
            }

            return $result;
        });
    }

    /** Withdraw a purchased physical roll, including rejected stock, with original valuation. */
    public function returnReceipt(array $input, string $key, int $actor): array
    {
        return $this->command('receipt.return', $input, $key, $actor, function () use ($input, $key, $actor): array {
            $roll = $this->lockedRoll((string) ($input['roll_id'] ?? ''));
            $this->version($roll, $input);
            $this->warehouse($roll->warehouse_id);
            abort_unless($roll->source_type === 'purchasing.receipt', 422, 'Cuộn không có receipt mua hàng.');
            $quantity = $this->number($input['quantity'] ?? '', true);
            $before = $this->number($this->roll($roll->public_id)['quantity_on_hand']);
            abort_if($quantity->isGreaterThan($before), 409, 'Trả vượt tồn thực tế còn lại của cuộn.');
            $stock = $this->stock->withdrawProductionRoll(['warehouse_id' => $roll->warehouse_id, 'item_id' => $roll->item_id, 'batch_id' => $roll->batch_id, 'quantity' => (string) $quantity, 'source_id' => 'supplier-return:'.$key], $this->child($key, 'withdraw'), $actor);
            $secondary = $roll->secondary_on_hand === null ? null : ($quantity->isEqualTo($before) ? $this->number($roll->secondary_on_hand) : $this->number($roll->secondary_on_hand)->multipliedBy($quantity)->dividedBy($before, 6, RoundingMode::HalfUp));
            DB::table('inv_trace_units')->where('id', $roll->id)->update(['secondary_on_hand' => $secondary === null ? null : (string) $this->number($roll->secondary_on_hand)->minus($secondary), 'version' => $roll->version + 1, 'updated_at' => now()]);
            $this->measurement($roll->id, 'supplier.return', $quantity->negated(), $secondary?->negated(), $key, $actor);

            return $stock + ['roll_id' => $roll->public_id, 'quantity' => (string) $quantity, 'secondary_quantity' => $secondary === null ? null : (string) $secondary];
        });
    }

    public function roll(string $public): array
    {
        $r = DB::table('inv_trace_units')->where('public_id', $public)->first();
        abort_unless($r, 404);

        return $this->decimalResource((array) $r + ['quantity_on_hand' => $this->money($this->number(DB::table('inv_stock_balances')->where('batch_id', $r->batch_id)->sum('quantity_on_hand'))), 'quantity_reserved' => $this->money($this->number(DB::table('inv_stock_balances')->where('batch_id', $r->batch_id)->sum('quantity_reserved')))]);
    }

    private function command(string $operation, array $input, string $key, int $actor, callable $effect): array
    {
        abort_unless(preg_match('/^[A-Za-z0-9:_.-]{1,191}$/D', $key), 422, 'Command key không hợp lệ.');

        return DB::transaction(function () use ($operation, $input, $key, $actor, $effect): array {
            app(InventoryRuntimeState::class)->guard();
            abort_unless(app(ModuleCapabilityChecker::class)->has('inventory', 'inventory.production.post.v1'), 503, 'Inventory cần nâng cấp production contract.');
            $live = Admin::query()->whereKey($actor)->lockForUpdate()->first();
            abort_unless($live && $live->isAvailable() && ! $live->must_change_password, 403);
            $canonical = function ($value) use (&$canonical) {
                if (! is_array($value)) {
                    return $value;
                }
                if (! array_is_list($value)) {
                    ksort($value);
                }

                return array_map($canonical, $value);
            };
            $fingerprint = hash('sha256', json_encode([$operation, $actor, $canonical($input)], JSON_THROW_ON_ERROR));
            // Warehouse locks serialize first-insert and physical effects; a unique command
            // key additionally prevents two different scopes from sharing one identity.
            if (isset($input['warehouse_id'])) {
                DB::table('inv_warehouses')->where('id', $input['warehouse_id'])->lockForUpdate()->first();
            }
            $old = DB::table('inv_production_commands')->where('command_key', $key)->lockForUpdate()->first();
            if ($old) {
                abort_unless(hash_equals($old->fingerprint, $fingerprint), 409, 'Command key thuộc ý định khác.');

                return json_decode($old->response, true, flags: JSON_THROW_ON_ERROR) + ['replayed' => true];
            }
            $result = $this->decimalResource($this->stock->productionWrite($effect));
            DB::table('inv_production_commands')->insert(['command_key' => $key, 'operation' => $operation, 'actor_id' => $actor, 'fingerprint' => $fingerprint, 'response' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);

            return $result;
        }, 3);
    }

    private function warehouse(int $id, bool $allowCounting = false): object
    {
        $w = DB::table('inv_warehouses')->where('id', $id)->where('is_active', true)->where('production_managed', true)->lockForUpdate()->first();
        abort_unless($w && $w->legal_entity_ref && $w->catalog_id, 422, 'Kho chưa bật production contract.');
        abort_if(! $allowCounting && DB::table('inv_stocktakes')->where('warehouse_id', $id)->where('status', 'counting')->exists(), 409, 'Kho đang kiểm kê.');

        return $w;
    }

    private function lockedRoll(string $id): object
    {
        $r = DB::table('inv_trace_units')->where('public_id', $id)->lockForUpdate()->first();
        abort_unless($r, 404);

        return $r;
    }

    private function reservation(array $input): object
    {
        $r = DB::table('inv_reservations')->where('public_id', $input['reservation_id'] ?? '')->where('purpose', 'production')->lockForUpdate()->first();
        abort_unless($r && $r->status === 'active', 409, 'Giữ vật tư không còn hiệu lực.');
        $this->version($r, $input);

        return $r;
    }

    private function version(object $row, array $input): void
    {
        abort_unless(isset($input['expected_version']) && (int) $input['expected_version'] === (int) $row->version, 409, 'Dữ liệu đã đổi; tải lại phiên bản.');
    }

    private function order(array $input): string
    {
        return validator($input, ['order_ref' => 'required|string|max:191'])->validate()['order_ref'];
    }

    private function issueLayers(int $id): mixed
    {
        return DB::table('inv_production_issue_layers')->where('issue_id', $id)->orderBy('id')->lockForUpdate()->get();
    }

    private function issuePayload(int $id): array
    {
        return (array) DB::table('inv_production_issues')->find($id) + ['layers' => $this->issueLayers($id)->map(fn ($r) => (array) $r)->all()];
    }

    private function splitReservation(object $parent, BigDecimal $qty, int $actor): string
    {
        $public = (string) Str::uuid();
        $id = DB::table('inv_reservations')->insertGetId(['public_id' => $public, 'warehouse_id' => $parent->warehouse_id, 'item_id' => $parent->item_id, 'quantity' => (string) $qty, 'source_id' => $parent->order_ref, 'order_ref' => $parent->order_ref, 'purpose' => 'production', 'actor_id' => $actor, 'status' => 'active', 'protected_payment' => false, 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
        $remaining = $qty;
        foreach (DB::table('inv_reservation_allocations')->where('reservation_id', $parent->id)->orderBy('balance_id')->lockForUpdate()->get() as $a) {
            $take = BigDecimal::min($remaining, (string) $a->quantity);
            if (! $take->isPositive()) {
                continue;
            }
            DB::table('inv_reservation_allocations')->where('id', $a->id)->update(['quantity' => (string) $this->number($a->quantity)->minus($take)]);
            DB::table('inv_reservation_allocations')->insert(['reservation_id' => $id, 'balance_id' => $a->balance_id, 'quantity' => (string) $take]);
            $remaining = $remaining->minus($take);
            if ($remaining->isZero()) {
                break;
            }
        }
        abort_unless($remaining->isZero(), 409, 'Allocation giữ hàng không đủ.');
        $after = $this->number($parent->quantity)->minus($qty);
        DB::table('inv_reservations')->where('id', $parent->id)->update(['quantity' => (string) $after, 'version' => $parent->version + 1, 'status' => $after->isZero() ? 'committed' : 'active', 'updated_at' => now()]);

        return $public;
    }

    private function location(int $warehouse, string $status): ?int
    {
        if ($status === 'saleable') {
            return null;
        }
        $type = $status === 'rejected' ? 'quarantine' : $status;
        $code = '__PROD_'.strtoupper($status);
        DB::table('inv_locations')->insertOrIgnore(['warehouse_id' => $warehouse, 'code' => $code, 'name' => $status, 'type' => $type, 'is_default' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return (int) DB::table('inv_locations')->where('warehouse_id', $warehouse)->where('code', $code)->value('id');
    }

    private function balance(int $warehouse, int $item, int $batch, ?int $location): object
    {
        DB::table('inv_stock_balances')->insertOrIgnore(['warehouse_id' => $warehouse, 'item_id' => $item, 'batch_id' => $batch, 'batch_key' => $batch, 'location_id' => $location, 'location_key' => $location ?? 0, 'quantity_on_hand' => '0', 'quantity_reserved' => '0', 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('inv_stock_balances')->where('warehouse_id', $warehouse)->where('item_id', $item)->where('batch_key', $batch)->where('location_key', $location ?? 0)->lockForUpdate()->first();
    }

    private function measurement(int $roll, string $kind, BigDecimal $primary, ?BigDecimal $secondary, string $source, int $actor): void
    {
        DB::table('inv_trace_measurements')->insert(['trace_unit_id' => $roll, 'kind' => $kind, 'primary_quantity' => $this->money($primary), 'secondary_quantity' => $secondary === null ? null : $this->money($secondary), 'source_id' => mb_substr($source, 0, 191), 'actor_id' => $actor, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function number(mixed $value, bool $positive = false): BigDecimal
    {
        if (is_float($value)) {
            $value = number_format($value, 6, '.', '');
        }
        abort_unless(is_string($value) || is_int($value), 422, 'Decimal không hợp lệ.');
        abort_unless(preg_match('/^(?:0|[1-9]\d{0,17})(?:\.\d{1,6})?$/D', (string) $value), 422, 'Decimal vượt precision 24,6.');
        $n = BigDecimal::of((string) $value)->toScale(6);
        abort_if($positive && ! $n->isPositive(), 422, 'Số lượng phải dương.');

        return $n;
    }

    private function decimalResource(array $data): array
    {
        $fields = ['quantity', 'quantity_received', 'quantity_on_hand', 'quantity_reserved', 'secondary_quantity', 'secondary_received', 'secondary_on_hand', 'unit_cost', 'total_cost', 'material_cost', 'conversion_cost', 'returned_quantity', 'allocated_quantity', 'returned_cost', 'allocated_cost', 'amount', 'amount_total', 'remaining_value', 'realized_value', 'included_in_issues_value', 'realized_variance_value', 'quantity_basis', 'scrap_cost', 'rounding_variance'];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->decimalResource($value);
            } elseif ($value !== null && in_array($key, $fields, true)) {
                $data[$key] = (string) BigDecimal::of((string) $value)->toScale(6, RoundingMode::HalfUp);
            }
        }

        return $data;
    }

    private function traceLayers(object $trace): mixed
    {
        return DB::table('inv_cost_layers')->where('warehouse_id', $trace->warehouse_id)->where('batch_id', $trace->batch_id)->orderBy('id')->lockForUpdate()->get();
    }

    private function consumedOrigins(mixed $layers): array
    {
        $result = [];
        foreach ($layers as $layer) {
            $quantity = $this->number($layer->quantity_remaining)->minus(DB::table('inv_cost_layers')->where('id', $layer->id)->value('quantity_remaining'));
            if ($quantity->isPositive()) {
                $result[] = ['layer_id' => $layer->id, 'quantity' => (string) $quantity, 'unit_cost' => (string) $layer->unit_cost];
            }
        }

        return $result;
    }

    private function linkTargets(array $origins, array $targets, string $type, string $source): void
    {
        $index = 0;
        foreach ($targets as $target) {
            $needed = $this->number($target->quantity_received);
            while ($needed->isPositive()) {
                abort_unless(isset($origins[$index]), 409, 'Receipt thiếu provenance của layer nguồn.');
                $quantity = $this->number($origins[$index]['quantity']);
                $take = BigDecimal::min($needed, $quantity);
                app(InventoryValuationService::class)->link((int) $origins[$index]['layer_id'], (int) $target->id, $take, $type, $source);
                $needed = $needed->minus($take);
                $origins[$index]['quantity'] = (string) $quantity->minus($take);
                if ($quantity->isEqualTo($take)) {
                    $index++;
                }
            }
        }
    }

    private function layerCostShare(object $layer, BigDecimal $quantity): BigDecimal
    {
        $remainingQuantity = $this->number($layer->quantity)->minus((string) $layer->returned_quantity)->minus((string) $layer->allocated_quantity);
        $remainingCost = $this->number($layer->total_cost)->minus((string) $layer->returned_cost)->minus((string) $layer->allocated_cost);

        return $quantity->isEqualTo($remainingQuantity) ? $remainingCost : $remainingCost->multipliedBy($quantity)->dividedBy($remainingQuantity, 6, RoundingMode::HalfUp);
    }

    private function money(BigDecimal $value): string
    {
        return (string) $value->toScale(6, RoundingMode::HalfUp);
    }

    private function child(string $key, string $suffix): string
    {
        return 'production:'.hash('sha256', $key.':'.$suffix);
    }
}
