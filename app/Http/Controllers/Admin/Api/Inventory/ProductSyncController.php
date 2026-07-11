<?php

namespace App\Http\Controllers\Admin\Api\Inventory;

use App\Support\InventoryProductSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSyncController
{
    public function __invoke(Request $request, InventoryProductSyncService $syncService): JsonResponse
    {
        $run = $syncService->sync($request->user('admin')?->id);

        return response()->json([
            'message' => 'Da dong bo san pham sang hang hoa kho.',
            'data' => InventoryDataSerializer::syncRun($run),
        ]);
    }
}
