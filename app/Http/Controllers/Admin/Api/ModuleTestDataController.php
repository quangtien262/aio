<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Modules\TestData\ModuleTestDataService;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ModuleTestDataController
{
    public function index(ModuleTestDataService $data, SiteContext $site, ?string $key = null): JsonResponse
    {
        return response()->json(['data' => ['batches' => $data->batches($site->websiteKey(), $key)]]);
    }

    public function destroy(Request $request, ModuleTestDataService $data, SiteContext $site, ?string $key = null): JsonResponse
    {
        $values = $request->validate(['confirmed' => ['required', 'accepted'], 'batch_id' => ['nullable', 'uuid']]);
        $result = $data->remove($site->websiteKey(), $request->user('admin'), $key, $values['batch_id'] ?? null);

        return response()->json(['message' => 'Đã xóa dữ liệu demo được đánh dấu.', 'data' => $result]);
    }
}
