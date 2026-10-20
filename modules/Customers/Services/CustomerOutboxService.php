<?php

namespace Modules\Customers\Services;

use App\Core\Modules\ModuleCapabilityChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Customers\Events\CustomerChanged;

class CustomerOutboxService
{
    public function dispatch(int $limit = 100): array
    {
        $sent = 0;
        $failed = 0;
        if (! Schema::hasTable('cust_outbox_deliveries') || ! app(ModuleCapabilityChecker::class)->enabled('customers')) {
            return compact('sent', 'failed');
        }
        $ids = DB::table('cust_outbox_deliveries')->whereIn('status', ['pending', 'failed'])->where('attempts', '<', 8)->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()))->orderBy('id')->limit(min(500, max(1, $limit)))->pluck('id');
        foreach ($ids as $id) {
            try {
                DB::transaction(function () use ($id, &$sent) {
                    app(CustomerRuntimeState::class)->guard();
                    $d = DB::table('cust_outbox_deliveries')->where('id', $id)->lockForUpdate()->first();
                    if (! $d || $d->status === 'delivered') {
                        return;
                    }
                    abort_unless(app(ModuleCapabilityChecker::class)->enabled($d->destination), 503, 'App nhận đang tạm dừng.');
                    $e = DB::table('cust_events')->find($d->event_id);
                    $b = DB::table('cust_books')->find($e->book_id);
                    $p = $e->profile_id ? DB::table('cust_profiles')->find($e->profile_id) : null;
                    if ($p) {
                        event(new CustomerChanged($e->event_id, $d->destination, $b->website_key, $p->public_id, $e->event_type, (int) $e->aggregate_version, json_decode($e->changed_fields, true)));
                    }
                    DB::table('cust_outbox_deliveries')->where('id', $id)->update(['status' => 'delivered', 'attempts' => $d->attempts + 1, 'last_error' => null, 'updated_at' => now()]);
                    $sent++;
                }, 3);
            } catch (\Throwable $e) {
                DB::table('cust_outbox_deliveries')->where('id', $id)->where('status', '!=', 'delivered')->update(['status' => 'failed', 'attempts' => DB::raw('attempts + 1'), 'available_at' => now()->addMinutes(5), 'last_error' => 'Giao sự kiện chưa thành công; kiểm tra app nhận và listener.', 'updated_at' => now()]);
                $failed++;
            }
        }

        return compact('sent', 'failed');
    }

    public function pruneExports(): int
    {
        if (! Schema::hasTable('cust_exports')) {
            return 0;
        }$count = 0;
        foreach (DB::table('cust_exports')->where('expires_at', '<=', now())->where('status', '!=', 'expired')->limit(500)->get() as $export) {
            if ($export->path) {
                Storage::disk('local')->delete($export->path);
            }DB::table('cust_exports')->where('id', $export->id)->update(['status' => 'expired', 'path' => null, 'updated_at' => now()]);
            $count++;
        }

        return $count;
    }
}
