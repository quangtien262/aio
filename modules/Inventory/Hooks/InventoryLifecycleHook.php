<?php

namespace Modules\Inventory\Hooks;

use App\Core\Modules\Contracts\ModuleLifecycleHook;
use App\Core\Modules\Support\ModuleLifecycleContext;
use App\Models\InvLocation;
use App\Models\InvWarehouse;
use App\Models\Permission;
use App\Models\Role;

class InventoryLifecycleHook implements ModuleLifecycleHook
{
    public function preInstall(ModuleLifecycleContext $context): void
    {
    }

    public function postInstall(ModuleLifecycleContext $context): void
    {
        $this->ensureInventoryManagerRole();
        $this->ensureDefaultWarehouse();
    }

    public function preEnable(ModuleLifecycleContext $context): void
    {
    }

    public function postEnable(ModuleLifecycleContext $context): void
    {
        $this->ensureInventoryManagerRole();
        $this->ensureDefaultWarehouse();
    }

    public function preDisable(ModuleLifecycleContext $context): void
    {
    }

    public function postDisable(ModuleLifecycleContext $context): void
    {
    }

    public function preUpgrade(ModuleLifecycleContext $context): void
    {
    }

    public function postUpgrade(ModuleLifecycleContext $context): void
    {
        $this->ensureInventoryManagerRole();
        $this->ensureDefaultWarehouse();
    }

    public function preUninstall(ModuleLifecycleContext $context): void
    {
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
                'name' => 'Inventory Manager',
                'description' => 'Quan ly kho, hang hoa, ton kho va giao dich nhap xuat chuyen kho.',
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
