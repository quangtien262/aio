<?php

namespace Modules\Manufacturing\Services\Quality;

use App\Models\Admin;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Services\Execution\ExecutionService;
use Modules\Manufacturing\Services\ManufacturingData as D;

final class QualityService
{
    public function hold(object $factory, string $resource, array $data, Admin $actor, bool $release = false): array
    {
        $v = validator($data, ['reason' => 'required|string|min:5|max:2000', 'expected_version' => 'required|integer|min:1'])->validate();
        $b = D::row('mfg_bundles', $resource, $factory->company_id, $factory->id, true);
        $meta = D::json($b->metadata);
        abort_unless($release ? ($b->status === 'quality_hold' && isset($meta['manual_hold'])) : $b->status === 'active', 409, 'Trạng thái bó không phù hợp để giữ/giải phóng QC.');
        if ($release) {
            abort_if(DB::table('mfg_reworks')->where('bundle_id', $b->id)->where('status', 'open')->exists(), 409, 'Còn phiếu sửa chưa xử lý.');
            $meta['hold_history'][] = $meta['manual_hold'] + ['released_by' => $actor->id, 'released_at' => now()->toIso8601String(), 'release_reason' => $v['reason']];
            unset($meta['manual_hold']);
        } else {
            $meta['manual_hold'] = ['reason' => $v['reason'], 'held_by' => $actor->id, 'held_at' => now()->toIso8601String()];
        }
        $b = D::update('mfg_bundles', $b, ['status' => $release ? 'active' : 'quality_hold', 'metadata' => D::encode($meta)], $v['expected_version']);
        D::event($factory, $release ? 'quality.released' : 'quality.held', $b->order_id, $b->id, ['source_ref' => $b->public_id, 'reason' => $v['reason']], $actor->id);

        return D::resource($b);
    }

    public function inspect(object $factory, array $data, Admin $actor): array
    {
        $b = D::row('mfg_bundles', (string) ($data['bundle_id'] ?? ''), $factory->company_id, $factory->id, true);
        $meta = D::json($b->metadata);
        $checkpoint = $data['checkpoint'] ?? 'final';
        abort_unless(in_array($checkpoint, ['inline', 'end_line', 'final'], true), 422, 'Điểm kiểm chất lượng không hợp lệ.');
        $final = $checkpoint === 'final';
        $openReworks = DB::table('mfg_reworks')->where('bundle_id', $b->id)->where('status', 'open')->get();
        abort_if(! $final && $openReworks->isNotEmpty(), 409, 'Hoàn tất phiếu sửa đang mở trước đợt kiểm tiếp theo.');
        abort_if($final && $openReworks->contains(fn ($r) => (D::json($r->metadata)['checkpoint'] ?? 'final') !== 'final'
            || (D::json($r->metadata)['subcontract_completion'] ?? false)), 409, 'Còn phần sửa tại công đoạn hoặc gia công chưa hoàn tất.');
        abort_unless(in_array($b->status, ['active', 'quality_hold'], true) && (! $final || ($meta['routing_complete'] ?? false)), 409, 'Bó cần ở xưởng và hoàn tất routing trước kiểm cuối.');
        $qty = D::pieces($data['quantity'] ?? '');
        $good = D::pieces($data['good_quantity'] ?? '0', true);
        $rework = D::pieces($data['rework_quantity'] ?? '0', true);
        $scrap = D::pieces($data['scrap_quantity'] ?? '0', true);
        abort_unless(BigDecimal::of($good)->plus($rework)->plus($scrap)->isEqualTo($qty), 422, 'Tổng đạt, sửa và loại phải bằng lượng kiểm.');
        $inspected = BigDecimal::of($final ? ($meta['qc_inspected'] ?? '0') : '0')->plus($qty);
        $operationScrap = BigDecimal::of($meta['operation_scrap'] ?? $meta['scrap_quantity'] ?? '0');
        abort_if($inspected->plus($operationScrap)->isGreaterThan((string) $b->quantity), 409, 'Đã kiểm số lượng này; dùng tái kiểm cho phần cần sửa.');
        abort_if(! $final && BigDecimal::of($scrap)->isPositive(), 422, 'Ghi loại tại công đoạn hoặc kiểm cuối để bảo toàn lượng routing.');
        $inspection = D::insert('mfg_quality_inspections', ['company_id' => $factory->company_id, 'factory_id' => $factory->id,
            'order_id' => $b->order_id, 'bundle_id' => $b->id, 'checkpoint' => $checkpoint, 'inspected_quantity' => $qty,
            'accepted_quantity' => $good, 'rejected_quantity' => (string) BigDecimal::of($rework)->plus($scrap),
            'defects' => D::encode(['code' => mb_substr((string) ($data['defect_code'] ?? ''), 0, 100), 'rework' => $rework, 'scrap' => $scrap]),
            'measurements' => D::encode($data['measurements'] ?? []), 'inspected_by' => $actor->id, 'inspected_at' => now(),
            'status' => BigDecimal::of($rework)->isPositive() ? 'rework' : (BigDecimal::of($scrap)->isPositive() ? 'rejected' : 'accepted'),
            'metadata' => D::encode(['notes' => mb_substr((string) ($data['notes'] ?? ''), 0, 4000), 'policy' => ['mode' => 'full_count', 'revision' => 1]])]);
        if (BigDecimal::of($rework)->isPositive()) {
            D::insert('mfg_reworks', ['company_id' => $factory->company_id, 'factory_id' => $factory->id,
                'order_id' => $b->order_id, 'bundle_id' => $b->id, 'inspection_id' => $inspection->id,
                'quantity' => $rework, 'reason' => $data['notes'] ?? $data['defect_code'] ?? 'Sửa lỗi QC', 'operation_key' => $b->operation_key, 'status' => 'open',
                'metadata' => D::encode(['checkpoint' => $checkpoint])]);
        }
        $meta['operation_scrap'] = (string) $operationScrap;
        if ($final) {
            $meta['qc_inspected'] = (string) $inspected;
        }
        $meta['scrap_quantity'] = (string) BigDecimal::of($meta['scrap_quantity'] ?? '0')->plus($scrap);
        $pending = DB::table('mfg_reworks')->where('bundle_id', $b->id)->where('status', 'open')->exists();
        D::update('mfg_bundles', $b, ['accepted_quantity' => (string) BigDecimal::of((string) $b->accepted_quantity)->plus($final ? $good : '0'),
            'rejected_quantity' => (string) BigDecimal::of((string) $b->rejected_quantity)->plus($scrap),
            'status' => $pending || isset($meta['manual_hold']) ? 'quality_hold' : 'active', 'metadata' => D::encode($meta)], $data['expected_version'] ?? null);
        D::event($factory, 'quality.inspected', $b->order_id, $b->id, ['quantity' => $qty, 'source_ref' => $inspection->public_id], $actor->id);

        return D::resource($inspection);
    }

    public function rework(object $factory, array $data, Admin $actor): array
    {
        $b = D::row('mfg_bundles', (string) ($data['bundle_id'] ?? ''), $factory->company_id, $factory->id, true);
        abort_unless($b->status === 'quality_hold', 409, 'Bó không có phần cần tái kiểm.');
        $q = DB::table('mfg_reworks')->where('bundle_id', $b->id)->where('status', 'open')->orderBy('id');
        if (! empty($data['rework_id'])) {
            $q->where('public_id', $data['rework_id']);
        }
        $r = $q->lockForUpdate()->first();
        abort_unless($r, 409, 'Không có phiếu sửa đang mở.');
        $qty = D::pieces($data['quantity'] ?? '');
        $good = D::pieces($data['good_quantity'] ?? '0', true);
        $scrap = D::pieces($data['scrap_quantity'] ?? '0', true);
        abort_unless(BigDecimal::of($good)->plus($scrap)->isEqualTo($qty), 422, 'Tốt + loại phải bằng lượng tái kiểm.');
        $done = BigDecimal::of((string) $r->completed_quantity)->plus($qty);
        abort_if($done->isGreaterThan((string) $r->quantity), 409, 'Tái kiểm vượt phần còn phải sửa.');
        D::update('mfg_reworks', $r, ['completed_quantity' => (string) $done, 'status' => $done->isEqualTo((string) $r->quantity) ? 'completed' : 'open'], $r->version);
        $meta = D::json($b->metadata);
        $pending = DB::table('mfg_reworks')->where('bundle_id', $b->id)->where('status', 'open')->exists();
        $external = D::json($r->metadata)['subcontract_completion'] ?? false;
        if ($external) {
            $subMeta = D::json($r->metadata);
            $subcontract = D::row('mfg_subcontracts', (string) $subMeta['subcontract_id'], $factory->company_id, $factory->id, true);
            $fee = D::decimal($subMeta['unit_cost'], true)->multipliedBy($good)->toScale(6, RoundingMode::HalfUp);
            if ($fee->isPositive()) {
                D::insert('mfg_cost_entries', ['company_id' => $factory->company_id, 'factory_id' => $factory->id,
                    'order_id' => $b->order_id, 'type' => 'subcontract', 'source_module' => 'manufacturing',
                    'source_id' => $r->public_id.':'.$r->version, 'amount' => (string) $fee,
                    'description' => 'Nghiệm thu sau sửa gia công '.$subcontract->code, 'status' => 'approved']);
            }
            D::update('mfg_subcontracts', $subcontract, ['fee_amount' => (string) BigDecimal::of((string) $subcontract->fee_amount)->plus($fee),
                'scrap_quantity' => (string) BigDecimal::of((string) $subcontract->scrap_quantity)->plus($scrap)], $subcontract->version);
            $receipt = $meta['subcontract_receipt'];
            $receipt['good'] = (string) BigDecimal::of($receipt['good'])->plus($good);
            $receipt['scrap'] = (string) BigDecimal::of($receipt['scrap'])->plus($scrap);
            $meta['subcontract_receipt'] = $receipt;
            $b = D::update('mfg_bundles', $b, ['status' => $pending ? 'quality_hold' : 'active', 'metadata' => D::encode($meta)], $data['expected_version'] ?? null);
            if (! $pending) {
                return app(ExecutionService::class)->operation($factory, $b->public_id, ['expected_version' => $b->version,
                    'quantity' => $b->quantity, 'good_quantity' => $receipt['good'], 'scrap_quantity' => $receipt['scrap']], $actor);
            }
        } elseif (in_array(D::json($r->metadata)['checkpoint'] ?? 'final', ['inline', 'end_line'], true)) {
            abort_if(BigDecimal::of($scrap)->isPositive(), 422, 'Ghi loại tại công đoạn hoặc kiểm cuối.');
            $b = D::update('mfg_bundles', $b, ['status' => $pending || isset($meta['manual_hold']) ? 'quality_hold' : 'active'], $data['expected_version'] ?? null);
        } else {
            $meta['scrap_quantity'] = (string) BigDecimal::of($meta['scrap_quantity'] ?? '0')->plus($scrap);
            $b = D::update('mfg_bundles', $b, ['accepted_quantity' => (string) BigDecimal::of((string) $b->accepted_quantity)->plus($good),
                'rejected_quantity' => (string) BigDecimal::of((string) $b->rejected_quantity)->plus($scrap),
                'status' => $pending || isset($meta['manual_hold']) ? 'quality_hold' : 'active', 'metadata' => D::encode($meta)], $data['expected_version'] ?? null);
        }
        D::event($factory, 'quality.reworked', $b->order_id, $b->id, ['quantity' => $qty, 'source_ref' => $r->public_id], $actor->id);

        return D::resource($b);
    }
}
