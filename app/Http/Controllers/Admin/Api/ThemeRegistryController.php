<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Themes\ThemeRegistry;
use Illuminate\Http\JsonResponse;

class ThemeRegistryController
{
    public function __invoke(ThemeRegistry $themeRegistry): JsonResponse
    {
        return response()->json([
            'data' => $themeRegistry->all()->all(),
        ]);
    }
}
