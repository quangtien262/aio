<?php

namespace App\Console\Commands;

use App\Core\Modules\ModuleCapabilityChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\FnbPos\Jobs\ReconcileFnbIntegration;

final class FnbDispatchIntegrationsCommand extends Command
{
    protected $signature = 'fnb:dispatch-integrations {--limit=50}';

    protected $description = 'Dispatch only explicitly authorized local F&B integrations; workers revalidate live authority';

    public function handle(ModuleCapabilityChecker $capabilities): int
    {
        if (! $capabilities->enabled('fnb-pos') || ! Schema::hasTable('fnb_integration_mappings')) {
            return self::SUCCESS;
        }
        $ids = DB::table('fnb_integration_mappings')->where('enabled', true)->where('automatic', true)->whereNotNull('authorized_by')
            ->orderBy('last_dispatched_at')->orderBy('id')->limit(max(1, min(500, (int) $this->option('limit'))))->pluck('id');
        foreach ($ids as $id) {
            $claimed = DB::table('fnb_integration_mappings')->where('id', $id)->where('enabled', true)->where('automatic', true)
                ->update(['last_dispatched_at' => now()]);
            if ($claimed) {
                ReconcileFnbIntegration::dispatch((int) $id);
            }
        }
        $this->info('Queued '.$ids->count().' explicitly enabled integration(s).');

        return self::SUCCESS;
    }
}
