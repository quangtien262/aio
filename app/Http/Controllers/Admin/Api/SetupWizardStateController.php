<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\SiteProfile;
use Illuminate\Http\JsonResponse;

class SetupWizardStateController
{
    public function __invoke(): JsonResponse
    {
        $siteProfile = SiteProfile::query()->first();
        $allSteps = config('aio.setup_steps', []);
        $completedSteps = $siteProfile?->completed_steps ?? [];

        return response()->json([
            'data' => [
                'site_name' => $siteProfile?->site_name,
                'website_type' => $siteProfile?->website_type,
                'active_theme_key' => $siteProfile?->active_theme_key,
                'is_setup_completed' => (bool) $siteProfile?->is_setup_completed,
                'steps' => collect($allSteps)->map(fn (string $step): array => [
                    'key' => $step,
                    'is_completed' => in_array($step, $completedSteps, true),
                ])->all(),
            ],
        ]);
    }
}
