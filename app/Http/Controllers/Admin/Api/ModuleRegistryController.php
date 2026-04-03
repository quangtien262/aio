<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Modules\ModuleRegistry;
use Illuminate\Http\JsonResponse;

class ModuleRegistryController
{
    public function __invoke(ModuleRegistry $moduleRegistry): JsonResponse
    {
        return response()->json([
            'data' => $moduleRegistry->all()->all(),
        ]);
    }
}
