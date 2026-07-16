<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Core\Themes\ThemeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ThemeDemoDataController
{
    public function store(Request $request, string $key, ThemeRegistry $themeRegistry, ThemeDemoContentGenerator $generator): JsonResponse
    {
        abort_if($themeRegistry->all()->firstWhere('key', $key) === null, 404, 'Theme not found.');

        $validated = $request->validate([
            'preset' => ['required', 'string', 'max:120'],
            'reset_all' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $generator->generate($key, $validated['preset'], (bool) ($validated['reset_all'] ?? false));
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

    public function destroy(string $key, ThemeRegistry $themeRegistry, ThemeDemoContentGenerator $generator): JsonResponse
    {
        abort_if($themeRegistry->all()->firstWhere('key', $key) === null, 404, 'Theme not found.');

        $result = $generator->delete($key);

        return response()->json([
            'message' => 'Đã xóa data test cho theme.',
            'data' => $result,
        ]);
    }
}
