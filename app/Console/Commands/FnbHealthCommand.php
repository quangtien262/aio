<?php

namespace App\Console\Commands;

use App\Core\Modules\ModuleCapabilityChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FnbHealthCommand extends Command
{
    protected $signature = 'fnb:health {--json : Output machine-readable, non-PII health counters}';

    protected $description = 'Inspect F&B lifecycle, unresolved money operations and background backlog without changing data';

    public function handle(ModuleCapabilityChecker $capabilities): int
    {
        $health = ['installed' => Schema::hasTable('fnb_site_settings'), 'enabled' => $capabilities->enabled('fnb-pos')];
        if ($health['installed']) {
            $health += [
                'draining_sites' => DB::table('fnb_site_settings')->where('operational_state', 'draining')->count(),
                'open_shifts' => DB::table('fnb_shifts')->where('status', 'open')->count(),
                'active_sessions' => DB::table('fnb_service_sessions')->whereIn('status', ['open', 'settling'])->count(),
                'uncertain_payments' => DB::table('fnb_payments')->whereIn('status', ['processing', 'uncertain', 'reconciling'])->count(),
                'uncertain_refunds' => DB::table('fnb_refunds')->whereIn('status', ['processing', 'uncertain', 'reconciling'])->count(),
                'pending_compensations' => DB::table('fnb_fulfillment_compensations')->whereNotNull('active_slot')->count(),
                'outbox_pending' => DB::table('fnb_outbox_deliveries')->whereIn('status', ['pending', 'failed', 'uncertain'])->count(),
                'stock_bridge_pending' => DB::table('fnb_stock_consumptions')->whereIn('status', ['pending', 'failed', 'attention'])->count(),
                'exports_queued' => DB::table('fnb_report_exports')->where('status', 'queued')->count(),
                'exports_failed' => DB::table('fnb_report_exports')->where('status', 'failed')->count(),
            ];
        }
        if ($this->option('json')) {
            $this->line(json_encode($health, JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Signal', 'Value'], collect($health)->map(fn ($value, $key) => [$key, is_bool($value) ? ($value ? 'yes' : 'no') : $value])->values()->all());
        }

        return self::SUCCESS;
    }
}
