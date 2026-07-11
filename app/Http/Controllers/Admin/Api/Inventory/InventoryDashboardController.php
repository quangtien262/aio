<?php

namespace App\Http\Controllers\Admin\Api\Inventory;

use App\Models\InvItem;
use App\Models\InvStockBalance;
use App\Models\InvStockDocument;
use App\Models\InvStockMovement;
use App\Models\InvWarehouse;
use Illuminate\Http\JsonResponse;

class InventoryDashboardController
{
    public function __invoke(): JsonResponse
    {
        $recentMovements = InvStockMovement::query()
            ->with(['item', 'warehouse', 'batch'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (InvStockMovement $movement): array => InventoryDataSerializer::movement($movement))
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'metrics' => [
                    'warehouses' => InvWarehouse::query()->count(),
                    'active_items' => InvItem::query()->where('is_active', true)->count(),
                    'total_on_hand' => (float) InvStockBalance::query()->sum('quantity_on_hand'),
                    'low_stock_items' => InvStockBalance::query()->where('quantity_on_hand', '<=', 5)->where('quantity_on_hand', '>', 0)->count(),
                    'out_of_stock_items' => InvStockBalance::query()->where('quantity_on_hand', '<=', 0)->count(),
                    'documents' => InvStockDocument::query()->count(),
                ],
                'recent_movements' => $recentMovements,
            ],
        ]);
    }
}
