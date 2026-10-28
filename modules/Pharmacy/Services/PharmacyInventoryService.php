<?php

namespace Modules\Pharmacy\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Services\InventoryManagedStockService;
use Modules\Inventory\Services\InventoryV2Service;

final class PharmacyInventoryService
{
    public function assertEligible(Admin $actor, object $pharmacy, object $product, int $batchId, string $action = 'dispensing.prepare'): void
    {
        abort_unless((int) $product->chain_id === (int) $pharmacy->chain_id && $product->status === 'active', 422, 'Thuốc không thuộc danh mục nhà thuốc.');
        abort_if((bool) $product->controlled, 409, 'Thuốc kiểm soát đặc biệt chưa được mở cho vận hành.');
        abort_if((bool) $product->cold_chain, 409, 'Thuốc cần chuỗi lạnh chưa được nghiệm thu.');
        abort_if(DB::table('ph_recalls')->where('chain_id', $pharmacy->chain_id)->where('batch_id', $batchId)->exists(), 409, 'Lô thuộc hồ sơ thu hồi toàn chuỗi; không được cấp/giao thuốc.');
        app(InventoryManagedStockService::class)->withinOwner('pharmacy', $pharmacy->public_id, $actor->id, $action, fn () => app(InventoryManagedStockService::class)->assertEligible((int) $pharmacy->warehouse_id, (int) $product->inventory_item_id, $batchId, $actor->id));
    }

    public function reserve(Admin $actor, object $pharmacy, object $product, string $quantity, int $batchId, string $source, string $key): array
    {
        $this->assertEligible($actor, $pharmacy, $product, $batchId);

        return app(InventoryManagedStockService::class)->withinOwner('pharmacy', $pharmacy->public_id, $actor->id, 'dispensing.prepare', fn () => app(InventoryV2Service::class)->reserve((int) $pharmacy->warehouse_id, (int) $product->inventory_item_id, $quantity, $source, $key, $actor->id, $batchId));
    }

    public function commit(Admin $actor, object $pharmacy, string $reservation, string $key): array
    {
        return app(InventoryManagedStockService::class)->withinOwner('pharmacy', $pharmacy->public_id, $actor->id, 'dispensing.finalize', fn () => app(InventoryV2Service::class)->commitReservation($reservation, $key, $actor->id));
    }

    public function release(Admin $actor, object $pharmacy, string $reservation, string $key): array
    {
        return app(InventoryManagedStockService::class)->withinOwner('pharmacy', $pharmacy->public_id, $actor->id, 'dispensing.cancel', fn () => app(InventoryV2Service::class)->releaseReservation($reservation, $key, $actor->id));
    }

    public function returnToQuarantine(Admin $actor, object $pharmacy, object $product, int $batch, string $quantity, string $unitCost, string $originalOperationId, string $key): array
    {
        abort_unless((int) $product->chain_id === (int) $pharmacy->chain_id, 403);

        return app(InventoryManagedStockService::class)->withinOwner('pharmacy', $pharmacy->public_id, $actor->id, 'dispensing.return', fn () => app(InventoryV2Service::class)->receive(['warehouse_id' => $pharmacy->warehouse_id, 'item_id' => $product->inventory_item_id, 'batch_id' => $batch, 'quantity' => $quantity, 'unit_cost' => $unitCost, 'disposition' => 'quarantine', 'source_id' => 'pharmacy-return:'.$originalOperationId], $key, $actor->id));
    }
}
