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
            'message' => 'Đã đồng bộ sản phẩm sang hàng hóa kho.',
            'data' => InventoryDataSerializer::syncRun($run),
        ]);
    }
}
