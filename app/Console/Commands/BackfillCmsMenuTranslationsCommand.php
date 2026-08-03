<?php

namespace App\Console\Commands;

use App\Core\Cms\CmsMenuTranslationBackfill;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BackfillCmsMenuTranslationsCommand extends Command
{
    protected $signature = 'localization:backfill-menus
        {--website= : Chỉ backfill một website_key}
        {--dry-run : Phân tích và rollback toàn bộ thay đổi}';

    protected $description = 'Chuẩn hóa bản dịch Menu sang payload item_key v2 mà không xóa dữ liệu cũ.';

    public function handle(CmsMenuTranslationBackfill $backfill): int
    {
        $websiteKey = strtolower(trim((string) $this->option('website')));
        $websiteKey = $websiteKey !== '' ? $websiteKey : null;
        $dryRun = (bool) $this->option('dry-run');

        DB::beginTransaction();

        try {
            $report = $backfill->run($websiteKey);

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        $this->info($dryRun
            ? 'Đã phân tích backfill Menu; không lưu thay đổi.'
            : 'Đã backfill bản dịch Menu.');
        $this->table(
            ['Chỉ số', 'Số lượng'],
            collect($report)
                ->map(fn (int $value, string $key): array => [$key, $value])
                ->values()
                ->all(),
        );

        return self::SUCCESS;
    }
}
