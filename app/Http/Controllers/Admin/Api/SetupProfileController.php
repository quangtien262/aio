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

        if (filled($validated['company_name'] ?? null) || filled($validated['primary_color'] ?? null)) {
            $completedSteps->push('branding');
        }

        $branding = array_filter([
            'company_name' => $validated['company_name'] ?? null,
            'slogan' => $validated['slogan'] ?? null,
            'primary_color' => $validated['primary_color'] ?? null,
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
