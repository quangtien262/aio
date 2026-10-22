<?php

namespace Modules\Inventory\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Inventory owns stock; callers authorize the delegated branch/warehouse action. */
final class InventoryV2Service
{
    private int $productionWriteDepth = 0;

    /** Internal adapter boundary. Callers authorize the native production action first. */
    public function productionWrite(callable $callback): mixed
    {
        $this->productionWriteDepth++;
        try {
            return $callback();
        } finally {
            $this->productionWriteDepth--;
        }
    }

    public function ensureItem(int $skuId): int
    {
        return DB::transaction(function () use ($skuId): int {
            $this->runtime(lock: true);
            $sku = DB::table('merch_skus')->where('id', $skuId)->where('is_active', true)->first();
            abort_unless($sku, 422, 'Hàng hóa chưa sẵn sàng.');
            DB::table('inv_items')->insertOrIgnore([
                'merchandise_sku_id' => $skuId, 'sku' => $sku->sku, 'name' => $sku->name,
                'unit' => $sku->base_unit, 'costing_method' => 'fifo', 'track_batch' => ($sku->tracking_mode ?? '') === 'batch' || ($sku->requires_expiry ?? false),
                'track_serial' => false, 'sale_price' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return (int) DB::table('inv_items')->where('merchandise_sku_id', $skuId)->value('id');
        }, 3);
    }

    public function createWarehouse(array $input): array
    {
        return DB::transaction(function () use ($input): array {
            $this->runtime(lock: true);
            validator($input, ['code' => 'required|string|max:50', 'name' => 'required|string|max:255', 'catalog_id' => 'required|integer|exists:merch_catalogs,id', 'legal_entity_ref' => 'required|string|max:100', 'branch_id' => 'nullable|integer'])->validate();
            // Keep the soft catalog reference valid if fixture cleanup is running.
            abort_unless(DB::table('merch_catalogs')->where('id', $input['catalog_id'])->sharedLock()->first(), 422, 'Danh mục hàng hóa không còn tồn tại.');
            $timezone = $input['timezone'] ?? null;
            if (! $timezone && ! empty($input['branch_id']) && Schema::hasTable('retail_branches')) {
                $timezone = DB::table('retail_branches')->where('id', $input['branch_id'])->value('timezone');
            }
            $timezone = $timezone ?: 'UTC';
            validator(['timezone' => $timezone], ['timezone' => 'required|timezone'])->validate();
            $id = DB::table('inv_warehouses')->insertGetId([
                'public_id' => (string) Str::uuid(), 'code' => $input['code'], 'name' => $input['name'], 'catalog_id' => $input['catalog_id'],
                'legal_entity_ref' => $input['legal_entity_ref'], 'branch_id' => $input['branch_id'] ?? null,
                'timezone' => $timezone, 'v2_managed' => true, 'is_active' => true, 'is_default' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return (array) DB::table('inv_warehouses')->find($id);
        }, 3);
    }

    public function receive(array $input, string $key, int $actor): array
    {
        return $this->command('receive', $key, $actor, $input, function (string $operation) use ($input, $actor): array {
            $warehouse = $this->warehouse((int) $input['warehouse_id']);
            $item = $this->item((int) $input['item_id'], $warehouse);
            $this->notCounting($warehouse->id);
            if (isset($input['legal_entity_ref']) || isset($input['owner_ref'])) {
                abort_unless(($input['legal_entity_ref'] ?? $input['owner_ref']) === $warehouse->legal_entity_ref, 403, 'Sai pháp nhân của kho.');
            }
            $quantity = $this->positive($input['quantity']);
            $unitCost = $this->number($input['unit_cost'] ?? '0');
            abort_if($unitCost->isNegative(), 422, 'Giá vốn không được âm.');
            $batch = $this->batch($input, $item);
            $disposition = $input['disposition'] ?? 'saleable';
            abort_unless(in_array($disposition, ['saleable', 'quarantine', 'damaged'], true), 422, 'Tình trạng nhận hàng không hợp lệ.');
            $location = null;
            if ($disposition !== 'saleable') {
                DB::table('inv_locations')->insertOrIgnore(['warehouse_id' => $warehouse->id, 'code' => '__'.strtoupper($disposition), 'name' => $disposition === 'quarantine' ? 'Hàng cách ly' : 'Hàng hỏng', 'type' => $disposition, 'is_default' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
                $location = DB::table('inv_locations')->where('warehouse_id', $warehouse->id)->where('code', '__'.strtoupper($disposition))->value('id');
            }
            if ($batch && $disposition === 'saleable') {
                abort_if(DB::table('inv_batches')->where('id', $batch)->whereDate('expires_at', '<', now($warehouse->timezone)->toDateString())->exists(), 422, 'Hàng hết hạn phải nhận vào khu vực cách ly.');
            }
            $balance = $this->balance($warehouse->id, $item->id, $batch, $location);
            $after = $this->number($balance->quantity_on_hand)->plus($quantity);
            DB::table('inv_stock_balances')->where('id', $balance->id)->update(['quantity_on_hand' => (string) $after, 'last_movement_at' => now(), 'updated_at' => now()]);
            $document = $this->document('receipt', null, $warehouse->id, $actor, $operation);
            $line = $this->line($document, $item->id, $batch, $quantity, $unitCost);
            $movement = $this->movement($document, $line, $warehouse->id, $item->id, $batch, $quantity, $after, $unitCost, $actor, 'receipt', $operation);
            DB::table('inv_cost_layers')->insert(['warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'batch_id' => $batch, 'location_id' => $location, 'document_line_id' => $line, 'quantity_received' => (string) $quantity, 'quantity_remaining' => (string) $quantity, 'unit_cost' => (string) $unitCost, 'received_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

            DB::table('inv_stock_movements')->where('id', $movement)->update(['location_id' => $location]);

            return ['public_id' => $operation, 'document_id' => $document, 'movement_id' => $movement, 'warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'batch_id' => $batch, 'disposition' => $disposition, 'quantity' => (string) $quantity, 'unit_cost' => (string) $unitCost];
        });
    }

    public function availability(int $warehouseId, int $itemId): string
    {
        $warehouse = $this->warehouse($warehouseId, false);
        $this->item($itemId, $warehouse, false);
        $sum = BigDecimal::zero();
        foreach ($this->sellableBalances($warehouseId, $itemId)->get() as $balance) {
            $available = $this->number($balance->quantity_on_hand)->minus((string) $this->number($balance->quantity_reserved));
            if ($available->isPositive()) {
                $sum = $sum->plus($available);
            }
        }

        return (string) $sum->toScale(6);
    }

    /** Internal production withdrawal: returns to suppliers may include rejected/expired stock. */
    public function withdrawProductionRoll(array $input, string $key, int $actor): array
    {
        abort_unless($this->productionWriteDepth > 0, 403);

        return $this->command('production.withdraw', $key, $actor, $input, function (string $operation) use ($input, $actor): array {
            $warehouse = $this->warehouse((int) $input['warehouse_id']);
            abort_unless($warehouse->production_managed, 422);
            $item = $this->item((int) $input['item_id'], $warehouse);
            $this->notCounting($warehouse->id);
            $quantity = $this->positive($input['quantity']);
            $remaining = $quantity;
            $totalCost = BigDecimal::zero();
            $document = $this->document('issue', $warehouse->id, null, $actor, $operation);
            $allocations = [];
            foreach (DB::table('inv_stock_balances')->where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->where('batch_id', $input['batch_id'])->orderBy('id')->lockForUpdate()->get() as $balance) {
                $available = $this->number($balance->quantity_on_hand)->minus((string) $balance->quantity_reserved);
                $take = BigDecimal::min($remaining, $available);
                if (! $take->isPositive()) {
                    continue;
                }
                $cost = $this->consumeCost($warehouse->id, $item->id, $balance->batch_id, $take, $balance->location_id, $operation);
                $unitCost = $cost->dividedBy($take, 6, RoundingMode::HalfUp);
                $after = $this->number($balance->quantity_on_hand)->minus($take);
                DB::table('inv_stock_balances')->where('id', $balance->id)->update(['quantity_on_hand' => (string) $after, 'updated_at' => now()]);
                $line = $this->line($document, $item->id, $balance->batch_id, $take, $unitCost);
                $movement = $this->movement($document, $line, $warehouse->id, $item->id, $balance->batch_id, $take->negated(), $after, $unitCost, $actor, 'issue', $operation);
                DB::table('inv_stock_movements')->where('id', $movement)->update(['location_id' => $balance->location_id]);
                $totalCost = $totalCost->plus($cost);
                $allocations[] = ['batch_id' => $balance->batch_id, 'location_id' => $balance->location_id, 'quantity' => (string) $take, 'unit_cost' => (string) $unitCost];
                $remaining = $remaining->minus($take);
                if ($remaining->isZero()) {
                    break;
                }
            }
            abort_unless($remaining->isZero(), 409, 'Không đủ tồn cuộn chưa được giữ để trả.');

            return ['public_id' => $operation, 'operation_id' => $operation, 'document_id' => $document, 'quantity' => (string) $quantity, 'total_cost' => (string) $totalCost->toScale(6, RoundingMode::HalfUp), 'allocations' => $allocations];
        });
    }

    public function reserve(int $warehouseId, int $itemId, string $quantity, string $source, string $key, int $actor, ?int $batchId = null): array
    {
        $input = compact('warehouseId', 'itemId', 'quantity', 'source', 'batchId');

        return $this->command('reserve', $key, $actor, $input, function () use ($warehouseId, $itemId, $quantity, $source, $actor, $batchId): array {
            $warehouse = $this->warehouse($warehouseId);
            $item = $this->item($itemId, $warehouse);
            $this->notCounting($warehouseId);
            abort_if($item->costing_method !== 'fifo', 422, 'Phương pháp giá vốn chưa được hỗ trợ.');
            abort_if($item->track_serial, 422, 'Hàng serial cần quy trình phân bổ riêng.');
            $quantity = $this->positive($quantity);
            $remaining = $quantity;
            $allocations = [];
            foreach ($this->sellableBalances($warehouseId, $itemId)->when($batchId !== null, fn (Builder $q) => $q->where('inv_stock_balances.batch_id', $batchId))->lockForUpdate()->get() as $balance) {
                $available = $this->number($balance->quantity_on_hand)->minus((string) $this->number($balance->quantity_reserved));
                if (! $available->isPositive()) {
                    continue;
                }
                $take = $available->isLessThan($remaining) ? $available : $remaining;
                $allocations[] = ['balance_id' => $balance->id, 'quantity' => (string) $take];
                $remaining = $remaining->minus($take);
                if ($remaining->isZero()) {
                    break;
                }
            }
            if (! $remaining->isZero()) {
                throw new ConflictHttpException('Không đủ tồn bán được.');
            }
            $publicId = (string) Str::uuid();
            $id = DB::table('inv_reservations')->insertGetId(['public_id' => $publicId, 'warehouse_id' => $warehouseId, 'item_id' => $itemId, 'quantity' => (string) $quantity, 'source_id' => $source, 'actor_id' => $actor, 'status' => 'active', 'protected_payment' => false, 'expires_at' => now()->addMinutes(15), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($allocations as $allocation) {
                DB::table('inv_reservation_allocations')->insert($allocation + ['reservation_id' => $id]);
                $balance = DB::table('inv_stock_balances')->find($allocation['balance_id']);
                DB::table('inv_stock_balances')->where('id', $balance->id)->update(['quantity_reserved' => (string) $this->number($balance->quantity_reserved)->plus($allocation['quantity']), 'updated_at' => now()]);
            }

            return $this->reservation($id);
        });
    }

    public function commitReservation(string $reservationId, string $key, int $actor): array
    {
        return $this->command('commit', $key, $actor, compact('reservationId'), function (string $operation) use ($reservationId, $actor): array {
            $reservation = $this->lockedReservation($reservationId);
            abort_unless($reservation->status === 'active', 409, 'Giữ hàng không còn hiệu lực.');
            abort_if(! $reservation->protected_payment && now()->greaterThan($reservation->expires_at), 409, 'Giữ hàng đã hết hạn.');
            $warehouse = $this->warehouse($reservation->warehouse_id);
            $this->item($reservation->item_id, $warehouse);
            $this->notCounting($warehouse->id);
            $document = $this->document('issue', $warehouse->id, null, $actor, $operation);
            $totalCost = BigDecimal::zero();
            foreach (DB::table('inv_reservation_allocations')->where('reservation_id', $reservation->id)->orderBy('balance_id')->get() as $allocation) {
                $balance = DB::table('inv_stock_balances')->where('id', $allocation->balance_id)->lockForUpdate()->first();
                $quantity = $this->positive($allocation->quantity);
                abort_if($this->number($balance->quantity_on_hand)->isLessThan($quantity) || $this->number($balance->quantity_reserved)->isLessThan($quantity), 409, 'Tồn giữ không còn nhất quán.');
                $batch = $balance->batch_id ? DB::table('inv_batches')->find($balance->batch_id) : null;
                abort_if($batch && (! $batch->is_active || ($batch->expires_at && substr($batch->expires_at, 0, 10) < now($warehouse->timezone)->toDateString())), 409, 'Lô đã hết hạn hoặc bị khóa.');
                $cost = $this->consumeCost($warehouse->id, $reservation->item_id, $balance->batch_id, $quantity, $balance->location_id, $operation);
                $totalCost = $totalCost->plus($cost);
                $unitCost = $cost->dividedBy($quantity, 6, RoundingMode::HalfUp);
                $after = $this->number($balance->quantity_on_hand)->minus($quantity);
                DB::table('inv_stock_balances')->where('id', $balance->id)->update(['quantity_on_hand' => (string) $after, 'quantity_reserved' => (string) $this->number($balance->quantity_reserved)->minus($quantity), 'last_movement_at' => now(), 'updated_at' => now()]);
                $line = $this->line($document, $reservation->item_id, $balance->batch_id, $quantity, $unitCost);
                $this->movement($document, $line, $warehouse->id, $reservation->item_id, $balance->batch_id, $quantity->negated(), $after, $unitCost, $actor, 'issue', $operation);
            }
            DB::table('inv_reservations')->where('id', $reservation->id)->update(['status' => 'committed', 'updated_at' => now()]);

            $allocations = DB::table('inv_stock_document_lines')->where('document_id', $document)->orderBy('id')->get()->map(fn ($line): array => ['batch_id' => $line->batch_id, 'quantity' => (string) $this->number($line->quantity), 'unit_cost' => (string) $this->number($line->unit_cost)])->all();

            return $this->reservation($reservation->id) + ['document_id' => $document, 'total_cost' => (string) $totalCost->toScale(6, RoundingMode::HalfUp), 'unit_cost' => (string) $totalCost->dividedBy((string) $this->number($reservation->quantity), 6, RoundingMode::HalfUp), 'allocations' => $allocations, 'operation_id' => $operation];
        });
    }

    public function releaseReservation(string $reservationId, string $key, int $actor): array
    {
        return $this->command('release', $key, $actor, compact('reservationId'), function () use ($reservationId): array {
            $reservation = $this->lockedReservation($reservationId);
            abort_unless($reservation->status === 'active' && ! $reservation->protected_payment, 409, 'Giữ hàng chưa được phép giải phóng.');
            foreach (DB::table('inv_reservation_allocations')->where('reservation_id', $reservation->id)->orderBy('balance_id')->get() as $allocation) {
                $balance = DB::table('inv_stock_balances')->where('id', $allocation->balance_id)->lockForUpdate()->first();
                DB::table('inv_stock_balances')->where('id', $balance->id)->update(['quantity_reserved' => (string) $this->number($balance->quantity_reserved)->minus((string) $allocation->quantity), 'updated_at' => now()]);
            }
            DB::table('inv_reservations')->where('id', $reservation->id)->update(['status' => 'released', 'updated_at' => now()]);

            return $this->reservation($reservation->id);
        });
    }

    public function protectReservation(string $reservationId, bool $protected): void
    {
        DB::transaction(function () use ($reservationId, $protected): void {
            $this->runtime(lock: true);
            DB::table('inv_reservations')->where('public_id', $reservationId)->where('status', 'active')->update(['protected_payment' => $protected, 'updated_at' => now()]);
        }, 3);
    }

    public function expireReservations(int $limit = 100): int
    {
        $count = 0;
        $query = DB::table('inv_reservations')->where('status', 'active')->where('protected_payment', false)->where('expires_at', '<', now());
        if (Schema::hasColumn('inv_reservations', 'purpose')) {
            $query->where('purpose', '!=', 'production');
        }
        foreach ($query->limit(min(1000, max(1, $limit)))->get() as $reservation) {
            $this->releaseReservation($reservation->public_id, 'expire:'.$reservation->public_id, $reservation->actor_id);
            $count++;
        }

        return $count;
    }

    public function dispatchTransfer(array $input, string $key, int $actor): array
    {
        return $this->command('transfer.dispatch', $key, $actor, $input, function () use ($input, $key, $actor): array {
            $source = $this->warehouse((int) $input['source_warehouse_id']);
            $destination = $this->warehouse((int) $input['destination_warehouse_id']);
            abort_if($source->id === $destination->id || $source->legal_entity_ref !== $destination->legal_entity_ref || $source->catalog_id !== $destination->catalog_id, 422, 'Chuyển kho phải cùng pháp nhân và danh mục hàng.');
            $this->notCounting($source->id);
            $this->notCounting($destination->id);
            abort_if(empty($input['lines']) || count($input['lines']) > 200, 422, 'Phiếu chuyển cần 1–200 dòng.');
            $publicId = (string) Str::uuid();
            $id = DB::table('inv_transfers')->insertGetId(['public_id' => $publicId, 'source_warehouse_id' => $source->id, 'destination_warehouse_id' => $destination->id, 'legal_entity_ref' => $source->legal_entity_ref, 'status' => 'dispatched', 'version' => 1, 'actor_id' => $actor, 'created_at' => now(), 'updated_at' => now()]);
            foreach (array_values($input['lines']) as $index => $line) {
                $hold = $this->reserve($source->id, (int) $line['item_id'], (string) $line['quantity'], 'transfer:'.$publicId, $key.':r:'.$index, $actor, $line['batch_id'] ?? null);
                $issued = $this->commitReservation($hold['public_id'], $key.':c:'.$index, $actor);
                foreach (DB::table('inv_stock_document_lines')->where('document_id', $issued['document_id'])->orderBy('id')->get() as $allocation) {
                    DB::table('inv_transfer_lines')->insert(['transfer_id' => $id, 'item_id' => $line['item_id'], 'batch_id' => $allocation->batch_id, 'quantity' => $allocation->quantity, 'received_quantity' => '0', 'unit_cost' => $allocation->unit_cost]);
                }
            }

            return $this->transfer($id);
        });
    }

    public function receiveTransfer(string $transferId, array $input, string $key, int $actor): array
    {
        return $this->command('transfer.receive', $key, $actor, compact('transferId', 'input'), function () use ($transferId, $input, $key, $actor): array {
            $transfer = DB::table('inv_transfers')->where('public_id', $transferId)->lockForUpdate()->first();
            abort_unless($transfer, 404);
            abort_unless(in_array($transfer->status, ['dispatched', 'partially_received'], true), 409, 'Phiếu chuyển đã kết thúc.');
            abort_unless((int) ($input['expected_version'] ?? 0) === $transfer->version, 409, 'Phiên bản phiếu chuyển đã thay đổi.');
            abort_if($transfer->actor_id === $actor, 403, 'Người giao không tự xác nhận nhận hàng.');
            foreach ($input['lines'] ?? [] as $index => $received) {
                $line = DB::table('inv_transfer_lines')->where('transfer_id', $transfer->id)->where('id', $received['line_id'])->lockForUpdate()->first();
                abort_unless($line, 404);
                $quantity = $this->positive($received['quantity']);
                $after = $this->number($line->received_quantity)->plus($quantity);
                abort_if($after->isGreaterThan((string) $line->quantity), 422, 'Số nhận vượt số đang vận chuyển.');
                $this->receive(['warehouse_id' => $transfer->destination_warehouse_id, 'item_id' => $line->item_id, 'batch_id' => $line->batch_id, 'quantity' => (string) $quantity, 'unit_cost' => $line->base_unit_cost ?? $line->unit_cost, 'disposition' => $received['disposition'] ?? 'saleable', 'source_id' => 'transfer:'.$transfer->public_id.':'.$line->id], $key.':line:'.$index, $actor);
                DB::table('inv_transfer_lines')->where('id', $line->id)->update(['received_quantity' => (string) $after]);
            }
            $complete = true;
            foreach (DB::table('inv_transfer_lines')->where('transfer_id', $transfer->id)->get() as $line) {
                if (! $this->number($line->quantity)->isEqualTo((string) $line->received_quantity)) {
                    $complete = false;
                }
            }
            DB::table('inv_transfers')->where('id', $transfer->id)->update(['status' => $complete ? 'received' : 'partially_received', 'version' => $transfer->version + 1, 'updated_at' => now()]);

            return $this->transfer($transfer->id);
        });
    }

    public function beginStocktake(int $warehouseId, string $key, int $actor, bool $allowProductionHolds = false): array
    {
        return $this->command('stocktake.begin', $key, $actor, ($allowProductionHolds ? compact('warehouseId', 'allowProductionHolds') : compact('warehouseId')), function () use ($warehouseId, $actor, $allowProductionHolds): array {
            $this->warehouse($warehouseId);
            $this->notCounting($warehouseId);
            abort_if($allowProductionHolds && $this->productionWriteDepth < 1, 403);
            $holds = DB::table('inv_reservations')->where('warehouse_id', $warehouseId)->where('status', 'active');
            if ($allowProductionHolds) {
                $holds->where('purpose', '!=', 'production');
            }
            abort_if($holds->exists(), 409, 'Cần xử lý giữ hàng/quota trước kiểm kê.');
            $publicId = (string) Str::uuid();
            $id = DB::table('inv_stocktakes')->insertGetId(['public_id' => $publicId, 'warehouse_id' => $warehouseId, 'status' => 'counting', 'maker_id' => $actor, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            foreach (DB::table('inv_stock_balances')->where('warehouse_id', $warehouseId)->orderBy('id')->lockForUpdate()->get() as $balance) {
                DB::table('inv_stocktake_lines')->insert(['stocktake_id' => $id, 'balance_id' => $balance->id, 'expected_quantity' => $balance->quantity_on_hand]);
            }

            return $this->stocktake($id);
        });
    }

    public function postStocktake(string $stocktakeId, array $counts, string $key, int $actor): array
    {
        return $this->command('stocktake.post', $key, $actor, compact('stocktakeId', 'counts'), function (string $operation) use ($stocktakeId, $counts, $actor): array {
            $take = DB::table('inv_stocktakes')->where('public_id', $stocktakeId)->lockForUpdate()->first();
            abort_unless($take, 404);
            abort_unless($take->status === 'counting', 409);
            abort_if($take->maker_id === $actor, 403, 'Người đếm không tự duyệt chênh lệch.');
            $this->warehouse($take->warehouse_id);
            $lines = DB::table('inv_stocktake_lines')->where('stocktake_id', $take->id)->get();
            abort_unless(count($counts) === $lines->count(), 422, 'Cần nhập đủ các dòng kiểm kê.');
            $indexed = collect($counts)->keyBy('balance_id');
            foreach ($lines as $line) {
                abort_unless($indexed->has($line->balance_id), 422, 'Thiếu dòng kiểm kê.');
                $count = $this->number($indexed[$line->balance_id]['quantity']);
                abort_if($count->isNegative(), 422);
                $balance = DB::table('inv_stock_balances')->where('id', $line->balance_id)->lockForUpdate()->first();
                abort_unless($this->number($balance->quantity_on_hand)->isEqualTo((string) $line->expected_quantity), 409, 'Tồn đã thay đổi trong phạm vi đang khóa.');
                abort_if($count->isLessThan((string) $balance->quantity_reserved), 409, 'Số kiểm thấp hơn lượng đã giữ; đối soát giữ hàng trước khi ghi nhận.');
                $delta = $count->minus((string) $line->expected_quantity);
                if (! $delta->isZero()) {
                    // Adjustment is a new immutable movement; no balance overwrite without lineage.
                    $costLayer = DB::table('inv_cost_layers')->where('warehouse_id', $balance->warehouse_id)->where('item_id', $balance->item_id)->where('batch_id', $balance->batch_id)->where('location_id', $balance->location_id)->orderByDesc('id')->first();
                    abort_if($costLayer === null, 422, 'Thiếu giá vốn để xử lý kiểm kê.');
                    $cost = $this->number($costLayer->unit_cost);
                    if ($this->number($costLayer->quantity_remaining)->isPositive()) {
                        $cost = $cost->plus(app(InventoryValuationService::class)->remaining($costLayer->id)->dividedBy((string) $costLayer->quantity_remaining, 6, RoundingMode::HalfUp));
                    }
                    if ($delta->isNegative()) {
                        $actualCost = $this->consumeCost($balance->warehouse_id, $balance->item_id, $balance->batch_id, $delta->abs(), $balance->location_id, $operation);
                        $cost = $actualCost->dividedBy((string) $delta->abs(), 6, RoundingMode::HalfUp);
                    } else {
                        DB::table('inv_cost_layers')->insert(['warehouse_id' => $balance->warehouse_id, 'item_id' => $balance->item_id, 'batch_id' => $balance->batch_id, 'location_id' => $balance->location_id, 'quantity_received' => (string) $delta, 'quantity_remaining' => (string) $delta, 'unit_cost' => (string) $cost, 'received_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                    }
                    $doc = $this->document('adjustment', null, $balance->warehouse_id, $actor, $operation.'-'.$line->id);
                    $docLine = $this->line($doc, $balance->item_id, $balance->batch_id, $delta, $this->number($cost));
                    DB::table('inv_stock_balances')->where('id', $balance->id)->update(['quantity_on_hand' => (string) $count, 'updated_at' => now()]);
                    $this->movement($doc, $docLine, $balance->warehouse_id, $balance->item_id, $balance->batch_id, $delta, $count, $this->number($cost), $actor, 'adjustment', $operation);
                }
                DB::table('inv_stocktake_lines')->where('id', $line->id)->update(['counted_quantity' => (string) $count]);
            }
            DB::table('inv_stocktakes')->where('id', $take->id)->update(['status' => 'posted', 'checker_id' => $actor, 'version' => $take->version + 1, 'updated_at' => now()]);

            return $this->stocktake($take->id);
        });
    }

    public function grantEscrow(array $input, string $key, int $actor): array
    {
        return $this->command('escrow.grant', $key, $actor, $input, function () use ($input, $key, $actor): array {
            validator($input, ['gateway_id' => 'required|string|max:100', 'epoch' => 'required|integer|min:1'])->validate();
            $warehouse = $this->warehouse((int) $input['warehouse_id']);
            $this->item((int) $input['item_id'], $warehouse);
            abort_if(DB::table('inv_escrows')->where('gateway_id', $input['gateway_id'])->where('warehouse_id', $warehouse->id)->where('item_id', $input['item_id'])->where('status', 'active')->exists(), 409, 'Gateway đã có quota active cho hàng này.');
            $hold = $this->reserve($warehouse->id, (int) $input['item_id'], (string) $input['quantity'], 'escrow:'.$input['gateway_id'].':'.$input['epoch'], $key.':hold', $actor, $input['batch_id'] ?? null);
            DB::table('inv_reservations')->where('id', $hold['id'])->update(['protected_payment' => true]);
            $id = DB::table('inv_escrows')->insertGetId(['public_id' => (string) Str::uuid(), 'gateway_id' => $input['gateway_id'], 'epoch' => $input['epoch'], 'warehouse_id' => $warehouse->id, 'item_id' => $input['item_id'], 'reservation_id' => $hold['id'], 'quantity' => $hold['quantity'], 'consumed_quantity' => '0', 'last_sequence' => 0, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

            return (array) DB::table('inv_escrows')->find($id);
        });
    }

    /** Central ingestion only. Escrow stays reserved until each consumption is committed. */
    public function consumeEscrow(string $escrowId, int $epoch, int $sequence, string $quantity, string $key, int $actor): array
    {
        return $this->command('escrow.consume', $key, $actor, compact('escrowId', 'epoch', 'sequence', 'quantity'), function () use ($escrowId, $epoch, $sequence, $quantity, $key, $actor): array {
            $escrow = DB::table('inv_escrows')->where('public_id', $escrowId)->lockForUpdate()->first();
            abort_unless($escrow, 404);
            abort_unless($escrow->status === 'active' && $escrow->epoch === $epoch && $sequence === $escrow->last_sequence + 1, 409, 'Epoch hoặc sequence không hợp lệ.');
            $quantity = $this->positive($quantity);
            $consumed = $this->number($escrow->consumed_quantity)->plus($quantity);
            abort_if($consumed->isGreaterThan((string) $escrow->quantity), 409, 'Consumption vượt quota đã cấp.');
            $parent = DB::table('inv_reservations')->where('id', $escrow->reservation_id)->lockForUpdate()->first();
            $remaining = $quantity;
            $childPublic = (string) Str::uuid();
            $child = DB::table('inv_reservations')->insertGetId(['public_id' => $childPublic, 'warehouse_id' => $escrow->warehouse_id, 'item_id' => $escrow->item_id, 'quantity' => (string) $quantity, 'source_id' => 'escrow:'.$escrow->public_id.':'.$sequence, 'actor_id' => $actor, 'status' => 'active', 'protected_payment' => true, 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
            foreach (DB::table('inv_reservation_allocations')->where('reservation_id', $parent->id)->orderBy('balance_id')->lockForUpdate()->get() as $allocation) {
                $available = $this->number($allocation->quantity);
                if (! $available->isPositive()) {
                    continue;
                }
                $take = $available->isLessThan($remaining) ? $available : $remaining;
                DB::table('inv_reservation_allocations')->where('id', $allocation->id)->update(['quantity' => (string) $available->minus($take)]);
                DB::table('inv_reservation_allocations')->insert(['reservation_id' => $child, 'balance_id' => $allocation->balance_id, 'quantity' => (string) $take]);
                $remaining = $remaining->minus($take);
                if ($remaining->isZero()) {
                    break;
                }
            }
            abort_unless($remaining->isZero(), 409, 'Quota allocation không nhất quán.');
            $stock = $this->commitReservation($childPublic, $key.':stock', $actor);
            DB::table('inv_escrows')->where('id', $escrow->id)->update(['consumed_quantity' => (string) $consumed, 'last_sequence' => $sequence, 'status' => $consumed->isEqualTo((string) $escrow->quantity) ? 'consumed' : 'active', 'updated_at' => now()]);
            DB::table('inv_reservations')->where('id', $parent->id)->update(['quantity' => (string) $this->number($escrow->quantity)->minus($consumed), 'status' => $consumed->isEqualTo((string) $escrow->quantity) ? 'committed' : 'active', 'updated_at' => now()]);

            return (array) DB::table('inv_escrows')->find($escrow->id) + ['stock_operation' => $stock];
        });
    }

    private function command(string $kind, string $key, int $actor, array $input, callable $effect): array
    {
        abort_unless($key !== '' && strlen($key) <= 191, 422, 'Thiếu mã chống giao dịch trùng.');

        return DB::transaction(function () use ($kind, $key, $actor, $input, $effect): array {
            $this->runtime(lock: true);
            $fingerprint = hash('sha256', json_encode([$kind, $actor, $this->canonical($input)], JSON_THROW_ON_ERROR));
            DB::table('inv_v2_operations')->insertOrIgnore(['public_id' => (string) Str::uuid(), 'idempotency_key' => $key, 'fingerprint' => $fingerprint, 'kind' => $kind, 'actor_id' => $actor, 'source_id' => $input['source_id'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
            $operation = DB::table('inv_v2_operations')->where('idempotency_key', $key)->lockForUpdate()->first();
            abort_unless($operation->fingerprint === $fingerprint, 409, 'Mã giao dịch đã dùng cho nội dung khác.');
            if ($operation->response !== null) {
                return json_decode($operation->response, true, flags: JSON_THROW_ON_ERROR) + ['replayed' => true];
            }
            $result = $effect($operation->public_id);
            DB::table('inv_v2_operations')->where('id', $operation->id)->update(['response' => json_encode($result, JSON_THROW_ON_ERROR), 'updated_at' => now()]);

            return $result;
        }, 3);
    }

    private function canonical(mixed $input): mixed
    {
        if (! is_array($input)) {
            return $input;
        }
        if (! array_is_list($input)) {
            ksort($input);
        }
        foreach ($input as &$value) {
            $value = $this->canonical($value);
        }

        return $input;
    }

    private function warehouse(int $id, bool $lock = true): object
    {
        $this->runtime();
        $query = DB::table('inv_warehouses')->where('id', $id)->where('is_active', true)->where('v2_managed', true);
        $warehouse = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($warehouse && $warehouse->catalog_id && $warehouse->legal_entity_ref, 422, 'Kho chưa có ownership Retail hợp lệ.');
        abort_if($lock && ($warehouse->production_managed ?? false) && $this->productionWriteDepth < 1, 409, 'Kho sản xuất phải ghi qua production contract để giữ trace và cost lineage.');

        return $warehouse;
    }

    private function item(int $id, object $warehouse, bool $lock = true): object
    {
        $query = DB::table('inv_items')->where('id', $id)->where('is_active', true);
        $item = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($item && $item->merchandise_sku_id, 422, 'Hàng chưa liên kết Merchandise.');
        abort_unless(DB::table('merch_skus')->where('id', $item->merchandise_sku_id)->where('catalog_id', $warehouse->catalog_id)->where('is_active', true)->exists(), 403, 'Hàng thuộc danh mục khác.');

        return $item;
    }

    private function notCounting(int $warehouse): void
    {
        abort_if(DB::table('inv_stocktakes')->where('warehouse_id', $warehouse)->where('status', 'counting')->exists(), 409, 'Kho đang khóa kiểm kê.');
    }

    private function batch(array $input, object $item): ?int
    {
        if (! empty($input['batch_id'])) {
            $batch = DB::table('inv_batches')->where('id', $input['batch_id'])->where('item_id', $item->id)->first();
            abort_unless($batch && ($batch->is_active || in_array($input['disposition'] ?? 'saleable', ['quarantine', 'damaged'], true)), 422, 'Lô không hợp lệ.');

            return $batch->id;
        }
        if (! empty($input['batch_code'])) {
            $requiresExpiry = (bool) DB::table('merch_skus')->where('id', $item->merchandise_sku_id)->value('requires_expiry');
            $expiresAt = $input['expires_at'] ?? null;
            abort_if($requiresExpiry && empty($expiresAt), 422, 'Lô hàng cần hạn sử dụng.');
            validator(['expires_at' => $expiresAt], ['expires_at' => 'nullable|date_format:Y-m-d'])->validate();
            DB::table('inv_batches')->insertOrIgnore(['item_id' => $item->id, 'batch_code' => $input['batch_code'], 'expires_at' => $expiresAt, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            $batch = DB::table('inv_batches')->where('item_id', $item->id)->where('batch_code', $input['batch_code'])->first();
            abort_unless(substr((string) $batch->expires_at, 0, 10) === substr((string) $expiresAt, 0, 10), 409, 'Mã lô đã có hạn khác.');

            return $batch->id;
        }
        abort_if($item->track_batch, 422, 'Hàng theo lô cần mã lô.');

        return null;
    }

    private function balance(int $warehouse, int $item, ?int $batch, ?int $location = null): object
    {
        DB::table('inv_stock_balances')->insertOrIgnore(['warehouse_id' => $warehouse, 'item_id' => $item, 'location_id' => $location, 'location_key' => $location ?? 0, 'batch_id' => $batch, 'batch_key' => $batch ?? 0, 'quantity_on_hand' => '0', 'quantity_reserved' => '0', 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('inv_stock_balances')->where(['warehouse_id' => $warehouse, 'item_id' => $item, 'location_key' => $location ?? 0, 'batch_key' => $batch ?? 0])->lockForUpdate()->first();
    }

    private function sellableBalances(int $warehouse, int $item): Builder
    {
        $today = now(DB::table('inv_warehouses')->where('id', $warehouse)->value('timezone') ?: 'UTC')->toDateString();

        return DB::table('inv_stock_balances')->leftJoin('inv_batches', 'inv_batches.id', '=', 'inv_stock_balances.batch_id')->leftJoin('inv_locations', 'inv_locations.id', '=', 'inv_stock_balances.location_id')
            ->where('inv_stock_balances.warehouse_id', $warehouse)->where('inv_stock_balances.item_id', $item)
            ->where(fn (Builder $q) => $q->whereNull('inv_stock_balances.location_id')->orWhereNotIn('inv_locations.type', ['quarantine', 'damaged']))
            ->where(fn (Builder $q) => $q->whereNull('inv_stock_balances.batch_id')->orWhere(fn (Builder $b) => $b->where('inv_batches.is_active', true)->where(fn (Builder $d) => $d->whereNull('inv_batches.expires_at')->orWhereDate('inv_batches.expires_at', '>=', $today))))
            ->select('inv_stock_balances.*')->orderByRaw('inv_batches.expires_at IS NULL')->orderBy('inv_batches.expires_at')->orderBy('inv_stock_balances.id');
    }

    private function lockedReservation(string $publicId): object
    {
        $r = DB::table('inv_reservations')->where('public_id', $publicId)->lockForUpdate()->first();
        abort_unless($r, 404, 'Không tìm thấy giữ hàng.');

        return $r;
    }

    private function reservation(int $id): array
    {
        return (array) DB::table('inv_reservations')->find($id);
    }

    private function transfer(int $id): array
    {
        return (array) DB::table('inv_transfers')->find($id) + ['lines' => DB::table('inv_transfer_lines')->where('transfer_id', $id)->get()->map(fn ($l) => (array) $l)->all()];
    }

    private function stocktake(int $id): array
    {
        return (array) DB::table('inv_stocktakes')->find($id) + ['lines' => DB::table('inv_stocktake_lines')->where('stocktake_id', $id)->get()->map(fn ($l) => (array) $l)->all()];
    }

    private function consumeCost(int $warehouse, int $item, ?int $batch, BigDecimal $quantity, ?int $location = null, string $operation = 'inventory.consume'): BigDecimal
    {
        $remaining = $quantity;
        $cost = BigDecimal::zero();
        foreach (DB::table('inv_cost_layers')->where('warehouse_id', $warehouse)->where('item_id', $item)->where('batch_id', $batch)->where('location_id', $location)->where('quantity_remaining', '>', 0)->orderBy('received_at')->orderBy('id')->lockForUpdate()->get() as $layer) {
            $available = $this->number($layer->quantity_remaining);
            $take = $available->isLessThan($remaining) ? $available : $remaining;
            DB::table('inv_cost_layers')->where('id', $layer->id)->update(['quantity_remaining' => (string) $available->minus($take), 'updated_at' => now()]);
            $value = $take->multipliedBy((string) $this->number($layer->unit_cost))->plus(app(InventoryValuationService::class)->consume($layer, $take, $operation));
            abort_if($value->isNegative(), 409, 'Điều chỉnh giá trị làm âm giá vốn xuất.');
            $cost = $cost->plus($value);
            $remaining = $remaining->minus($take);
            if ($remaining->isZero()) {
                break;
            }
        }
        abort_unless($remaining->isZero(), 409, 'Thiếu cost layer để xuất kho.');

        return $cost;
    }

    private function document(string $type, ?int $source, ?int $destination, int $actor, string $reference): int
    {
        return DB::table('inv_stock_documents')->insertGetId(['code' => 'V2-'.str_replace('-', '', (string) Str::uuid()), 'type' => $type, 'status' => 'posted', 'source_warehouse_id' => $source, 'destination_warehouse_id' => $destination, 'reference' => $reference, 'created_by_admin_id' => $actor, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function line(int $document, int $item, ?int $batch, BigDecimal $quantity, BigDecimal $cost): int
    {
        return DB::table('inv_stock_document_lines')->insertGetId(['document_id' => $document, 'item_id' => $item, 'batch_id' => $batch, 'quantity' => (string) $quantity, 'unit_cost' => (string) $cost, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function movement(int $document, int $line, int $warehouse, int $item, ?int $batch, BigDecimal $quantity, BigDecimal $after, BigDecimal $cost, int $actor, string $type, string $reference): int
    {
        return DB::table('inv_stock_movements')->insertGetId(['document_id' => $document, 'document_line_id' => $line, 'warehouse_id' => $warehouse, 'item_id' => $item, 'batch_id' => $batch, 'quantity_delta' => (string) $quantity, 'balance_after' => (string) $after, 'unit_cost' => (string) $cost, 'type' => $type, 'reference' => $reference, 'created_by_admin_id' => $actor, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function number(mixed $value): BigDecimal
    {
        try {
            $raw = (string) $value;
            if (strlen($raw) > 64 || (preg_match('/[eE]([+-]?\d+)/', $raw, $exponent) && abs((int) $exponent[1]) > 24)) {
                throw new \InvalidArgumentException('Decimal is outside the supported range.');
            }
            $number = BigDecimal::of($raw)->toScale(6, RoundingMode::Unnecessary);
            if ($number->abs()->isGreaterThan('999999999999999999.999999')) {
                throw new \InvalidArgumentException('Decimal is outside the storage range.');
            }

            return $number;
        } catch (\Throwable) {
            throw ValidationException::withMessages(['quantity' => 'Số thập phân không hợp lệ hoặc quá 6 chữ số lẻ.']);
        }
    }

    private function positive(mixed $value): BigDecimal
    {
        $n = $this->number($value);
        abort_unless($n->isPositive(), 422, 'Số lượng phải lớn hơn 0.');

        return $n;
    }

    private function runtime(bool $lock = false): void
    {
        app(InventoryRuntimeState::class)->guard($lock);
        $query = DB::table('module_installations')->where('key', 'inventory');
        $installation = ($lock ? $query->sharedLock() : $query)->first();
        abort_unless($installation && $installation->status === 'enabled' && version_compare($installation->version, '0.3.0', '>='), 503, 'Inventory v2 chưa được nâng cấp và bật.');
        abort_if(DB::table('module_lifecycle_operations')->where('module_key', 'inventory')->where('active_slot', 'active')->exists(), 503, 'Inventory đang thay đổi vòng đời.');
        abort_unless(DB::table('module_installations')->where('key', 'merchandise')->where('status', 'enabled')->exists(), 503, 'Merchandise chưa sẵn sàng.');
    }
}
