<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\SiteProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SetupProfileController
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:255'],
            'website_type' => ['required', 'string', Rule::in(array_keys(config('aio.website_types', [])))],
            'company_name' => ['nullable', 'string', 'max:255'],
            'slogan' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'primary_color_deep' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'accent_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'accent_soft_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'background_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'surface_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'surface_tint_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'favicon_url' => ['nullable', 'url', 'max:2048'],
            'support_hotline' => ['nullable', 'string', 'max:120'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_location' => ['nullable', 'string', 'max:120'],
        ]);

        $siteProfile = SiteProfile::query()->firstOrNew();
        $completedSteps = collect($siteProfile->completed_steps ?? [])
            ->push('website_type')
            ->unique()
            ->values();

        $paletteFields = [
            'primary_color',
            'primary_color_deep',
            'accent_color',
            'accent_soft_color',
            'background_color',
            'surface_color',
            'surface_tint_color',
        ];

        if (filled($validated['company_name'] ?? null) || collect($paletteFields)->contains(fn (string $field): bool => filled($validated[$field] ?? null))) {
            $completedSteps->push('branding');
        }

        $branding = array_filter([
            'company_name' => $validated['company_name'] ?? null,
            'slogan' => $validated['slogan'] ?? null,
            'primary_color' => $validated['primary_color'] ?? null,
            'primary_color_deep' => $validated['primary_color_deep'] ?? null,
            'accent_color' => $validated['accent_color'] ?? null,
            'accent_soft_color' => $validated['accent_soft_color'] ?? null,
            'background_color' => $validated['background_color'] ?? null,
            'surface_color' => $validated['surface_color'] ?? null,
            'surface_tint_color' => $validated['surface_tint_color'] ?? null,
            'logo_url' => $validated['logo_url'] ?? null,
            'favicon_url' => $validated['favicon_url'] ?? null,
            'support_hotline' => $validated['support_hotline'] ?? null,
            'support_email' => $validated['support_email'] ?? null,
            'support_location' => $validated['support_location'] ?? null,
        ], fn ($value) => filled($value));

        $siteProfile->forceFill([
            'site_name' => $validated['site_name'],
            'website_type' => $validated['website_type'],
            'branding' => array_merge($siteProfile->branding ?? [], $branding),
            'completed_steps' => $completedSteps->unique()->values()->all(),
        ])->save();

        return response()->json([
            'message' => 'Đã lưu cấu hình website.',
        ]);
    }
}
