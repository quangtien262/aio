<?php

namespace App\Http\Controllers\Admin\Api\Inventory;

use App\Models\InvItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = InvItem::query()
            ->with('catalogProduct')
            ->withSum('balances', 'quantity_on_hand')
            ->orderBy('name');

        if ($search = trim((string) $request->string('search'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $items = $query->get()
            ->map(fn (InvItem $item): array => InventoryDataSerializer::item($item))
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
