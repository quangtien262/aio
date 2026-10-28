<?php

namespace Modules\Pharmacy\Services\Sales;

use App\Models\Admin;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Modules\Pharmacy\Services\Dispensing\DispensingService;
use Modules\Pharmacy\Services\Dispensing\TransactionSupport;
use Modules\Pharmacy\Services\PharmacyInventoryService;

final class PharmacySaleService extends TransactionSupport
{
    public function openShift(Admin $actor, array $input, string $key): array
    {
        return $this->command($actor, 'shift.open', $input, $key,
            fn (Admin $live) => $this->pharmacy($live, $input, 'pharmacy.shift.manage'),
            function (Admin $live) use ($input): array {
                $p = $this->pharmacy($live, $input, 'pharmacy.shift.manage', true);
                validator($input, ['counter_id' => 'required|string', 'opening_minor' => 'required'])->validate();
                $counter = $this->scopedRow('ph_counters', $input['counter_id'], $p, true);
                abort_unless($counter->status === 'active', 409, 'Quầy không hoạt động.');
                abort_if(DB::table('ph_shifts')->where('counter_id', $counter->id)->where('status', 'open')->exists(), 409, 'Quầy đã có ca đang mở.');
                $shift = $this->insert('ph_shifts', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'counter_id' => $counter->id, 'admin_id' => $live->id, 'opening_minor' => $this->money($input['opening_minor'])]);
                $this->event($live, $p, 'shift.opened', $shift);

                return $this->shiftSnapshot($shift, $p);
            });
    }

    public function closeShift(Admin $actor, array $input, string $key): array
    {
        validator($input, ['shift_id' => 'required|string', 'closing_minor' => 'required', 'reason' => 'nullable|string|max:2000'])->validate();

        return $this->command($actor, 'shift.close', $input, $key,
            fn (Admin $live) => $this->scopeFor($live, 'ph_shifts', $input['shift_id'], 'pharmacy.shift.manage'),
            function (Admin $live) use ($input): array {
                $p = $this->scopeFor($live, 'ph_shifts', $input['shift_id'], 'pharmacy.shift.manage', true);
                $shift = $this->scopedRow('ph_shifts', $input['shift_id'], $p, true);
                $this->version($shift, $input);
                abort_unless($shift->status === 'open', 409, 'Ca đã đóng.');
                abort_unless((int) $shift->admin_id === (int) $live->id || $live->isSystemOwner(), 403, 'Chỉ người mở ca được đóng ca của mình.');
                $expected = $this->expectedCash($shift);
                $closing = $this->money($input['closing_minor']);
                abort_if($expected !== $closing && empty(trim($input['reason'] ?? '')), 422, 'Chênh lệch ca phải có lý do.');
                $shift = $this->change('ph_shifts', $shift, ['status' => 'closed', 'closed_at' => now(), 'closing_minor' => $closing, 'expected_minor' => $expected, 'difference_minor' => $closing - $expected, 'encrypted_close_reason' => $this->encrypt($input['reason'] ?? '')]);
                $this->event($live, $p, 'shift.closed', $shift);

                return $this->shiftSnapshot($shift, $p);
            });
    }

    public function finalize(Admin $actor, array $input, string $key): array
    {
        validator($input, ['dispensing_id' => 'required|string', 'shift_id' => 'required|string', 'tenders' => 'required|array|min:1|max:10', 'tenders.*.method' => 'required|in:cash,manual_transfer', 'tenders.*.amount_minor' => 'required', 'tenders.*.tendered_minor' => 'nullable', 'tenders.*.reference' => 'nullable|string|max:1000'])->validate();

        return $this->command($actor, 'dispensing.finalize', $input, $key,
            function (Admin $live) use ($input): object {
                $p = $this->scopeFor($live, 'ph_dispensings', $input['dispensing_id'], 'pharmacy.sale.create');
                $this->access()->pharmacy($live, $p->public_id, 'pharmacy.payment.collect');

                return $p;
            }, function (Admin $live) use ($input): array {
                $p = $this->scopeFor($live, 'ph_dispensings', $input['dispensing_id'], 'pharmacy.sale.create', true);
                $this->access()->pharmacy($live, $p->public_id, 'pharmacy.payment.collect', true);
                $d = $this->scopedRow('ph_dispensings', $input['dispensing_id'], $p, true);
                $this->version($d, $input);
                $shift = $this->scopedRow('ph_shifts', $input['shift_id'], $p, true);
                abort_unless($shift->status === 'open' && (int) $shift->admin_id === (int) $live->id, 409, 'Cần ca đang mở của người thu tiền tại đúng nhà thuốc.');
                $dispensing = app(DispensingService::class);
                $dispensing->assertFinalizable($live, $d, $p);
                $lines = $dispensing->lines($d);
                $total = (int) $lines->sum('total_minor');
                $paid = 0;
                foreach ($input['tenders'] as $tender) {
                    $amount = $this->money($tender['amount_minor']);
                    abort_unless($amount > 0, 422, 'Khoản thanh toán phải lớn hơn không.');
                    $paid += $amount;
                    if ($tender['method'] === 'manual_transfer') {
                        abort_if(empty(trim($tender['reference'] ?? '')), 422, 'Chuyển khoản thủ công cần bằng chứng đối soát; không phải xác nhận tự động từ ngân hàng.');
                    }
                    if ($tender['method'] === 'cash') {
                        $tendered = isset($tender['tendered_minor']) ? $this->money($tender['tendered_minor']) : $amount;
                        abort_if($tendered < $amount, 422, 'Tiền khách đưa chưa đủ.');
                    }
                }
                abort_unless($paid === $total, 422, 'Tổng thanh toán phải khớp đúng tổng hóa đơn.');
                $sale = $this->insert('ph_sales', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'dispensing_id' => $d->id, 'shift_id' => $shift->id, 'total_minor' => $total, 'created_by' => $live->id]);
                foreach ($input['tenders'] as $tender) {
                    $amount = $this->money($tender['amount_minor']);
                    $tendered = $tender['method'] === 'cash' ? $this->money($tender['tendered_minor'] ?? $amount) : null;
                    $this->insert('ph_tenders', ['sale_id' => $sale->id, 'method' => $tender['method'], 'amount_minor' => $amount, 'tendered_minor' => $tendered, 'change_minor' => $tendered !== null ? $tendered - $amount : 0, 'encrypted_reference' => isset($tender['reference']) ? $this->encrypt($tender['reference']) : null]);
                }
                foreach (DB::table('ph_dispensing_allocations')->whereIn('dispensing_line_id', $lines->pluck('id'))->where('status', 'reserved')->orderBy('id')->lockForUpdate()->get() as $a) {
                    $result = app(PharmacyInventoryService::class)->commit($live, $p, $a->reservation_id, 'ph:commit:'.$a->public_id);
                    DB::table('ph_dispensing_allocations')->where('id', $a->id)->update(['status' => 'committed', 'operation_id' => $result['operation_id'], 'document_id' => $result['document_id'], 'unit_cost' => $result['unit_cost'], 'total_cost' => $result['total_cost'], 'updated_at' => now()]);
                }
                DB::table('ph_dispense_claims')->whereIn('dispensing_line_id', $lines->pluck('id'))->where('status', 'reserved')->update(['status' => 'committed_pending_handover', 'expires_at' => null, 'updated_at' => now()]);
                $d = $this->change('ph_dispensings', $d, ['status' => 'finalized', 'finalized_at' => now()]);
                $this->event($live, $p, 'sale.finalized', $sale);

                return $dispensing->snapshot($d, $p, $live) + ['sale' => $this->saleSnapshot($sale, $p)];
            });
    }

    public function returnSale(Admin $actor, array $input, string $key): array
    {
        validator($input, ['dispensing_id' => 'required|string', 'lines' => 'required|array|min:1|max:100', 'lines.*.line_id' => 'required|string', 'lines.*.allocation_id' => 'nullable|string', 'lines.*.batch_id' => 'nullable|integer|min:1', 'lines.*.quantity' => 'required', 'reason' => 'required|string|max:2000'])->validate();

        return $this->command($actor, 'dispensing.return', $input, $key,
            fn (Admin $live) => $this->scopeFor($live, 'ph_dispensings', $input['dispensing_id'], 'pharmacy.return.manage'),
            function (Admin $live) use ($input): array {
                $p = $this->scopeFor($live, 'ph_dispensings', $input['dispensing_id'], 'pharmacy.return.manage', true);
                $d = $this->scopedRow('ph_dispensings', $input['dispensing_id'], $p, true);
                $this->version($d, $input);
                abort_unless(in_array($d->status, ['finalized', 'handed_over', 'partially_returned'], true), 409, 'Hồ sơ chưa bán hoặc đã nhận lại hết.');
                $sale = DB::table('ph_sales')->where('dispensing_id', $d->id)->lockForUpdate()->first();
                abort_unless($sale, 409, 'Thiếu giao dịch gốc.');
                $return = $this->insert('ph_returns', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'sale_id' => $sale->id, 'dispensing_id' => $d->id, 'created_by' => $live->id, 'amount_minor' => 0, 'encrypted_reason' => $this->encrypt($input['reason'])]);
                $total = 0;
                foreach ($input['lines'] as $i => $request) {
                    $line = $this->row('ph_dispensing_lines', $request['line_id'], true);
                    abort_unless((int) $line->dispensing_id === (int) $d->id && $line->status === 'active', 404);
                    $product = DB::table('ph_products')->where('id', $line->product_id)->first();
                    abort_unless($product && (int) $product->chain_id === (int) $p->chain_id, 404);
                    $quantity = $this->quantity($request['quantity']);
                    $this->increment($product, $quantity);
                    $query = DB::table('ph_dispensing_allocations')->where('dispensing_line_id', $line->id)->where('status', 'committed');
                    if (! empty($request['allocation_id'])) {
                        $query->where('public_id', $request['allocation_id']);
                    } elseif (! empty($request['batch_id'])) {
                        $query->where('batch_id', $request['batch_id']);
                    }
                    abort_unless((clone $query)->count() === 1, 422, 'Nhận lại phải xác định đúng phân bổ lô đã bán.');
                    $allocation = $query->lockForUpdate()->first();
                    $previousAllocation = $this->decimalSum(DB::table('ph_return_lines')->where('allocation_id', $allocation->id), 'quantity');
                    abort_if($previousAllocation->plus($quantity)->isGreaterThan((string) $allocation->quantity), 422, 'Số lượng nhận lại vượt lượng đã bán ở lô này.');
                    $previousLine = $this->decimalSum(DB::table('ph_return_lines')->where('dispensing_line_id', $line->id), 'quantity');
                    $newQuantity = $previousLine->plus($quantity);
                    abort_if($newQuantity->isGreaterThan((string) $line->quantity), 422, 'Nhận lại vượt dòng bán gốc.');
                    $previousAmount = (int) DB::table('ph_return_lines')->where('dispensing_line_id', $line->id)->sum('amount_minor');
                    $cumulative = BigDecimal::of((string) $line->total_minor)->multipliedBy($newQuantity)->dividedBy((string) $line->quantity, 0, RoundingMode::HalfUp)->toInt();
                    $amount = $cumulative - $previousAmount;
                    abort_if($amount < 0, 409, 'Số tiền nhận lại không nhất quán.');
                    $operation = app(PharmacyInventoryService::class)->returnToQuarantine($live, $p, $product, (int) $allocation->batch_id, $quantity, (string) $allocation->unit_cost, $allocation->operation_id, 'ph:return:'.$return->public_id.':'.$i);
                    $this->insert('ph_return_lines', ['return_id' => $return->id, 'dispensing_line_id' => $line->id, 'allocation_id' => $allocation->id, 'batch_id' => $allocation->batch_id, 'quantity' => $quantity, 'amount_minor' => $amount, 'inventory_operation_id' => $operation['operation_id'] ?? $operation['public_id']]);
                    $total += $amount;
                }
                abort_if((int) $sale->returned_minor + $total > (int) $sale->total_minor, 409, 'Giá trị nhận lại vượt hóa đơn.');
                DB::table('ph_returns')->where('id', $return->id)->update(['amount_minor' => $total]);
                $sale = $this->change('ph_sales', $sale, ['returned_minor' => (int) $sale->returned_minor + $total]);
                $fullyReturned = true;
                foreach (app(DispensingService::class)->lines($d) as $line) {
                    $returned = $this->decimalSum(DB::table('ph_return_lines')->where('dispensing_line_id', $line->id), 'quantity');
                    $fullyReturned = $fullyReturned && $returned->isEqualTo((string) $line->quantity);
                }
                $d = $this->change('ph_dispensings', $d, ['status' => $fullyReturned ? 'returned' : 'partially_returned']);
                $this->insert('ph_custody_events', ['dispensing_id' => $d->id, 'actor_id' => $live->id, 'action' => 'returned_to_quarantine', 'encrypted_evidence' => $this->encrypt(['return_id' => $return->public_id, 'reason' => $input['reason']])]);
                $this->event($live, $p, 'sale.returned', $sale);

                return app(DispensingService::class)->snapshot($d, $p, $live) + ['return_id' => $return->public_id, 'return_amount_minor' => $total, 'disposition' => 'quarantine'];
            });
    }

    public function refund(Admin $actor, array $input, string $key): array
    {
        validator($input, ['dispensing_id' => 'required|string', 'return_id' => 'nullable|string', 'shift_id' => 'required|string', 'amount_minor' => 'required', 'method' => 'required|in:cash', 'reason' => 'required|string|max:2000'])->validate();

        return $this->command($actor, 'dispensing.refund', $input, $key,
            function (Admin $live) use ($input): object {
                $p = $this->scopeFor($live, 'ph_dispensings', $input['dispensing_id'], 'pharmacy.return.manage');
                $this->access()->pharmacy($live, $p->public_id, 'pharmacy.payment.collect');

                return $p;
            }, function (Admin $live) use ($input): array {
                $p = $this->scopeFor($live, 'ph_dispensings', $input['dispensing_id'], 'pharmacy.return.manage', true);
                $this->access()->pharmacy($live, $p->public_id, 'pharmacy.payment.collect', true);
                $d = $this->scopedRow('ph_dispensings', $input['dispensing_id'], $p, true);
                $this->version($d, $input);
                $sale = DB::table('ph_sales')->where('dispensing_id', $d->id)->lockForUpdate()->first();
                abort_unless($sale, 409);
                $query = DB::table('ph_returns')->where('sale_id', $sale->id)->whereColumn('refunded_minor', '<', 'amount_minor');
                if (! empty($input['return_id'])) {
                    $query->where('public_id', $input['return_id']);
                }
                abort_unless((clone $query)->count() === 1, 422, 'Chọn đúng hồ sơ nhận lại đang chờ hoàn tiền.');
                $return = $query->lockForUpdate()->first();
                $amount = $this->money($input['amount_minor']);
                abort_unless($amount > 0 && $amount <= (int) $return->amount_minor - (int) $return->refunded_minor && $amount <= (int) $sale->total_minor - (int) $sale->refunded_minor, 422, 'Tiền hoàn vượt giá trị được phép.');
                $shift = $this->scopedRow('ph_shifts', $input['shift_id'], $p, true);
                abort_unless($shift->status === 'open' && (int) $shift->admin_id === (int) $live->id, 409, 'Hoàn tiền cần ca đang mở của người thu/chi.');
                // A manual bank transfer cannot silently become an unrecorded cash payout.
                $cashPaid = (int) DB::table('ph_tenders')->where('sale_id', $sale->id)->where('method', 'cash')->sum('amount_minor');
                $cashRefunded = (int) DB::table('ph_refunds')->where('sale_id', $sale->id)->where('method', 'cash')->sum('amount_minor');
                abort_if($amount > $cashPaid - $cashRefunded, 409, 'Chưa mở hoàn tiền ngoài tiền mặt; không được đổi phương thức hoàn.');
                $refund = $this->insert('ph_refunds', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'sale_id' => $sale->id, 'return_id' => $return->id, 'shift_id' => $shift->id, 'created_by' => $live->id, 'amount_minor' => $amount, 'method' => 'cash', 'status' => 'paid', 'encrypted_reason' => $this->encrypt($input['reason'])]);
                $return = $this->change('ph_returns', $return, ['refunded_minor' => (int) $return->refunded_minor + $amount, 'status' => (int) $return->refunded_minor + $amount === (int) $return->amount_minor ? 'refunded' : 'partially_refunded']);
                $sale = $this->change('ph_sales', $sale, ['refunded_minor' => (int) $sale->refunded_minor + $amount, 'status' => (int) $sale->refunded_minor + $amount === (int) $sale->total_minor ? 'refunded' : 'partially_refunded']);
                $d = $this->change('ph_dispensings', $d, []);
                $this->event($live, $p, 'sale.refunded', $sale);

                return app(DispensingService::class)->snapshot($d, $p, $live) + ['refund_id' => $refund->public_id, 'refunded_minor' => (int) $sale->refunded_minor];
            });
    }

    public function listShifts(Admin $actor, array $filters): array
    {
        $p = $this->pharmacy($actor, $filters, 'pharmacy.shift.manage');
        $query = $this->filterList(DB::table('ph_shifts')->where('pharmacy_id', $p->id), $filters, $p, ['public_id'], fn ($q, $search) => $q->orWhereIn('counter_id', DB::table('ph_counters')->where('pharmacy_id', $p->id)->where(fn ($q) => $q->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%'))->select('id')));

        return $this->pageList($query, $filters, fn ($r) => $this->shiftSnapshot($r, $p));
    }

    public function listSales(Admin $actor, array $filters): array
    {
        $p = $this->pharmacy($actor, $filters, 'pharmacy.sale.view');
        $query = $this->filterList(DB::table('ph_sales')->where('pharmacy_id', $p->id), $filters, $p, ['public_id'], fn ($q, $search) => $q->orWhereIn('dispensing_id', DB::table('ph_dispensings')->where('pharmacy_id', $p->id)->where('public_id', 'like', '%'.$search.'%')->select('id')));

        return $this->pageList($query, $filters, fn ($r) => $this->saleSnapshot($r, $p));
    }

    public function showSale(Admin $actor, string $id): array
    {
        $p = $this->scopeFor($actor, 'ph_sales', $id, 'pharmacy.sale.view');

        return $this->saleSnapshot($this->scopedRow('ph_sales', $id, $p), $p);
    }

    private function saleSnapshot(object $sale, object $p): array
    {
        return $this->ref($sale) + ['pharmacy_id' => $p->public_id, 'dispensing_id' => DB::table('ph_dispensings')->where('id', $sale->dispensing_id)->value('public_id'),
            'total_minor' => (int) $sale->total_minor, 'returned_minor' => (int) $sale->returned_minor, 'refunded_minor' => (int) $sale->refunded_minor,
            'tenders' => DB::table('ph_tenders')->where('sale_id', $sale->id)->get()->map(fn ($t) => ['id' => $t->public_id, 'method' => $t->method, 'amount_minor' => (int) $t->amount_minor, 'change_minor' => (int) $t->change_minor, 'status' => $t->status])->all()];
    }

    private function expectedCash(object $shift): int
    {
        $paid = (int) DB::table('ph_tenders')->join('ph_sales', 'ph_sales.id', '=', 'ph_tenders.sale_id')->where('ph_sales.shift_id', $shift->id)->where('ph_tenders.method', 'cash')->sum('ph_tenders.amount_minor');
        $refunded = (int) DB::table('ph_refunds')->where('shift_id', $shift->id)->where('method', 'cash')->sum('amount_minor');

        $cashIn = (int) DB::table('ph_cash_movements')->where('shift_id', $shift->id)->where('direction', 'in')->sum('amount_minor');
        $cashOut = (int) DB::table('ph_cash_movements')->where('shift_id', $shift->id)->where('direction', 'out')->sum('amount_minor');

        return (int) $shift->opening_minor + $paid - $refunded + $cashIn - $cashOut;
    }

    private function shiftSnapshot(object $shift, object $p): array
    {
        return $this->ref($shift) + ['pharmacy_id' => $p->public_id, 'counter_id' => DB::table('ph_counters')->where('id', $shift->counter_id)->value('public_id'), 'admin_id' => $shift->admin_id,
            'cash_movements' => DB::table('ph_cash_movements')->where('shift_id', $shift->id)->get(['public_id as id', 'direction', 'amount_minor', 'created_at'])->toArray(),
            'opening_minor' => (int) $shift->opening_minor, 'expected_minor' => $shift->expected_minor !== null ? (int) $shift->expected_minor : $this->expectedCash($shift),
            'closing_minor' => $shift->closing_minor !== null ? (int) $shift->closing_minor : null, 'difference_minor' => $shift->difference_minor !== null ? (int) $shift->difference_minor : null];
    }
}
