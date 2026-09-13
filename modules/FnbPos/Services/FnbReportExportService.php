<?php

namespace Modules\FnbPos\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Jobs\BuildFnbReportExport;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;

class FnbReportExportService
{
    public function __construct(private readonly FnbCommandRunner $commands, private readonly FnbService $fnb, private readonly FnbOutletAccessService $access) {}

    public function request(FnbContext $ctx, array $input, string $key): array
    {
        $data = validator($input, [
            'report_type' => ['required', Rule::in(['operations', 'financial'])],
            'format' => ['required', Rule::in(['csv'])],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'business_day_id' => ['nullable', 'integer', 'min:1'], 'shift_id' => ['nullable', 'integer', 'min:1'], 'terminal_id' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        return $this->commands->run($ctx, 'report.export', $data, $key, function () use ($ctx, $data, $key): array {
            $actor = Admin::query()->findOrFail($ctx->actorId);
            $this->access->authorize($actor, $ctx->websiteKey, $ctx->outletId, 'fnb.report.'.$data['report_type'].'.view');
            $result = $this->fnb->summaryReport($ctx, array_intersect_key($data, array_flip(['from', 'to', 'business_day_id', 'shift_id', 'terminal_id'])));
            $report = $result['resource'] ?? $result;
            if ($data['report_type'] === 'operations') {
                $report = [
                    'period' => $report['period'],
                    'totals' => array_intersect_key($report['totals'], ['checks' => true, 'orders' => true]),
                    'top_items' => array_map(fn ($item) => array_intersect_key((array) $item, array_flip(['item_id', 'item_code', 'item_name', 'quantity'])), $report['top_items']),
                ];
            }
            $snapshot = json_encode(['filters' => $data, 'report' => $report], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $id = DB::table('fnb_report_exports')->insertGetId([
                'website_key' => $ctx->websiteKey, 'outlet_id' => $ctx->outletId, 'requested_by' => $ctx->actorId,
                'report_type' => $data['report_type'], 'format' => 'csv', 'status' => 'queued',
                'filter_snapshot' => $snapshot, 'filter_hash' => $this->commands->fingerprint(json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR)),
                'idempotency_key' => $key, 'queued_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            BuildFnbReportExport::dispatch($id)->onQueue('fnb')->afterCommit();

            return ['resource' => ['id' => $id, 'status' => 'queued', 'report_type' => $data['report_type'], 'format' => 'csv']];
        });
    }
}
