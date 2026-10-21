<?php

namespace Modules\Manufacturing\Services\Costing;

use App\Models\Admin;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Services\InventoryProductionService;
use Modules\Manufacturing\Services\Execution\ExecutionService;
use Modules\Manufacturing\Services\ManufacturingData as D;

final class CostingService
{
    public function record(object $factory, array $data, Admin $actor): array
    {
        $v = validator($data, ['order_id' => 'required|uuid', 'category' => 'required|in:labor,overhead,subcontract',
            'amount' => 'required', 'notes' => 'required|string|max:4000'])->validate();
        $order = D::row('mfg_orders', $v['order_id'], $factory->company_id, $factory->id, true);
        abort_if($order->status === 'cancelled', 409, 'Lệnh đã hủy.');
        if ($order->status === 'closed' || (D::json($order->metadata)['cost_closed'] ?? false)) {
            validator($data, ['reopen_reason' => 'required|string|min:5|max:1000'])->validate();
            D::update('mfg_orders', $order, ['status' => $order->status === 'closed' ? 'shipped' : $order->status,
                'metadata' => D::metadata($order, ['cost_closed' => false, 'cost_reopened_by' => $actor->id, 'cost_reopen_reason' => $data['reopen_reason']])], $order->version);
        }
        $entry = D::insert('mfg_cost_entries', ['company_id' => $factory->company_id, 'factory_id' => $factory->id,
            'order_id' => $order->id, 'type' => $v['category'], 'amount' => (string) D::decimal($v['amount'], true),
            'source_module' => 'manufacturing', 'description' => $v['notes'], 'status' => 'approved',
            'metadata' => D::encode(['approved_by' => $actor->id, 'approved_at' => now()->toIso8601String()])]);
        D::event($factory, 'cost.recorded', $order->id, null, ['source_ref' => $entry->public_id, 'category' => $v['category']], $actor->id);

        return D::resource($entry);
    }

    public function adjust(object $factory, string $resource, array $data, Admin $actor, string $key): array
    {
        $v = validator($data, ['reason' => 'required|string|min:5|max:1000', 'expected_version' => 'required|integer|min:1'])->validate();
        $order = D::row('mfg_orders', $resource, $factory->company_id, $factory->id, true);
        abort_unless((int) $order->version === (int) $v['expected_version'], 409, 'Phiên bản lệnh đã đổi.');
        abort_unless(in_array($order->status, ['in_progress', 'partially_shipped', 'shipped'], true), 409, 'Lệnh không ở trạng thái điều chỉnh chi phí.');
        $postings = DB::table('inv_production_postings')->where('order_ref', $order->public_id)->orderBy('id')->lockForUpdate()->get();
        abort_if($postings->isEmpty(), 409, 'Chưa có thành phẩm để phân bổ chi phí.');
        $planned = $produced = $approved = $posted = BigDecimal::zero();
        foreach (DB::table('mfg_order_lines')->where('order_id', $order->id)->get() as $line) {
            $planned = $planned->plus((string) $line->quantity);
            $produced = $produced->plus((string) $line->completed_quantity);
        }
        foreach (DB::table('mfg_cost_entries')->where('order_id', $order->id)->where('status', 'approved')->whereIn('type', ['labor', 'subcontract', 'overhead'])->pluck('amount') as $amount) {
            $approved = $approved->plus((string) $amount);
        }
        foreach ($postings as $posting) {
            $posted = $posted->plus((string) $posting->conversion_cost);
        }
        $prior = $this->adjustedConversion($postings->pluck('id')->all());
        $delta = $approved->multipliedBy($produced)->dividedBy($planned, 6, RoundingMode::HalfUp)->minus($posted)->minus($prior)->toScale(6);
        abort_if($delta->isZero(), 409, 'Không có chênh lệch chi phí chuyển đổi cần phân bổ.');
        $run = D::insert('mfg_cost_runs', ['company_id' => $factory->company_id, 'factory_id' => $factory->id,
            'order_id' => $order->id, 'code' => 'ADJ-'.strtoupper(substr(bin2hex(random_bytes(8)), 0, 16)), 'status' => 'adjusted',
            'total_cost' => (string) $delta, 'output_quantity' => (string) $produced,
            'unit_cost' => (string) $delta->dividedBy($produced, 6, RoundingMode::HalfUp),
            'policy' => D::encode(['revision' => 1, 'basis' => 'approved_conversion_output_quantity', 'reason' => $v['reason']]),
            'posted_by' => $actor->id, 'posted_at' => now()]);
        $remaining = $delta;
        $events = [];
        foreach ($postings as $index => $posting) {
            $quantity = BigDecimal::zero();
            foreach (D::json($posting->outputs) as $output) {
                $quantity = $quantity->plus($output['quantity']);
            }
            $share = $index === $postings->count() - 1 ? $remaining : $delta->multipliedBy($quantity)->dividedBy($produced, 6, RoundingMode::Down);
            $remaining = $remaining->minus($share);
            if ($share->isZero()) {
                continue;
            }
            $event = app(InventoryProductionService::class)->adjustOutputCost(['posting_id' => $posting->public_id,
                'expected_version' => $posting->version, 'amount' => (string) $share, 'source_id' => $run->public_id,
                'reason' => $v['reason']], app(ExecutionService::class)->child($key, 'valuation-'.$posting->public_id), $actor->id);
            $events[] = $event['public_id'];
        }
        DB::table('mfg_cost_runs')->where('id', $run->id)->update(['allocations' => D::encode(['valuation_event_ids' => $events,
            'approved_conversion' => (string) $approved, 'prior_allocated_conversion' => (string) $posted->plus($prior)]), 'updated_at' => now()]);
        D::update('mfg_orders', $order, ['metadata' => D::metadata($order, ['last_cost_adjustment_id' => $run->public_id])], $order->version);
        D::event($factory, 'cost.adjusted', $order->id, null, ['source_ref' => $run->public_id, 'valuation_event_ids' => $events], $actor->id);

        return D::resource(DB::table('mfg_cost_runs')->find($run->id));
    }

    public function close(object $factory, string $resource, array $data, Admin $actor): array
    {
        $order = D::row('mfg_orders', $resource, $factory->company_id, $factory->id, true);
        abort_unless(in_array($order->status, ['in_progress', 'partially_shipped', 'shipped'], true), 409, 'Lệnh chưa có sản xuất hoặc đã khóa.');
        abort_if(D::json($order->metadata)['cost_closed'] ?? false, 409, 'Giá thành đã khóa; mở lại bằng khoản chi phí điều chỉnh có lý do.');
        abort_unless((int) ($data['expected_version'] ?? 0) === (int) $order->version, 409, 'Phiên bản lệnh đã đổi.');
        $material = BigDecimal::zero();
        $allocatedMaterial = BigDecimal::zero();
        $postedConversion = BigDecimal::zero();
        $output = BigDecimal::zero();
        $complete = true;
        foreach (DB::table('mfg_order_lines')->where('order_id', $order->id)->get() as $l) {
            $output = $output->plus((string) $l->completed_quantity);
            $complete = $complete && BigDecimal::of((string) $l->completed_quantity)->isEqualTo((string) $l->quantity);
        }
        foreach (DB::table('inv_production_issues')->where('order_ref', $order->public_id)->get() as $issue) {
            foreach (DB::table('inv_production_issue_layers')->where('issue_id', $issue->id)->get() as $layer) {
                $material = $material->plus(BigDecimal::of((string) $layer->total_cost)->minus((string) $layer->returned_cost));
            }
        }
        $postingRefs = [];
        foreach (DB::table('inv_production_postings')->where('order_ref', $order->public_id)->get() as $posting) {
            $allocatedMaterial = $allocatedMaterial->plus((string) $posting->material_cost);
            $postedConversion = $postedConversion->plus((string) $posting->conversion_cost);
            $postingRefs[] = $posting->public_id;
        }
        $totals = ['labor' => BigDecimal::zero(), 'subcontract' => BigDecimal::zero(), 'overhead' => BigDecimal::zero()];
        foreach (DB::table('mfg_cost_entries')->where('order_id', $order->id)->where('status', 'approved')->get() as $entry) {
            if (isset($totals[$entry->type])) {
                $totals[$entry->type] = $totals[$entry->type]->plus((string) $entry->amount);
            }
        }
        $conversion = $totals['labor']->plus($totals['subcontract'])->plus($totals['overhead']);
        $adjusted = $this->adjustedConversion(DB::table('inv_production_postings')->where('order_ref', $order->public_id)->pluck('id')->all());
        $delta = $conversion->minus($postedConversion)->minus($adjusted)->toScale(6, RoundingMode::HalfUp);
        $pendingMaterial = $material->minus($allocatedMaterial)->toScale(6, RoundingMode::HalfUp);
        $final = $complete && $delta->isZero() && $pendingMaterial->isZero();
        $total = $material->plus($conversion)->toScale(6, RoundingMode::HalfUp);
        $run = D::insert('mfg_cost_runs', ['company_id' => $factory->company_id, 'factory_id' => $factory->id,
            'order_id' => $order->id, 'code' => 'COST-'.strtoupper(substr(bin2hex(random_bytes(8)), 0, 16)),
            'material_cost' => (string) $material->toScale(6, RoundingMode::HalfUp), 'labor_cost' => (string) $totals['labor']->toScale(6, RoundingMode::HalfUp),
            'subcontract_cost' => (string) $totals['subcontract']->toScale(6, RoundingMode::HalfUp), 'overhead_cost' => (string) $totals['overhead']->toScale(6, RoundingMode::HalfUp),
            'total_cost' => (string) $total, 'output_quantity' => (string) $output,
            'unit_cost' => $output->isPositive() ? (string) $allocatedMaterial->plus($postedConversion)->plus($adjusted)->dividedBy($output, 6, RoundingMode::HalfUp) : '0',
            'policy' => D::encode(['revision' => 1, 'basis' => 'actual_material_approved_conversion', 'scrap' => 'absorbed_in_good_output']),
            'allocations' => D::encode(['posting_ids' => $postingRefs, 'unallocated_material' => (string) $pendingMaterial,
                'unallocated_conversion' => (string) $delta, 'output_cost' => (string) $allocatedMaterial->plus($postedConversion)->plus($adjusted),
                'wip_cost' => (string) $pendingMaterial->plus($delta), 'valuation_adjustments' => (string) $adjusted, 'fully_produced' => $complete]),
            'status' => $final ? 'final' : 'needs_adjustment', 'posted_by' => $final ? $actor->id : null, 'posted_at' => $final ? now() : null]);
        if ($final) {
            $shipped = DB::table('mfg_order_lines')->where('order_id', $order->id)->get()->every(fn ($l) => BigDecimal::of((string) $l->shipped_quantity)->isEqualTo((string) $l->quantity));
            D::update('mfg_orders', $order, ['status' => $shipped ? 'closed' : $order->status,
                'metadata' => D::metadata($order, ['cost_closed' => true, 'final_cost_run_id' => $run->public_id])], $order->version);
        }
        D::event($factory, 'cost.calculated', $order->id, null, ['source_ref' => $run->public_id, 'status' => $run->status], $actor->id);

        return D::resource($run);
    }

    private function adjustedConversion(array $postingIds): BigDecimal
    {
        $total = BigDecimal::zero();
        foreach (DB::table('inv_valuation_events')->whereIn('posting_id', $postingIds)->where('source_type', 'late_conversion')->pluck('amount') as $amount) {
            $total = $total->plus((string) $amount);
        }

        return $total;
    }
}
