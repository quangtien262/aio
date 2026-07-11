<?php

namespace App\Http\Controllers\Admin\Api\Inventory;

use App\Models\InvStockMovement;
use Illuminate\Http\JsonResponse;

class StockMovementIndexController
{
    public function __invoke(): JsonResponse
    {
        $movements = InvStockMovement::query()
            ->with(['item', 'warehouse', 'batch'])
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (InvStockMovement $movement): array => InventoryDataSerializer::movement($movement))
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'items' => $movements,
                'total' => count($movements),
            ],
        ]);
    }
}
