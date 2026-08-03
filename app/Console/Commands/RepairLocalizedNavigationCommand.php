<?php

namespace App\Console\Commands;

use App\Core\Cms\CmsMenuLinkIdentityBackfill;
use App\Support\Localization\CmsPageRouteRepair;
use Illuminate\Console\Command;

final class RepairLocalizedNavigationCommand extends Command
{
    protected $signature = 'localization:repair-navigation
        {--website= : Chỉ sửa một website_key}
        {--dry-run : Chỉ báo cáo, không ghi dữ liệu}
        {--json : Xuất báo cáo JSON}';

    protected $description = 'Backfill identity của Menu và canonical route CMS Page theo locale.';

    public function handle(
        CmsMenuLinkIdentityBackfill $menuBackfill,
        CmsPageRouteRepair $pageRouteRepair,
    ): int {
        $websiteKey = trim((string) $this->option('website')) ?: null;
        $dryRun = (bool) $this->option('dry-run');
        $report = [
            'dry_run' => $dryRun,
            'website_key' => $websiteKey,
            'cms_page_routes' => $pageRouteRepair->run(
                $websiteKey,
                $dryRun,
            ),
            'cms_menu_links' => $menuBackfill->run(
                $websiteKey,
                $dryRun,
            ),
        ];
        $hasErrors = $report['cms_page_routes']['errors'] !== [];

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $report,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));
        } else {
            $this->info($dryRun
                ? 'Localization navigation repair (dry-run)'
                : 'Localization navigation repair');
            $this->table(
                ['Scope', 'Scanned', 'Changed', 'Unchanged', 'Errors'],
                [
                    [
                        'CMS Page routes',
                        $report['cms_page_routes']['translations_scanned'],
                        $report['cms_page_routes']['routes_repaired'],
                        $report['cms_page_routes']['routes_unchanged'],
                        count($report['cms_page_routes']['errors']),
                    ],
                    [
                        'CMS Menu links',
                        $report['cms_menu_links']['menus_scanned'],
                        $report['cms_menu_links']['menus_updated'],
                        '-',
                        0,
                    ],
                ],
            );
        }

        return $hasErrors ? self::FAILURE : self::SUCCESS;
    }
}
