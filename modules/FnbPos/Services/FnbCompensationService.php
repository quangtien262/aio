<?php

namespace Modules\FnbPos\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Domain\MinorMoney;

/** Operator-confirmed local refund and immutable stock reclassification. No provider calls. */
final class FnbCompensationService
{
    private const COMPONENTS = ['allocated_subtotal_minor', 'allocated_discount_minor', 'allocated_service_charge_minor',
        'allocated_tax_minor', 'allocated_pricing_rounding_minor', 'allocated_total_minor'];

    public function __construct(private readonly FnbCommandRunner $commands, private readonly FnbSettlementService $settlement,
        private readonly FnbKitchenEventService $kitchen, private readonly FnbOutboxService $outbox,
        private readonly FnbTenderAllocationService $tenderAllocator) {}

    public function compensate(FnbContext $ctx, int $checkLineId, array $input, string $key): array
    {
        $data = validator($input, ['shift_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'regex:/^[1-9]\d*(\.\d{1,6})?$|^0\.\d{1,6}$/'],
            'expected_version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:3', 'max:500']])->validate();

        return $this->commands->run($ctx, 'fulfillment.compensate', $data + ['check_line_id' => $checkLineId], $key, function () use ($ctx, $checkLineId, $data, $key): array {
            $allocation = $this->locked('fnb_check_lines', $ctx, $checkLineId);
            $check = $this->locked('fnb_checks', $ctx, (int) $allocation->check_id);
            $order = $this->locked('fnb_orders', $ctx, (int) $allocation->order_id);
            $line = $this->locked('fnb_order_lines', $ctx, (int) $allocation->order_line_id);
            if ((int) $order->version !== (int) $data['expected_version']) {
                throw new FnbConflictException('Đơn đã thay đổi.', ['version' => (int) $order->version]);
            }
            if ($check->status !== 'closed' || ! in_array($line->status, ['waiting', 'preparing', 'ready'], true)) {
                throw new FnbConflictException('Chỉ bồi hoàn món đã thanh toán và chưa phục vụ.');
            }
            if ($this->scope('fnb_fulfillment_compensations', $ctx)->where('check_line_id', $checkLineId)->whereNotNull('active_slot')->exists()) {
                throw new FnbConflictException('Món đang có yêu cầu bồi hoàn chưa kết thúc.');
            }
            $quantity = MinorMoney::quantityMicros($data['quantity']);
            $sourceQuantity = MinorMoney::quantityMicros((string) $allocation->allocated_quantity);
            $prior = DB::table('fnb_refund_allocations as a')->join('fnb_refunds as r', 'r.id', '=', 'a.refund_id')
                ->where('a.website_key', $ctx->websiteKey)->where('a.outlet_id', $ctx->outletId)->where('a.check_line_id', $checkLineId)
                ->whereIn('r.status', ['requested', 'reserved', 'processing', 'uncertain', 'reconciling', 'succeeded'])->get(['a.*']);
            $linked = $this->scope('fnb_fulfillment_compensation_refund_allocations', $ctx)->whereIn('refund_allocation_id', $prior->pluck('id'))->pluck('refund_allocation_id');
            if ($prior->count() !== $linked->count()) {
                throw new FnbConflictException('Món có hoàn tiền riêng trước đó; cần đối soát trước khi bồi hoàn fulfillment.');
            }
            $priorQuantity = (int) bcmul($prior->reduce(fn ($total, $row) => bcadd($total, (string) $row->quantity, 6), '0'), '1000000', 0);
            $remaining = $sourceQuantity - $priorQuantity;
            $unserved = bcsub(bcsub(bcsub((string) $line->ordered_quantity, (string) $line->fulfilled_quantity, 6), (string) $line->voided_quantity, 6), (string) $line->compensated_quantity, 6);
            if ($quantity > $remaining || bccomp($data['quantity'], $unserved, 6) > 0) {
                throw new FnbConflictException('Số lượng bồi hoàn vượt phần chưa hoàn/chưa phục vụ.');
            }
            $components = [];
            foreach (self::COMPONENTS as $component) {
                if ($component === 'allocated_total_minor') {
                    continue;
                }
                $components[$component] = $quantity === $remaining
                    ? (int) $allocation->$component - (int) $prior->sum($component)
                    : $this->proportion((int) $allocation->$component, $quantity, $sourceQuantity);
            }
            $components['allocated_total_minor'] = $components['allocated_subtotal_minor'] - $components['allocated_discount_minor']
                + $components['allocated_service_charge_minor'] + $components['allocated_tax_minor'] + $components['allocated_pricing_rounding_minor'];
            $amount = $components['allocated_total_minor'];
            $grossRemaining = (int) $this->scope('fnb_check_financial_projections', $ctx)->where('check_id', $check->id)->value('net_collected_total_minor');
            $cashRounding = (int) ($check->cash_rounding_minor ?? 0);
            $roundingForRefund = 0;
            if ($amount + $cashRounding === $grossRemaining) {
                $amount += $cashRounding;
                $roundingForRefund = $cashRounding;
            }
            if ($amount < 1) {
                throw new FnbConflictException('Bồi hoàn cần giá trị dương; món miễn phí cần quy trình ghi nhận riêng.');
            }
            $tenders = [];
            $left = $amount;
            foreach ($this->scope('fnb_payments', $ctx)->where('check_id', $check->id)->where('status', 'succeeded')->orderBy('id')->lockForUpdate()->get() as $payment) {
                if ($payment->provider_connection_key !== null || $left === 0) {
                    continue;
                }
                $used = (int) $this->scope('fnb_refunds', $ctx)->where('payment_id', $payment->id)
                    ->whereIn('status', ['requested', 'reserved', 'processing', 'uncertain', 'reconciling', 'succeeded'])->sum('amount_minor');
                $take = min($left, max(0, (int) $payment->amount_minor - $used));
                if ($take > 0) {
                    $tenders[(int) $payment->id] = $take;
                    $left -= $take;
                }
            }
            if ($left > 0) {
                throw new FnbConflictException('Khoản thu thủ công không còn đủ hạn mức hoàn; giao dịch provider cần đối soát riêng.');
            }
            $tenderVectors = $this->tenderAllocator->allocate(array_diff_key($components, ['allocated_total_minor' => true])
                + ['allocated_cash_rounding_minor' => $roundingForRefund], $quantity, $tenders);
            $ticketLine = $this->scope('fnb_kitchen_ticket_lines', $ctx)->where('order_line_id', $line->id)->lockForUpdate()->first();
            $financial = $components + ['refund_total_minor' => $amount, 'check_line_id' => $checkLineId];
            $kitchen = ['prior_status' => $line->status, 'ticket_line_id' => $ticketLine?->id, 'requested_quantity' => $data['quantity']];
            $id = DB::table('fnb_fulfillment_compensations')->insertGetId([
                'website_key' => $ctx->websiteKey, 'outlet_id' => $ctx->outletId, 'public_id' => (string) Str::uuid(),
                'business_day_id' => $check->business_day_id, 'currency' => $check->currency, 'session_id' => $check->session_id,
                'order_id' => $order->id, 'order_line_id' => $line->id, 'check_id' => $check->id, 'check_line_id' => $checkLineId,
                'kitchen_line_id' => $ticketLine?->id, 'requested_quantity' => $data['quantity'],
                'financial_snapshot' => json_encode($financial, JSON_THROW_ON_ERROR), 'kitchen_snapshot' => json_encode($kitchen, JSON_THROW_ON_ERROR),
                'recipe_snapshot' => $line->recipe_snapshot, 'snapshot_hash' => $this->commands->fingerprint([$financial, $kitchen, $line->recipe_snapshot]),
                'refund_group_key' => 'compensation:'.$key, 'status' => 'requested', 'reason' => $data['reason'],
                'approval_id' => $ctx->authorizationEvidence->approvalId(),
                'idempotency_key' => $key, 'request_fingerprint' => $this->commands->fingerprint($data), 'active_slot' => 'active',
                'requested_at' => now(), 'requested_by' => $ctx->actorId, 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            // Inner trusted context: outer command already checked BOTH executor permissions and consumed dual approval.
            $trusted = new FnbContext(
                $ctx->websiteKey,
                $ctx->outletId,
                $ctx->actorId,
                $ctx->terminalId,
                authorizationEvidence: $ctx->authorizationEvidence,
            );
            $refundIds = [];
            foreach ($tenderVectors as $paymentId => $vector) {
                $cash = $vector['allocated_cash_rounding_minor'];
                $allocations = [[
                    'check_line_id' => $checkLineId, 'allocation_kind' => 'line', 'quantity' => MinorMoney::formatQuantity($vector['quantity_micros']),
                    ...array_intersect_key($vector, array_flip(self::COMPONENTS)),
                    'allocated_cash_rounding_minor' => 0, 'allocated_total_minor' => $vector['refund_total_minor'] - $cash,
                ]];
                if ($cash !== 0) {
                    $allocations[] = array_fill_keys(self::COMPONENTS, 0) + [
                        'check_line_id' => null, 'allocation_kind' => 'cash_rounding', 'quantity' => null, 'allocated_cash_rounding_minor' => $cash,
                    ];
                    $allocations[1]['allocated_total_minor'] = $cash;
                }
                $refundResult = $this->settlement->refundAllocated($trusted, $paymentId, (int) $data['shift_id'], [
                    'amount_minor' => $vector['refund_total_minor'], 'reason' => $data['reason'], 'allocations' => $allocations,
                ], 'comp-refund:'.hash('sha256', $key.':'.$paymentId));
                $refundId = (int) $refundResult['resource']['refund']['id'];
                $refundIds[] = $refundId;
                $refund = $this->locked('fnb_refunds', $ctx, $refundId);
                if ($refund->status !== 'succeeded') {
                    throw new FnbConflictException('Provider không đồng bộ chưa được hỗ trợ trong bản bồi hoàn này.');
                }
                foreach ($this->scope('fnb_refund_allocations', $ctx)->where('refund_id', $refundId)->get() as $refundAllocation) {
                    DB::table('fnb_fulfillment_compensation_refund_allocations')->insert([
                        'website_key' => $ctx->websiteKey, 'outlet_id' => $ctx->outletId, 'compensation_id' => $id,
                        'refund_id' => $refundId, 'refund_allocation_id' => $refundAllocation->id, 'check_id' => $check->id,
                        'source_check_line_id' => $checkLineId, 'allocation_check_line_id' => $refundAllocation->check_line_id,
                        'allocation_kind' => $refundAllocation->allocation_kind, 'quantity' => $refundAllocation->quantity,
                        ...array_intersect_key((array) $refundAllocation, array_flip([...self::COMPONENTS, 'allocated_cash_rounding_minor'])),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
            $this->reclassifyStock($ctx, $id, $check, $line, $data['quantity'], (string) $allocation->allocated_quantity, $line->status);
            $compensated = bcadd((string) $line->compensated_quantity, $data['quantity'], 6);
            $fullyCancelled = bccomp($compensated, bcsub((string) $line->ordered_quantity, (string) $line->voided_quantity, 6), 6) === 0;
            $state = $fullyCancelled ? 'cancelled_compensated' : $line->status;
            $this->scope('fnb_order_lines', $ctx)->where('id', $line->id)->update(['compensated_quantity' => $compensated, 'status' => $state, 'updated_at' => now()]);
            if ($ticketLine) {
                $this->scope('fnb_kitchen_ticket_lines', $ctx)->where('id', $ticketLine->id)->update([
                    'compensated_quantity' => $compensated, 'status_rollup' => $state, 'cancelled_at' => $fullyCancelled ? now() : null,
                    'version' => $ticketLine->version + 1, 'updated_at' => now(),
                ]);
                $ticket = $this->locked('fnb_kitchen_tickets', $ctx, (int) $ticketLine->ticket_id);
                $remainingTicket = $this->scope('fnb_kitchen_ticket_lines', $ctx)->where('ticket_id', $ticket->id)->whereIn('status_rollup', ['waiting', 'preparing', 'ready'])->exists();
                $this->scope('fnb_kitchen_tickets', $ctx)->where('id', $ticket->id)->update([
                    'version' => $ticket->version + 1, 'status_rollup' => $remainingTicket ? $ticket->status_rollup : 'cancelled_mixed', 'updated_at' => now(),
                ]);
                $this->kitchen->append($ctx, 'compensation:'.$id.':resolved', 'ticket_line.compensated', [
                    'ticket_id' => $ticket->id, 'ticket_line_id' => $ticketLine->id, 'quantity' => $data['quantity'], 'status' => $state, 'reason' => $data['reason'],
                ], (int) $ticket->prep_station_id, (int) $ticket->id, (int) $ticketLine->id);
            }
            $active = $this->scope('fnb_order_lines', $ctx)->where('order_id', $order->id)->whereIn('status', ['draft', 'waiting', 'preparing', 'ready', 'compensation_pending'])->exists();
            $this->scope('fnb_orders', $ctx)->where('id', $order->id)->update([
                'version' => $order->version + 1, 'lifecycle_status' => $active ? $order->lifecycle_status : 'completed',
                'fulfillment_status' => $active ? $order->fulfillment_status : 'completed', 'completed_at' => $active ? null : now(), 'updated_at' => now(),
            ]);
            $this->scope('fnb_fulfillment_compensations', $ctx)->where('id', $id)->update([
                'status' => 'resolved', 'active_slot' => null, 'resolved_at' => now(), 'resolved_by' => $ctx->actorId, 'version' => 2, 'updated_at' => now(),
            ]);
            $event = $this->outbox->emit($ctx, 'fulfillment_compensation', $id, 2, 'fnb.fulfillment.compensated', [
                'compensation_id' => $id, 'check_id' => $check->id, 'check_line_id' => $checkLineId, 'refund_ids' => $refundIds, 'quantity' => $data['quantity'],
            ]);

            return ['resource' => ['id' => $id, 'status' => 'resolved', 'version' => 2, 'refund_ids' => $refundIds,
                'requested_quantity' => $data['quantity'], 'refund_total_minor' => $amount], 'events' => [$event],
                'meta' => ['resource_type' => 'fulfillment_compensation', 'resource_id' => $id, 'version' => 2]];
        });
    }

    private function reclassifyStock(FnbContext $ctx, int $compensationId, object $check, object $line, string $quantity, string $allocatedQuantity, string $priorState): void
    {
        $source = $this->scope('fnb_stock_consumptions', $ctx)->where('kind', 'sale')->where('source_type', 'check')->where('source_id', (string) $check->id)->lockForUpdate()->first();
        if (! $source) {
            throw new FnbConflictException('Không tìm thấy sổ tiêu hao của hóa đơn đã đóng.');
        }
        $sourceLines = $this->scope('fnb_stock_consumption_lines', $ctx)->where('consumption_id', $source->id)->where('order_line_id', $line->id)->get();
        $alreadyCompensated = $this->scope('fnb_fulfillment_compensations', $ctx)->where('check_id', $check->id)
            ->where('order_line_id', $line->id)->where('status', 'resolved')->get(['requested_quantity'])
            ->reduce(fn ($sum, $row) => bcadd($sum, (string) $row->requested_quantity, 6), '0');
        $isFinalRemainder = bccomp(bcadd($alreadyCompensated, $quantity, 6), $allocatedQuantity, 6) === 0;
        $vectors = [];
        foreach ($sourceLines as $sourceLine) {
            $prior = DB::table('fnb_stock_consumption_lines as l')->join('fnb_stock_consumptions as c', 'c.id', '=', 'l.consumption_id')
                ->where('l.source_consumption_line_id', $sourceLine->id)->where('c.kind', 'reversal')->get(['l.quantity'])
                ->reduce(fn ($sum, $row) => bcadd($sum, (string) $row->quantity, 6), '0');
            $reversalQuantity = bcdiv(bcmul((string) $sourceLine->quantity, $quantity, 12), $allocatedQuantity, 6);
            $remainder = bcsub((string) $sourceLine->quantity, $prior, 6);
            if ($isFinalRemainder) {
                $reversalQuantity = $remainder;
            }
            if (bccomp($reversalQuantity, $remainder, 6) > 0) {
                throw new FnbConflictException('Đảo tiêu hao vượt số lượng gốc.');
            }
            $vectors[] = ['source_consumption_line_id' => $sourceLine->id, 'order_line_id' => $line->id,
                'ingredient_id' => $sourceLine->ingredient_id, 'quantity' => $reversalQuantity, 'base_unit' => $sourceLine->base_unit,
                'snapshot' => $sourceLine->snapshot];
        }
        foreach (in_array($priorState, ['preparing', 'ready'], true) ? ['reversal', 'waste'] : ['reversal'] as $kind) {
            $snapshot = ['compensation_id' => $compensationId, 'source_consumption_id' => $source->id, 'order_line_id' => $line->id, 'quantity' => $quantity, 'kind' => $kind];
            $id = DB::table('fnb_stock_consumptions')->insertGetId([
                'website_key' => $ctx->websiteKey, 'outlet_id' => $ctx->outletId, 'public_id' => (string) Str::uuid(),
                'source_type' => 'fulfillment_compensation', 'source_id' => (string) $compensationId, 'source_version' => 2,
                'source_event_id' => 'compensation:'.$compensationId.':'.$kind, 'reversal_of_id' => $kind === 'reversal' ? $source->id : null,
                'kind' => $kind, 'status' => 'pending', 'idempotency_key' => 'compensation:'.$compensationId.':'.$kind,
                'payload_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'payload_hash' => $this->commands->fingerprint($snapshot),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($vectors as $vector) {
                DB::table('fnb_stock_consumption_lines')->insert($vector + [
                    'website_key' => $ctx->websiteKey, 'outlet_id' => $ctx->outletId, 'consumption_id' => $id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    private function proportion(int $total, int $part, int $whole): int
    {
        $quotient = bcdiv(bcadd(bcmul((string) abs($total), (string) $part, 0), (string) intdiv($whole, 2), 0), (string) $whole, 0);

        return (int) $quotient * ($total < 0 ? -1 : 1);
    }

    private function scope(string $table, FnbContext $ctx): Builder
    {
        return DB::table($table)->where('website_key', $ctx->websiteKey)->where('outlet_id', $ctx->outletId);
    }

    private function locked(string $table, FnbContext $ctx, int $id): object
    {
        return $this->scope($table, $ctx)->where('id', $id)->lockForUpdate()->firstOrFail();
    }
}
