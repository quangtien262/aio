<?php

namespace App\Http\Controllers\Admin\Api\Inventory;

use App\Models\InvSerialNumber;
use Illuminate\Http\JsonResponse;

class SerialNumberIndexController
{
    public function __invoke(): JsonResponse
    {
        $items = InvSerialNumber::query()
            ->with(['item', 'batch'])
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (InvSerialNumber $serialNumber): array => InventoryDataSerializer::serialNumber($serialNumber))
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
