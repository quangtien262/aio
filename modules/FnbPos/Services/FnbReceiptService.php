<?php

namespace Modules\FnbPos\Services;

use App\Models\Admin;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\Security\FnbAuditLogger;

final class FnbReceiptService
{
    public function __construct(private readonly FnbCommandRunner $commands, private readonly FnbAuditLogger $audit) {}

    public function generate(FnbContext $ctx, int $checkId, string $key): array
    {
        return $this->commands->run($ctx, 'receipt.generate', ['check_id' => $checkId], $key, function () use ($ctx, $checkId, $key): array {
            $check = $this->scope('fnb_checks', $ctx)->where('id', $checkId)->lockForUpdate()->firstOrFail();
            if ($check->finalized_at === null || in_array($check->status, ['void', 'voided'], true)) {
                throw new FnbConflictException('Chỉ tạo phiếu cho hóa đơn đã chốt và chưa hủy.');
            }
            $outlet = DB::table('fnb_outlets')->where('website_key', $ctx->websiteKey)->where('id', $ctx->outletId)->firstOrFail();
            $lines = DB::table('fnb_check_lines as cl')->join('fnb_order_lines as ol', 'ol.id', '=', 'cl.order_line_id')
                ->where('cl.website_key', $ctx->websiteKey)->where('cl.outlet_id', $ctx->outletId)->where('cl.check_id', $checkId)
                ->orderBy('cl.id')->get(['cl.id', 'cl.order_line_id', 'cl.allocated_quantity', 'cl.allocated_total_minor',
                    'ol.item_code_snapshot', 'ol.item_name_snapshot', 'ol.variant_code_snapshot', 'ol.variant_name_snapshot']);
            $modifiers = $this->scope('fnb_order_line_modifiers', $ctx)->whereIn('order_line_id', $lines->pluck('order_line_id'))
                ->get(['order_line_id', 'group_name_snapshot', 'option_name_snapshot', 'quantity'])->groupBy('order_line_id');
            $snapshot = [
                'document_type' => 'sales_receipt', 'not_tax_invoice' => true, 'template_version' => 1,
                'outlet' => ['name' => $outlet->name, 'address' => $outlet->address, 'phone' => $outlet->phone],
                'check' => [
                    'check_no' => $check->check_no, 'currency' => $check->currency, 'timezone' => $check->timezone_snapshot,
                    'finalized_at' => $check->finalized_at, 'subtotal_minor' => (int) $check->subtotal_minor,
                    'discount_total_minor' => (int) $check->discount_total_minor, 'tax_total_minor' => (int) $check->tax_total_minor,
                    'service_charge_total_minor' => (int) $check->service_charge_total_minor,
                    'grand_total_minor' => (int) $check->grand_total_minor, 'cash_rounding_minor' => $check->cash_rounding_minor,
                    'settlement_total_minor' => $check->settlement_total_minor,
                ],
                'lines' => $lines->map(fn ($line) => [
                    'id' => $line->id, 'quantity' => (string) $line->allocated_quantity, 'total_minor' => (int) $line->allocated_total_minor,
                    'item' => ['code' => $line->item_code_snapshot, 'name' => $line->item_name_snapshot],
                    'variant' => ['code' => $line->variant_code_snapshot, 'name' => $line->variant_name_snapshot],
                    'modifiers' => ($modifiers[$line->order_line_id] ?? collect())->map(fn ($modifier) => [
                        'group_name' => $modifier->group_name_snapshot, 'name' => $modifier->option_name_snapshot, 'quantity' => (string) $modifier->quantity,
                    ])->all(),
                ])->all(),
                'payments' => $this->scope('fnb_payments', $ctx)->where('check_id', $checkId)->where('status', 'succeeded')
                    ->orderBy('id')->get(['method_name_snapshot', 'amount_minor', 'tendered_minor', 'change_minor', 'processed_at'])->map(fn ($payment) => (array) $payment)->all(),
                'generated_at' => now()->toIso8601String(),
            ];
            $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $id = DB::table('fnb_print_jobs')->insertGetId([
                'website_key' => $ctx->websiteKey, 'outlet_id' => $ctx->outletId, 'terminal_id' => $ctx->terminalId,
                'document_type' => 'check', 'document_id' => (string) $checkId, 'template_key' => 'cafe-receipt', 'template_version' => 1,
                'payload_snapshot' => $json, 'payload_hash' => $this->commands->fingerprint($snapshot), 'status' => 'generated',
                'idempotency_key' => $key, 'request_count' => 0, 'reprint_count' => 0, 'version' => 1,
                'requested_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->record($ctx, $id, 'generated');

            return $this->read($ctx, $id);
        });
    }

    public function transition(FnbContext $ctx, int $id, string $action, int $version, string $key): array
    {
        return $this->commands->run($ctx, 'receipt.'.$action, compact('id', 'action', 'version'), $key, function () use ($ctx, $id, $action, $version): array {
            $job = $this->scope('fnb_print_jobs', $ctx)->where('id', $id)->lockForUpdate()->firstOrFail();
            if ((int) $job->version !== $version) {
                throw new FnbConflictException('Phiếu in đã thay đổi. Vui lòng tải lại.', ['version' => $job->version]);
            }
            if ($action === 'request') {
                $changes = ['status' => 'print_requested', 'request_count' => $job->request_count + 1,
                    'reprint_count' => max(0, (int) $job->request_count), 'requested_at' => now(), 'user_confirmed_at' => null];
            } else {
                if ($action !== 'confirm' || $job->status !== 'print_requested') {
                    throw new FnbConflictException('Chưa có yêu cầu in cần xác nhận.');
                }
                $changes = ['status' => 'user_confirmed', 'user_confirmed_at' => now()];
            }
            $this->scope('fnb_print_jobs', $ctx)->where('id', $id)->update($changes + ['version' => $version + 1, 'updated_at' => now()]);
            $this->record($ctx, $id, $changes['status']);

            return $this->read($ctx, $id);
        });
    }

    public function read(FnbContext $ctx, int $id): array
    {
        $job = $this->scope('fnb_print_jobs', $ctx)->where('id', $id)->firstOrFail();
        $snapshot = json_decode($job->payload_snapshot, true, 512, JSON_THROW_ON_ERROR);
        // MySQL JSON normalizes key order; the fingerprint must use canonical decoded values.
        $storedHash = $this->commands->fingerprint($snapshot);
        if (! hash_equals($job->payload_hash, $storedHash)) {
            throw new FnbConflictException('Checksum phiếu in không hợp lệ.');
        }

        return ['id' => $job->id, 'status' => $job->status, 'version' => $job->version,
            'request_count' => $job->request_count, 'reprint_count' => $job->reprint_count,
            'user_confirmed_at' => $job->user_confirmed_at, 'snapshot' => $snapshot];
    }

    private function scope(string $table, FnbContext $ctx): Builder
    {
        return DB::table($table)->where('website_key', $ctx->websiteKey)->where('outlet_id', $ctx->outletId);
    }

    private function record(FnbContext $ctx, int $id, string $state): void
    {
        $this->audit->record('fnb.receipt.'.$state, $ctx->websiteKey, Admin::findOrFail($ctx->actorId), 'print_job:'.$id,
            null, ['print_job_id' => $id, 'state' => $state], [$ctx->outletId]);
    }
}
