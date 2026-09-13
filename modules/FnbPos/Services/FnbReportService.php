<?php

namespace Modules\FnbPos\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Domain\FnbValidationException;

/**
 * Read-only operational/financial projection built exclusively from settled
 * records.  It deliberately reports gross payments and refunds separately:
 * a refund never reopens or reduces the historic paid state of a check.
 */
final class FnbReportService
{
    /** @param array<string,mixed> $filters */
    public function summary(FnbContext $context, array $filters = []): array
    {
        $data = validator($filters, [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'business_day_id' => ['nullable', 'integer', 'min:1'],
            'shift_id' => ['nullable', 'integer', 'min:1'],
            'terminal_id' => ['nullable', 'integer', 'min:1'],
        ])->validate();
        if ($context->terminalId !== null) {
            if (isset($data['terminal_id']) && (int) $data['terminal_id'] !== $context->terminalId) {
                throw new FnbValidationException('Report terminal filter conflicts with the authorized terminal scope.');
            }
            // A terminal-bound context can never widen itself by omitting the
            // query filter. An explicitly unbound context is required for an
            // authorized all-terminal outlet report.
            $data['terminal_id'] = $context->terminalId;
        }

        $outlet = DB::table('fnb_outlets')
            ->where('website_key', $context->websiteKey)
            ->where('id', $context->outletId)
            ->first();
        if ($outlet === null) {
            throw new FnbValidationException('Reporting outlet was not found.');
        }

        $today = CarbonImmutable::now($outlet->timezone)->toDateString();
        $from = (string) ($data['from'] ?? $today);
        $to = (string) ($data['to'] ?? $from);
        $days = DB::table('fnb_business_days')
            ->where('website_key', $context->websiteKey)
            ->where('outlet_id', $context->outletId)
            ->when(isset($data['business_day_id']), fn ($query) => $query->where('id', $data['business_day_id']))
            ->when(! isset($data['business_day_id']), fn ($query) => $query->whereBetween('business_date', [$from, $to]))
            ->orderBy('business_date')
            ->get(['id', 'business_date']);
        if (isset($data['business_day_id']) && $days->isEmpty()) {
            throw new FnbValidationException('Business day is outside this outlet.');
        }
        $dayIds = $days->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $payments = $this->payments($context, $dayIds, $data);
        $refunds = $this->refunds($context, $dayIds, $data);
        $gross = (int) $payments->sum('amount_minor');
        $refunded = (int) $refunds->sum('amount_minor');
        $net = $gross - $refunded;

        $checks = $this->checks($context, $dayIds, $data);
        $orders = $this->orders($context, $dayIds, $data);
        $closedCheckCount = $checks->count();
        $itemSummary = $this->topItems(
            $context,
            $this->saleItemRows($context, $dayIds, $data),
            $refunds,
        );
        $closedSales = $itemSummary['closed_sales_minor'];

        return ['resource' => [
            'period' => [
                'from' => $days->first()?->business_date ?? $from,
                'to' => $days->last()?->business_date ?? $to,
                'timezone' => $outlet->timezone,
                'business_day_ids' => $dayIds,
                'business_day_id' => isset($data['business_day_id']) ? (int) $data['business_day_id'] : null,
                'shift_id' => isset($data['shift_id']) ? (int) $data['shift_id'] : null,
                'terminal_id' => isset($data['terminal_id']) ? (int) $data['terminal_id'] : null,
            ],
            'basis' => [
                'cash_flow' => 'succeeded payments by original business day/processing shift; succeeded refunds by processing business day/shift',
                'closed_sales' => 'immutable check-line snapshots from closed checks, scoped through source order shift/terminal',
                'item_refunds' => 'succeeded refund allocations by refund processing business day/shift',
            ],
            'totals' => [
                // Compatibility aliases retained for the initial frontend.
                'gross_sales_minor' => $gross,
                'refunds_minor' => $refunded,
                'net_sales_minor' => $net,
                'gross_collected_minor' => $gross,
                'refund_disbursed_minor' => $refunded,
                'net_collected_minor' => $net,
                'closed_sales_minor' => $closedSales,
                'checks' => $closedCheckCount,
                'orders' => $orders->count(),
                'average_check_minor' => $closedCheckCount > 0 ? intdiv($closedSales, $closedCheckCount) : 0,
                'cash_variance_minor' => $this->cashVariance($context, $dayIds, $data),
            ],
            'tenders' => $this->tenders($payments, $refunds),
            'top_items' => $itemSummary['items'],
            'generated_at' => now()->toIso8601String(),
        ]];
    }

    /** @param list<int> $dayIds @param array<string,mixed> $filters */
    private function payments(FnbContext $context, array $dayIds, array $filters): Collection
    {
        if ($dayIds === []) {
            return collect();
        }

        return DB::table('fnb_payments')
            ->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)
            ->whereIn('business_day_id', $dayIds)->where('status', 'succeeded')
            ->when(isset($filters['shift_id']), fn ($query) => $query->where('shift_id', $filters['shift_id']))
            ->when(isset($filters['terminal_id']), fn ($query) => $query->where('terminal_id', $filters['terminal_id']))
            ->get(['id', 'check_id', 'payment_method_id', 'method_code_snapshot', 'method_name_snapshot', 'method_kind_snapshot', 'amount_minor']);
    }

    /** @param list<int> $dayIds @param array<string,mixed> $filters */
    private function refunds(FnbContext $context, array $dayIds, array $filters): Collection
    {
        if ($dayIds === []) {
            return collect();
        }

        return DB::table('fnb_refunds')
            ->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)
            ->whereIn('processing_business_day_id', $dayIds)->where('status', 'succeeded')
            ->when(isset($filters['shift_id']), fn ($query) => $query->where('processed_shift_id', $filters['shift_id']))
            ->when(isset($filters['terminal_id']), fn ($query) => $query->where('processed_terminal_id', $filters['terminal_id']))
            ->get(['id', 'payment_id', 'check_id', 'method_code_snapshot', 'method_name_snapshot', 'method_kind_snapshot', 'amount_minor']);
    }

    /** @param list<int> $dayIds @param array<string,mixed> $filters */
    private function checks(FnbContext $context, array $dayIds, array $filters): Collection
    {
        if ($dayIds === []) {
            return collect();
        }

        return DB::table('fnb_checks as checks')
            ->join('fnb_check_lines as allocation', function ($join): void {
                $join->on('allocation.website_key', '=', 'checks.website_key')
                    ->on('allocation.outlet_id', '=', 'checks.outlet_id')
                    ->on('allocation.check_id', '=', 'checks.id');
            })
            ->join('fnb_orders as orders', function ($join): void {
                $join->on('orders.website_key', '=', 'allocation.website_key')
                    ->on('orders.outlet_id', '=', 'allocation.outlet_id')
                    ->on('orders.id', '=', 'allocation.order_id');
            })
            ->where('checks.website_key', $context->websiteKey)->where('checks.outlet_id', $context->outletId)
            ->whereIn('checks.business_day_id', $dayIds)->where('checks.status', 'closed')
            ->when(isset($filters['shift_id']), fn ($query) => $query->where('orders.shift_id', $filters['shift_id']))
            ->when(isset($filters['terminal_id']), fn ($query) => $query->where('orders.terminal_id', $filters['terminal_id']))
            ->distinct()->get(['checks.id', 'checks.status']);
    }

    /** @param list<int> $dayIds @param array<string,mixed> $filters */
    private function orders(FnbContext $context, array $dayIds, array $filters): Collection
    {
        if ($dayIds === []) {
            return collect();
        }

        return DB::table('fnb_orders')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)
            ->whereIn('business_day_id', $dayIds)->whereIn('lifecycle_status', ['active', 'completed'])
            ->when(isset($filters['shift_id']), fn ($query) => $query->where('shift_id', $filters['shift_id']))
            ->when(isset($filters['terminal_id']), fn ($query) => $query->where('terminal_id', $filters['terminal_id']))
            ->get(['id']);
    }

    /** @return list<array<string,mixed>> */
    private function tenders(Collection $payments, Collection $refunds): array
    {
        $result = [];
        foreach ($payments as $payment) {
            $key = (string) $payment->method_code_snapshot;
            $result[$key] ??= [
                'method_code' => $key,
                'method_name' => $payment->method_name_snapshot,
                'method_kind' => $payment->method_kind_snapshot,
                'gross_paid_minor' => 0,
                'refunded_minor' => 0,
                'net_collected_minor' => 0,
                'payment_count' => 0,
                'refund_count' => 0,
            ];
            $result[$key]['gross_paid_minor'] += (int) $payment->amount_minor;
            $result[$key]['payment_count']++;
        }
        foreach ($refunds as $refund) {
            $key = (string) $refund->method_code_snapshot;
            $result[$key] ??= [
                'method_code' => $key,
                'method_name' => $refund->method_name_snapshot,
                'method_kind' => $refund->method_kind_snapshot,
                'gross_paid_minor' => 0,
                'refunded_minor' => 0,
                'net_collected_minor' => 0,
                'payment_count' => 0,
                'refund_count' => 0,
            ];
            $result[$key]['refunded_minor'] += (int) $refund->amount_minor;
            $result[$key]['refund_count']++;
        }
        foreach ($result as &$row) {
            $row['net_collected_minor'] = $row['gross_paid_minor'] - $row['refunded_minor'];
        }
        unset($row);

        return array_values($result);
    }

    /** @param list<int> $dayIds @param array<string,mixed> $filters */
    private function saleItemRows(FnbContext $context, array $dayIds, array $filters): Collection
    {
        if ($dayIds === []) {
            return collect();
        }

        return DB::table('fnb_check_lines as allocation')
            ->join('fnb_checks as checks', function ($join): void {
                $join->on('checks.website_key', '=', 'allocation.website_key')
                    ->on('checks.outlet_id', '=', 'allocation.outlet_id')
                    ->on('checks.id', '=', 'allocation.check_id');
            })
            ->join('fnb_orders as orders', function ($join): void {
                $join->on('orders.website_key', '=', 'allocation.website_key')
                    ->on('orders.outlet_id', '=', 'allocation.outlet_id')
                    ->on('orders.id', '=', 'allocation.order_id');
            })
            ->join('fnb_order_lines as line', function ($join): void {
                $join->on('line.website_key', '=', 'allocation.website_key')
                    ->on('line.outlet_id', '=', 'allocation.outlet_id')
                    ->on('line.id', '=', 'allocation.order_line_id');
            })
            ->where('allocation.website_key', $context->websiteKey)->where('allocation.outlet_id', $context->outletId)
            ->whereIn('checks.business_day_id', $dayIds)->where('checks.status', 'closed')
            ->when(isset($filters['shift_id']), fn ($query) => $query->where('orders.shift_id', $filters['shift_id']))
            ->when(isset($filters['terminal_id']), fn ($query) => $query->where('orders.terminal_id', $filters['terminal_id']))
            ->get(['allocation.id as check_line_id', 'line.item_id', 'line.item_code_snapshot', 'line.item_name_snapshot', 'allocation.allocated_quantity', 'allocation.allocated_total_minor']);
    }

    /**
     * Sales use closed-check snapshots in the selected sales scope; refunds
     * use their processing scope and may therefore appear without a same-day
     * sale (for example, a return of yesterday's item).
     *
     * @return array{items:list<array<string,mixed>>,closed_sales_minor:int}
     */
    private function topItems(FnbContext $context, Collection $saleRows, Collection $refunds): array
    {
        $refundRows = $refunds->isEmpty() ? collect() : DB::table('fnb_refund_allocations as allocation')
            ->join('fnb_check_lines as check_line', function ($join): void {
                $join->on('check_line.website_key', '=', 'allocation.website_key')
                    ->on('check_line.outlet_id', '=', 'allocation.outlet_id')
                    ->on('check_line.id', '=', 'allocation.check_line_id');
            })
            ->join('fnb_order_lines as line', function ($join): void {
                $join->on('line.website_key', '=', 'check_line.website_key')
                    ->on('line.outlet_id', '=', 'check_line.outlet_id')
                    ->on('line.id', '=', 'check_line.order_line_id');
            })
            ->where('allocation.website_key', $context->websiteKey)->where('allocation.outlet_id', $context->outletId)
            ->whereIn('allocation.refund_id', $refunds->pluck('id')->map(fn ($id): int => (int) $id)->all())
            ->where('allocation.allocation_kind', 'line')->whereNotNull('allocation.check_line_id')
            ->get([
                'line.item_id', 'line.item_code_snapshot', 'line.item_name_snapshot',
                'allocation.quantity as refunded_quantity', 'allocation.allocated_total_minor as refunded_minor',
            ]);

        $items = [];
        $closedSales = 0;
        foreach ($saleRows as $row) {
            $id = (int) $row->item_id;
            $items[$id] ??= [
                'item_id' => $id,
                'item_code' => $row->item_code_snapshot,
                'item_name' => $row->item_name_snapshot,
                'quantity' => '0.000000',
                'refunded_quantity' => '0.000000',
                'net_quantity' => '0.000000',
                'gross_sales_minor' => 0,
                'refunded_minor' => 0,
                'net_sales_minor' => 0,
            ];
            $items[$id]['quantity'] = bcadd($items[$id]['quantity'], (string) $row->allocated_quantity, 6);
            $items[$id]['gross_sales_minor'] += (int) $row->allocated_total_minor;
            $closedSales += (int) $row->allocated_total_minor;
        }
        foreach ($refundRows as $row) {
            $id = (int) $row->item_id;
            $items[$id] ??= [
                'item_id' => $id,
                'item_code' => $row->item_code_snapshot,
                'item_name' => $row->item_name_snapshot,
                'quantity' => '0.000000',
                'refunded_quantity' => '0.000000',
                'net_quantity' => '0.000000',
                'gross_sales_minor' => 0,
                'refunded_minor' => 0,
                'net_sales_minor' => 0,
            ];
            $items[$id]['refunded_quantity'] = bcadd($items[$id]['refunded_quantity'], (string) $row->refunded_quantity, 6);
            $items[$id]['refunded_minor'] += (int) $row->refunded_minor;
        }
        foreach ($items as &$row) {
            $row['net_quantity'] = bcsub($row['quantity'], $row['refunded_quantity'], 6);
            $row['net_sales_minor'] = $row['gross_sales_minor'] - $row['refunded_minor'];
        }
        unset($row);
        usort($items, fn (array $left, array $right): int => ($right['net_sales_minor'] <=> $left['net_sales_minor']) ?: ($left['item_id'] <=> $right['item_id'])
        );

        return ['items' => array_slice(array_values($items), 0, 50), 'closed_sales_minor' => $closedSales];
    }

    /** @param list<int> $dayIds @param array<string,mixed> $filters */
    private function cashVariance(FnbContext $context, array $dayIds, array $filters): int
    {
        if ($dayIds === []) {
            return 0;
        }

        return (int) DB::table('fnb_shifts')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)
            ->whereIn('business_day_id', $dayIds)->whereNotNull('variance_minor')
            ->when(isset($filters['shift_id']), fn ($query) => $query->where('id', $filters['shift_id']))
            ->when(isset($filters['terminal_id']), fn ($query) => $query->where('terminal_id', $filters['terminal_id']))
            ->sum('variance_minor');
    }
}
