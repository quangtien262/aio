<?php

namespace App\Support\AccountingTax;

use App\Models\AcctInventoryWarehouseMapping;
use App\Models\InvWarehouse;
use App\Support\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AccountingInventoryWarehouseService
{
    public function __construct(
        private readonly ModuleCapabilityService $capabilities,
        private readonly AuditLogger $audit,
    ) {}

    /** @return list<array<string, mixed>> */
    public function forOrganization(int $organizationId): array
    {
        $this->assertAvailable();

        return AcctInventoryWarehouseMapping::query()
            ->where('organization_id', $organizationId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(fn (AcctInventoryWarehouseMapping $mapping): array => $this->serialize($mapping))
            ->all();
    }

    /** @return list<array{id: int, code: string, name: string, address: ?string}> */
    public function availableForOrganization(int $organizationId): array
    {
        $this->assertAvailable();

        // Warehouse ownership is globally unique, so both this organization's
        // mappings and mappings owned by other organizations are unavailable.
        $unavailableWarehouseIds = AcctInventoryWarehouseMapping::query()
            ->pluck('inventory_warehouse_id');

        return InvWarehouse::query()
            ->where('is_active', true)
            ->whereNotIn('id', $unavailableWarehouseIds)
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'address'])
            ->map(static fn (InvWarehouse $warehouse): array => [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
                'address' => $warehouse->address,
            ])
            ->all();
    }

    public function map(int $organizationId, int $warehouseId, bool $isDefault, ?int $adminId): AcctInventoryWarehouseMapping
    {
        $this->assertAvailable();
        $warehouse = InvWarehouse::query()->where('is_active', true)->find($warehouseId);

        if ($warehouse === null) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['Kho không tồn tại, chưa hoạt động hoặc Inventory chưa sẵn sàng.'],
            ]);
        }

        try {
            return DB::transaction(function () use ($organizationId, $warehouse, $isDefault, $adminId): AcctInventoryWarehouseMapping {
                $conflict = AcctInventoryWarehouseMapping::query()
                    ->lockForUpdate()
                    ->where('inventory_warehouse_id', $warehouse->id)
                    ->where('organization_id', '!=', $organizationId)
                    ->first();

                if ($conflict !== null) {
                    throw ValidationException::withMessages([
                        'warehouse_id' => ['Kho đã được ánh xạ cho một pháp nhân kế toán khác.'],
                    ]);
                }

                if ($isDefault) {
                    AcctInventoryWarehouseMapping::query()
                        ->where('organization_id', $organizationId)
                        ->update(['is_default' => false, 'default_slot' => null]);
                }

                $mapping = AcctInventoryWarehouseMapping::query()->updateOrCreate(
                    [
                        'organization_id' => $organizationId,
                        'inventory_warehouse_id' => $warehouse->id,
                    ],
                    [
                        'is_default' => $isDefault,
                        'warehouse_snapshot' => [
                            'id' => $warehouse->id,
                            'code' => $warehouse->code,
                            'name' => $warehouse->name,
                            'address' => $warehouse->address,
                        ],
                        'created_by' => $adminId,
                    ],
                );
                $this->audit->record(
                    'accounting.inventory.warehouse_mapped',
                    $mapping,
                    null,
                    $mapping->toArray(),
                    moduleKey: 'accounting-tax',
                );

                return $mapping;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['Kho vừa được ánh xạ hoặc pháp nhân đã có kho mặc định khác; hãy tải lại danh sách.'],
            ]);
        }
    }

    public function unmap(AcctInventoryWarehouseMapping $mapping): void
    {
        $before = $mapping->toArray();
        $mapping->delete();
        $this->audit->record(
            'accounting.inventory.warehouse_unmapped',
            $mapping,
            $before,
            null,
            moduleKey: 'accounting-tax',
        );
    }

    public function assertMapped(int $organizationId, int $warehouseId): void
    {
        $this->assertAvailable();

        if (! AcctInventoryWarehouseMapping::query()
            ->where('organization_id', $organizationId)
            ->where('inventory_warehouse_id', $warehouseId)
            ->exists()) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['Kho chưa được ánh xạ với pháp nhân của chứng từ.'],
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function serialize(AcctInventoryWarehouseMapping $mapping): array
    {
        return [
            'id' => $mapping->id,
            'organization_id' => $mapping->organization_id,
            'inventory_warehouse_id' => $mapping->inventory_warehouse_id,
            'is_default' => $mapping->is_default,
            'warehouse' => $mapping->warehouse_snapshot,
        ];
    }

    private function assertAvailable(): void
    {
        if (! $this->capabilities->has('inventory', 'inventory.documents.write.v1')
            || ! Schema::hasTable('inv_warehouses')) {
            throw ValidationException::withMessages([
                'inventory' => ['Module Inventory chưa được cài, bật hoặc chưa cung cấp capability cần thiết.'],
            ]);
        }
    }
}
