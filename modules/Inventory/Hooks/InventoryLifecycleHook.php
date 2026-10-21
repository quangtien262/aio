<?php

namespace Modules\Inventory\Hooks;

use App\Core\Modules\Contracts\ModuleLifecycleHook;
use App\Core\Modules\Support\ModuleLifecycleContext;
use App\Models\InvLocation;
use App\Models\InvWarehouse;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryLifecycleHook implements ModuleLifecycleHook
{
    public function preInstall(ModuleLifecycleContext $context): void {}

    public function postInstall(ModuleLifecycleContext $context): void
    {
        $this->ensureInventoryManagerRole();
        $this->ensureDefaultWarehouse();
    }

    public function preEnable(ModuleLifecycleContext $context): void {}

    public function postEnable(ModuleLifecycleContext $context): void
    {
        $this->ensureInventoryManagerRole();
        $this->ensureDefaultWarehouse();
    }

    public function preDisable(ModuleLifecycleContext $context): void
    {
        $this->ensureDrained();
    }

    public function postDisable(ModuleLifecycleContext $context): void {}

    public function preUpgrade(ModuleLifecycleContext $context): void
    {
        $this->ensureDrained(allowProductionHolds: true);
    }

    public function postUpgrade(ModuleLifecycleContext $context): void
    {
        $this->ensureInventoryManagerRole();
        $this->ensureDefaultWarehouse();
    }

    public function preUninstall(ModuleLifecycleContext $context): void
    {
        $this->ensureDrained();
        if (DB::table('inv_stock_movements')->exists()) {
            throw new \RuntimeException('Không thể gỡ Inventory khi đã có lịch sử tồn kho.');
        }
    }

    public function postUninstall(ModuleLifecycleContext $context): void
    {
        Role::query()->where('key', 'inventory.manager')->first()?->delete();
    }

    private function ensureInventoryManagerRole(): void
    {
        $role = Role::query()->firstOrCreate(
            ['key' => 'inventory.manager'],
            [
                'name' => 'Quản lý kho',
                'description' => 'Quản lý kho, hàng hóa, tồn kho và giao dịch nhập, xuất, chuyển kho.',
            ],
        );

        $permissionIds = Permission::query()
            ->whereIn('key', [
                'inventory.view',
                'inventory.warehouse.manage',
                'inventory.location.manage',
                'inventory.item.manage',
                'inventory.item.sync',
                'inventory.receipt.manage',
                'inventory.issue.manage',
                'inventory.transfer.manage',
                'inventory.adjustment.manage',
                'inventory.stocktake.manage',
                'inventory.replenishment.view',
                'inventory.report.view',
            ])
            ->pluck('id')
            ->all();

        $role->permissions()->syncWithoutDetaching($permissionIds);
    }

    private function ensureDrained(bool $allowProductionHolds = false): void
    {
        foreach (['inv_reservations' => ['active'], 'inv_transfers' => ['dispatched', 'partially_received'], 'inv_stocktakes' => ['counting']] as $table => $statuses) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $query = DB::table($table)->whereIn('status', $statuses);
            if ($allowProductionHolds && $table === 'inv_reservations' && Schema::hasColumn($table, 'purpose')) {
                // Production holds persist through forward-compatible schema upgrades.
                // Runtime drain already waits for every stock transaction to finish.
                $query->where('purpose', '!=', 'production');
            }
            if ($query->exists()) {
                throw new \RuntimeException('Cần đối soát giữ hàng, điều chuyển và kiểm kê trước khi tắt Inventory.');
            }
        }
    }

    private function ensureDefaultWarehouse(): void
    {
        $warehouse = InvWarehouse::query()->firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Main Warehouse',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        if (! InvWarehouse::query()->where('is_default', true)->exists()) {
            $warehouse->forceFill(['is_default' => true])->save();
        }

        InvLocation::query()->firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'code' => 'MAIN'],
            [
                'name' => 'Main storage',
                'type' => 'storage',
                'is_default' => true,
                'is_active' => true,
            ],
        );
    }
}
