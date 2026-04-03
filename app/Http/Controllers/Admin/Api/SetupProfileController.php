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
        ]);

        $siteProfile = SiteProfile::query()->firstOrNew();
        $completedSteps = collect($siteProfile->completed_steps ?? [])
            ->push('website_type')
            ->unique()
            ->values()
            ->all();

        $siteProfile->forceFill([
            'site_name' => $validated['site_name'],
            'website_type' => $validated['website_type'],
            'completed_steps' => $completedSteps,
        ])->save();

        return response()->json([
            'message' => 'Setup profile saved successfully.',
        ]);
    }
}
