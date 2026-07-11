<?php

namespace App\Http\Controllers\Admin\Api\Inventory;

use App\Models\InvStockBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockBalanceIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = InvStockBalance::query()
            ->with(['warehouse', 'item', 'batch'])
            ->orderBy('warehouse_id')
            ->orderBy('item_id');

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', (int) $request->integer('warehouse_id'));
        }

        if ($search = trim((string) $request->string('search'))) {
            $query->whereHas('item', function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $balances = $query->get()
            ->map(fn (InvStockBalance $balance): array => InventoryDataSerializer::balance($balance))
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'items' => $balances,
                'total' => count($balances),
            ],
        ]);
    }
}
