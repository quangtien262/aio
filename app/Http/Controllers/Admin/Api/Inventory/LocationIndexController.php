<?php

namespace App\Http\Controllers\Admin\Api\Inventory;

use App\Models\InvLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = InvLocation::query()
            ->with(['warehouse', 'parent'])
            ->orderBy('warehouse_id')
            ->orderBy('sort_order')
            ->orderBy('code');

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', (int) $request->integer('warehouse_id'));
        }

        $items = $query->get()
            ->map(fn (InvLocation $location): array => InventoryDataSerializer::location($location))
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
            ],
        ]);
    }
}
