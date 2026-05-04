<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Themes\ThemeRegistry;
use App\Support\FrontendLocalization;
use Illuminate\Http\JsonResponse;

class ThemeRegistryController
{
    public function __invoke(ThemeRegistry $themeRegistry): JsonResponse
    {
        return response()->json([
            'data' => $themeRegistry->all()->all(),
            'meta' => [
                'default_locale' => FrontendLocalization::defaultLocale(),
                'fallback_locale' => FrontendLocalization::fallbackLocale(),
                'source_locale' => FrontendLocalization::sourceLocale(),
                'locales' => FrontendLocalization::localeOptions(),
            ],
        ]);
    }
}
