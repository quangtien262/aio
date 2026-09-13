<?php

namespace Modules\FnbPos\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\FnbPos\Services\FnbReportExportService;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;

class FnbReportExportController
{
    public function store(Request $request, FnbApiController $api, FnbCommandSecurity $security, FnbReportExportService $exports)
    {
        $data = $request->only(['report_type', 'format', 'from', 'to', 'business_day_id', 'shift_id', 'terminal_id']);
        $key = (string) $request->header('Idempotency-Key', '');
        validator(['key' => $key], ['key' => ['required', 'string', 'min:8', 'max:120']])->validate();
        $ctx = $api->context($request, 'fnb.report.export', false, $security->authorization($request, 'report.export', $data));
        $result = $exports->request($ctx, $data, $key);

        return response()->json(['data' => $result['resource'], 'meta' => ['replayed' => $result['replayed']]], 202);
    }

    public function download(Request $request, FnbApiController $api, FnbOutletAccessService $access)
    {
        $ctx = $api->context($request, 'fnb.report.export');
        $export = DB::table('fnb_report_exports')->where('website_key', $ctx->websiteKey)->where('outlet_id', $ctx->outletId)
            ->where('requested_by', $ctx->actorId)->where('id', (int) $request->route('resource'))->first();
        abort_unless($export, 404);
        $access->authorize($request->user('admin'), $ctx->websiteKey, $ctx->outletId, 'fnb.report.'.$export->report_type.'.view');
        abort_unless($export->status === 'completed' && $export->expires_at && now()->lessThan($export->expires_at), 410, 'Bản xuất chưa hoàn tất hoặc đã hết hạn.');
        $prefix = 'fnb/exports/'.hash('sha256', $ctx->websiteKey).'/'.$ctx->outletId.'/';
        abort_unless(str_starts_with((string) $export->private_path, $prefix) && ! str_contains($export->private_path, '..'), 409);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($export->private_path), 404);
        $content = $disk->get($export->private_path);
        abort_unless(hash_equals((string) $export->checksum, hash('sha256', $content)), 409, 'Tệp không khớp bản xuất đã xác nhận.');

        return response($content)->withHeaders([
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="fnb-report-'.$export->id.'.csv"',
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
