<?php

namespace App\Console\Commands;

use App\Core\Modules\ModuleCapabilityChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\FnbPos\Jobs\BuildFnbReportExport;

final class FnbRecoverExportsCommand extends Command
{
    protected $signature = 'fnb:recover-exports {--limit=100}';

    protected $description = 'Requeue stale F&B exports after a lost after-commit dispatch; never retry financial mutations';

    public function handle(ModuleCapabilityChecker $capabilities): int
    {
        if (! $capabilities->enabled('fnb-pos') || ! Schema::hasTable('fnb_report_exports')) {
            return self::SUCCESS;
        }
        $limit = max(1, min(500, (int) $this->option('limit')));
        $ids = DB::table('fnb_report_exports')->where('status', 'queued')
            ->where('queued_at', '<', now()->subMinutes(5))->orderBy('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            BuildFnbReportExport::dispatch((int) $id)->onQueue('fnb');
        }
        $this->info('Queued '.$ids->count().' stale export(s).');

        return self::SUCCESS;
    }
}
