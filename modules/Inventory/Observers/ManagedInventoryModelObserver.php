<?php

namespace Modules\Inventory\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Inventory\Services\InventoryManagedStockService;

/** Covers legacy metadata editors which can otherwise change stock eligibility. */
final class ManagedInventoryModelObserver
{
    public function saving(Model $model): void
    {
        $this->guard($model, 'metadata.update');
    }

    public function deleting(Model $model): void
    {
        $this->guard($model, 'metadata.delete');
    }

    private function guard(Model $model, string $operation): void
    {
        if (! Schema::hasColumn('inv_warehouses', 'managed_owner_module')) {
            return;
        }
        $ids = [];
        if ($model->getTable() === 'inv_warehouses') {
            if (! $model->exists) {
                return;
            }
            $ids[] = $model->getKey();
            abort_if($model->getRawOriginal('managed_owner_module') && ($model->isDirty('managed_owner_module') || $model->isDirty('managed_owner_scope') || $model->isDirty('v2_managed')), 409, 'Không được tháo ownership/v2 qua editor kho.');
        } elseif ($model->getTable() === 'inv_locations') {
            $ids = array_filter([$model->getRawOriginal('warehouse_id'), $model->warehouse_id]);
        } elseif ($model->getTable() === 'inv_batches' && $model->exists) {
            $ids = DB::table('inv_stock_balances')->where('batch_id', $model->getKey())->pluck('warehouse_id')->all();
        }
        sort($ids);
        $service = app(InventoryManagedStockService::class);
        $actor = $service->context()['actor'] ?? (int) auth('admin')->id();
        foreach (array_unique($ids) as $id) {
            $warehouse = DB::table('inv_warehouses')->where('id', $id)->first();
            if (! $warehouse || empty($warehouse->managed_owner_module)) {
                continue;
            }
            $service->authorize($warehouse, $operation, $actor);
            if ($model->getTable() === 'inv_locations' && $model->exists && ($operation === 'metadata.delete' || $model->isDirty(['type', 'is_active', 'warehouse_id']))) {
                abort_if(DB::table('inv_stock_balances')->where('location_id', $model->getKey())->where(function ($q): void {
                    $q->where('quantity_on_hand', '>', 0)->orWhere('quantity_reserved', '>', 0);
                })->exists(), 409, 'Đổi disposition có tồn phải qua managed stock contract.');
            }
        }
    }
}
