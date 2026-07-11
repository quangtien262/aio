<?php

namespace App\Http\Controllers\Admin\Api\Inventory;

use App\Models\InvBatch;
use Illuminate\Http\JsonResponse;

class BatchIndexController
{
    public function __invoke(): JsonResponse
    {
        $items = InvBatch::query()
            ->with('item')
            ->orderByRaw('expires_at is null')
            ->orderBy('expires_at')
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (InvBatch $batch): array => InventoryDataSerializer::batch($batch))
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
