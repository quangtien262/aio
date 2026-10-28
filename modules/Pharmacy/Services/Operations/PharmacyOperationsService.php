<?php

namespace Modules\Pharmacy\Services\Operations;

use App\Models\Admin;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Services\InventoryManagedStockService;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Pharmacy\Services\Access\PharmacyAccessService;
use Modules\Pharmacy\Services\Commands\PharmacyCommandService;
use Modules\Pharmacy\Services\PharmacyData as D;

final class PharmacyOperationsService
{
    public function dispatch(Admin $actor, string $action, array $input, string $key): array
    {
        $permission = match ($action) {
            'receipt.create', 'receipt.inspect' => 'pharmacy.receipt.manage',
            'recall.create', 'recall.confirm' => 'pharmacy.recall.manage',
            'transfer.create', 'transfer.approve', 'transfer.dispatch', 'transfer.receive' => 'pharmacy.transfer.manage',
            'stocktake.create', 'stocktake.post' => 'pharmacy.stocktake.manage',
            default => 'pharmacy.quality.manage',
        };

        return app(PharmacyCommandService::class)->run($actor, $action, $input, $key,
            fn ($live) => ['pharmacy' => app(PharmacyAccessService::class)->pharmacy($live, $input['pharmacy_id'] ?? '', $permission, true), 'permission' => $permission],
            function ($live, $scope) use ($action, $input, $key) {
                $p = $scope['pharmacy'];

                return match ($action) {
                    'receipt.create' => $this->receive($live, $p, $input, $key), 'receipt.inspect' => $this->inspect($live, $p, $input, $key),
                    'quality.hold' => $this->hold($live, $p, $input, $key), 'quality.release' => $this->release($live, $p, $input, $key),
                    'quality.disposition' => $this->disposition($live, $p, $input, $key), 'quality.destroy', 'quality.supplier-return' => $this->withdraw($live, $p, $input, $key, $action),
                    'recall.create' => $this->recall($live, $p, $input, $key), 'recall.confirm' => $this->confirmRecall($live, $p, $input),
                    'temperature.record' => $this->temperature($live, $p, $input),
                    'transfer.create' => $this->transfer($live, $p, $input, $key), 'transfer.approve', 'transfer.dispatch' => $this->advanceTransfer($live, $p, $input, $key, $action), 'transfer.receive' => $this->receiveTransfer($live, $p, $input, $key),
                    'stocktake.create' => $this->stocktake($live, $p, $input, $key), 'stocktake.post' => $this->postStocktake($live, $p, $input, $key),
                    default => abort(422, 'Nghiệp vụ kho/chất lượng không hợp lệ.'),
                };
            });
    }

    private function within(Admin $actor, object $p, string $action, callable $effect, array $metadata = []): mixed
    {
        return app(InventoryManagedStockService::class)->withinOwner('pharmacy', $p->public_id, $actor->id, $action, $effect, $metadata);
    }

    private function product(object $p, mixed $id): object
    {
        return D::row('ph_products', (string) $id, $p->chain_id);
    }

    private function batch(object $p, int $id): array
    {
        $batch = DB::table('inv_batches')->where('id', $id)->first();
        abort_unless($batch, 404);
        $product = DB::table('ph_products')->where('chain_id', $p->chain_id)->where('inventory_item_id', $batch->item_id)->first();
        abort_unless($product, 404);

        return [$batch, $product];
    }

    private function receive(Admin $actor, object $p, array $input, string $key): array
    {
        $v = validator($input, ['product_id' => 'required|uuid', 'quantity' => 'required', 'unit_cost' => 'required', 'batch_code' => 'required|string|max:150', 'expires_at' => 'required|date', 'reference' => 'nullable|string|max:150', 'supplier_reference' => 'nullable|string|max:150'])->validate();
        $product = $this->product($p, $v['product_id']);
        $quantity = (string) D::decimal($v['quantity']);
        $cost = (string) D::decimal($v['unit_cost'], true);
        $stock = $this->within($actor, $p, 'receipt.create', fn () => app(InventoryV2Service::class)->receive(['warehouse_id' => $p->warehouse_id, 'item_id' => $product->inventory_item_id, 'quantity' => $quantity, 'unit_cost' => $cost, 'batch_code' => $v['batch_code'], 'expires_at' => $v['expires_at'], 'disposition' => 'quarantine', 'source_id' => 'pharmacy-receipt:'.$key], $key.':stock', $actor->id));
        $receipt = D::insert('ph_receipts', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'product_id' => $product->id, 'batch_id' => $stock['batch_id'], 'quantity' => $quantity, 'unit_cost' => $cost, 'stock_operation_id' => $stock['public_id'], 'reference' => $v['reference'] ?? null, 'supplier_reference' => $v['supplier_reference'] ?? null, 'actor_id' => $actor->id, 'status' => 'quarantine']);

        return D::resource($receipt);
    }

    private function inspect(Admin $actor, object $p, array $input, string $key): array
    {
        $v = validator($input, ['receipt_id' => 'required|uuid', 'expected_version' => 'required|integer', 'decision' => 'required|in:release,reject', 'reason' => 'required|string|max:1000'])->validate();
        app(PharmacyAccessService::class)->professional($actor, $p, 'review', true);
        $row = D::row('ph_receipts', $v['receipt_id'], $p->chain_id, $p->id, true);
        abort_unless($row->status === 'quarantine', 409, 'Phiếu đã được kiểm.');
        $product = D::row('ph_products', (int) $row->product_id, $p->chain_id);
        $state = $v['decision'] === 'release' ? 'accepted_pending_release' : 'rejected';
        $row = D::update('ph_receipts', $row, ['status' => $state, 'inspected_by' => $actor->id, 'inspected_at' => now(), 'inspection_reason' => $v['reason']], $v['expected_version']);
        $pending = DB::table('ph_receipts')->where('pharmacy_id', $p->id)->where('batch_id', $row->batch_id)->whereIn('status', ['quarantine', 'rejected'])->exists();
        if ($v['decision'] === 'reject' || ! $pending) {
            $this->within($actor, $p, 'receipt.inspect', fn () => app(InventoryManagedStockService::class)->changeDisposition(['warehouse_id' => $p->warehouse_id, 'item_id' => $product->inventory_item_id, 'batch_id' => $row->batch_id, 'disposition' => $v['decision'] === 'release' ? 'saleable' : 'rejected', 'reason' => $v['reason']], $key.':inspection', $actor->id));
            if ($v['decision'] === 'release') {
                DB::table('ph_receipts')->where('pharmacy_id', $p->id)->where('batch_id', $row->batch_id)->where('status', 'accepted_pending_release')->update(['status' => 'released', 'updated_at' => now()]);
            }
        }

        return D::resource(DB::table('ph_receipts')->find($row->id));
    }

    private function hold(Admin $actor, object $p, array $input, string $key): array
    {
        $v = validator($input, ['batch_id' => 'required|integer', 'reason' => 'required|string|max:1000', 'reason_code' => 'sometimes|string|max:80'])->validate();
        [$batch, $product] = $this->batch($p, $v['batch_id']);
        $hold = $this->within($actor, $p, 'quality.hold', fn () => app(InventoryManagedStockService::class)->placeHold(['warehouse_id' => $p->warehouse_id, 'item_id' => $product->inventory_item_id, 'batch_id' => $batch->id, 'reason_code' => $v['reason_code'] ?? 'quality', 'reason' => $v['reason']], $key.':hold', $actor->id));
        $row = D::insert('ph_quality_cases', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'product_id' => $product->id, 'batch_id' => $batch->id, 'kind' => 'hold', 'reason' => $v['reason'], 'hold_id' => $hold['public_id'], 'actor_id' => $actor->id]);

        return D::resource($row) + ['hold' => $hold];
    }

    private function release(Admin $actor, object $p, array $input, string $key): array
    {
        $v = validator($input, ['hold_id' => 'required|uuid', 'expected_version' => 'required|integer', 'reason' => 'required|string|max:1000'])->validate();
        $hold = DB::table('inv_stock_holds')->where('public_id', $v['hold_id'])->where('warehouse_id', $p->warehouse_id)->lockForUpdate()->first();
        abort_unless($hold, 404);
        abort_if($hold->reason_code === 'recall', 409, 'Hold thu hồi cần hoàn tất hồ sơ thu hồi, không được giải phóng thủ công.');

        return $this->within($actor, $p, 'quality.release', fn () => app(InventoryManagedStockService::class)->releaseHold($v['hold_id'], $v['expected_version'], $v['reason'], $key.':release', $actor->id));
    }

    private function disposition(Admin $actor, object $p, array $input, string $key): array
    {
        [$batch, $product] = $this->batch($p, (int) ($input['batch_id'] ?? 0));

        return $this->within($actor, $p, 'quality.disposition', fn () => app(InventoryManagedStockService::class)->changeDisposition(['warehouse_id' => $p->warehouse_id, 'item_id' => $product->inventory_item_id, 'batch_id' => $batch->id, 'disposition' => $input['disposition'] ?? '', 'reason' => $input['reason'] ?? ''], $key.':disposition', $actor->id));
    }

    private function withdraw(Admin $actor, object $p, array $input, string $key, string $action): array
    {
        [$batch, $product] = $this->batch($p, (int) ($input['batch_id'] ?? 0));
        if ($action === 'quality.supplier-return') {
            $source = $input['source_movement_id'] ?? null;
            abort_if(DB::table('ph_receipts')->where('pharmacy_id', $p->id)->where('batch_id', $batch->id)->whereNotNull('purchase_receipt_id')->when($source !== null, fn ($q) => $q->where('source_movement_id', $source))->exists(), 409, 'Hàng nhận theo PO phải trả bằng luồng Mua hàng để đối soát công nợ và số đã nhận.');
        }

        return $this->within($actor, $p, 'quality.destroy', fn () => app(InventoryManagedStockService::class)->withdrawRestricted(['warehouse_id' => $p->warehouse_id, 'item_id' => $product->inventory_item_id, 'batch_id' => $batch->id, 'quantity' => (string) D::decimal($input['quantity'] ?? null), 'purpose' => $action === 'quality.destroy' ? 'destruction' : 'supplier_return', 'reason' => $input['reason'] ?? '', 'source_movement_id' => $input['source_movement_id'] ?? null], $key.':withdraw', $actor->id));
    }

    private function recall(Admin $actor, object $p, array $input, string $key): array
    {
        $v = validator($input, ['batch_id' => 'required|integer', 'reference' => 'required|string|max:100', 'reason' => 'required|string|max:1000'])->validate();
        app(PharmacyAccessService::class)->chain($actor, (int) $p->chain_id, 'pharmacy.recall.manage', true);
        D::row('ph_chains', (int) $p->chain_id, lock: true);
        [$batch, $product] = $this->batch($p, $v['batch_id']);
        $recall = D::insert('ph_recalls', ['chain_id' => $p->chain_id, 'product_id' => $product->id, 'batch_id' => $batch->id, 'reference' => $v['reference'], 'reason' => $v['reason'], 'actor_id' => $actor->id]);
        foreach (DB::table('ph_pharmacies')->where('chain_id', $p->chain_id)->where('status', 'active')->orderBy('warehouse_id')->get() as $target) {
            $hold = $this->within($actor, $target, 'recall.create', fn () => app(InventoryManagedStockService::class)->placeHold(['warehouse_id' => $target->warehouse_id, 'item_id' => $product->inventory_item_id, 'batch_id' => $batch->id, 'reason_code' => 'recall', 'reason' => $v['reason'], 'source_ref' => $recall->public_id], $key.':'.$target->id, $actor->id));
            D::insert('ph_recall_tasks', ['chain_id' => $p->chain_id, 'pharmacy_id' => $target->id, 'recall_id' => $recall->id, 'hold_id' => $hold['public_id'], 'status' => 'pending']);
        }
        app(PharmacyCommandService::class)->emit(['chain' => D::row('ph_chains', (int) $p->chain_id)], 'pharmacy.recall.created', 'recall', $recall->public_id, 1);

        return D::resource($recall);
    }

    private function confirmRecall(Admin $actor, object $p, array $input): array
    {
        $task = D::row('ph_recall_tasks', (string) ($input['task_id'] ?? ''), $p->chain_id, $p->id, true);
        app(PharmacyAccessService::class)->professional($actor, $p, 'review', true);

        return D::resource(D::update('ph_recall_tasks', $task, ['status' => 'isolated', 'isolated_quantity' => (string) D::decimal($input['quantity'] ?? '0', true), 'confirmed_by' => $actor->id, 'confirmed_at' => now()], $input['expected_version'] ?? null));
    }

    private function temperature(Admin $actor, object $p, array $input): array
    {
        $v = validator($input, ['zone' => 'required|string|max:100', 'temperature' => 'required|numeric|between:-100,100', 'humidity' => 'nullable|numeric|between:0,100', 'measured_at' => 'required|date|before_or_equal:now'])->validate();

        return D::resource(D::insert('ph_temperature_readings', $v + ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'actor_id' => $actor->id, 'source' => 'manual']));
    }

    private function transfer(Admin $actor, object $p, array $input, string $key): array
    {
        $v = validator($input, ['destination_pharmacy_id' => 'required|uuid', 'reason' => 'required|string|max:1000', 'lines' => 'required|array|min:1|max:100', 'lines.*.product_id' => 'required|uuid', 'lines.*.quantity' => 'required'])->validate();
        $destination = app(PharmacyAccessService::class)->pharmacy($actor, $v['destination_pharmacy_id'], 'pharmacy.transfer.manage', true);
        abort_unless($destination->id !== $p->id && (int) $destination->chain_id === (int) $p->chain_id && $destination->legal_entity_ref === $p->legal_entity_ref, 422, 'Điều chuyển thường chỉ dùng trong cùng chuỗi và pháp nhân.');
        $lines = array_map(function ($line) use ($p): array {
            $product = $this->product($p, $line['product_id']);

            return ['item_id' => $product->inventory_item_id, 'quantity' => (string) D::decimal($line['quantity'])] + (isset($line['batch_id']) ? ['batch_id' => (int) $line['batch_id']] : []);
        }, $v['lines']);

        return D::resource(D::insert('ph_transfers', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'destination_pharmacy_id' => $destination->id, 'lines' => D::encode($lines), 'reason' => $v['reason'], 'actor_id' => $actor->id, 'status' => 'draft']));
    }

    private function advanceTransfer(Admin $actor, object $p, array $input, string $key, string $action): array
    {
        $row = D::row('ph_transfers', (string) ($input['transfer_id'] ?? ''), $p->chain_id, $p->id, true);
        abort_unless((int) ($input['expected_version'] ?? 0) === (int) $row->version, 409);
        $destination = app(PharmacyAccessService::class)->pharmacy($actor, (int) $row->destination_pharmacy_id, 'pharmacy.transfer.manage', true);
        if ($action === 'transfer.approve') {
            abort_unless($row->status === 'draft', 409, 'Phiếu không còn ở trạng thái nháp.');
            abort_if((int) $row->actor_id === (int) $actor->id, 403, 'Người lập không tự duyệt điều chuyển.');

            return D::resource(D::update('ph_transfers', $row, ['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()], $input['expected_version']));
        }
        abort_unless($row->status === 'approved', 409, 'Cần duyệt phiếu trước khi xuất.');
        abort_unless($destination->legal_entity_ref === $p->legal_entity_ref, 409, 'Pháp nhân kho đã thay đổi.');
        // Approval is checked live, including revocation of the approver's native grant.
        $approver = Admin::query()->findOrFail($row->approved_by);
        app(PharmacyAccessService::class)->pharmacy($approver, (int) $p->id, 'pharmacy.transfer.manage', true);
        app(PharmacyAccessService::class)->pharmacy($approver, (int) $destination->id, 'pharmacy.transfer.manage', true);
        $result = $this->within($actor, $p, 'transfer.dispatch', fn () => app(InventoryV2Service::class)->dispatchTransfer(['source_warehouse_id' => $p->warehouse_id, 'destination_warehouse_id' => $destination->warehouse_id, 'lines' => D::json($row->lines)], $key.':transfer', $actor->id), ['authorized_scopes' => [$p->public_id, $destination->public_id]]);

        return D::resource(D::update('ph_transfers', $row, ['status' => 'dispatched', 'inventory_transfer_id' => $result['public_id'], 'lines' => D::encode($result['lines']), 'dispatched_by' => $actor->id], $input['expected_version']));
    }

    private function receiveTransfer(Admin $actor, object $p, array $input, string $key): array
    {
        $row = D::row('ph_transfers', (string) ($input['transfer_id'] ?? ''), $p->chain_id, lock: true);
        abort_unless((int) $row->destination_pharmacy_id === (int) $p->id, 403);
        abort_unless((int) ($input['expected_version'] ?? 0) === (int) $row->version, 409);
        $lines = $input['lines'] ?? array_map(fn ($line) => ['line_id' => $line['id'], 'quantity' => (string) BigDecimal::of($line['quantity'])->minus($line['received_quantity'] ?? '0'), 'disposition' => 'quarantine'], D::json($row->lines));
        foreach ($lines as &$line) {
            $line['disposition'] = 'quarantine';
        } unset($line);
        $inventoryVersion = DB::table('inv_transfers')->where('public_id', $row->inventory_transfer_id)->value('version');
        $result = $this->within($actor, $p, 'transfer.receive', fn () => app(InventoryV2Service::class)->receiveTransfer($row->inventory_transfer_id, ['lines' => $lines, 'expected_version' => $inventoryVersion], $key.':receive', $actor->id));
        foreach (array_values($lines) as $index => $received) {
            $line = collect($result['lines'])->firstWhere('id', $received['line_id']);
            $stock = D::json(DB::table('inv_v2_operations')->where('idempotency_key', $key.':receive:line:'.$index)->value('response'));
            $product = DB::table('ph_products')->where('chain_id', $p->chain_id)->where('inventory_item_id', $line['item_id'])->first();
            D::insert('ph_receipts', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'product_id' => $product->id, 'batch_id' => $line['batch_id'], 'quantity' => (string) D::decimal($received['quantity']), 'unit_cost' => $line['unit_cost'], 'stock_operation_id' => $stock['public_id'], 'reference' => $row->public_id, 'supplier_reference' => 'transfer:'.$row->public_id, 'actor_id' => $actor->id, 'status' => 'quarantine']);
        }

        return D::resource(D::update('ph_transfers', $row, ['status' => $result['status'], 'lines' => D::encode($result['lines'])], $input['expected_version']));
    }

    private function stocktake(Admin $actor, object $p, array $input, string $key): array
    {
        $v = validator($input, ['reason' => 'required|string|max:1000', 'zone' => 'nullable|string|max:100'])->validate();
        $result = $this->within($actor, $p, 'stocktake.create', fn () => app(InventoryV2Service::class)->beginStocktake($p->warehouse_id, $key.':count', $actor->id));

        return D::resource(D::insert('ph_stocktakes', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'inventory_stocktake_id' => $result['public_id'], 'reason' => $v['reason'], 'zone' => $v['zone'] ?? null, 'actor_id' => $actor->id, 'status' => 'counting']));
    }

    private function postStocktake(Admin $actor, object $p, array $input, string $key): array
    {
        $row = D::row('ph_stocktakes', (string) ($input['stocktake_id'] ?? ''), $p->chain_id, $p->id, true);
        app(PharmacyAccessService::class)->professional($actor, $p, 'review', true);
        $counts = $input['counts'] ?? [];
        abort_unless($counts !== [], 422, 'Cần đủ dòng kiểm kê theo balance_id và số đếm.');
        $result = $this->within($actor, $p, 'stocktake.post', fn () => app(InventoryV2Service::class)->postStocktake($row->inventory_stocktake_id, $counts, $key.':post', $actor->id));

        return D::resource(D::update('ph_stocktakes', $row, ['status' => $result['status']], $input['expected_version'] ?? null));
    }
}
