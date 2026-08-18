<?php

use App\Jobs\AccountingTax\DispatchPendingEinvoiceTransmissions;
use App\Jobs\AccountingTax\PruneExpiredAccountingExports;
use App\Jobs\AccountingTax\ScheduleMsmiInboundSyncs;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('audit:verify-chain --json')
    ->dailyAt('02:10')
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/audit-chain.log'));

Schedule::job(new DispatchPendingEinvoiceTransmissions)
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

Schedule::job(new ScheduleMsmiInboundSyncs)
    ->dailyAt('01:30')
    ->withoutOverlapping(60);

Schedule::job(new PruneExpiredAccountingExports)
    ->dailyAt('03:10')
    ->withoutOverlapping(60);
