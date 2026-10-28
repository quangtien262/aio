<?php

namespace Modules\Purchasing\Services;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\Admin;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Inventory\Services\InventoryManagedStockService;
use Modules\Merchandise\Services\MerchandiseService;

/** Additive Purchasing-owned return ledger for managed, restricted physical stock. */
final class PurchasingManagedStockService
{
    public function returnReceiptLine(Admin $actor, string $orderId, array $input, string $key): array
    {
        $v = validator($input, ['expected_version' => 'required|integer|min:1', 'receipt_line_id' => 'required|integer', 'quantity' => 'required', 'reason' => 'required|string|max:1000'])->validate();
        abort_unless(preg_match('/^[A-Za-z0-9._:-]{8,120}$/D', $key), 422);

        return DB::transaction(function () use ($actor, $orderId, $v, $key): array {
            $live = Admin::query()->whereKey($actor->id)->lockForUpdate()->first();
            abort_unless($live && $live->isAvailable() && ! $live->must_change_password && (int) $live->auth_version === (int) $actor->auth_version && $live->canAccess('purchasing.return.manage', 'global') && $live->canAccess('inventory.issue.manage', 'global'), 403);
            app(PurchasingRuntimeState::class)->guard();
            abort_unless(app(ModuleCapabilityChecker::class)->has('purchasing', 'purchasing.managed_returns.v1'), 503);
            $order = DB::table('purchase_orders')->where('public_id', $orderId)->lockForUpdate()->first();
            abort_unless($order, 404);
            app(InventoryManagedStockService::class)->authorizeCommand('managed.withdraw', ['warehouse_id' => $order->warehouse_id], $actor->id);
            $fingerprint = hash('sha256', json_encode([$actor->id, $orderId, $v], JSON_THROW_ON_ERROR));
            DB::table('purchase_commands')->insertOrIgnore(['command_key' => $key, 'action' => 'order.managed.return', 'actor_id' => $actor->id, 'fingerprint' => $fingerprint, 'created_at' => now(), 'updated_at' => now()]);
            $command = DB::table('purchase_commands')->where('command_key', $key)->lockForUpdate()->first();
            abort_unless(hash_equals($command->fingerprint, $fingerprint), 409, 'Key đã dùng cho ý định trả khác.');
            if ($command->response !== null) {
                return json_decode($command->response, true, flags: JSON_THROW_ON_ERROR);
            }
            abort_unless((int) $order->version === (int) $v['expected_version'], 409, 'PO đã thay đổi.');
            $warehouse = DB::table('inv_warehouses')->where('id', $order->warehouse_id)->lockForUpdate()->first();
            abort_unless($warehouse && $warehouse->legal_entity_ref === $order->legal_entity_ref && (int) $warehouse->catalog_id === (int) $order->catalog_id, 409, 'Ownership PO/kho đã đổi.');
            $receipt = DB::table('purchase_receipt_lines as l')->join('purchase_receipts as r', 'r.id', '=', 'l.receipt_id')->where('l.id', $v['receipt_line_id'])->where('r.order_id', $order->id)->select('l.*')->lockForUpdate()->first();
            abort_unless($receipt, 404);
            $line = DB::table('purchase_order_lines')->where('id', $receipt->order_line_id)->where('order_id', $order->id)->first();
            abort_unless($line, 404);
            $quantity = app(MerchandiseService::class)->convertQuantity($line->sku_id, (string) $v['quantity']);
            abort_unless(BigDecimal::of($quantity)->isPositive(), 422);
            $returned = BigDecimal::of((string) $receipt->quantity_returned)->plus($quantity);
            abort_if($returned->isGreaterThan((string) $receipt->quantity), 409, 'Trả vượt receipt gốc.');
            $operation = DB::table('inv_v2_operations')->where('public_id', $receipt->stock_operation_id)->first();
            $stockRef = $operation?->response ? json_decode($operation->response, true, flags: JSON_THROW_ON_ERROR) : [];
            abort_unless(! empty($stockRef['movement_id']), 409, 'Thiếu provenance receipt; đối soát trước khi trả.');
            $stock = app(InventoryManagedStockService::class)->withdrawRestricted(['warehouse_id' => $order->warehouse_id, 'item_id' => $line->inventory_item_id, 'batch_id' => $receipt->batch_id, 'quantity' => $quantity, 'purpose' => 'supplier_return', 'reason' => $v['reason'], 'source_movement_id' => $stockRef['movement_id']], 'purchase-return:'.hash('sha256', $key), $actor->id);
            $allocated = DB::table('purchase_returns')->where('receipt_line_id', $receipt->id)->sum('amount_minor');
            $amount = BigDecimal::of((string) $receipt->amount_minor)->multipliedBy($returned)->dividedBy((string) $receipt->quantity, 0, RoundingMode::HalfUp)->minus((string) $allocated);
            $public = (string) Str::uuid();
            DB::table('purchase_returns')->insert(['public_id' => $public, 'order_id' => $order->id, 'receipt_line_id' => $receipt->id, 'quantity' => $quantity, 'amount_minor' => (string) $amount, 'actor_id' => $actor->id, 'stock_operation_id' => $stock['public_id'], 'reason' => $v['reason'], 'created_at' => now(), 'updated_at' => now()]);
            DB::table('purchase_receipt_lines')->where('id', $receipt->id)->update(['quantity_returned' => (string) $returned->toScale(6), 'updated_at' => now()]);
            DB::table('purchase_order_lines')->where('id', $line->id)->update(['quantity_returned' => (string) BigDecimal::of((string) $line->quantity_returned)->plus($quantity)->toScale(6), 'updated_at' => now()]);
            DB::table('purchase_orders')->where('id', $order->id)->update(['version' => $order->version + 1, 'updated_at' => now()]);
            DB::table('purchase_events')->insert(['public_id' => (string) Str::uuid(), 'order_id' => $order->id, 'action' => 'order.returned', 'actor_id' => $actor->id, 'payload' => json_encode(['return_id' => $public, 'receipt_line_id' => $receipt->id], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            $result = app(PurchasingService::class)->order($order->public_id) + ['return_id' => $public];
            DB::table('purchase_commands')->where('id', $command->id)->update(['response' => json_encode($result, JSON_THROW_ON_ERROR), 'updated_at' => now()]);

            return $result;
        }, 3);
    }
}
