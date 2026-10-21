<?php

namespace Modules\Manufacturing\Services\Commands;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Services\Runtime\ManufacturingRuntimeState;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ManufacturingOutboxService
{
    public function dispatch(int $limit = 100): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'blocked' => 0];
        if (! Schema::hasTable('mfg_outbox') || ! app(ModuleCapabilityChecker::class)->enabled('manufacturing') || app(ManufacturingRuntimeState::class)->state()['paused']) {
            return $result;
        }
        $ids = DB::table('mfg_outbox')->whereIn('status', ['pending', 'failed'])->where('attempts', '<', 8)
            ->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()))->orderBy('id')->limit(min(500, max(1, $limit)))->pluck('id');
        foreach ($ids as $id) {
            try {
                DB::transaction(function () use ($id, &$result): void {
                    app(ManufacturingRuntimeState::class)->guard(true);
                    $event = DB::table('mfg_outbox')->where('id', $id)->lockForUpdate()->first();
                    if (! $event || $event->status === 'delivered' || $event->status === 'blocked') {
                        return;
                    }
                    if ($event->destination !== 'audit') {
                        DB::table('mfg_outbox')->where('id', $id)->update(['status' => 'blocked', 'last_error' => 'Chưa có adapter được cấu hình và nghiệm thu cho destination này.', 'updated_at' => now()]);
                        $result['blocked']++;

                        return;
                    }
                    app(ManufacturingCommandService::class)->consume('manufacturing.audit', $event->event_id, function () use ($event): void {
                        app(AuditLogger::class)->record('manufacturing.event.delivered', 'manufacturing-event', null, [
                            'event_id' => $event->event_id, 'event_type' => $event->event_type, 'aggregate_type' => $event->aggregate_type,
                            'aggregate_id' => $event->aggregate_id, 'aggregate_version' => $event->aggregate_version, 'schema_version' => $event->schema_version,
                        ], null, 'manufacturing', '', 'manufacturing');
                    });
                    DB::table('mfg_outbox')->where('id', $id)->update(['status' => 'delivered', 'attempts' => $event->attempts + 1, 'delivered_at' => now(), 'last_error' => null, 'updated_at' => now()]);
                    $result['sent']++;
                }, 3);
            } catch (\Throwable $e) {
                if ($e instanceof HttpException && in_array($e->getStatusCode(), [423, 503], true)) {
                    $result['blocked']++;
                    break;
                }
                DB::table('mfg_outbox')->where('id', $id)->where('status', '!=', 'delivered')->update(['status' => 'failed', 'attempts' => DB::raw('attempts + 1'), 'available_at' => now()->addMinutes(5), 'last_error' => 'Giao sự kiện thất bại; kiểm tra worker và audit storage.', 'updated_at' => now()]);
                report($e);
                $result['failed']++;
            }
        }

        return $result;
    }
}
