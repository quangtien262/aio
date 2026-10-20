<?php

namespace Modules\RetailPos\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\RetailPos\Services\RetailService;

class RetailController
{
    public function __construct(private readonly RetailService $retail, private readonly InventoryV2Service $inventory) {}

    public function read(Request $request): JsonResponse
    {
        $actor = $request->user('admin');
        $kind = (string) $request->route('retail_read');
        $data = match ($kind) {
            'bootstrap','branches' => $kind === 'bootstrap' ? $this->retail->bootstrap($actor) : $this->retail->bootstrap($actor)['branches'],
            'products' => $this->retail->products($actor, (string) $request->query('branch_id'), substr((string) $request->query('query', ''), 0, 150), $request->boolean('include_catalog')),
            'customer-context' => $this->retail->customerContext($actor, (string) $request->query('branch_id')),
            'command' => $this->retail->commandStatus($actor, (string) $request->route('resource')),
            'settings' => ['installed_version' => '0.1.0-dev.2', 'currency' => 'VND', 'stock_authority' => 'inventory', 'offline' => ['enabled' => false, 'reason' => 'Gateway chưa được nghiệm thu'], 'payment_provider_connected' => false],
            'transfers','stocktakes' => $this->warehouseReads($request, $kind),
            default => $this->retail->read($actor, $kind, (string) $request->query('branch_id'), $request->query()),
        };

        return response()->json(['data' => $this->retail->sanitize($actor, $data)])->header('Cache-Control', 'no-store');
    }

    public function mutate(Request $request): JsonResponse
    {
        $actor = $request->user('admin');
        $operation = (string) $request->route('retail_command');
        $data = $request->all();
        $id = (string) $request->route('resource', '');
        $key = (string) $request->header('Idempotency-Key', '');
        $result = $this->retail->command($actor, $key, $operation, ['resource' => $id, 'data' => $data], fn ($live) => match ($operation) {
            'setup' => $this->retail->setup($live, $data),
            'branch.create' => $this->retail->createBranch($live, $data),
            'terminal.create' => $this->retail->createTerminal($live, $data),
            'product.create' => $this->retail->saveProduct($live, $data),
            'assortment.save' => $this->retail->assortment($live, $id !== '' ? array_replace($data, ['sku_id' => $id]) : $data),
            'receipt.create' => $this->retail->receipt($live, $data, $key),
            'shift.open' => $this->retail->openShift($live, $data),
            'shift.close' => $this->retail->closeShift($live, $id, $data),
            'shift.reconcile' => $this->retail->closeShift($live, $id, $data, true),
            'sale.create' => $this->retail->createSale($live, $data),
            'sale.complete' => $this->retail->completeSale($live, $id, $data, $key),
            'sale.cancel' => $this->retail->cancelSale($live, $id, $data),
            'sale.return' => $this->retail->returnSale($live, $id, $data, $key),
            'return.refund' => $this->retail->refund($live, $id, $data),
            'customer.create' => $this->retail->saveCustomer($live, $data, null, $key),
            'customer.update' => $this->retail->saveCustomer($live, $data, $id, $key),
            'membership.save' => $this->membership($request, $data),
            'transfer.create','transfer.dispatch','transfer.receive','stocktake.create','stocktake.post' => $this->warehouseCommand($request, $operation, $id, $data, $key),
            default => abort(404),
        });

        return response()->json(['data' => $this->retail->sanitize($actor, $result), 'meta' => ['idempotency_key' => $key]]);
    }

    public function customerHistory(Request $request, string $resource): JsonResponse
    {
        $actor = $request->user('admin');

        return response()->json(['data' => $this->retail->sanitize($actor, $this->retail->customerHistory($actor, (string) $request->query('branch_id'), $resource))])->header('Cache-Control', 'no-store');
    }

    private function membership(Request $request, array $data): array
    {
        $actor = $request->user('admin');
        abort_unless($actor->canAccess('retail.membership.manage', 'global'), 403);
        $valid = validator($data, ['branch_id' => 'required|uuid', 'admin_id' => 'required|integer|exists:admins,id', 'preset' => 'required|in:hq,manager,cashier,stock,finance', 'is_active' => 'required|boolean'])->validate();
        $b = $this->retail->branch($actor, $valid['branch_id'], 'retail.membership.manage', true);
        // This maps existing identities only; granting core roles remains global Admin API work.
        DB::table('retail_memberships')->updateOrInsert(['admin_id' => $valid['admin_id'], 'chain_id' => $b->chain_id, 'branch_id' => $b->id], ['preset' => $valid['preset'], 'is_active' => $valid['is_active'], 'created_at' => now(), 'updated_at' => now()]);
        DB::table('admins')->where('id', $valid['admin_id'])->increment('auth_version');

        return ['branch_id' => $b->public_id, 'admin_id' => $valid['admin_id'], 'preset' => $valid['preset'], 'is_active' => $valid['is_active']];
    }

    private function warehouseReads(Request $request, string $kind): array
    {
        $permission = $kind === 'transfers' ? 'retail.transfer.manage' : 'retail.stocktake.manage';
        $b = $this->retail->branch($request->user('admin'), (string) $request->query('branch_id'), $permission);
        $table = $kind === 'transfers' ? 'inv_transfers' : 'inv_stocktakes';
        $rows = DB::table($table)->where(function ($q) use ($kind, $b): void {
            if ($kind === 'transfers') {
                $q->where('source_warehouse_id', $b->warehouse_id)->orWhere('destination_warehouse_id', $b->warehouse_id);
            } else {
                $q->where('warehouse_id', $b->warehouse_id);
            }
        })->orderByDesc('id')->limit(100)->get();

        return $rows->map(function ($row) use ($kind): array {
            $lines = DB::table($kind === 'transfers' ? 'inv_transfer_lines' : 'inv_stocktake_lines')->where($kind === 'transfers' ? 'transfer_id' : 'stocktake_id', $row->id)->get()->map(fn ($l) => (array) $l)->all();

            return array_replace((array) $row, ['id' => $row->public_id, 'lines' => $lines]);
        })->all();
    }

    private function warehouseCommand(Request $request, string $kind, string $id, array $data, string $key): array
    {
        $actor = $request->user('admin');
        if ($kind === 'transfer.create') {
            $from = $this->retail->branch($actor, (string) ($data['source_branch_id'] ?? ''), 'retail.transfer.manage', true);
            $to = $this->retail->branch($actor, (string) ($data['destination_branch_id'] ?? ''), 'retail.transfer.manage', true);
            abort_unless($from->chain_id === $to->chain_id && $from->legal_entity_id === $to->legal_entity_id, 422, 'Chuyển nội bộ phải cùng chuỗi và pháp nhân.');
            abort_unless($actor->hasPermission('inventory.transfer.manage'), 403);
            $lines = validator($data, ['lines' => 'required|array|min:1|max:200', 'lines.*.sku_id' => 'required|uuid', 'lines.*.quantity' => 'required'])->validate()['lines'];
            $catalog = DB::table('retail_chains')->where('id', $from->chain_id)->value('catalog_id');
            $items = array_map(function ($line) use ($catalog): array {
                $sku = $this->retail->find('merch_skus', $line['sku_id']);
                abort_unless($sku->catalog_id === $catalog, 404);

                return ['item_id' => $this->inventory->ensureItem((int) $sku->id), 'quantity' => (string) $line['quantity']];
            }, $lines);
            $result = $this->inventory->dispatchTransfer(['source_warehouse_id' => $from->warehouse_id, 'destination_warehouse_id' => $to->warehouse_id, 'lines' => $items], $key, (int) $actor->id);

            return array_replace($result, ['id' => $result['public_id']]);
        }
        if ($kind === 'transfer.dispatch') {
            abort(422, 'Tạo phiếu đã xuất giao trong phiên bản development này; không xuất lại.');
        }
        if ($kind === 'transfer.receive') {
            $transfer = $this->retail->find('inv_transfers', $id, true);
            $b = DB::table('retail_branches')->where('warehouse_id', $transfer->destination_warehouse_id)->first();
            abort_unless($b, 404);
            $this->retail->branch($actor, $b->public_id, 'retail.transfer.manage', true);
            abort_unless($actor->hasPermission('inventory.transfer.manage'), 403);
            $result = $this->inventory->receiveTransfer($id, $data, $key, (int) $actor->id);

            return array_replace($result, ['id' => $result['public_id']]);
        }
        if ($kind === 'stocktake.create') {
            $b = $this->retail->branch($actor, (string) ($data['branch_id'] ?? ''), 'retail.stocktake.manage', true);
            abort_unless($actor->hasPermission('inventory.stocktake.manage'), 403);
            $result = $this->inventory->beginStocktake((int) $b->warehouse_id, $key, (int) $actor->id);

            return array_replace($result, ['id' => $result['public_id']]);
        }
        $take = $this->retail->find('inv_stocktakes', $id, true);
        $b = DB::table('retail_branches')->where('warehouse_id', $take->warehouse_id)->first();
        abort_unless($b, 404);
        $this->retail->branch($actor, $b->public_id, 'retail.stocktake.manage', true);
        abort_unless($actor->hasPermission('inventory.stocktake.manage'), 403);
        $result = $this->inventory->postStocktake($id, $data['counts'] ?? $data['lines'] ?? [], $key, (int) $actor->id);

        return array_replace($result, ['id' => $result['public_id']]);
    }
}
