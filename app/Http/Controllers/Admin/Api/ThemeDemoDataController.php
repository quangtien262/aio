<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Core\Themes\ThemeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ThemeDemoDataController
{
    public function __invoke(Request $request, string $key, ThemeRegistry $themeRegistry, ThemeDemoContentGenerator $generator): JsonResponse
    {
        abort_if($themeRegistry->all()->firstWhere('key', $key) === null, 404, 'Theme not found.');

        $validated = $request->validate([
            'preset' => ['required', 'string', 'max:120'],
        ]);

        try {
            $result = $generator->generate($key, $validated['preset']);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Đã tạo data test cho theme.',
            'data' => $result,
        ]);
    }
}
