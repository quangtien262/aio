<?php

namespace Modules\Pharmacy\Services\Purchasing;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Services\InventoryManagedStockService;
use Modules\Pharmacy\Services\Access\PharmacyAccessService;
use Modules\Pharmacy\Services\Commands\PharmacyCommandService;
use Modules\Pharmacy\Services\PharmacyData as D;
use Modules\Purchasing\Services\PurchasingManagedStockService;
use Modules\Purchasing\Services\PurchasingService;

/** Native Pharmacy scope plus existing global Purchasing authority; no implied delegation. */
final class PharmacyPurchasingService
{
    public function dispatch(Admin $actor, string $action, array $input, string $key): array
    {
        $native = match ($action) {
            'supplier.save' => 'purchasing.supplier.manage', 'purchasing.create' => 'purchasing.order.create',
            'purchasing.approve' => 'purchasing.order.approve', 'purchasing.receive' => 'purchasing.receipt.manage',
            'purchasing.return' => 'purchasing.return.manage', default => abort(422, 'Nghiệp vụ mua hàng chưa được hỗ trợ.'),
        };

        return app(PharmacyCommandService::class)->run($actor, $action, $input, $key, function (Admin $live) use ($input, $native, $action): array {
            abort_unless(app(ModuleCapabilityChecker::class)->has('purchasing', 'purchasing.orders.manage.v1'), 503, 'Cần cài đặt hoặc cập nhật và bật ứng dụng “Mua hàng và nhà cung cấp” trước khi mua hoặc nhận hàng.');
            if ($action === 'purchasing.return') {
                abort_unless(app(ModuleCapabilityChecker::class)->has('purchasing', 'purchasing.managed_returns.v1'), 503, 'Cần cập nhật ứng dụng “Mua hàng và nhà cung cấp” để trả hàng cách ly hoặc bị hạn chế.');
            }
            $access = app(PharmacyAccessService::class);
            $p = $access->pharmacy($live, $input['pharmacy_id'] ?? '', 'pharmacy.purchasing.manage', true);
            abort_unless($live->canAccess($native, 'global'), 403, 'Cần được cấp quyền tương ứng trong ứng dụng “Mua hàng và nhà cung cấp” trên toàn hệ thống.');
            if (in_array($action, ['purchasing.create', 'purchasing.receive', 'purchasing.return'], true)) {
                abort_unless($live->canAccess($action === 'purchasing.return' ? 'inventory.issue.manage' : 'inventory.receipt.manage', 'global'), 403, 'Cần được cấp quyền tương ứng trong ứng dụng “Quản lý kho” trên toàn hệ thống.');
            }
            if ($action === 'supplier.save') {
                $access->chain($live, (int) $p->chain_id, 'pharmacy.purchasing.manage', true);
            }

            return ['pharmacy' => $p, 'permission' => 'pharmacy.purchasing.manage'];
        }, function (Admin $live, array $scope) use ($action, $input, $key): array {
            $p = $scope['pharmacy'];

            return match ($action) {
                'supplier.save' => $this->supplier($live, $p, $input, $key),
                'purchasing.create' => $this->create($live, $p, $input, $key),
                'purchasing.approve' => $this->approve($live, $p, $input, $key),
                'purchasing.receive' => $this->receive($live, $p, $input, $key),
                'purchasing.return' => $this->returned($live, $p, $input, $key),
            };
        });
    }

    private function supplier(Admin $actor, object $p, array $input, string $key): array
    {
        $v = validator($input, ['supplier_id' => 'nullable|uuid', 'name' => 'required|string|max:255', 'code' => 'required|string|max:60', 'phone' => 'nullable|string|max:40', 'email' => 'nullable|email|max:255', 'address' => 'nullable|string|max:4000', 'license_number' => 'required|string|max:255', 'valid_until' => 'required|date|after:now', 'expected_version' => 'sometimes|integer|min:1', 'supplier_revision' => 'nullable|string|size:64'])->validate();
        $shared = app(PurchasingService::class);
        $values = array_intersect_key($v, array_flip(['code', 'name', 'phone', 'email', 'address']));
        $old = null;
        if (! empty($v['supplier_id'])) {
            $supplier = DB::table('purchase_suppliers')->where('public_id', $v['supplier_id'])->first();
            abort_unless($supplier, 404);
            $old = DB::table('ph_supplier_links')->where('chain_id', $p->chain_id)->where('supplier_id', $supplier->id)->lockForUpdate()->first();
            abort_unless($old, 404, 'NCC chưa được liên kết chuỗi này.');
            abort_unless((int) ($v['expected_version'] ?? 0) === (int) $old->version && ! empty($v['supplier_revision']), 409, 'NCC/qualification đã thay đổi.');
            $supplier = $shared->updateSupplier($v['supplier_id'], $values + ['expected_revision' => $v['supplier_revision']], $this->child($actor, $key, 'supplier'), $actor->id);
        } else {
            $supplier = $shared->createSupplier($values, $this->child($actor, $key, 'supplier'), $actor->id);
        }
        $overlay = ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'supplier_id' => $supplier['id'], 'license_number' => $v['license_number'], 'valid_until' => $v['valid_until'], 'verified_by' => $actor->id, 'status' => 'active'];
        $link = $old ? D::update('ph_supplier_links', $old, $overlay, $v['expected_version']) : D::insert('ph_supplier_links', $overlay);

        return D::resource($link) + ['supplier' => array_intersect_key($supplier, array_flip(['id', 'public_id', 'code', 'name', 'revision']))];
    }

    private function qualified(object $p, string $supplierId): object
    {
        $supplier = DB::table('purchase_suppliers')->where('public_id', $supplierId)->where('is_active', true)->first();
        abort_unless($supplier, 404);
        abort_unless(DB::table('ph_supplier_links')->where('chain_id', $p->chain_id)->where('supplier_id', $supplier->id)->where('status', 'active')->where('valid_until', '>', now())->whereNotNull('verified_by')->sharedLock()->first(), 409, 'Qualification NCC chưa hợp lệ hoặc đã hết hiệu lực.');

        return $supplier;
    }

    private function create(Admin $actor, object $p, array $input, string $key): array
    {
        $v = validator($input, ['supplier_id' => 'required|uuid', 'lines' => 'required|array|min:1|max:300', 'lines.*.product_id' => 'required|uuid|distinct', 'lines.*.quantity' => 'required', 'lines.*.unit_cost' => 'required', 'expected_at' => 'nullable|date', 'note' => 'nullable|string|max:4000'])->validate();
        $supplier = $this->qualified($p, $v['supplier_id']);
        $chain = D::row('ph_chains', (int) $p->chain_id);
        $lines = [];
        foreach ($v['lines'] as $line) {
            $product = D::row('ph_products', $line['product_id'], $p->chain_id);
            abort_unless($product->status === 'active', 422);
            $lines[] = ['sku_id' => $product->sku_id, 'quantity' => (string) D::decimal($line['quantity']), 'unit_cost' => (string) D::decimal($line['unit_cost'], true)];
        }
        $order = app(PurchasingService::class)->createOrder(['supplier_id' => $supplier->id, 'warehouse_id' => $p->warehouse_id, 'catalog_id' => $chain->catalog_id, 'lines' => $lines, 'expected_at' => $v['expected_at'] ?? null, 'note' => $v['note'] ?? null], $this->child($actor, $key, 'create'), $actor->id);
        $link = D::insert('ph_purchase_links', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'order_id' => $order['id'], 'order_public_id' => $order['public_id'], 'supplier_id' => $supplier->id, 'status' => $order['status']]);

        return $this->result($link, $order);
    }

    private function linked(object $p, array $input, bool $requireQualification = true): array
    {
        $link = DB::table('ph_purchase_links')->where('chain_id', $p->chain_id)->where('pharmacy_id', $p->id)->where(fn ($q) => $q->where('public_id', $input['order_id'] ?? '')->orWhere('order_public_id', $input['order_id'] ?? ''))->lockForUpdate()->first();
        abort_unless($link, 404);
        abort_unless((int) ($input['expected_version'] ?? 0) === (int) $link->version, 409, 'Liên kết PO đã thay đổi.');
        $order = DB::table('purchase_orders')->where('id', $link->order_id)->lockForUpdate()->first();
        abort_unless($order && (int) $order->warehouse_id === (int) $p->warehouse_id && $order->legal_entity_ref === $p->legal_entity_ref, 409, 'Ownership PO/kho không còn khớp.');
        $supplier = DB::table('purchase_suppliers')->find($order->supplier_id);
        abort_unless($supplier, 409, 'NCC gốc không còn tồn tại.');
        if ($requireQualification) {
            $this->qualified($p, $supplier->public_id);
        }

        return [$link, $order];
    }

    private function approve(Admin $actor, object $p, array $input, string $key): array
    {
        [$link, $order] = $this->linked($p, $input);
        $result = app(PurchasingService::class)->approveOrder($order->public_id, $order->version, $this->child($actor, $key, 'approve'), $actor->id);
        $link = D::update('ph_purchase_links', $link, ['status' => $result['status']], $input['expected_version']);

        return $this->result($link, $result);
    }

    private function receive(Admin $actor, object $p, array $input, string $key): array
    {
        [$link, $order] = $this->linked($p, $input);
        $v = validator($input, ['lines' => 'required|array|min:1|max:300', 'lines.*.order_line_id' => 'required|uuid|distinct', 'lines.*.quantity' => 'required', 'lines.*.batch_code' => 'required|string|max:150', 'lines.*.expires_at' => 'required|date_format:Y-m-d', 'reference' => 'nullable|string|max:100'])->validate();
        $lines = [];
        foreach ($v['lines'] as $line) {
            $row = DB::table('purchase_order_lines')->where('order_id', $order->id)->where('public_id', $line['order_line_id'])->first();
            abort_unless($row, 404);
            $lines[] = array_replace($line, ['order_line_id' => $row->id]);
        }
        $result = app(InventoryManagedStockService::class)->withinOwner('pharmacy', $p->public_id, $actor->id, 'purchasing.receive', fn () => app(PurchasingService::class)->receiveOrder($order->public_id, ['expected_version' => $order->version, 'lines' => $lines, 'reference' => $v['reference'] ?? null], $this->child($actor, $key, 'receive'), $actor->id), ['source_type' => 'purchase_order', 'source_id' => $order->public_id]);
        $receipt = DB::table('purchase_receipts')->where('order_id', $order->id)->orderByDesc('id')->first();
        $receipts = [];
        foreach (DB::table('purchase_receipt_lines')->where('receipt_id', $receipt->id)->get() as $line) {
            $product = DB::table('ph_products')->where('chain_id', $p->chain_id)->where('sku_id', DB::table('purchase_order_lines')->where('id', $line->order_line_id)->value('sku_id'))->first();
            abort_unless($product, 409);
            $operation = DB::table('inv_v2_operations')->where('public_id', $line->stock_operation_id)->first();
            $stock = D::json($operation?->response);
            $row = D::insert('ph_receipts', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'product_id' => $product->id, 'batch_id' => $line->batch_id, 'quantity' => $line->quantity, 'unit_cost' => $line->unit_cost, 'stock_operation_id' => $line->stock_operation_id, 'source_movement_id' => $stock['movement_id'] ?? null, 'reference' => $v['reference'] ?? null, 'supplier_reference' => $order->public_id, 'purchase_receipt_id' => $receipt->id, 'purchase_receipt_line_id' => $line->id, 'actor_id' => $actor->id, 'status' => 'quarantine']);
            $receipts[] = D::resource($row);
        }
        $link = D::update('ph_purchase_links', $link, ['status' => $result['status']], $input['expected_version']);

        return $this->result($link, $result) + ['receipts' => $receipts];
    }

    private function returned(Admin $actor, object $p, array $input, string $key): array
    {
        // Lapsed procurement qualification blocks new buying/receiving, never removal of unsafe stock.
        [$link, $order] = $this->linked($p, $input, false);
        $v = validator($input, ['receipt_id' => 'required|uuid', 'quantity' => 'required', 'reason' => 'required|string|max:1000'])->validate();
        $receipt = D::row('ph_receipts', $v['receipt_id'], $p->chain_id, $p->id, true);
        abort_unless($receipt->purchase_receipt_line_id && DB::table('purchase_receipts')->where('id', $receipt->purchase_receipt_id)->where('order_id', $order->id)->exists(), 404, 'Receipt không thuộc PO đã chọn.');
        $result = app(InventoryManagedStockService::class)->withinOwner('pharmacy', $p->public_id, $actor->id, 'purchasing.return', fn () => app(PurchasingManagedStockService::class)->returnReceiptLine($actor, $order->public_id, ['expected_version' => $order->version, 'receipt_line_id' => $receipt->purchase_receipt_line_id, 'quantity' => (string) D::decimal($v['quantity']), 'reason' => $v['reason']], $this->child($actor, $key, 'return')), ['source_type' => 'purchase_order', 'source_id' => $order->public_id, 'source_line_id' => $receipt->public_id]);
        $link = D::update('ph_purchase_links', $link, ['status' => $result['status']], $input['expected_version']);

        return $this->result($link, $result);
    }

    private function result(object $link, array $order): array
    {
        return D::resource($link) + ['order' => $order];
    }

    private function child(Admin $actor, string $key, string $action): string
    {
        return 'ph-purchase:'.hash('sha256', $actor->id.'|'.$key.'|'.$action);
    }
}
