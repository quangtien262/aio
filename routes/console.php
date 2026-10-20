<?php

use App\Jobs\AccountingTax\DispatchPendingEinvoiceTransmissions;
use App\Jobs\AccountingTax\PruneExpiredAccountingExports;
use App\Jobs\AccountingTax\ScheduleMsmiInboundSyncs;
use App\Models\SiteProfile;
use App\Support\SitemapService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\Customers\Services\CustomerOutboxService;

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

Schedule::command('fnb:recover-exports --limit=100')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

Schedule::command('fnb:dispatch-integrations --limit=50')
    ->everyMinute()
    ->withoutOverlapping(5);

Artisan::command('sitemap:refresh', function () {
    $sitemaps = app(SitemapService::class);
    foreach (SiteProfile::withoutGlobalScopes()->distinct()->pluck('website_key') as $website) {
        $snapshot = $sitemaps->snapshot($website ?: 'website-main', true);
        $this->line(($website ?: 'website-main').': '.$snapshot['total'].' URLs');
    }
})->purpose('Refresh public sitemaps for every website');

Schedule::command('sitemap:refresh')->everyFiveMinutes()->withoutOverlapping(5);

Artisan::command('customers:dispatch-events {--limit=100}', function () {
    $this->line(json_encode(app(CustomerOutboxService::class)->dispatch((int) $this->option('limit')), JSON_THROW_ON_ERROR));
})->purpose('Deliver versioned customer events to installed consumer extensions');
Artisan::command('customers:prune-exports', function () {
    $this->line((string) app(CustomerOutboxService::class)->pruneExports());
})->purpose('Remove expired private customer export files');
Schedule::command('customers:dispatch-events --limit=100')->everyMinute()->withoutOverlapping(5);
Schedule::command('customers:prune-exports')->hourly()->withoutOverlapping(10);
