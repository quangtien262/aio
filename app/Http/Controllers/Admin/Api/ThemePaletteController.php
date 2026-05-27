<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Themes\ThemeRegistry;
use App\Models\SiteProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThemePaletteController
{
    public function __invoke(Request $request, string $key, ThemeRegistry $themeRegistry): JsonResponse
    {
        abort_unless($themeRegistry->all()->firstWhere('key', strtoupper($key)) !== null, 404, 'Theme not found.');

        $validated = $request->validate([
            'primary_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'primary_color_deep' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'accent_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'accent_soft_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'background_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'surface_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'surface_tint_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
        ]);

        $siteProfile = SiteProfile::query()->firstOrNew();
        $completedSteps = collect($siteProfile->completed_steps ?? [])
            ->push('branding')
            ->unique()
            ->values();

        $palette = array_filter($validated, fn ($value) => filled($value));
        $themePalettes = $siteProfile->theme_palettes ?? [];
        $themePalettes[strtoupper($key)] = $palette;

        $siteProfile->forceFill([
            'site_name' => $siteProfile->site_name ?? 'AIO Website',
            'theme_palettes' => $themePalettes,
            'completed_steps' => $completedSteps->all(),
        ])->save();

        return response()->json([
            'message' => 'Đã lưu palette của theme.',
            'data' => [
                'theme_key' => strtoupper($key),
                'palette' => $palette,
            ],
        ]);
    }
}
