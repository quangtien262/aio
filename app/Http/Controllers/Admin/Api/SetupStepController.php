<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\Admin;
use App\Models\ModuleInstallation;
use App\Models\SiteProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class SetupStepController
{
    public function __invoke(string $step): JsonResponse
    {
        abort_unless(in_array($step, config('aio.setup_steps', []), true), 404, 'Setup step not found.');

        $siteProfile = SiteProfile::query()->firstOrNew();

        if ($step === 'finish') {
            abort_unless($this->canFinishSetup($siteProfile), 422, 'Chưa thể chốt setup vì vẫn còn bước nền tảng chưa hoàn tất.');
        }

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
            'message' => $step === 'finish' ? 'Đã chốt setup wizard.' : 'Đã cập nhật trạng thái bước setup.',
        ]);
    }

    private function canFinishSetup(SiteProfile $siteProfile): bool
    {
        $completedSteps = collect($siteProfile->completed_steps ?? []);
        $branding = $siteProfile->branding ?? [];

        return filled($siteProfile->site_name)
            && filled($siteProfile->website_type)
            && filled($siteProfile->active_theme_key)
            && (filled($branding['company_name'] ?? null) || filled($branding['primary_color'] ?? null) || $completedSteps->contains('branding'))
            && (ModuleInstallation::query()->where('status', 'enabled')->exists() || $completedSteps->contains('modules'))
            && Admin::query()->where('is_active', true)->exists();
    }
}
