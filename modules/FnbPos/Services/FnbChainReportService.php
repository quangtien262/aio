<?php

namespace Modules\FnbPos\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;

final class FnbChainReportService
{
    public function __construct(private readonly FnbOutletAccessService $access) {}

    public function summary(Admin $actor, string $website, array $input): array
    {
        $data = validator($input, [
            'scope' => ['required', Rule::in(['outlet', 'region', 'chain'])],
            'outlet_id' => ['required_if:scope,outlet', 'nullable', 'integer', 'min:1'],
            'region_id' => ['required_if:scope,region', 'nullable', 'integer', 'min:1'],
            'report_type' => ['sometimes', Rule::in(['financial', 'operations'])],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.date('Y-m-d', strtotime(($input['from'] ?? 'today').' +366 days'))],
        ])->validate();
        $financial = ($data['report_type'] ?? 'financial') === 'financial';
        $permission = 'fnb.report.'.($financial ? 'financial' : 'operations').'.view';
        if ($data['scope'] !== 'outlet') {
            $this->access->authorizeHq($actor, $website, 'fnb.chain.view');
            $this->access->authorizeHq($actor, $website, $permission);
        }
        // HQ history includes discontinued outlets: closing a shop must not erase its sales.
        $outlets = DB::table('fnb_outlets')->where('website_key', $website)
            ->when($data['scope'] === 'outlet', fn ($q) => $q->where('status', 'active'))
            ->when($data['scope'] === 'outlet', fn ($q) => $q->where('id', $data['outlet_id']))
            ->when($data['scope'] === 'region', fn ($q) => $q->where('region_id', $data['region_id']))
            ->orderBy('id')->get(['id', 'code', 'name', 'status', 'region_id', 'brand_id', 'currency', 'timezone']);
        abort_if($outlets->isEmpty(), 404, 'Không có điểm bán trong phạm vi đã chọn.');
        if ($data['scope'] === 'outlet') {
            $this->access->authorizeTerminal($actor, $website, (int) $outlets->first()->id, null, $permission);
        }
        abort_if($outlets->pluck('currency')->unique()->count() !== 1, 422, 'Chỉ tổng hợp các điểm bán cùng đơn vị tiền tệ.');
        $ids = $outlets->pluck('id')->all();
        $days = DB::table('fnb_business_days')->where('website_key', $website)->whereIn('outlet_id', $ids)
            ->whereBetween('business_date', [$data['from'], $data['to']])->pluck('id');
        $scope = fn (string $table) => DB::table($table)->where('website_key', $website)->whereIn('outlet_id', $ids);
        $payments = $scope('fnb_payments')->whereIn('business_day_id', $days)->where('status', 'succeeded');
        $refunds = $scope('fnb_refunds')->whereIn('processing_business_day_id', $days)->where('status', 'succeeded');
        $gross = (clone $payments)->selectRaw('outlet_id, SUM(amount_minor) AS amount')->groupBy('outlet_id')->pluck('amount', 'outlet_id');
        $returned = (clone $refunds)->selectRaw('outlet_id, SUM(amount_minor) AS amount')->groupBy('outlet_id')->pluck('amount', 'outlet_id');
        $checks = $scope('fnb_checks')->whereIn('business_day_id', $days)->where('status', 'closed');
        $sales = (clone $checks)->selectRaw('outlet_id, SUM(grand_total_minor) AS amount, COUNT(*) AS checks')->groupBy('outlet_id')->get()->keyBy('outlet_id');
        $orders = $scope('fnb_orders')->whereIn('business_day_id', $days)->whereNotIn('lifecycle_status', ['draft', 'cancelled'])
            ->selectRaw('outlet_id, COUNT(*) AS amount')->groupBy('outlet_id')->pluck('amount', 'outlet_id');
        $variance = $scope('fnb_shifts')->whereIn('business_day_id', $days)->whereNotNull('variance_minor')
            ->selectRaw('outlet_id, SUM(variance_minor) AS amount')->groupBy('outlet_id')->pluck('amount', 'outlet_id');
        $totals = ['gross_collected_minor' => 0, 'refund_disbursed_minor' => 0, 'net_collected_minor' => 0,
            'closed_sales_minor' => 0, 'checks' => 0, 'orders' => 0, 'cash_variance_minor' => 0];
        $items = $outlets->map(function ($outlet) use ($gross, $returned, $sales, $orders, $variance, &$totals, $financial): array {
            $values = ['gross_collected_minor' => (int) ($gross[$outlet->id] ?? 0), 'refund_disbursed_minor' => (int) ($returned[$outlet->id] ?? 0),
                'net_collected_minor' => (int) ($gross[$outlet->id] ?? 0) - (int) ($returned[$outlet->id] ?? 0),
                'closed_sales_minor' => (int) ($sales[$outlet->id]->amount ?? 0), 'checks' => (int) ($sales[$outlet->id]->checks ?? 0),
                'orders' => (int) ($orders[$outlet->id] ?? 0), 'cash_variance_minor' => (int) ($variance[$outlet->id] ?? 0)];
            foreach ($values as $key => $value) {
                $totals[$key] += $value;
            }
            $values['average_check_minor'] = $values['checks'] > 0 ? intdiv($values['closed_sales_minor'], $values['checks']) : 0;

            return (array) $outlet + ['totals' => $financial ? $values : array_intersect_key($values, array_flip(['checks', 'orders']))];
        })->all();
        $totals['average_check_minor'] = $totals['checks'] > 0 ? intdiv($totals['closed_sales_minor'], $totals['checks']) : 0;
        $tenders = [];
        foreach (['paid' => $payments, 'refunded' => $refunds] as $kind => $query) {
            foreach ((clone $query)->selectRaw('method_code_snapshot, MIN(method_name_snapshot) AS name, SUM(amount_minor) AS amount')->groupBy('method_code_snapshot')->get() as $row) {
                $code = $row->method_code_snapshot;
                $tenders[$code] ??= ['method_code' => $code, 'method_name' => $row->name, 'gross_paid_minor' => 0, 'refunded_minor' => 0];
                $tenders[$code][$kind === 'paid' ? 'gross_paid_minor' : 'refunded_minor'] = (int) $row->amount;
            }
        }
        foreach ($tenders as &$tender) {
            $tender['net_collected_minor'] = $tender['gross_paid_minor'] - $tender['refunded_minor'];
        }
        unset($tender);
        $top = DB::table('fnb_check_lines as line')->join('fnb_checks as checks', 'checks.id', '=', 'line.check_id')
            ->join('fnb_order_lines as original', 'original.id', '=', 'line.order_line_id')
            ->where('checks.website_key', $website)->where('line.website_key', $website)->whereIn('checks.outlet_id', $ids)
            ->whereIn('checks.business_day_id', $days)->where('checks.status', 'closed')
            ->selectRaw('original.item_id, MIN(original.item_code_snapshot) AS item_code, MIN(original.item_name_snapshot) AS item_name, SUM(line.allocated_quantity) AS quantity, SUM(line.allocated_total_minor) AS gross_sales_minor')
            ->groupBy('original.item_id')->orderByDesc('gross_sales_minor')->get()->map(fn ($row) => $financial ? (array) $row : array_intersect_key((array) $row, array_flip(['item_id', 'item_code', 'item_name', 'quantity'])))->all();
        if ($financial) {
            $topById = [];
            foreach ($top as $row) {
                $topById[$row['item_id']] = $row + ['refunded_minor' => 0, 'refunded_quantity' => '0.000000'];
            }
            $refundItems = DB::table('fnb_refund_allocations as a')->join('fnb_refunds as r', 'r.id', '=', 'a.refund_id')
                ->join('fnb_check_lines as c', 'c.id', '=', 'a.check_line_id')->join('fnb_order_lines as o', 'o.id', '=', 'c.order_line_id')
                ->where('r.website_key', $website)->whereIn('r.outlet_id', $ids)->whereIn('r.processing_business_day_id', $days)
                ->where('r.status', 'succeeded')->where('a.allocation_kind', 'line')
                ->selectRaw('o.item_id, MIN(o.item_code_snapshot) AS item_code, MIN(o.item_name_snapshot) AS item_name, SUM(a.quantity) AS quantity, SUM(a.allocated_total_minor) AS refunded_minor')
                ->groupBy('o.item_id')->get();
            foreach ($refundItems as $refund) {
                $topById[$refund->item_id] ??= ['item_id' => $refund->item_id, 'item_code' => $refund->item_code, 'item_name' => $refund->item_name, 'quantity' => '0.000000', 'gross_sales_minor' => 0];
                $topById[$refund->item_id]['refunded_minor'] = (int) $refund->refunded_minor;
                $topById[$refund->item_id]['refunded_quantity'] = (string) $refund->quantity;
            }
            foreach ($topById as &$row) {
                $row['gross_sales_minor'] = (int) $row['gross_sales_minor'];
                $row['refunded_minor'] = (int) ($row['refunded_minor'] ?? 0);
                $row['net_sales_minor'] = $row['gross_sales_minor'] - $row['refunded_minor'];
                $row['net_quantity'] = bcsub((string) $row['quantity'], (string) ($row['refunded_quantity'] ?? '0'), 6);
            }
            unset($row);
            $top = array_values($topById);
            usort($top, fn ($a, $b) => ($b['net_sales_minor'] <=> $a['net_sales_minor']) ?: ($a['item_id'] <=> $b['item_id']));
        }
        $top = array_slice($top, 0, 50);

        return ['scope' => $data['scope'], 'currency' => $outlets->first()->currency, 'outlets' => $items,
            'totals' => $financial ? $totals : array_intersect_key($totals, array_flip(['checks', 'orders'])),
            'tenders' => $financial ? array_values($tenders) : [], 'top_items' => $top,
            'period' => ['from' => $data['from'], 'to' => $data['to']], 'generated_at' => now()->toIso8601String()];
    }
}
