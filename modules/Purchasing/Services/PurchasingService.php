<?php

namespace Modules\Purchasing\Services;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\Admin;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Merchandise\Services\MerchandiseService;

class PurchasingService
{
    public function __construct(private readonly InventoryV2Service $inventory, private readonly MerchandiseService $merchandise) {}

    public function nativeMutation(Admin $actor, callable $callback): array
    {
        return DB::transaction(function () use ($actor, $callback): array {
            $live = Admin::query()->whereKey($actor->id)->lockForUpdate()->first();
            abort_unless($live && $live->is_active && $live->status === 'active' && ! $live->locked_at
                && (int) $live->auth_version === (int) $actor->auth_version, 403, 'Tài khoản hoặc phiên xác thực đã thay đổi.');
            app(PurchasingRuntimeState::class)->guard();

            return $callback();
        }, 3);
    }

    public function createSupplier(array $data, ?string $key = null, int $actor = 0): array
    {
        if ($key !== null) {
            return $this->command('supplier.create', $key, $actor, $data, fn (): array => $this->createSupplier($data));
        }

        return DB::transaction(function () use ($data): array {
            app(PurchasingRuntimeState::class)->guard();
            $v = Validator::make($data, ['code' => 'required|string|max:60|unique:purchase_suppliers,code', 'name' => 'required|string|max:255', 'phone' => 'nullable|string|max:40', 'email' => 'nullable|email|max:255', 'address' => 'nullable|string|max:4000', 'payment_terms_days' => 'sometimes|integer|between:0,365', 'is_active' => 'sometimes|boolean'])->validate();
            $id = DB::table('purchase_suppliers')->insertGetId($v + ['public_id' => (string) Str::uuid(), 'payment_terms_days' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

            return (array) DB::table('purchase_suppliers')->find($id);
        }, 3);
    }

    public function createOrder(array $data, string $key, int $actor): array
    {
        $v = Validator::make($data, ['supplier_id' => 'required|integer', 'warehouse_id' => 'required|integer', 'catalog_id' => 'required|integer', 'expected_at' => 'nullable|date', 'note' => 'nullable|string|max:4000', 'lines' => 'required|array|min:1|max:300', 'lines.*.sku_id' => 'required|integer', 'lines.*.quantity' => 'required', 'lines.*.unit_cost' => 'required'])->validate();

        return $this->command('order.create', $key, $actor, $v, function () use ($v, $actor): array {
            $this->inventoryReady();
            $warehouse = $this->warehouse((int) $v['warehouse_id'], (int) $v['catalog_id']);
            $supplier = DB::table('purchase_suppliers')->where('id', $v['supplier_id'])->where('is_active', true)->first();
            abort_unless($supplier, 422, 'Nhà cung cấp không hoạt động.');
            $public = (string) Str::uuid();
            $orderId = DB::table('purchase_orders')->insertGetId([
                'public_id' => $public, 'code' => 'PO-'.strtoupper(substr(str_replace('-', '', $public), 0, 16)),
                'supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id, 'catalog_id' => $v['catalog_id'], 'legal_entity_ref' => $warehouse->legal_entity_ref,
                'status' => 'draft', 'currency' => 'VND', 'created_by_admin_id' => $actor, 'expected_at' => $v['expected_at'] ?? null,
                'due_at' => now()->addDays($supplier->payment_terms_days)->toDateString(), 'note' => $v['note'] ?? null, 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $seen = [];
            foreach ($v['lines'] as $line) {
                $sku = DB::table('merch_skus')->where('id', $line['sku_id'])->where('catalog_id', $v['catalog_id'])->where('is_active', true)->first();
                abort_unless($sku, 422, 'SKU không thuộc danh mục được cấp cho kho.');
                if (isset($seen[$sku->id])) {
                    throw ValidationException::withMessages(['lines' => 'Một SKU chỉ xuất hiện một lần trong đơn mua.']);
                }
                $seen[$sku->id] = true;
                $quantity = $this->merchandise->convertQuantity($sku->id, (string) $line['quantity']);
                $cost = (string) $this->decimal((string) $line['unit_cost'], 'unit_cost', true);
                $itemId = $this->inventory->ensureItem($sku->id);
                DB::table('purchase_order_lines')->insert([
                    'public_id' => (string) Str::uuid(), 'order_id' => $orderId, 'sku_id' => $sku->id, 'inventory_item_id' => $itemId,
                    'sku_snapshot' => $sku->sku, 'name_snapshot' => $sku->name, 'unit_snapshot' => $sku->base_unit,
                    'quantity_ordered' => $quantity, 'quantity_received' => '0', 'quantity_returned' => '0', 'unit_cost' => $cost, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $this->event($orderId, 'order.created', $actor, ['order_id' => $public]);

            return $this->order($public);
        });
    }

    public function approveOrder(string $publicId, int $version, string $key, int $actor): array
    {
        return $this->command('order.approve', $key, $actor, compact('publicId', 'version'), function () use ($publicId, $version, $actor): array {
            $o = $this->lockOrder($publicId, $version);
            abort_unless($o->status === 'draft', 422, 'Chỉ duyệt đơn nháp.');
            abort_if((int) $o->created_by_admin_id === $actor, 403, 'Người lập không được tự duyệt đơn mua.');
            DB::table('purchase_orders')->where('id', $o->id)->update(['status' => 'approved', 'approved_by_admin_id' => $actor, 'approved_at' => now(), 'version' => $o->version + 1, 'updated_at' => now()]);
            $this->event($o->id, 'order.approved', $actor, ['version' => $version]);

            return $this->order($publicId);
        });
    }

    public function receiveOrder(string $publicId, array $data, string $key, int $actor): array
    {
        $v = Validator::make($data, ['expected_version' => 'required|integer|min:1', 'reference' => 'nullable|string|max:100', 'lines' => 'required|array|min:1|max:300', 'lines.*.order_line_id' => 'required|integer', 'lines.*.quantity' => 'required', 'lines.*.batch_code' => 'nullable|string|max:255', 'lines.*.expires_at' => 'nullable|date'])->validate();

        return $this->command('order.receive', $key, $actor, ['order' => $publicId] + $v, function () use ($publicId, $v, $key, $actor): array {
            $this->inventoryReady();
            $o = $this->lockOrder($publicId, $v['expected_version']);
            abort_unless(in_array($o->status, ['approved', 'partially_received'], true), 422, 'Đơn mua chưa được duyệt hoặc đã kết thúc.');
            $w = $this->warehouse($o->warehouse_id, $o->catalog_id);
            abort_unless($w->legal_entity_ref === $o->legal_entity_ref, 409, 'Chủ sở hữu kho đã thay đổi.');
            $receiptId = DB::table('purchase_receipts')->insertGetId(['public_id' => (string) Str::uuid(), 'order_id' => $o->id, 'actor_id' => $actor, 'reference' => $v['reference'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($v['lines'] as $index => $input) {
                $line = DB::table('purchase_order_lines')->where('id', $input['order_line_id'])->where('order_id', $o->id)->lockForUpdate()->first();
                abort_unless($line, 422, 'Dòng nhận không thuộc đơn mua.');
                $quantity = $this->merchandise->convertQuantity($line->sku_id, (string) $input['quantity']);
                $received = BigDecimal::of((string) $line->quantity_received)->plus($quantity);
                abort_if($received->isGreaterThan((string) $line->quantity_ordered), 422, 'Không nhận vượt số lượng đã đặt.');
                $stock = $this->inventory->receive([
                    'warehouse_id' => $o->warehouse_id, 'item_id' => $line->inventory_item_id, 'quantity' => $quantity, 'unit_cost' => (string) $line->unit_cost,
                    'source_id' => 'purchase-receipt:'.$receiptId.':'.$index, 'legal_entity_ref' => $o->legal_entity_ref,
                    'batch_code' => $input['batch_code'] ?? null, 'expires_at' => $input['expires_at'] ?? null,
                ], $this->childKey($key, 'receipt-'.$index), $actor);
                $batchId = isset($stock['batch_id']) ? $stock['batch_id'] : (($input['batch_code'] ?? null) ? DB::table('inv_batches')->where('item_id', $line->inventory_item_id)->where('batch_code', $input['batch_code'])->value('id') : null);
                $amount = (string) BigDecimal::of($quantity)->multipliedBy((string) $line->unit_cost)->toScale(0, RoundingMode::HalfUp);
                DB::table('purchase_receipt_lines')->insert(['receipt_id' => $receiptId, 'order_line_id' => $line->id, 'quantity' => $quantity, 'quantity_returned' => '0', 'unit_cost' => $line->unit_cost, 'amount_minor' => $amount, 'stock_operation_id' => $stock['public_id'], 'batch_id' => $batchId, 'batch_code' => $input['batch_code'] ?? null, 'expires_at' => $input['expires_at'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('purchase_order_lines')->where('id', $line->id)->update(['quantity_received' => (string) $received->toScale(6), 'updated_at' => now()]);
            }
            $remaining = DB::table('purchase_order_lines')->where('order_id', $o->id)->get()->contains(fn ($l) => BigDecimal::of((string) $l->quantity_received)->isLessThan((string) $l->quantity_ordered));
            DB::table('purchase_orders')->where('id', $o->id)->update(['status' => $remaining ? 'partially_received' : 'received', 'version' => $o->version + 1, 'updated_at' => now()]);
            $this->event($o->id, 'order.received', $actor, ['receipt_id' => $receiptId]);

            return $this->order($publicId);
        });
    }

    public function returnReceiptLine(string $publicId, array $data, string $key, int $actor): array
    {
        $v = Validator::make($data, ['expected_version' => 'required|integer|min:1', 'receipt_line_id' => 'required|integer', 'quantity' => 'required', 'reason' => 'required|string|max:1000'])->validate();

        return $this->command('order.return', $key, $actor, ['order' => $publicId] + $v, function () use ($publicId, $v, $key, $actor): array {
            $this->inventoryReady();
            $o = $this->lockOrder($publicId, $v['expected_version']);
            $warehouse = $this->warehouse($o->warehouse_id, $o->catalog_id);
            abort_unless($warehouse->legal_entity_ref === $o->legal_entity_ref, 409, 'Chủ sở hữu kho đã thay đổi.');
            $rl = DB::table('purchase_receipt_lines')->join('purchase_receipts', 'purchase_receipts.id', '=', 'purchase_receipt_lines.receipt_id')->where('purchase_receipt_lines.id', $v['receipt_line_id'])->where('purchase_receipts.order_id', $o->id)->select('purchase_receipt_lines.*')->lockForUpdate()->first();
            abort_unless($rl, 422, 'Phiếu nhận không thuộc đơn mua.');
            $line = DB::table('purchase_order_lines')->where('id', $rl->order_line_id)->lockForUpdate()->first();
            $qty = $this->merchandise->convertQuantity($line->sku_id, (string) $v['quantity']);
            $returned = BigDecimal::of((string) $rl->quantity_returned)->plus($qty);
            abort_if($returned->isGreaterThan((string) $rl->quantity), 422, 'Lượng trả vượt lượng còn được trả của phiếu nhận.');
            $reservation = $this->inventory->reserve($o->warehouse_id, $line->inventory_item_id, $qty, 'purchase-return:'.$key, $this->childKey($key, 'reserve'), $actor, $rl->batch_id);
            $stock = $this->inventory->commitReservation($reservation['public_id'], $this->childKey($key, 'commit'), $actor);
            $allocatedSoFar = (string) DB::table('purchase_returns')->where('receipt_line_id', $rl->id)->sum('amount_minor');
            $minor = (string) BigDecimal::of((string) $rl->amount_minor)->multipliedBy($returned)->dividedBy((string) $rl->quantity, 0, RoundingMode::HalfUp)->minus($allocatedSoFar);
            DB::table('purchase_returns')->insert(['public_id' => (string) Str::uuid(), 'order_id' => $o->id, 'receipt_line_id' => $rl->id, 'quantity' => $qty, 'amount_minor' => $minor, 'actor_id' => $actor, 'stock_operation_id' => $stock['public_id'], 'reason' => $v['reason'], 'created_at' => now(), 'updated_at' => now()]);
            DB::table('purchase_receipt_lines')->where('id', $rl->id)->update(['quantity_returned' => (string) $returned->toScale(6), 'updated_at' => now()]);
            DB::table('purchase_order_lines')->where('id', $line->id)->update(['quantity_returned' => (string) BigDecimal::of((string) $line->quantity_returned)->plus($qty)->toScale(6), 'updated_at' => now()]);
            DB::table('purchase_orders')->where('id', $o->id)->update(['version' => $o->version + 1, 'updated_at' => now()]);
            $this->event($o->id, 'order.returned', $actor, ['receipt_line_id' => $rl->id, 'quantity' => $qty]);

            return $this->order($publicId);
        });
    }

    public function payOrder(string $publicId, array $data, string $key, int $actor): array
    {
        $v = Validator::make($data, ['expected_version' => 'required|integer|min:1', 'amount_minor' => ['required', 'regex:/^[1-9]\d{0,14}$/'], 'method' => 'required|in:cash,bank', 'reference' => 'nullable|string|max:120'])->validate();

        return $this->command('order.pay', $key, $actor, ['order' => $publicId] + $v, function () use ($publicId, $v, $actor): array {
            $o = $this->lockOrder($publicId, $v['expected_version']);
            $summary = $this->order($publicId)['financials'];
            abort_if(BigDecimal::of((string) $v['amount_minor'])->isGreaterThan($summary['payable_minor']), 422, 'Thanh toán vượt công nợ còn lại của hàng đã nhận.');
            DB::table('purchase_payments')->insert(['public_id' => (string) Str::uuid(), 'order_id' => $o->id, 'amount_minor' => (string) $v['amount_minor'], 'method' => $v['method'], 'reference' => $v['reference'] ?? null, 'actor_id' => $actor, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('purchase_orders')->where('id', $o->id)->update(['version' => $o->version + 1, 'updated_at' => now()]);
            $this->event($o->id, 'order.paid', $actor, ['amount_minor' => (string) $v['amount_minor'], 'method' => $v['method']]);

            return $this->order($publicId);
        });
    }

    public function closeOrder(string $publicId, int $version, string $key, int $actor): array
    {
        return $this->command('order.close', $key, $actor, compact('publicId', 'version'), function () use ($publicId, $version, $actor): array {
            $o = $this->lockOrder($publicId, $version);
            abort_unless(in_array($o->status, ['draft', 'approved', 'partially_received', 'received'], true), 422, 'Đơn đã kết thúc.');
            $received = DB::table('purchase_receipts')->where('order_id', $o->id)->exists();
            DB::table('purchase_orders')->where('id', $o->id)->update(['status' => $received ? 'closed' : 'cancelled', 'version' => $o->version + 1, 'updated_at' => now()]);
            $this->event($o->id, 'order.closed', $actor, ['remaining_cancelled' => true]);

            return $this->order($publicId);
        });
    }

    public function order(string $publicId): array
    {
        $o = DB::table('purchase_orders')->where('public_id', $publicId)->first();
        abort_unless($o, 404);
        $lines = DB::table('purchase_order_lines')->where('order_id', $o->id)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $receivedMinor = BigDecimal::of((string) DB::table('purchase_receipt_lines')->join('purchase_receipts', 'purchase_receipts.id', '=', 'purchase_receipt_lines.receipt_id')->where('purchase_receipts.order_id', $o->id)->sum('purchase_receipt_lines.amount_minor'));
        $returned = BigDecimal::of((string) DB::table('purchase_returns')->where('order_id', $o->id)->sum('amount_minor'));
        $paid = BigDecimal::of((string) DB::table('purchase_payments')->where('order_id', $o->id)->sum('amount_minor'));

        return (array) $o + ['lines' => $lines,
            'receipts' => DB::table('purchase_receipts')->where('order_id', $o->id)->orderBy('id')->get()->map(function ($r): array {
                return (array) $r + ['lines' => DB::table('purchase_receipt_lines')->where('receipt_id', $r->id)->get()->map(fn ($l) => (array) $l)->all()];
            })->all(),
            'payments' => DB::table('purchase_payments')->where('order_id', $o->id)->get()->map(fn ($r) => (array) $r)->all(),
            'returns' => DB::table('purchase_returns')->where('order_id', $o->id)->get()->map(fn ($r) => (array) $r)->all(),
            'financials' => ['received_minor' => (string) $receivedMinor, 'returned_minor' => (string) $returned, 'paid_minor' => (string) $paid, 'payable_minor' => (string) BigDecimal::max('0', $receivedMinor->minus($returned)->minus($paid)), 'supplier_credit_minor' => (string) BigDecimal::max('0', $paid->plus($returned)->minus($receivedMinor)), 'basis' => 'received_provisional'],
        ];
    }

    public function commandStatus(string $key, int $actor): array
    {
        $command = DB::table('purchase_commands')->where('command_key', $key)->where('actor_id', $actor)->first();

        return $command && $command->response !== null
            ? ['status' => 'committed', 'resource' => json_decode($command->response, true, flags: JSON_THROW_ON_ERROR)]
            : ['status' => 'unknown'];
    }

    private function lockOrder(string $id, int $version): object
    {
        $o = DB::table('purchase_orders')->where('public_id', $id)->lockForUpdate()->first();
        abort_unless($o, 404);
        abort_unless((int) $o->version === $version, 409, 'Phiên bản đơn mua đã thay đổi.');

        return $o;
    }

    private function warehouse(int $warehouseId, int $catalogId): object
    {
        $w = DB::table('inv_warehouses')->where('id', $warehouseId)->lockForUpdate()->first();
        abort_unless($w && $w->is_active && (int) $w->catalog_id === $catalogId && filled($w->legal_entity_ref), 422, 'Kho/danh mục/chủ sở hữu chưa được cấu hình đúng.');

        return $w;
    }

    private function inventoryReady(): void
    {
        abort_unless(app(ModuleCapabilityChecker::class)->has('inventory', 'inventory.movements.post.v2'), 503, 'Inventory v2 chưa sẵn sàng.');
    }

    private function event(?int $orderId, string $action, int $actor, array $payload): void
    {
        DB::table('purchase_events')->insert(['public_id' => (string) Str::uuid(), 'order_id' => $orderId, 'action' => $action, 'actor_id' => $actor, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    private function command(string $action, string $key, int $actor, array $payload, callable $callback): array
    {
        if ($actor <= 0 || ! preg_match('/^[A-Za-z0-9._:-]{8,120}$/', $key)) {
            throw ValidationException::withMessages(['command' => 'Actor và Idempotency-Key không hợp lệ.']);
        }
        $canonical = function ($v) use (&$canonical) {
            if (! is_array($v)) {
                return $v;
            } if (! array_is_list($v)) {
                ksort($v);
            }

            return array_map($canonical, $v);
        };
        $fingerprint = hash('sha256', json_encode([$action, $actor, $canonical($payload)], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($action, $key, $actor, $fingerprint, $callback): array {
            $live = Admin::query()->whereKey($actor)->lockForUpdate()->first();
            abort_unless($live && $live->is_active && $live->status === 'active' && ! $live->locked_at, 403, 'Tài khoản không hoạt động.');
            app(PurchasingRuntimeState::class)->guard();
            abort_unless(app(ModuleCapabilityChecker::class)->enabled('purchasing'), 503, 'Purchasing đang dừng hoặc chưa được bật.');
            $permission = match ($action) {
                'supplier.create' => 'purchasing.supplier.manage',
                'order.create' => 'purchasing.order.create',
                'order.approve', 'order.close' => 'purchasing.order.approve',
                'order.receive' => 'purchasing.receipt.manage',
                'order.return' => 'purchasing.return.manage',
                'order.pay' => 'purchasing.payment.manage',
                default => abort(403),
            };
            abort_unless($live->canAccess($permission, 'global'), 403, 'Quyền mua hàng toàn cục đã bị thu hồi.');
            $inventoryPermission = match ($action) {
                'order.create', 'order.receive' => 'inventory.receipt.manage',
                'order.return' => 'inventory.issue.manage',
                default => null,
            };
            abort_if($inventoryPermission && ! $live->canAccess($inventoryPermission, 'global'), 403, 'Cần quyền Kho toàn cục cho nghiệp vụ này.');
            DB::table('purchase_commands')->insertOrIgnore(['command_key' => $key, 'action' => $action, 'actor_id' => $actor, 'fingerprint' => $fingerprint, 'created_at' => now(), 'updated_at' => now()]);
            $cmd = DB::table('purchase_commands')->where('command_key', $key)->lockForUpdate()->first();
            abort_unless($cmd && hash_equals($cmd->fingerprint, $fingerprint), 409, 'Idempotency-Key đã dùng cho ý định hoặc người dùng khác.');
            if ($cmd->response !== null) {
                return json_decode($cmd->response, true, flags: JSON_THROW_ON_ERROR);
            }
            $result = $callback();
            DB::table('purchase_commands')->where('id', $cmd->id)->update(['response' => json_encode($result, JSON_THROW_ON_ERROR), 'updated_at' => now()]);

            return $result;
        }, 3);
    }

    private function childKey(string $key, string $operation): string
    {
        return 'purchase:'.hash('sha256', $key.':'.$operation);
    }

    private function decimal(string $value, string $field, bool $zero = false): BigDecimal
    {
        try {
            if (! preg_match('/^\d{1,15}(?:\.\d{1,6})?$/', $value)) {
                throw new \InvalidArgumentException;
            }
            $d = BigDecimal::of($value)->toScale(6, RoundingMode::Unnecessary);
            if ($zero ? $d->isNegative() : $d->isLessThanOrEqualTo('0')) {
                throw new \InvalidArgumentException;
            }

            return $d;
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'Số thập phân không hợp lệ.']);
        }
    }
}
