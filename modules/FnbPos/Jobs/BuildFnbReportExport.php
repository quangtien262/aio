<?php

namespace Modules\FnbPos\Jobs;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\Admin;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\FnbPos\Services\FnbCommandRunner;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;
use RuntimeException;
use Throwable;

class BuildFnbReportExport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public array $backoff = [15, 30, 60, 120];

    public function __construct(public readonly int $exportId) {}

    public function uniqueId(): string
    {
        return 'fnb-export:'.$this->exportId;
    }

    public function handle(ModuleCapabilityChecker $capabilities, FnbOutletAccessService $access): void
    {
        if (! $capabilities->enabled('fnb-pos')) {
            $this->release(60);

            return;
        }
        DB::transaction(function () use ($access): void {
            $export = DB::table('fnb_report_exports')->where('id', $this->exportId)->lockForUpdate()->first();
            if (! $export || in_array($export->status, ['completed', 'expired'], true)) {
                return;
            }
            $actor = Admin::query()->find($export->requested_by);
            if (! $actor || ! $actor->isAvailable()) {
                throw new RuntimeException('Export requester is no longer active.');
            }
            $access->authorize($actor, $export->website_key, (int) $export->outlet_id, 'fnb.report.export');
            $access->authorize($actor, $export->website_key, (int) $export->outlet_id, 'fnb.report.'.$export->report_type.'.view');
            $snapshot = json_decode($export->filter_snapshot, true, 512, JSON_THROW_ON_ERROR);
            if (! hash_equals($export->filter_hash, app(FnbCommandRunner::class)->fingerprint($snapshot))) {
                throw new RuntimeException('Report snapshot checksum mismatch.');
            }
            $stream = fopen('php://temp', 'w+');
            if ($stream === false) {
                throw new RuntimeException('Unable to create export stream.');
            }
            try {
                fwrite($stream, "\xEF\xBB\xBF");
                $this->row($stream, ['section', 'metric', 'value', 'unit']);
                foreach ($snapshot['report']['totals'] as $metric => $value) {
                    $this->row($stream, ['totals', $metric, $value, str_ends_with($metric, '_minor') ? 'minor' : 'count']);
                }
                $financial = $export->report_type === 'financial';
                $this->row($stream, $financial
                    ? ['items', 'code', 'name', 'quantity', 'gross_sales_minor', 'refunded_minor', 'net_sales_minor']
                    : ['items', 'code', 'name', 'quantity']);
                foreach ($snapshot['report']['top_items'] ?? [] as $item) {
                    $row = ['item', $item['item_code'] ?? '', $item['item_name'] ?? '', $item['quantity'] ?? ''];
                    if ($financial) {
                        $row = [...$row, $item['gross_sales_minor'] ?? '', $item['refunded_minor'] ?? '', $item['net_sales_minor'] ?? ''];
                    }
                    $this->row($stream, $row);
                }
                rewind($stream);
                $bytes = stream_get_contents($stream);
                if ($bytes === false) {
                    throw new RuntimeException('Unable to read export stream.');
                }
            } finally {
                fclose($stream);
            }
            $path = 'fnb/exports/'.hash('sha256', $export->website_key).'/'.$export->outlet_id.'/'.Str::uuid().'.csv';
            $temporary = $path.'.tmp';
            $disk = Storage::disk('local');
            if (! $disk->put($temporary, $bytes) || ! $disk->move($temporary, $path)) {
                throw new RuntimeException('Unable to persist private export.');
            }
            DB::table('fnb_report_exports')->where('id', $export->id)->update([
                'status' => 'completed', 'private_path' => $path, 'checksum' => hash('sha256', $bytes),
                'attempts' => $export->attempts + 1, 'completed_at' => now(), 'expires_at' => now()->addDay(),
                'last_error' => null, 'updated_at' => now(),
            ]);
        });
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('fnb_report_exports')->where('id', $this->exportId)->whereNotIn('status', ['completed', 'expired'])->update([
            'status' => 'failed', 'last_error' => 'Không thể tạo bản xuất. Kiểm tra quyền, module và cấu hình lưu trữ.', 'updated_at' => now(),
        ]);
    }

    /** Neutralize spreadsheet formulas including whitespace/control prefixes. */
    private function row($stream, array $values): void
    {
        $values = array_map(function ($value): string {
            $value = (string) $value;
            if (preg_match('/^[\s\x00-\x1f]*[=+@-]/u', $value)) {
                return "'".$value;
            }

            return $value;
        }, $values);
        fputcsv($stream, $values, ',', '"', '');
    }
}
