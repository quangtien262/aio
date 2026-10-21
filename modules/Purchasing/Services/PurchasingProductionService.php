<?php

namespace Modules\Purchasing\Services;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\Admin;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Inventory\Services\InventoryProductionService;
use Modules\Merchandise\Services\MerchandiseService;

/** HQ procurement remains global. Factory membership alone never grants purchasing access. */
class PurchasingProductionService
{
    public function __construct(private readonly PurchasingService $orders, private readonly InventoryProductionService $stock) {}

    public function requisition(array $input, string $key, int $actor): array
    {
        return $this->command('requisition.create', 'purchasing.order.create', $input, $key, $actor, function () use ($input, $actor): array {
            $v = validator($input, ['order_ref' => 'required|string|max:191', 'source_revision' => 'sometimes|string|max:80', 'warehouse_id' => 'required|integer', 'catalog_id' => 'required|integer', 'lines' => 'required|array|min:1|max:300', 'lines.*.sku_id' => 'required|integer|distinct', 'lines.*.quantity' => 'required'])->validate();
            $w = $this->warehouse($v['warehouse_id']);
            abort_unless((int) $w->catalog_id === (int) $v['catalog_id'], 422);
            foreach ($v['lines'] as &$line) {
                abort_unless(DB::table('merch_skus')->where('id', $line['sku_id'])->where('catalog_id', $w->catalog_id)->where('is_active', true)->exists(), 422);
                $line['quantity'] = app(MerchandiseService::class)->convertQuantity((int) $line['sku_id'], (string) $line['quantity']);
            }
            unset($line);
            $revision = $v['source_revision'] ?? '1';
            $old = DB::table('purchase_requisitions')->where('order_ref', $v['order_ref'])->where('source_revision', $revision)->where('warehouse_id', $w->id)->first();
            if ($old) {
                abort_unless(json_decode($old->lines, true) === $v['lines'], 409, 'Revision yêu cầu mua đã có nội dung khác.');

                return (array) $old;
            }
            $id = DB::table('purchase_requisitions')->insertGetId(['public_id' => (string) Str::uuid(), 'order_ref' => $v['order_ref'], 'source_revision' => $revision, 'warehouse_id' => $w->id, 'catalog_id' => $w->catalog_id, 'lines' => json_encode($v['lines'], JSON_THROW_ON_ERROR), 'status' => 'open', 'actor_id' => $actor, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

            return (array) DB::table('purchase_requisitions')->find($id);
        });
    }

    public function createOrder(array $input, string $key, int $actor): array
    {
        return $this->command('order.production.create', 'purchasing.order.create', $input, $key, $actor, function () use ($input, $key, $actor): array {
            $r = DB::table('purchase_requisitions')->where('public_id', $input['requisition_id'] ?? '')->lockForUpdate()->first();
            abort_unless($r && $r->status === 'open' && ! $r->purchase_order_id, 409, 'Yêu cầu mua đã chuyển hoặc không tồn tại.');
            $data = array_replace($input, ['warehouse_id' => $r->warehouse_id, 'catalog_id' => $r->catalog_id]);
            $requested = collect(json_decode($r->lines, true))->keyBy('sku_id');
            abort_unless(count($data['lines'] ?? []) === $requested->count(), 422, 'PO phải phủ đúng các dòng yêu cầu.');
            foreach ($data['lines'] as $line) {
                abort_unless($requested->has($line['sku_id'] ?? null) && $this->number($line['quantity'] ?? '')->isEqualTo($requested[$line['sku_id']]['quantity']), 422, 'PO khác SKU/số lượng yêu cầu.');
            }
            $order = $this->orders->createOrder($data, $this->child($key, 'order'), $actor);
            DB::table('purchase_requisitions')->where('id', $r->id)->update(['status' => 'ordered', 'purchase_order_id' => $order['id'], 'version' => $r->version + 1, 'updated_at' => now()]);

            return $order + ['requisition_id' => $r->public_id];
        });
    }

    public function receive(array $input, string $key, int $actor): array
    {
        return $this->command('order.production.receive', 'purchasing.receipt.manage', $input, $key, $actor, function () use ($input, $key, $actor): array {
            $v = validator($input, ['order_id' => 'required|uuid', 'expected_version' => 'required|integer|min:1', 'lines' => 'required|array|min:1|max:300', 'lines.*.order_line_id' => 'required|integer|distinct', 'lines.*.rolls' => 'required|array|min:1|max:300', 'lines.*.rolls.*.code' => 'required|string|max:100', 'lines.*.rolls.*.quantity' => 'required'])->validate();
            $o = $this->order($v['order_id'], $v['expected_version']);
            abort_unless(in_array($o->status, ['approved', 'partially_received'], true), 409, 'PO chưa duyệt hoặc đã đóng.');
            $w = $this->warehouse($o->warehouse_id);
            abort_unless($w->legal_entity_ref === $o->legal_entity_ref, 409);
            $receipt = DB::table('purchase_receipts')->insertGetId(['public_id' => (string) Str::uuid(), 'order_id' => $o->id, 'actor_id' => $actor, 'reference' => $input['reference'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
            $receivedRolls = [];
            foreach ($input['lines'] as $i => $entry) {
                $line = DB::table('purchase_order_lines')->where('order_id', $o->id)->where('id', $entry['order_line_id'])->lockForUpdate()->first();
                abort_unless($line, 422);
                $received = $this->number($line->quantity_received);
                foreach ($entry['rolls'] as $j => $roll) {
                    $qty = $this->number($roll['quantity']);
                    abort_unless($qty->isPositive(), 422);
                    $received = $received->plus($qty);
                    abort_if($received->isGreaterThan((string) $line->quantity_ordered), 422, 'Nhận vượt PO.');
                    $trace = $this->stock->createRoll(array_replace($roll, ['warehouse_id' => $w->id, 'item_id' => $line->inventory_item_id, 'unit_cost' => (string) $line->unit_cost, 'disposition' => 'quarantine', 'source_type' => 'purchasing.receipt', 'source_id' => (string) $receipt]), $this->child($key, 'roll-'.$i.'-'.$j), $actor);
                    // Liabilities remain received_provisional; QC does not silently erase
                    // received goods or payable. A supplier return is a separate command.
                    $amount = $qty->multipliedBy((string) $line->unit_cost)->toScale(0, RoundingMode::HalfUp);
                    $public = (string) Str::uuid();
                    $id = DB::table('purchase_receipt_lines')->insertGetId(['public_id' => $public, 'receipt_id' => $receipt, 'order_line_id' => $line->id, 'quantity' => (string) $qty, 'quantity_returned' => '0', 'unit_cost' => $line->unit_cost, 'amount_minor' => (string) $amount, 'stock_operation_id' => $trace['receipt']['public_id'], 'batch_id' => $trace['batch_id'], 'batch_code' => $roll['lot_code'] ?? null, 'trace_unit_id' => $trace['id'], 'secondary_quantity' => $trace['secondary_received'], 'secondary_unit' => $trace['secondary_unit'], 'qc_status' => 'quarantine', 'created_at' => now(), 'updated_at' => now()]);
                    $receivedRolls[] = ['receipt_line_id' => $id, 'receipt_line_public_id' => $public, 'roll' => $trace];
                }
                DB::table('purchase_order_lines')->where('id', $line->id)->update(['quantity_received' => (string) $received, 'updated_at' => now()]);
            }
            $partial = DB::table('purchase_order_lines')->where('order_id', $o->id)->get()->contains(fn ($l) => $this->number($l->quantity_received)->isLessThan((string) $l->quantity_ordered));
            DB::table('purchase_orders')->where('id', $o->id)->update(['status' => $partial ? 'partially_received' : 'received', 'version' => $o->version + 1, 'updated_at' => now()]);

            return $this->orders->order($o->public_id) + ['received_rolls' => $receivedRolls];
        });
    }

    public function inspectReceipt(array $input, string $key, int $actor): array
    {
        return $this->command('receipt.production.inspect', 'purchasing.receipt.manage', $input, $key, $actor, function () use ($input, $key, $actor): array {
            $line = DB::table('purchase_receipt_lines')->where('id', $input['receipt_line_id'] ?? 0)->lockForUpdate()->first();
            abort_unless($line && $line->trace_unit_id, 404);
            $roll = DB::table('inv_trace_units')->find($line->trace_unit_id);
            $result = $this->stock->inspectRoll(array_replace($input, ['roll_id' => $roll->public_id]), $this->child($key, 'inspect'), $actor);
            DB::table('purchase_receipt_lines')->where('id', $line->id)->update(['qc_status' => $result['disposition'], 'updated_at' => now()]);

            return $result;
        });
    }

    public function returnRoll(array $input, string $key, int $actor): array
    {
        return $this->command('receipt.production.return', 'purchasing.return.manage', $input, $key, $actor, function () use ($input, $key, $actor): array {
            $v = validator($input, ['receipt_line_id' => 'required|integer', 'expected_version' => 'required|integer|min:1', 'quantity' => 'required', 'reason' => 'required|string|max:1000'])->validate();
            $line = DB::table('purchase_receipt_lines as l')->join('purchase_receipts as r', 'r.id', '=', 'l.receipt_id')->where('l.id', $v['receipt_line_id'])->lockForUpdate()->first(['l.*', 'r.order_id']);
            abort_unless($line && $line->trace_unit_id, 404);
            $o = DB::table('purchase_orders')->where('id', $line->order_id)->lockForUpdate()->first();
            abort_unless((int) $o->version === $v['expected_version'], 409);
            $this->warehouse($o->warehouse_id);
            $roll = DB::table('inv_trace_units')->where('id', $line->trace_unit_id)->lockForUpdate()->first();
            $qty = $this->number($v['quantity']);
            abort_unless($qty->isPositive(), 422);
            $returned = $this->number($line->quantity_returned)->plus($qty);
            abort_if($returned->isGreaterThan((string) $line->quantity), 422, 'Trả vượt receipt gốc.');
            $stock = $this->stock->returnReceipt(['roll_id' => $roll->public_id, 'expected_version' => $roll->version, 'quantity' => (string) $qty, 'reason' => $v['reason']], $this->child($key, 'withdraw'), $actor);
            $allocated = $this->number(DB::table('purchase_returns')->where('receipt_line_id', $line->id)->sum('amount_minor'));
            $amount = $this->number($line->amount_minor)->multipliedBy($returned)->dividedBy((string) $line->quantity, 0, RoundingMode::HalfUp)->minus($allocated);
            DB::table('purchase_returns')->insert(['public_id' => (string) Str::uuid(), 'order_id' => $o->id, 'receipt_line_id' => $line->id, 'quantity' => (string) $qty, 'amount_minor' => (string) $amount, 'actor_id' => $actor, 'stock_operation_id' => $stock['operation_id'], 'reason' => $v['reason'], 'created_at' => now(), 'updated_at' => now()]);
            DB::table('purchase_receipt_lines')->where('id', $line->id)->update(['quantity_returned' => (string) $returned, 'updated_at' => now()]);
            $poLine = DB::table('purchase_order_lines')->where('id', $line->order_line_id)->lockForUpdate()->first();
            DB::table('purchase_order_lines')->where('id', $poLine->id)->update(['quantity_returned' => (string) $this->number($poLine->quantity_returned)->plus($qty), 'updated_at' => now()]);
            DB::table('purchase_orders')->where('id', $o->id)->update(['version' => $o->version + 1, 'updated_at' => now()]);

            return $this->orders->order($o->public_id);
        });
    }

    private function command(string $action, string $permission, array $input, string $key, int $actor, callable $effect): array
    {
        abort_unless(preg_match('/^[A-Za-z0-9:_.-]{8,120}$/D', $key), 422);

        return DB::transaction(function () use ($action, $permission, $input, $key, $actor, $effect): array {
            $live = Admin::query()->whereKey($actor)->lockForUpdate()->first();
            abort_unless($live && $live->isAvailable() && ! $live->must_change_password && $live->canAccess($permission, 'global'), 403);
            app(PurchasingRuntimeState::class)->guard();
            abort_unless(app(ModuleCapabilityChecker::class)->has('purchasing', 'purchasing.production.requisitions.v1'), 503);
            if ($action === 'order.production.receive') {
                abort_unless(app(ModuleCapabilityChecker::class)->has('purchasing', 'purchasing.receipt_lines.identity.v1'), 503, 'Nâng Purchasing để nhận cuộn với định danh receipt line công khai.');
            }
            $inventoryPermission = str_contains($action, 'return') ? 'inventory.issue.manage' : 'inventory.receipt.manage';
            abort_unless($live->canAccess($inventoryPermission, 'global'), 403);
            $canonical = function ($v) use (&$canonical) {
                if (! is_array($v)) {
                    return $v;
                } if (! array_is_list($v)) {
                    ksort($v);
                }

                return array_map($canonical, $v);
            };
            $fingerprint = hash('sha256', json_encode([$action, $actor, $canonical($input)], JSON_THROW_ON_ERROR));
            $old = DB::table('purchase_commands')->where('command_key', $key)->lockForUpdate()->first();
            if ($old) {
                abort_unless(hash_equals($old->fingerprint, $fingerprint), 409);

                return json_decode($old->response, true, flags: JSON_THROW_ON_ERROR) + ['replayed' => true];
            }
            $result = $effect();
            DB::table('purchase_commands')->insert(['command_key' => $key, 'action' => $action, 'actor_id' => $actor, 'fingerprint' => $fingerprint, 'response' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('purchase_events')->insert(['public_id' => (string) Str::uuid(), 'action' => $action, 'actor_id' => $actor, 'payload' => json_encode(['command_key' => $key, 'resource_id' => $result['public_id'] ?? $result['id'] ?? null], JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return $result;
        }, 3);
    }

    private function warehouse(int $id): object
    {
        $w = DB::table('inv_warehouses')->where('id', $id)->where('production_managed', true)->where('is_active', true)->lockForUpdate()->first();
        abort_unless($w && $w->catalog_id && $w->legal_entity_ref, 422, 'Kho sản xuất chưa sẵn sàng.');

        return $w;
    }

    private function order(string $id, int $version): object
    {
        $o = DB::table('purchase_orders')->where('public_id', $id)->lockForUpdate()->first();
        abort_unless($o, 404);
        abort_unless((int) $o->version === $version, 409);

        return $o;
    }

    private function number(mixed $value): BigDecimal
    {
        if (is_float($value)) {
            $value = number_format($value, 6, '.', '');
        }
        abort_unless(preg_match('/^\d{1,18}(?:\.\d{1,6})?$/D', (string) $value), 422, 'Decimal không hợp lệ.');

        return BigDecimal::of((string) $value)->toScale(6);
    }

    private function child(string $key, string $operation): string
    {
        return 'purchase-production:'.hash('sha256', $key.':'.$operation);
    }
}
