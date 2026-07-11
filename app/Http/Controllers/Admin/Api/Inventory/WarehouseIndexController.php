<?php

namespace App\Http\Controllers\Admin\Api\Inventory;

use App\Models\InvWarehouse;
use Illuminate\Http\JsonResponse;

class WarehouseIndexController
{
    public function __invoke(): JsonResponse
    {
        $warehouses = InvWarehouse::query()
            ->withCount('balances')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (InvWarehouse $warehouse): array => InventoryDataSerializer::warehouse($warehouse))
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'items' => $warehouses,
                'total' => count($warehouses),
            ],
        ]);
    }
}
