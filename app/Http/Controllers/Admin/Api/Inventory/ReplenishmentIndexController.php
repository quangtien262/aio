<?php

namespace App\Http\Controllers\Admin\Api\Inventory;

use App\Models\InvItem;
use Illuminate\Http\JsonResponse;

class ReplenishmentIndexController
{
    public function __invoke(): JsonResponse
    {
        $items = InvItem::query()
            ->withSum('balances', 'quantity_on_hand')
            ->where('reorder_min', '>', 0)
            ->orderBy('name')
            ->get()
            ->map(function (InvItem $item): array {
                $onHand = (float) ($item->balances_sum_quantity_on_hand ?? 0);
                $min = (float) $item->reorder_min;
                $max = (float) $item->reorder_max;
                $target = $max > $min ? $max : $min;

                return [
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'item_sku' => $item->sku,
                    'on_hand' => $onHand,
                    'reorder_min' => $min,
                    'reorder_max' => $max,
                    'suggested_quantity' => max(0, $target - $onHand),
                    'preferred_supplier' => $item->preferred_supplier,
                    'status' => $onHand <= 0 ? 'out' : ($onHand <= $min ? 'low' : 'ok'),
                ];
            })
            ->filter(fn (array $item): bool => $item['status'] !== 'ok')
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
