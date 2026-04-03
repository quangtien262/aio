<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\SiteProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class SetupStepController
{
    public function __invoke(string $step): JsonResponse
    {
        abort_unless(in_array($step, config('aio.setup_steps', []), true), 404, 'Setup step not found.');

        $siteProfile = SiteProfile::query()->firstOrNew();
        $completedSteps = collect($siteProfile->completed_steps ?? [])
            ->push($step)
            ->unique()
            ->values()
            ->all();

        $payload = [
            'site_name' => $siteProfile->site_name ?? 'AIO Website',
            'completed_steps' => $completedSteps,
        ];

        if ($step === 'finish') {
            $payload['is_setup_completed'] = true;
            $payload['setup_completed_at'] = Carbon::now();
        }

        $siteProfile->forceFill($payload)->save();

        return response()->json([
            'message' => 'Setup step completed successfully.',
        ]);
    }
}
