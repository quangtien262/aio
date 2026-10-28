<?php

namespace Modules\Pharmacy\Services\Integrations;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Modules\Pharmacy\Services\Access\PharmacyAccessService;
use Modules\Pharmacy\Services\Commands\PharmacyCommandService;
use Modules\Pharmacy\Services\Dispensing\DispensingService;
use Modules\Pharmacy\Services\PharmacyData as D;
use Modules\Pharmacy\Services\Runtime\PharmacyRuntimeState;

/** Expiration uses the original live authority; revoke creates an explicit recovery case. */
final class PharmacyRecoveryService
{
    public function recoverExpiring(int $limit = 100, ?float $deadline = null): array
    {
        try {
            app(PharmacyRuntimeState::class)->guard(false);
        } catch (\Throwable) {
            return ['resolved' => 0, 'manual_review' => 0];
        }
        $done = $blocked = 0;
        $rows = DB::table('ph_dispensings')->whereIn('status', ['prepared', 'reviewed'])->where('reservation_expires_at', '<=', now())->whereNotExists(fn ($q) => $q->selectRaw('1')->from('ph_recovery_cases')->whereColumn('ph_recovery_cases.dispensing_id', 'ph_dispensings.id')->whereIn('ph_recovery_cases.status', ['manual_review', 'resolved']))->orderBy('id')->limit(min(500, max(1, $limit)))->get();
        foreach ($rows as $row) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                break;
            }
            try {
                DB::transaction(function () use ($row): void {
                    app(PharmacyRuntimeState::class)->guard(true);
                    $current = DB::table('ph_dispensings')->where('id', $row->id)->lockForUpdate()->first();
                    if (! in_array($current->status, ['prepared', 'reviewed'], true) || now()->lessThan($current->reservation_expires_at)) {
                        return;
                    }
                    $case = DB::table('ph_recovery_cases')->where('dispensing_id', $current->id)->lockForUpdate()->first();
                    if ($case && in_array($case->status, ['manual_review', 'resolved'], true)) {
                        return;
                    }
                    if (! $case) {
                        $case = D::insert('ph_recovery_cases', ['chain_id' => $current->chain_id, 'pharmacy_id' => $current->pharmacy_id, 'dispensing_id' => $current->id, 'actor_id' => $current->created_by, 'actor_auth_version' => $current->created_auth_version ?? 0, 'status' => 'pending']);
                    }
                    $actor = Admin::query()->find($case->actor_id);
                    if (! $actor || ! $actor->isAvailable() || ! $case->actor_auth_version || (int) $actor->auth_version !== (int) $case->actor_auth_version) {
                        DB::table('ph_recovery_cases')->where('id', $case->id)->update(['status' => 'manual_review', 'error_code' => 'ORIGINAL_AUTHORITY_CHANGED', 'attempts' => $case->attempts + 1, 'updated_at' => now()]);

                        return;
                    }
                    app(DispensingService::class)->cancel($actor, ['dispensing_id' => $current->public_id, 'expected_version' => $current->version, 'reason' => 'Hệ thống giải phóng giữ lượng/tồn hết hạn theo ủy quyền gốc.'], 'ph-expire:'.hash('sha256', $case->public_id.'|'.$current->version));
                    DB::table('ph_recovery_cases')->where('id', $case->id)->update(['status' => 'resolved', 'error_code' => null, 'attempts' => $case->attempts + 1, 'updated_at' => now()]);
                }, 3);
                $status = DB::table('ph_recovery_cases')->where('dispensing_id', $row->id)->value('status');
                $status === 'resolved' ? $done++ : $blocked++;
            } catch (\Throwable) {
                DB::transaction(function () use ($row): void {
                    app(PharmacyRuntimeState::class)->guard(true);
                    $current = DB::table('ph_dispensings')->where('id', $row->id)->lockForUpdate()->first();
                    if (! $current || ! in_array($current->status, ['prepared', 'reviewed'], true)) {
                        return;
                    }
                    $case = DB::table('ph_recovery_cases')->where('dispensing_id', $current->id)->lockForUpdate()->first();
                    if (! $case) {
                        $case = D::insert('ph_recovery_cases', ['chain_id' => $current->chain_id, 'pharmacy_id' => $current->pharmacy_id, 'dispensing_id' => $current->id, 'actor_id' => $current->created_by, 'actor_auth_version' => $current->created_auth_version ?? 0]);
                    }
                    DB::table('ph_recovery_cases')->where('id', $case->id)->update(['status' => 'manual_review', 'error_code' => 'NATIVE_PERMISSION_OR_STATE_CHANGED', 'attempts' => $case->attempts + 1, 'updated_at' => now()]);
                }, 3);
                $blocked++;
            }
        }

        return ['resolved' => $done, 'manual_review' => $blocked];
    }

    public function dispatch(Admin $actor, string $action, array $input, string $key): array
    {
        abort_unless($action === 'recovery.resolve', 422);

        return app(PharmacyCommandService::class)->run($actor, $action, $input, $key, function (Admin $live) use ($input): array {
            return ['pharmacy' => app(PharmacyAccessService::class)->pharmacy($live, $input['pharmacy_id'] ?? '', 'pharmacy.dispensing.manage', true), 'permission' => 'pharmacy.dispensing.manage'];
        }, function (Admin $live, array $scope) use ($input, $key): array {
            $p = $scope['pharmacy'];
            $case = D::row('ph_recovery_cases', $input['case_id'] ?? '', $p->chain_id, $p->id, true);
            validator($input, ['reason' => 'required|string|max:1000'])->validate();
            $dispensing = D::row('ph_dispensings', (int) $case->dispensing_id, $p->chain_id, $p->id, true);
            $result = app(DispensingService::class)->cancel($live, ['dispensing_id' => $dispensing->public_id, 'expected_version' => $input['expected_version'] ?? 0, 'reason' => $input['reason']], 'ph-manual:'.hash('sha256', $live->id.'|'.$key));
            DB::table('ph_recovery_cases')->where('id', $case->id)->update(['status' => 'resolved', 'error_code' => null, 'updated_at' => now()]);

            return ['id' => $case->public_id, 'status' => 'resolved', 'dispensing_id' => $result['id']];
        });
    }
}
