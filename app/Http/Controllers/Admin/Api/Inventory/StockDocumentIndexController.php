<?php

namespace App\Http\Controllers\Admin\Api\Inventory;

use App\Models\InvStockDocument;
use Illuminate\Http\JsonResponse;

class StockDocumentIndexController
{
    public function __invoke(): JsonResponse
    {
        $documents = InvStockDocument::query()
            ->with(['sourceWarehouse', 'destinationWarehouse', 'lines.item', 'lines.batch'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (InvStockDocument $document): array => InventoryDataSerializer::document($document))
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'items' => $documents,
                'total' => count($documents),
            ],
        ]);
    }
}
