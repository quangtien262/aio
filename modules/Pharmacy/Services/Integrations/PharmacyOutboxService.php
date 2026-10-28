<?php

namespace Modules\Pharmacy\Services\Integrations;

use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Modules\Pharmacy\Services\Commands\PharmacyCommandService;
use Modules\Pharmacy\Services\Runtime\PharmacyRuntimeState;

final class PharmacyOutboxService
{
    public function dispatchDue(int $limit = 100, ?float $deadline = null): array
    {
        try {
            app(PharmacyRuntimeState::class)->guard(false);
        } catch (\Throwable) {
            return ['delivered' => 0, 'blocked' => 0];
        }
        $count = $blocked = 0;
        $rows = DB::table('ph_outbox')->where('status', 'pending')->where('available_at', '<=', now())->orderBy('id')->limit(min(500, max(1, $limit)))->pluck('id');
        foreach ($rows as $id) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                break;
            }
            try {
                $result = DB::transaction(function () use ($id): bool {
                    app(PharmacyRuntimeState::class)->guard(true);
                    $event = DB::table('ph_outbox')->where('id', $id)->lockForUpdate()->first();
                    if (! $event || $event->status !== 'pending') {
                        return false;
                    }
                    if ($event->destination !== 'audit') {
                        DB::table('ph_outbox')->where('id', $event->id)->update(['status' => 'blocked', 'last_error' => 'CONSUMER_NOT_REGISTERED', 'updated_at' => now()]);

                        return false;
                    }
                    app(PharmacyCommandService::class)->consume('pharmacy.audit.v1', $event->event_id, function () use ($event): void {
                        app(AuditLogger::class)->record('pharmacy.outbox.delivered', 'pharmacy-event', after: ['event_id' => $event->event_id, 'event_type' => $event->event_type, 'aggregate_type' => $event->aggregate_type, 'aggregate_id' => $event->aggregate_id, 'aggregate_version' => $event->aggregate_version], moduleKey: 'pharmacy');
                    });
                    DB::table('ph_outbox')->where('id', $event->id)->update(['status' => 'delivered', 'attempts' => $event->attempts + 1, 'delivered_at' => now(), 'lease_token' => null, 'lease_until' => null, 'updated_at' => now()]);

                    return true;
                }, 3);
                $result ? $count++ : $blocked++;
            } catch (\Throwable) {
                $blocked++;
            }
        }

        return ['delivered' => $count, 'blocked' => $blocked];
    }
}
