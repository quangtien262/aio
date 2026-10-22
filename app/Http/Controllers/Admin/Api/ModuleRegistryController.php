<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Modules\ModuleRegistry;
use App\Core\Modules\TestData\ModuleTestDataService;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;

class ModuleRegistryController
{
    public function __invoke(ModuleRegistry $moduleRegistry, ModuleTestDataService $testData, SiteContext $site): JsonResponse
    {
        return response()->json([
            'data' => $moduleRegistry->all()->map(fn (array $module) => [...$module, 'test_data' => $testData->metadata($module, $site->websiteKey())])->all(),
        ]);
    }
}
