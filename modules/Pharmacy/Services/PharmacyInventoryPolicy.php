<?php

namespace Modules\Pharmacy\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Contracts\ManagedStockPolicy;
use Modules\Pharmacy\Services\Access\PharmacyAccessService;

final class PharmacyInventoryPolicy implements ManagedStockPolicy
{
    public function authorize(int $actorId, object $warehouse, string $operation, array $context): void
    {
        $actor = Admin::query()->findOrFail($actorId);
        $access = app(PharmacyAccessService::class);
        $permission = match ($context['action'] ?? '') {
            'setup' => 'pharmacy.settings', 'receipt.create', 'receipt.inspect' => 'pharmacy.receipt.manage',
            'purchasing.receive', 'purchasing.return' => 'pharmacy.purchasing.manage',
            'quality.hold', 'quality.release', 'quality.disposition', 'quality.destroy', 'temperature.record' => 'pharmacy.quality.manage',
            'recall.create', 'recall.confirm' => 'pharmacy.recall.manage',
            'transfer.create', 'transfer.dispatch', 'transfer.receive' => 'pharmacy.transfer.manage', 'stocktake.create', 'stocktake.post' => 'pharmacy.stocktake.manage',
            'dispensing.finalize' => 'pharmacy.payment.collect', 'dispensing.return' => 'pharmacy.return.manage',
            'dispensing.review' => 'pharmacy.dispensing.review', 'dispensing.handover' => 'pharmacy.dispensing.handover',
            'dispensing.print' => 'pharmacy.documents.manage',
            'dispensing.prepare', 'dispensing.cancel' => 'pharmacy.dispensing.manage',
            'inventory.read' => 'pharmacy.inventory.view', default => abort(403, 'Ý định kho Nhà thuốc chưa được cấp quyền.'),
        };
        $pharmacy = $access->pharmacy($actor, (string) ($context['scope'] ?? ''), $permission, DB::transactionLevel() > 0);
        abort_unless((int) $pharmacy->warehouse_id === (int) $warehouse->id && (int) ($context['actor'] ?? $context['actor_id'] ?? 0) === $actorId, 403, 'Kho/actor không khớp phạm vi nhà thuốc.');
        $intent = $context['stock_intent'] ?? [];
        if ((($context['action'] ?? '') === 'transfer.dispatch' && $operation === 'transfer.dispatch') || in_array($context['action'] ?? '', ['receipt.inspect', 'quality.disposition'], true) && ($intent['disposition'] ?? '') === 'saleable') {
            $items = isset($intent['item_id']) ? [(int) $intent['item_id']] : array_column($intent['lines'] ?? [], 'item_id');
            abort_if(DB::table('ph_products')->where('chain_id', $pharmacy->chain_id)->whereIn('inventory_item_id', $items)->where(fn ($q) => $q->where('controlled', true)->orWhere('cold_chain', true))->exists(), 409, 'Gói thuốc kiểm soát đặc biệt/chuỗi lạnh chưa được mở; giữ hàng trong khu cách ly.');
        }
        if ($operation === 'receive' && in_array($context['action'] ?? '', ['receipt.create', 'transfer.receive', 'purchasing.receive', 'dispensing.return'], true)) {
            abort_unless(($intent['disposition'] ?? 'quarantine') === 'quarantine', 403, 'Thuốc nhận vào phải qua khu cách ly và kiểm nhận.');
        }
        if (in_array($context['action'] ?? '', ['quality.release', 'quality.disposition', 'quality.destroy', 'receipt.inspect'], true)) {
            $access->professional($actor, $pharmacy, 'review', DB::transactionLevel() > 0);
        }
        if (($context['action'] ?? '') === 'quality.disposition' && ($intent['disposition'] ?? '') === 'saleable') {
            abort_if(DB::table('ph_receipts')->where('pharmacy_id', $pharmacy->id)->where('batch_id', $intent['batch_id'] ?? 0)->whereIn('status', ['quarantine', 'rejected'])->exists(), 409, 'Cần hoàn tất kiểm nhận từng phiếu; không giải phóng lô qua thay đổi vị trí.');
        }
    }
}
