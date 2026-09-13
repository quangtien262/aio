<?php

namespace Modules\FnbPos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Domain\FnbNotFoundException;
use Modules\FnbPos\Domain\FnbValidationException;
use Modules\FnbPos\Domain\MinorMoney;
use Modules\FnbPos\Domain\RecipeConsumption;

final class FnbOrderService
{
    public function __construct(
        private readonly FnbCommandRunner $commands,
        private readonly FnbSequenceService $sequences,
        private readonly FnbPricingService $pricing,
        private readonly FnbKitchenEventService $kitchenEvents,
        private readonly FnbOutboxService $outbox,
    ) {}

    /** @param array<string,mixed> $input */
    public function createOrder(FnbContext $context, int $sessionId, int $shiftId, array $input, string $idempotencyKey, int $expectedSessionVersion): array
    {
        if ($context->terminalId === null) {
            throw new FnbValidationException('A terminal is required to create an order.');
        }
        $note = isset($input['note']) ? trim((string) $input['note']) : null;
        if ($note !== null && mb_strlen($note) > 1000) {
            throw new FnbValidationException('Order note is too long.');
        }

        return $this->commands->run($context, 'order.create', compact('sessionId', 'shiftId', 'note', 'expectedSessionVersion'), $idempotencyKey, function () use ($context, $sessionId, $shiftId, $note, $idempotencyKey, $expectedSessionVersion): array {
            $session = $this->locked('fnb_service_sessions', $context, $sessionId);
            $this->assertVersion('service_session', $session, $expectedSessionVersion);
            if ($session->status !== 'open') {
                throw new FnbConflictException('Orders can only be added to an open session.');
            }
            $shift = $this->locked('fnb_shifts', $context, $shiftId);
            if ($shift->status !== 'open'
                || (int) $shift->business_day_id !== (int) $session->business_day_id
                || $shift->currency !== $session->currency
                || (int) $shift->terminal_id !== $context->terminalId) {
                throw new FnbConflictException('Open shift, session, currency and terminal do not share one operational chain.');
            }
            $day = $this->locked('fnb_business_days', $context, (int) $session->business_day_id);
            if ($day->status !== 'open') {
                throw new FnbConflictException('Business day is no longer open.');
            }
            $number = $this->sequences->next($context, $day->business_date, 'order', 'ORD');
            $now = now();
            $orderId = DB::table('fnb_orders')->insertGetId([
                'website_key' => $context->websiteKey,
                'outlet_id' => $context->outletId,
                'public_id' => (string) Str::uuid(),
                'creation_key' => $idempotencyKey,
                'session_id' => $session->id,
                'business_day_id' => $day->id,
                'shift_id' => $shift->id,
                'terminal_id' => $context->terminalId,
                'sequence_no' => $number['sequence_no'],
                'order_no' => $number['document_no'],
                'lifecycle_status' => 'draft',
                'fulfillment_status' => 'draft',
                'currency' => $session->currency,
                'timezone_snapshot' => $session->timezone_snapshot,
                'note' => $note,
                'created_by' => $context->actorId,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('fnb_order_financial_projections')->insert([
                'website_key' => $context->websiteKey,
                'outlet_id' => $context->outletId,
                'order_id' => $orderId,
                'gross_paid_total_minor' => 0,
                'refunded_total_minor' => 0,
                'net_collected_total_minor' => 0,
                'payment_status' => 'unpaid',
                'refund_status' => 'none',
                'source_watermark' => 0,
                'version' => 1,
                'updated_at' => $now,
            ]);
            DB::table('fnb_service_sessions')->where('id', $session->id)->update([
                'version' => (int) $session->version + 1,
                'updated_at' => $now,
            ]);
            $this->orderEvent($context, $orderId, 'created', null, 'draft', 1, $idempotencyKey);
            $event = $this->outbox->emit($context, 'order', $orderId, 1, 'fnb.order.created', [
                'order_id' => $orderId,
                'order_no' => $number['document_no'],
                'session_id' => (int) $session->id,
            ]);

            return $this->envelope('order', $orderId, 1, $this->orderResource($context, $orderId), [$event], [
                'session_version' => (int) $session->version + 1,
            ]);
        });
    }

    /** @param array<string,mixed> $input */
    public function addOrderLine(FnbContext $context, int $orderId, array $input, string $idempotencyKey, int $expectedVersion): array
    {
        $data = validator($input, [
            'variant_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'regex:/^(?:0|[1-9]\d*)(?:\.\d{1,6})?$/'],
            'modifier_option_ids' => ['sometimes', 'array', 'max:30'],
            'modifier_option_ids.*' => ['integer', 'min:1', 'distinct'],
            'note' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        return $this->commands->run($context, 'order.line.add', $data + compact('orderId', 'expectedVersion'), $idempotencyKey, function () use ($context, $orderId, $data, $idempotencyKey, $expectedVersion): array {
            $order = $this->locked('fnb_orders', $context, $orderId);
            $this->assertVersion('order', $order, $expectedVersion);
            if ($order->lifecycle_status !== 'draft') {
                throw new FnbConflictException('Submitted order lines are immutable; create a new order round.');
            }
            $this->assertOperationalChain($context, $order);
            $this->assertNoPricingAdjustments($context, (int) $order->id);
            $priced = $this->pricing->priceLine($context, (int) $data['variant_id'], $data['quantity'], $data['modifier_option_ids'] ?? []);
            $sortOrder = (int) DB::table('fnb_order_lines')->where('order_id', $order->id)->max('sort_order') + 1;
            $now = now();
            $lineId = DB::table('fnb_order_lines')->insertGetId([
                'website_key' => $context->websiteKey,
                'outlet_id' => $context->outletId,
                'business_day_id' => $order->business_day_id,
                'public_id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'item_id' => $priced['item_id'],
                'variant_id' => $priced['variant_id'],
                'item_code_snapshot' => $priced['item_code_snapshot'],
                'item_name_snapshot' => $priced['item_name_snapshot'],
                'variant_code_snapshot' => $priced['variant_code_snapshot'],
                'variant_name_snapshot' => $priced['variant_name_snapshot'],
                'tax_category_snapshot' => $priced['tax_category_snapshot'],
                'tax_rate_bps_snapshot' => $priced['tax_rate_bps_snapshot'],
                'tax_inclusive_snapshot' => $priced['tax_inclusive_snapshot'],
                'ordered_quantity' => $priced['ordered_quantity'],
                'original_unit_price_minor' => $priced['original_unit_price_minor'],
                'unit_price_minor' => $priced['unit_price_minor'],
                'subtotal_minor' => $priced['subtotal_minor'],
                'discount_total_minor' => $priced['discount_total_minor'],
                'tax_total_minor' => $priced['tax_total_minor'],
                'service_charge_total_minor' => $priced['service_charge_total_minor'],
                'pricing_rounding_minor' => $priced['pricing_rounding_minor'],
                'total_minor' => $priced['total_minor'],
                'status' => 'draft',
                'prep_station_id' => $priced['prep_station_id'],
                'recipe_id' => $priced['recipe_id'],
                'recipe_snapshot' => $priced['recipe_snapshot'] === null ? null : json_encode($priced['recipe_snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'recipe_snapshot_hash' => $priced['recipe_snapshot_hash'],
                'note' => $data['note'] ?? null,
                'sort_order' => $sortOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($priced['modifiers'] as $modifier) {
                DB::table('fnb_order_line_modifiers')->insert([
                    'website_key' => $context->websiteKey,
                    'outlet_id' => $context->outletId,
                    'business_day_id' => $order->business_day_id,
                    'order_id' => $order->id,
                    'order_line_id' => $lineId,
                    ...$modifier,
                    'total_minor' => MinorMoney::multiply((int) $modifier['unit_price_delta_minor'], $priced['quantity_micros']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $version = (int) $order->version + 1;
            $this->repriceDraftOrder($order, $version);
            $this->orderEvent($context, $order->id, 'line_added', 'draft', 'draft', $version, $idempotencyKey, [
                'order_line_id' => $lineId,
            ]);
            $event = $this->outbox->emit($context, 'order', $order->id, $version, 'fnb.order.line_added', [
                'order_id' => (int) $order->id,
                'order_line_id' => $lineId,
            ]);

            return $this->envelope('order', (int) $order->id, $version, $this->orderResource($context, $order->id), [$event]);
        });
    }

    /** @param array<string,mixed> $input */
    public function updateDraftOrderLine(FnbContext $context, int $orderLineId, array $input, string $idempotencyKey, int $expectedOrderVersion): array
    {
        $data = validator($input, [
            'variant_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'regex:/^(?:0|[1-9]\d*)(?:\.\d{1,6})?$/'],
            'modifier_option_ids' => ['sometimes', 'array', 'max:30'],
            'modifier_option_ids.*' => ['integer', 'min:1', 'distinct'],
            'note' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        return $this->commands->run($context, 'order.line.update', $data + compact('orderLineId', 'expectedOrderVersion'), $idempotencyKey, function () use ($context, $orderLineId, $data, $idempotencyKey, $expectedOrderVersion): array {
            $line = $this->locked('fnb_order_lines', $context, $orderLineId);
            $order = $this->locked('fnb_orders', $context, (int) $line->order_id);
            $this->assertVersion('order', $order, $expectedOrderVersion);
            if ($order->lifecycle_status !== 'draft' || $line->status !== 'draft') {
                throw new FnbConflictException('Only a draft order line can be edited.');
            }
            $this->assertOperationalChain($context, $order);
            $this->assertNoPricingAdjustments($context, (int) $order->id);
            $priced = $this->pricing->priceLine($context, (int) $data['variant_id'], $data['quantity'], $data['modifier_option_ids'] ?? []);
            $now = now();
            DB::table('fnb_order_line_modifiers')->where('website_key', $context->websiteKey)
                ->where('outlet_id', $context->outletId)->where('order_line_id', $line->id)->delete();
            DB::table('fnb_order_lines')->where('id', $line->id)->update([
                'item_id' => $priced['item_id'],
                'variant_id' => $priced['variant_id'],
                'item_code_snapshot' => $priced['item_code_snapshot'],
                'item_name_snapshot' => $priced['item_name_snapshot'],
                'variant_code_snapshot' => $priced['variant_code_snapshot'],
                'variant_name_snapshot' => $priced['variant_name_snapshot'],
                'tax_category_snapshot' => $priced['tax_category_snapshot'],
                'tax_rate_bps_snapshot' => $priced['tax_rate_bps_snapshot'],
                'tax_inclusive_snapshot' => $priced['tax_inclusive_snapshot'],
                'ordered_quantity' => $priced['ordered_quantity'],
                'original_unit_price_minor' => $priced['original_unit_price_minor'],
                'unit_price_minor' => $priced['unit_price_minor'],
                'subtotal_minor' => $priced['subtotal_minor'],
                'discount_total_minor' => $priced['discount_total_minor'],
                'tax_total_minor' => $priced['tax_total_minor'],
                'service_charge_total_minor' => $priced['service_charge_total_minor'],
                'pricing_rounding_minor' => $priced['pricing_rounding_minor'],
                'total_minor' => $priced['total_minor'],
                'prep_station_id' => $priced['prep_station_id'],
                'recipe_id' => $priced['recipe_id'],
                'recipe_snapshot' => $priced['recipe_snapshot'] === null ? null : json_encode($priced['recipe_snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'recipe_snapshot_hash' => $priced['recipe_snapshot_hash'],
                'note' => $data['note'] ?? null,
                'updated_at' => $now,
            ]);
            foreach ($priced['modifiers'] as $modifier) {
                DB::table('fnb_order_line_modifiers')->insert([
                    'website_key' => $context->websiteKey,
                    'outlet_id' => $context->outletId,
                    'business_day_id' => $order->business_day_id,
                    'order_id' => $order->id,
                    'order_line_id' => $line->id,
                    ...$modifier,
                    'total_minor' => MinorMoney::multiply((int) $modifier['unit_price_delta_minor'], $priced['quantity_micros']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $version = (int) $order->version + 1;
            $this->repriceDraftOrder($order, $version);
            $this->orderEvent($context, $order->id, 'line_updated', 'draft', 'draft', $version, $idempotencyKey, [
                'order_line_id' => (int) $line->id,
            ]);
            $event = $this->outbox->emit($context, 'order', $order->id, $version, 'fnb.order.line_updated', [
                'order_id' => (int) $order->id, 'order_line_id' => (int) $line->id,
            ]);

            return $this->envelope('order', (int) $order->id, $version, $this->orderResource($context, $order->id), [$event]);
        });
    }

    public function removeDraftOrderLine(FnbContext $context, int $orderLineId, string $idempotencyKey, int $expectedOrderVersion): array
    {
        return $this->commands->run($context, 'order.line.remove', compact('orderLineId', 'expectedOrderVersion'), $idempotencyKey, function () use ($context, $orderLineId, $idempotencyKey, $expectedOrderVersion): array {
            $line = $this->locked('fnb_order_lines', $context, $orderLineId);
            $order = $this->locked('fnb_orders', $context, (int) $line->order_id);
            $this->assertVersion('order', $order, $expectedOrderVersion);
            if ($order->lifecycle_status !== 'draft' || $line->status !== 'draft') {
                throw new FnbConflictException('Only a draft order line can be removed.');
            }
            $this->assertOperationalChain($context, $order);
            $this->assertNoPricingAdjustments($context, (int) $order->id);
            if (DB::table('fnb_check_lines')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('order_line_id', $line->id)->exists()
                || DB::table('fnb_kitchen_ticket_lines')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('order_line_id', $line->id)->exists()) {
                throw new FnbConflictException('A dispatched or allocated line cannot be removed.');
            }
            DB::table('fnb_order_line_modifiers')->where('website_key', $context->websiteKey)
                ->where('outlet_id', $context->outletId)->where('order_line_id', $line->id)->delete();
            DB::table('fnb_order_lines')->where('id', $line->id)->delete();
            $version = (int) $order->version + 1;
            $this->repriceDraftOrder($order, $version);
            $this->orderEvent($context, $order->id, 'line_removed', 'draft', 'draft', $version, $idempotencyKey, [
                'order_line_id' => (int) $line->id,
                'item_code_snapshot' => $line->item_code_snapshot,
                'quantity' => (string) $line->ordered_quantity,
            ]);
            $event = $this->outbox->emit($context, 'order', $order->id, $version, 'fnb.order.line_removed', [
                'order_id' => (int) $order->id, 'order_line_id' => (int) $line->id,
            ]);

            return $this->envelope('order', (int) $order->id, $version, $this->orderResource($context, $order->id), [$event]);
        });
    }

    /** @param array<string,mixed> $input */
    public function applyOrderDiscount(FnbContext $context, int $orderId, array $input, string $idempotencyKey, int $expectedVersion): array
    {
        $data = validator($input, [
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ])->validate();
        $amountMinor = MinorMoney::assertMinor((int) $data['amount_minor'], 'amount_minor');
        $reason = trim((string) $data['reason']);

        return $this->commands->run(
            $context,
            'discount.apply',
            compact('orderId', 'amountMinor', 'reason', 'expectedVersion'),
            $idempotencyKey,
            function () use ($context, $orderId, $amountMinor, $reason, $idempotencyKey, $expectedVersion): array {
                $order = $this->locked('fnb_orders', $context, $orderId);
                $this->assertVersion('order', $order, $expectedVersion);
                if ($order->lifecycle_status !== 'draft') {
                    throw new FnbConflictException('Discounts can only be applied before an order is submitted.');
                }
                $this->assertOperationalChain($context, $order);

                $lines = DB::table('fnb_order_lines')->where('website_key', $context->websiteKey)
                    ->where('outlet_id', $context->outletId)->where('order_id', $order->id)
                    ->orderBy('id')->lockForUpdate()->get()
                    ->sortBy(fn (object $line): string => (string) $line->public_id, SORT_STRING)->values();
                if ($lines->isEmpty() || $lines->contains(fn (object $line): bool => $line->status !== 'draft')) {
                    throw new FnbConflictException('Discount allocation requires only mutable draft lines.');
                }
                if ($lines->contains(fn (object $line): bool => (int) $line->service_charge_total_minor !== 0)) {
                    throw new FnbConflictException('Discounting a taxable service charge is not supported by the current Pilot policy.');
                }
                if (DB::table('fnb_check_lines')->where('website_key', $context->websiteKey)
                    ->where('outlet_id', $context->outletId)->whereIn('order_line_id', $lines->pluck('id'))->exists()) {
                    throw new FnbConflictException('A check-linked order cannot be repriced.');
                }

                $weights = [];
                $availableMinor = 0;
                foreach ($lines as $line) {
                    $remainingMinor = (int) $line->subtotal_minor - (int) $line->discount_total_minor;
                    if ($remainingMinor < 0) {
                        throw new FnbConflictException('Existing discount exceeds the line subtotal.');
                    }
                    $weights[(int) $line->id] = $remainingMinor;
                    if ($availableMinor > PHP_INT_MAX - $remainingMinor) {
                        throw new FnbValidationException('Discountable order subtotal is too large.');
                    }
                    $availableMinor += $remainingMinor;
                }
                if ($amountMinor > $availableMinor) {
                    throw new FnbConflictException('Discount cannot make the order net item amount negative.', [
                        'discountable_minor' => $availableMinor,
                    ]);
                }

                $allocations = MinorMoney::allocate($amountMinor, $weights);
                $allocationSnapshot = [];
                foreach ($lines as $line) {
                    $allocatedMinor = $allocations[(int) $line->id];
                    $discountMinor = (int) $line->discount_total_minor + $allocatedMinor;
                    $netMinor = (int) $line->subtotal_minor - $discountMinor;
                    $taxMinor = $this->pricing->taxOnPreTaxNet($netMinor, (int) $line->tax_rate_bps_snapshot);
                    $total = bcadd(
                        bcadd((string) $netMinor, (string) $line->service_charge_total_minor, 0),
                        bcadd((string) $taxMinor, (string) $line->pricing_rounding_minor, 0),
                        0,
                    );
                    if (bccomp($total, '0', 0) < 0 || bccomp($total, (string) PHP_INT_MAX, 0) > 0) {
                        throw new FnbValidationException('Discounted line total is outside the supported money range.');
                    }

                    DB::table('fnb_order_lines')->where('id', $line->id)->update([
                        'discount_total_minor' => $discountMinor,
                        'tax_total_minor' => $taxMinor,
                        'total_minor' => (int) $total,
                        'updated_at' => now(),
                    ]);
                    $allocationSnapshot[] = [
                        'order_line_id' => (int) $line->id,
                        'order_line_public_id' => $line->public_id,
                        'allocated_discount_minor' => $allocatedMinor,
                        'cumulative_discount_minor' => $discountMinor,
                        'net_item_minor' => $netMinor,
                        'tax_before_minor' => (int) $line->tax_total_minor,
                        'tax_after_minor' => $taxMinor,
                        'total_after_minor' => (int) $total,
                    ];
                }

                $now = now();
                $adjustmentId = DB::table('fnb_order_adjustments')->insertGetId([
                    'website_key' => $context->websiteKey,
                    'outlet_id' => $context->outletId,
                    'business_day_id' => $order->business_day_id,
                    'order_id' => $order->id,
                    'line_id' => null,
                    'kind' => 'order_discount',
                    'method' => 'fixed',
                    'rate_bps' => null,
                    'value_minor' => $amountMinor,
                    'amount_minor' => $amountMinor,
                    'reason' => $reason,
                    'actor_id' => $context->actorId,
                    'approval_id' => $context->authorizationEvidence->approvalId(),
                    'idempotency_key' => $idempotencyKey,
                    'occurred_at' => $now,
                ]);
                $version = (int) $order->version + 1;
                $this->repriceDraftOrder($order, $version);
                $policy = [
                    'basis' => 'pre_tax_net',
                    'allocation' => 'largest_remainder',
                    'tie_break' => 'order_line.public_id:asc',
                    'method' => 'fixed',
                    'service_charge_policy' => 'unsupported_nonzero_rejected',
                ];
                $eventPayload = [
                    'adjustment_id' => $adjustmentId,
                    'amount_minor' => $amountMinor,
                    'reason' => $reason,
                    'policy' => $policy,
                    'allocations' => $allocationSnapshot,
                ];
                $this->orderEvent($context, $order->id, 'discount_applied', 'draft', 'draft', $version, $idempotencyKey, $eventPayload);
                $event = $this->outbox->emit($context, 'order', $order->id, $version, 'fnb.discount.applied', [
                    'order_id' => (int) $order->id,
                    'adjustment_id' => $adjustmentId,
                    'amount_minor' => $amountMinor,
                    'policy' => $policy,
                    'allocations' => $allocationSnapshot,
                ]);

                return $this->envelope('order', (int) $order->id, $version, $this->orderResource($context, $order->id), [$event], [
                    'adjustment_id' => $adjustmentId,
                ]);
            },
        );
    }

    public function submitOrder(FnbContext $context, int $orderId, string $idempotencyKey, int $expectedVersion): array
    {
        return $this->commands->run($context, 'order.submit', compact('orderId', 'expectedVersion'), $idempotencyKey, function () use ($context, $orderId, $idempotencyKey, $expectedVersion): array {
            $order = $this->locked('fnb_orders', $context, $orderId);
            $this->assertVersion('order', $order, $expectedVersion);
            if ($order->lifecycle_status !== 'draft') {
                throw new FnbConflictException('Only a draft order can be submitted.');
            }
            $this->assertOperationalChain($context, $order);
            $lines = DB::table('fnb_order_lines')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)
                ->where('order_id', $order->id)->where('status', 'draft')->orderBy('id')->lockForUpdate()->get();
            if ($lines->isEmpty()) {
                throw new FnbValidationException('An order requires at least one active line.');
            }

            $version = (int) $order->version + 1;
            $submittedAt = now();
            $snapshot = [
                'order_id' => (int) $order->id,
                'order_no' => $order->order_no,
                'currency' => $order->currency,
                'components_minor' => [
                    'subtotal' => (int) $order->subtotal_minor,
                    'discount' => (int) $order->discount_total_minor,
                    'tax' => (int) $order->tax_total_minor,
                    'service_charge' => (int) $order->service_charge_total_minor,
                    'pricing_rounding' => (int) $order->pricing_rounding_minor,
                    'grand_total' => (int) $order->grand_total_minor,
                ],
                'lines' => $lines->map(fn (object $line): array => [
                    'line_id' => (int) $line->id,
                    'item_id' => (int) $line->item_id,
                    'variant_id' => (int) $line->variant_id,
                    'quantity' => (string) $line->ordered_quantity,
                    'subtotal_minor' => (int) $line->subtotal_minor,
                    'discount_total_minor' => (int) $line->discount_total_minor,
                    'service_charge_total_minor' => (int) $line->service_charge_total_minor,
                    'tax_total_minor' => (int) $line->tax_total_minor,
                    'pricing_rounding_minor' => (int) $line->pricing_rounding_minor,
                    'total_minor' => (int) $line->total_minor,
                ])->all(),
                'adjustments' => DB::table('fnb_order_adjustments')->where('website_key', $context->websiteKey)
                    ->where('outlet_id', $context->outletId)->where('order_id', $order->id)
                    ->orderBy('id')->get()->map(fn (object $adjustment): array => $this->decode($adjustment))->all(),
            ];
            $pricingHash = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            $ticketIds = [];
            $stationLines = $lines->groupBy('prep_station_id');
            foreach ($stationLines as $stationId => $group) {
                if ($stationId === '' || $stationId === null) {
                    foreach ($group as $line) {
                        DB::table('fnb_order_lines')->where('id', $line->id)->update([
                            'status' => 'served',
                            'fulfilled_quantity' => $line->ordered_quantity,
                            'submitted_at' => $submittedAt,
                            'updated_at' => $submittedAt,
                        ]);
                    }

                    continue;
                }

                $dispatchKey = "order:{$order->id}:v{$version}:station:{$stationId}";
                $ticket = DB::table('fnb_kitchen_tickets')->where('dispatch_key', $dispatchKey)->first();
                if ($ticket === null) {
                    $day = DB::table('fnb_business_days')->where('id', $order->business_day_id)->firstOrFail();
                    $number = $this->sequences->next($context, $day->business_date, 'ticket', 'KDS');
                    $ticketId = DB::table('fnb_kitchen_tickets')->insertGetId([
                        'website_key' => $context->websiteKey,
                        'outlet_id' => $context->outletId,
                        'public_id' => (string) Str::uuid(),
                        'business_day_id' => $order->business_day_id,
                        'order_id' => $order->id,
                        'prep_station_id' => (int) $stationId,
                        'sequence_no' => $number['sequence_no'],
                        'ticket_no' => $number['document_no'],
                        'dispatch_key' => $dispatchKey,
                        'status_rollup' => 'in_progress',
                        'priority' => 0,
                        'fired_at' => $submittedAt,
                        'last_actor_id' => $context->actorId,
                        'version' => 1,
                        'created_at' => $submittedAt,
                        'updated_at' => $submittedAt,
                    ]);
                    foreach ($group as $line) {
                        $ticketLineId = DB::table('fnb_kitchen_ticket_lines')->insertGetId([
                            'website_key' => $context->websiteKey,
                            'outlet_id' => $context->outletId,
                            'business_day_id' => $order->business_day_id,
                            'order_id' => $order->id,
                            'ticket_id' => $ticketId,
                            'order_line_id' => $line->id,
                            'attempt_no' => 1,
                            'quantity' => $line->ordered_quantity,
                            'status_rollup' => 'waiting',
                            'note' => $line->note,
                            'waiting_at' => $submittedAt,
                            'version' => 1,
                            'created_at' => $submittedAt,
                            'updated_at' => $submittedAt,
                        ]);
                        DB::table('fnb_order_lines')->where('id', $line->id)->update([
                            'status' => 'waiting', 'submitted_at' => $submittedAt, 'updated_at' => $submittedAt,
                        ]);
                        $modifiers = DB::table('fnb_order_line_modifiers')->where('order_line_id', $line->id)->orderBy('id')
                            ->get(['option_code_snapshot', 'option_name_snapshot', 'quantity'])->map(fn ($row) => (array) $row)->all();
                        $this->kitchenEvents->append(
                            $context,
                            "ticket-line:{$ticketLineId}:fired",
                            'ticket_line.fired',
                            [
                                'ticket_id' => $ticketId,
                                'ticket_line_id' => $ticketLineId,
                                'ticket_no' => $number['document_no'],
                                'order_id' => (int) $order->id,
                                'order_no' => $order->order_no,
                                'line' => [
                                    'item_code' => $line->item_code_snapshot,
                                    'item_name' => $line->item_name_snapshot,
                                    'variant_name' => $line->variant_name_snapshot,
                                    'quantity' => (string) $line->ordered_quantity,
                                    'modifiers' => $modifiers,
                                    'note' => $line->note,
                                ],
                            ],
                            (int) $stationId,
                            $ticketId,
                            $ticketLineId,
                        );
                    }
                } else {
                    $ticketId = (int) $ticket->id;
                }
                $ticketIds[] = $ticketId;
            }

            $allImmediatelyServed = DB::table('fnb_order_lines')->where('order_id', $order->id)->whereNotIn('status', ['served', 'voided', 'cancelled_compensated'])->doesntExist();
            DB::table('fnb_orders')->where('id', $order->id)->update([
                'lifecycle_status' => $allImmediatelyServed ? 'completed' : 'active',
                'fulfillment_status' => $allImmediatelyServed ? 'fulfilled' : 'waiting',
                'pricing_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'pricing_hash' => $pricingHash,
                'submitted_at' => $submittedAt,
                'submitted_by' => $context->actorId,
                'completed_at' => $allImmediatelyServed ? $submittedAt : null,
                'version' => $version,
                'updated_at' => $submittedAt,
            ]);
            $this->orderEvent($context, $order->id, 'submitted', 'draft', $allImmediatelyServed ? 'completed' : 'active', $version, $idempotencyKey, ['ticket_ids' => $ticketIds]);
            $event = $this->outbox->emit($context, 'order', $order->id, $version, 'fnb.order.submitted', [
                'order_id' => (int) $order->id,
                'order_no' => $order->order_no,
                'ticket_ids' => $ticketIds,
                'pricing_hash' => $pricingHash,
            ]);

            return $this->envelope('order', (int) $order->id, $version, $this->orderResource($context, $order->id), [$event]);
        });
    }

    public function transitionKitchenLine(FnbContext $context, int $ticketLineId, string $toStatus, string $idempotencyKey, int $expectedTicketVersion): array
    {
        if (! in_array($toStatus, ['preparing', 'ready', 'served'], true)) {
            throw new FnbValidationException('Unsupported kitchen status.');
        }

        return $this->commands->run($context, 'kitchen.line.transition', compact('ticketLineId', 'toStatus', 'expectedTicketVersion'), $idempotencyKey, function () use ($context, $ticketLineId, $toStatus, $idempotencyKey, $expectedTicketVersion): array {
            $line = $this->locked('fnb_kitchen_ticket_lines', $context, $ticketLineId);
            $ticket = $this->locked('fnb_kitchen_tickets', $context, (int) $line->ticket_id);
            $this->assertVersion('kitchen_ticket', $ticket, $expectedTicketVersion);
            $allowed = ['waiting' => 'preparing', 'preparing' => 'ready', 'ready' => 'served'];
            if (($allowed[$line->status_rollup] ?? null) !== $toStatus) {
                throw new FnbConflictException('Invalid kitchen line transition.', [
                    'from' => $line->status_rollup, 'to' => $toStatus,
                ]);
            }
            $orderLine = $this->locked('fnb_order_lines', $context, (int) $line->order_line_id);
            $order = $this->locked('fnb_orders', $context, (int) $line->order_id);

            $servedQuantity = null;
            $fulfilledQuantity = null;
            if ($toStatus === 'served') {
                $ticketAccounted = bcadd(
                    bcadd((string) $line->served_quantity, (string) $line->voided_quantity, 6),
                    (string) $line->compensated_quantity,
                    6,
                );
                if (bccomp($ticketAccounted, (string) $line->quantity, 6) > 0) {
                    throw new FnbConflictException('Kitchen line quantity ledger exceeds its submitted quantity.');
                }
                $remainingTicketQuantity = bcsub((string) $line->quantity, $ticketAccounted, 6);
                if (bccomp($remainingTicketQuantity, '0', 6) <= 0) {
                    throw new FnbConflictException('Kitchen line has no remaining quantity to serve.');
                }

                $orderLineAccounted = bcadd(
                    bcadd((string) $orderLine->fulfilled_quantity, (string) $orderLine->voided_quantity, 6),
                    (string) $orderLine->compensated_quantity,
                    6,
                );
                if (bccomp($orderLineAccounted, (string) $orderLine->ordered_quantity, 6) > 0) {
                    throw new FnbConflictException('Order line quantity ledger exceeds ordered quantity.');
                }
                $remainingOrderQuantity = bcsub((string) $orderLine->ordered_quantity, $orderLineAccounted, 6);
                if (bccomp($remainingTicketQuantity, $remainingOrderQuantity, 6) > 0) {
                    throw new FnbConflictException('Fulfilled quantity would exceed the uncompensated order quantity.');
                }

                $servedQuantity = bcadd((string) $line->served_quantity, $remainingTicketQuantity, 6);
                $fulfilledQuantity = bcadd((string) $orderLine->fulfilled_quantity, $remainingTicketQuantity, 6);
                $finalOrderLineAccounted = bcadd(
                    bcadd($fulfilledQuantity, (string) $orderLine->voided_quantity, 6),
                    (string) $orderLine->compensated_quantity,
                    6,
                );
                if (bccomp($finalOrderLineAccounted, (string) $orderLine->ordered_quantity, 6) > 0) {
                    throw new FnbConflictException('Fulfilled, voided, and compensated quantity exceeds ordered quantity.');
                }
            }

            $lineVersion = (int) $line->version + 1;
            $lineUpdates = [
                'status_rollup' => $toStatus,
                $toStatus.'_at' => now(),
                'version' => $lineVersion,
                'updated_at' => now(),
            ];
            if ($toStatus === 'served') {
                $lineUpdates['served_quantity'] = $servedQuantity;
            }
            DB::table('fnb_kitchen_ticket_lines')->where('id', $line->id)->update($lineUpdates);

            $orderLineUpdates = ['status' => $toStatus, 'updated_at' => now()];
            if ($toStatus === 'served') {
                $orderLineUpdates['fulfilled_quantity'] = $fulfilledQuantity;
            }
            DB::table('fnb_order_lines')->where('id', $orderLine->id)->update($orderLineUpdates);

            $ticketVersion = (int) $ticket->version + 1;
            $ticketRollup = $this->ticketRollup((int) $ticket->id);
            $ticketUpdates = ['status_rollup' => $ticketRollup, 'version' => $ticketVersion, 'last_actor_id' => $context->actorId, 'updated_at' => now()];
            if ($toStatus === 'preparing' && $ticket->started_at === null) {
                $ticketUpdates['started_at'] = now();
            }
            if ($ticketRollup === 'all_ready') {
                $ticketUpdates['ready_at'] = now();
            }
            if ($ticketRollup === 'completed') {
                $ticketUpdates['served_at'] = now();
            }
            DB::table('fnb_kitchen_tickets')->where('id', $ticket->id)->update($ticketUpdates);

            [$orderLifecycle, $fulfillment] = $this->orderRollup((int) $order->id);
            $orderVersion = (int) $order->version + 1;
            DB::table('fnb_orders')->where('id', $order->id)->update([
                'lifecycle_status' => $orderLifecycle,
                'fulfillment_status' => $fulfillment,
                'completed_at' => $orderLifecycle === 'completed' ? now() : null,
                'version' => $orderVersion,
                'updated_at' => now(),
            ]);

            $kitchenEvent = $this->kitchenEvents->append(
                $context,
                "ticket-line:{$line->id}:v{$lineVersion}:{$toStatus}",
                'ticket_line.'.$toStatus,
                [
                    'ticket_id' => (int) $ticket->id,
                    'ticket_line_id' => (int) $line->id,
                    'order_id' => (int) $order->id,
                    'status' => $toStatus,
                    'ticket_version' => $ticketVersion,
                ],
                (int) $ticket->prep_station_id,
                (int) $ticket->id,
                (int) $line->id,
            );
            $this->orderEvent($context, $order->id, 'kitchen_line_'.$toStatus, $order->lifecycle_status, $orderLifecycle, $orderVersion, $idempotencyKey, ['ticket_line_id' => (int) $line->id]);
            $event = $this->outbox->emit($context, 'kitchen_ticket', $ticket->id, $ticketVersion, 'fnb.kitchen_line.'.$toStatus, [
                'ticket_id' => (int) $ticket->id,
                'ticket_line_id' => (int) $line->id,
                'order_id' => (int) $order->id,
                'status' => $toStatus,
            ]);

            return [
                'resource' => [
                    'ticket' => $this->ticketResource($context, (int) $ticket->id),
                    'line' => $this->record('fnb_kitchen_ticket_lines', (int) $line->id),
                ],
                'events' => [$kitchenEvent, $event],
                'meta' => [
                    'resource_type' => 'kitchen_ticket_line', 'resource_id' => (int) $line->id,
                    'version' => $lineVersion, 'ticket_version' => $ticketVersion, 'order_version' => $orderVersion,
                ],
            ];
        });
    }

    public function pollKitchen(FnbContext $context, int $cursor = 0, ?int $stationId = null, int $limit = 200): array
    {
        if ($context->outletId < 1 || ! DB::table('fnb_outlets')->where('website_key', $context->websiteKey)->where('id', $context->outletId)->exists()) {
            throw new FnbNotFoundException('Outlet was not found.');
        }
        $limit = max(1, min($limit, 500));
        if ($stationId !== null && ! DB::table('fnb_prep_stations')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('id', $stationId)->exists()) {
            throw new FnbNotFoundException('Preparation station was not found.');
        }

        $rows = DB::table('fnb_kitchen_events')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)
            ->where('sequence', '>', max(0, $cursor))
            ->when($stationId, fn ($query) => $query->where('prep_station_id', $stationId))
            ->orderBy('sequence')->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);
        $nextCursor = $rows->isEmpty() ? max(0, $cursor) : (int) $rows->last()->sequence;
        $tickets = DB::table('fnb_kitchen_tickets')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)
            ->when($stationId, fn ($query) => $query->where('prep_station_id', $stationId))
            ->whereNotIn('status_rollup', ['completed', 'all_voided', 'all_compensated', 'cancelled_mixed'])
            ->orderByDesc('priority')->orderBy('fired_at')->limit(300)->get()
            ->map(fn (object $ticket): array => $this->ticketResource($context, (int) $ticket->id))->all();

        return ['resource' => [
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
            'events' => $rows->map(function (object $row): array {
                return [
                    'sequence' => (int) $row->sequence,
                    'event_type' => $row->event_type,
                    'station_id' => $row->prep_station_id === null ? null : (int) $row->prep_station_id,
                    'ticket_id' => $row->ticket_id === null ? null : (int) $row->ticket_id,
                    'ticket_line_id' => $row->ticket_line_id === null ? null : (int) $row->ticket_line_id,
                    'payload' => json_decode((string) $row->payload, true, 512, JSON_THROW_ON_ERROR),
                    'occurred_at' => $row->occurred_at,
                ];
            })->all(),
            'tickets' => $tickets,
        ]];
    }

    public function voidOrderLine(FnbContext $context, int $orderLineId, string $reason, string $idempotencyKey, int $expectedOrderVersion): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw new FnbValidationException('A void reason between 3 and 500 characters is required.');
        }

        return $this->commands->run($context, 'order.line.void', compact('orderLineId', 'reason', 'expectedOrderVersion'), $idempotencyKey, function () use ($context, $orderLineId, $reason, $idempotencyKey, $expectedOrderVersion): array {
            $line = $this->locked('fnb_order_lines', $context, $orderLineId);
            $order = $this->locked('fnb_orders', $context, (int) $line->order_id);
            $this->assertVersion('order', $order, $expectedOrderVersion);
            if (! in_array($line->status, ['draft', 'waiting', 'preparing', 'ready'], true)) {
                throw new FnbConflictException('This line cannot use the normal void path.', ['status' => $line->status]);
            }
            $hasCheck = DB::table('fnb_check_lines')->where('order_line_id', $line->id)->exists();
            if ($hasCheck) {
                $hasProtectedPayment = DB::table('fnb_check_lines as line')
                    ->join('fnb_payments as payment', 'payment.check_id', '=', 'line.check_id')
                    ->where('line.order_line_id', $line->id)
                    ->whereIn('payment.status', ['reserved', 'processing', 'uncertain', 'reconciling', 'succeeded'])
                    ->exists();
                if ($hasProtectedPayment) {
                    throw new FnbConflictException('Paid or payment-active fulfillment requires compensation, not a normal void.');
                }
                throw new FnbConflictException('Void or replace the unpaid check allocation before voiding this line.');
            }

            $priorStatus = $line->status;
            DB::table('fnb_order_lines')->where('id', $line->id)->update([
                'status' => 'voided',
                'voided_quantity' => $line->ordered_quantity,
                'updated_at' => now(),
            ]);
            $kitchenEvents = [];
            $ticketLine = DB::table('fnb_kitchen_ticket_lines')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)
                ->where('order_line_id', $line->id)->lockForUpdate()->first();
            if ($ticketLine !== null && ! in_array($ticketLine->status_rollup, ['served', 'voided', 'cancelled_compensated'], true)) {
                DB::table('fnb_kitchen_ticket_lines')->where('id', $ticketLine->id)->update([
                    'status_rollup' => 'voided',
                    'voided_quantity' => $ticketLine->quantity,
                    'cancelled_at' => now(),
                    'version' => (int) $ticketLine->version + 1,
                    'updated_at' => now(),
                ]);
                $ticket = $this->locked('fnb_kitchen_tickets', $context, (int) $ticketLine->ticket_id);
                $ticketVersion = (int) $ticket->version + 1;
                $rollup = $this->ticketRollup((int) $ticket->id);
                DB::table('fnb_kitchen_tickets')->where('id', $ticket->id)->update([
                    'status_rollup' => $rollup,
                    'cancelled_at' => in_array($rollup, ['all_voided', 'cancelled_mixed'], true) ? now() : $ticket->cancelled_at,
                    'version' => $ticketVersion,
                    'last_actor_id' => $context->actorId,
                    'updated_at' => now(),
                ]);
                $kitchenEvents[] = $this->kitchenEvents->append(
                    $context,
                    "ticket-line:{$ticketLine->id}:void:{$idempotencyKey}",
                    'ticket_line.cancelled',
                    [
                        'ticket_id' => (int) $ticket->id,
                        'ticket_line_id' => (int) $ticketLine->id,
                        'order_id' => (int) $order->id,
                        'reason' => $reason,
                    ],
                    (int) $ticket->prep_station_id,
                    (int) $ticket->id,
                    (int) $ticketLine->id,
                );
            }

            if (in_array($priorStatus, ['preparing', 'ready'], true)) {
                $this->recordWaste($context, $order, $line, $idempotencyKey, $reason);
            }
            $version = (int) $order->version + 1;
            if ($order->lifecycle_status === 'draft') {
                $this->repriceDraftOrder($order, $version);
            } else {
                [$lifecycle, $fulfillment] = $this->orderRollup((int) $order->id);
                DB::table('fnb_orders')->where('id', $order->id)->update([
                    'lifecycle_status' => $lifecycle,
                    'fulfillment_status' => $fulfillment,
                    'completed_at' => $lifecycle === 'completed' ? now() : null,
                    'version' => $version,
                    'updated_at' => now(),
                ]);
            }
            $this->orderEvent($context, $order->id, 'line_voided', $order->lifecycle_status, $order->lifecycle_status, $version, $idempotencyKey, [
                'order_line_id' => (int) $line->id, 'reason' => $reason,
            ]);
            $event = $this->outbox->emit($context, 'order', $order->id, $version, 'fnb.order.line_voided', [
                'order_id' => (int) $order->id,
                'order_line_id' => (int) $line->id,
                'prior_fulfillment_status' => $priorStatus,
                'reason' => $reason,
            ]);

            return $this->envelope('order', (int) $order->id, $version, $this->orderResource($context, $order->id), [...$kitchenEvents, $event]);
        });
    }

    private function assertOperationalChain(FnbContext $context, object $order): void
    {
        $session = $this->locked('fnb_service_sessions', $context, (int) $order->session_id);
        $shift = $this->locked('fnb_shifts', $context, (int) $order->shift_id);
        $day = $this->locked('fnb_business_days', $context, (int) $order->business_day_id);
        if ($session->status !== 'open' || $shift->status !== 'open' || $day->status !== 'open'
            || (int) $shift->terminal_id !== (int) $order->terminal_id
            || $shift->currency !== $order->currency || $session->currency !== $order->currency) {
            throw new FnbConflictException('Order operational chain is no longer mutable.');
        }
    }

    private function assertNoPricingAdjustments(FnbContext $context, int $orderId): void
    {
        if (DB::table('fnb_order_adjustments')->where('website_key', $context->websiteKey)
            ->where('outlet_id', $context->outletId)->where('order_id', $orderId)->exists()) {
            throw new FnbConflictException('Lines cannot be changed after a pricing adjustment; create a replacement draft order.');
        }
    }

    private function repriceDraftOrder(object $order, int $version): void
    {
        $totals = DB::table('fnb_order_lines')->where('order_id', $order->id)->where('status', '!=', 'voided')->selectRaw(
            'COALESCE(SUM(subtotal_minor),0) subtotal_minor, COALESCE(SUM(discount_total_minor),0) discount_total_minor, COALESCE(SUM(tax_total_minor),0) tax_total_minor, COALESCE(SUM(service_charge_total_minor),0) service_charge_total_minor, COALESCE(SUM(pricing_rounding_minor),0) pricing_rounding_minor, COALESCE(SUM(total_minor),0) grand_total_minor'
        )->first();
        DB::table('fnb_orders')->where('id', $order->id)->update([
            'subtotal_minor' => (int) $totals->subtotal_minor,
            'discount_total_minor' => (int) $totals->discount_total_minor,
            'tax_total_minor' => (int) $totals->tax_total_minor,
            'service_charge_total_minor' => (int) $totals->service_charge_total_minor,
            'pricing_rounding_minor' => (int) $totals->pricing_rounding_minor,
            'grand_total_minor' => (int) $totals->grand_total_minor,
            'version' => $version,
            'updated_at' => now(),
        ]);
    }

    /** @return array{0:string,1:string} */
    private function orderRollup(int $orderId): array
    {
        $lines = DB::table('fnb_order_lines')->where('order_id', $orderId)
            ->get(['status', 'voided_quantity', 'compensated_quantity']);
        $statuses = $lines->pluck('status');
        $active = $statuses->filter(fn (string $status): bool => in_array($status, ['draft', 'waiting', 'preparing', 'ready', 'compensation_pending'], true));
        if ($active->isNotEmpty()) {
            $fulfillment = $statuses->contains('preparing') ? 'preparing' : ($statuses->contains('ready') ? 'partially_ready' : 'waiting');

            return ['active', $fulfillment];
        }
        $served = $statuses->filter(fn (string $status): bool => $status === 'served')->count();
        $cancelled = $statuses->count() - $served;
        $hasPartialCancellation = $lines->contains(fn (object $line): bool => bccomp((string) $line->voided_quantity, '0', 6) > 0
            || bccomp((string) $line->compensated_quantity, '0', 6) > 0
        );
        $fulfillment = $served === 0
            ? 'cancelled'
            : ($cancelled === 0 && ! $hasPartialCancellation ? 'fulfilled' : 'fulfilled_mixed');

        return ['completed', $fulfillment];
    }

    private function ticketRollup(int $ticketId): string
    {
        $statuses = DB::table('fnb_kitchen_ticket_lines')->where('ticket_id', $ticketId)->pluck('status_rollup');
        if ($statuses->contains(fn (string $status): bool => in_array($status, ['waiting', 'preparing', 'compensation_pending'], true))) {
            return 'in_progress';
        }
        if ($statuses->contains('ready')) {
            return 'all_ready';
        }
        if ($statuses->every(fn (string $status): bool => $status === 'voided')) {
            return 'all_voided';
        }
        if ($statuses->every(fn (string $status): bool => $status === 'cancelled_compensated')) {
            return 'all_compensated';
        }
        if ($statuses->contains('served') && $statuses->every(fn (string $status): bool => in_array($status, ['served', 'voided', 'cancelled_compensated'], true))) {
            return 'completed';
        }

        return 'cancelled_mixed';
    }

    private function recordWaste(FnbContext $context, object $order, object $line, string $idempotencyKey, string $reason): void
    {
        $sourceEventId = "order-line:{$line->id}:waste:v{$order->version}";
        if (DB::table('fnb_stock_consumptions')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('kind', 'waste')->where('source_event_id', $sourceEventId)->exists()) {
            return;
        }
        $recipe = $line->recipe_snapshot ? json_decode((string) $line->recipe_snapshot, true, 512, JSON_THROW_ON_ERROR) : null;
        $modifierRecipes = DB::table('fnb_order_line_modifiers')->where('website_key', $context->websiteKey)
            ->where('outlet_id', $context->outletId)->where('order_line_id', $line->id)
            ->whereNotNull('recipe_snapshot')->get([
                'modifier_option_id', 'quantity', 'recipe_snapshot', 'recipe_snapshot_hash',
            ]);
        $snapshot = [
            'order_id' => (int) $order->id,
            'order_line_id' => (int) $line->id,
            'quantity' => (string) $line->ordered_quantity,
            'prior_status' => $line->status,
            'reason' => $reason,
            'recipe' => $recipe,
            'modifier_recipes' => $modifierRecipes->map(fn (object $modifier): array => [
                'modifier_option_id' => (int) $modifier->modifier_option_id,
                'quantity' => (string) $modifier->quantity,
                'recipe_snapshot_hash' => $modifier->recipe_snapshot_hash,
            ])->all(),
        ];
        $id = DB::table('fnb_stock_consumptions')->insertGetId([
            'website_key' => $context->websiteKey,
            'outlet_id' => $context->outletId,
            'public_id' => (string) Str::uuid(),
            'source_type' => 'order_line',
            'source_id' => (string) $line->id,
            'source_version' => (int) $order->version,
            'source_event_id' => $sourceEventId,
            'kind' => 'waste',
            'status' => 'pending',
            'idempotency_key' => hash('sha256', $sourceEventId),
            'payload_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'payload_hash' => hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($recipe !== null) {
            $this->insertWasteRecipeLines($context, $id, (int) $line->id, $recipe, (string) $line->ordered_quantity);
        }
        foreach ($modifierRecipes as $modifier) {
            $modifierRecipe = json_decode((string) $modifier->recipe_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $this->insertWasteRecipeLines(
                $context,
                $id,
                (int) $line->id,
                $modifierRecipe,
                (string) $line->ordered_quantity,
                (string) $modifier->quantity,
                ['modifier_option_id' => (int) $modifier->modifier_option_id],
            );
        }
    }

    /** @param array<string,mixed> $recipe @param array<string,mixed> $source */
    private function insertWasteRecipeLines(
        FnbContext $context,
        int $consumptionId,
        int $orderLineId,
        array $recipe,
        string $saleQuantity,
        string $selectionQuantity = '1.000000',
        array $source = [],
    ): void {
        foreach (RecipeConsumption::expand($recipe, $saleQuantity, $selectionQuantity) as $ingredient) {
            $quantity = $ingredient['consumed_quantity'];
            unset($ingredient['consumed_quantity']);
            DB::table('fnb_stock_consumption_lines')->insert([
                'website_key' => $context->websiteKey,
                'outlet_id' => $context->outletId,
                'consumption_id' => $consumptionId,
                'order_line_id' => $orderLineId,
                'ingredient_id' => $ingredient['ingredient_id'],
                'quantity' => $quantity,
                'base_unit' => $ingredient['base_unit'],
                'snapshot' => json_encode($ingredient + $source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** @param array<string,mixed> $payload */
    private function orderEvent(FnbContext $context, int $orderId, string $type, ?string $from, ?string $to, int $version, string $idempotencyKey, array $payload = []): void
    {
        DB::table('fnb_order_events')->insert([
            'website_key' => $context->websiteKey,
            'outlet_id' => $context->outletId,
            'order_id' => $orderId,
            'event_type' => $type,
            'from_lifecycle_status' => $from,
            'to_lifecycle_status' => $to,
            'aggregate_version' => $version,
            'actor_id' => $context->actorId,
            'approver_id' => $context->authorizationEvidence->approverId(),
            'payload' => $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'idempotency_key' => $idempotencyKey,
            'occurred_at' => now(),
        ]);
    }

    /** @return array<string,mixed> */
    private function orderResource(FnbContext $context, int $orderId): array
    {
        $resource = $this->record('fnb_orders', $orderId);
        $resource['lines'] = DB::table('fnb_order_lines')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('order_id', $orderId)
            ->orderBy('sort_order')->get()->map(function (object $line): array {
                $value = $this->decode($line);
                $value['modifiers'] = DB::table('fnb_order_line_modifiers')->where('order_line_id', $line->id)->orderBy('id')->get()->map(fn ($row) => $this->decode($row))->all();

                return $value;
            })->all();
        $resource['adjustments'] = DB::table('fnb_order_adjustments')->where('website_key', $context->websiteKey)
            ->where('outlet_id', $context->outletId)->where('order_id', $orderId)->orderBy('id')
            ->get()->map(fn (object $adjustment): array => $this->decode($adjustment))->all();

        return $resource;
    }

    /** @return array<string,mixed> */
    private function ticketResource(FnbContext $context, int $ticketId): array
    {
        $ticket = DB::table('fnb_kitchen_tickets')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('id', $ticketId)->firstOrFail();
        $resource = $this->decode($ticket);
        // Deliberately project only KDS-safe snapshots: no customer, price,
        // discounts, payments or recipe cost fields are selected here.
        $resource['lines'] = DB::table('fnb_kitchen_ticket_lines as kitchen_line')
            ->join('fnb_order_lines as order_line', 'order_line.id', '=', 'kitchen_line.order_line_id')
            ->where('kitchen_line.website_key', $context->websiteKey)->where('kitchen_line.outlet_id', $context->outletId)
            ->where('kitchen_line.ticket_id', $ticketId)->orderBy('kitchen_line.id')
            ->get([
                'kitchen_line.id', 'kitchen_line.order_line_id', 'kitchen_line.attempt_no', 'kitchen_line.quantity',
                'kitchen_line.served_quantity', 'kitchen_line.voided_quantity', 'kitchen_line.compensated_quantity',
                'kitchen_line.status_rollup', 'kitchen_line.note', 'kitchen_line.waiting_at', 'kitchen_line.preparing_at',
                'kitchen_line.ready_at', 'kitchen_line.served_at', 'kitchen_line.version',
                'order_line.item_code_snapshot', 'order_line.item_name_snapshot', 'order_line.variant_code_snapshot', 'order_line.variant_name_snapshot',
            ])->map(fn (object $line): array => $this->decode($line))->all();

        return $resource;
    }

    private function locked(string $table, FnbContext $context, int $id): object
    {
        $record = DB::table($table)->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('id', $id)->lockForUpdate()->first();
        if ($record === null) {
            throw new FnbNotFoundException;
        }

        return $record;
    }

    private function assertVersion(string $type, object $record, int $expected): void
    {
        if ((int) $record->version !== $expected) {
            throw new FnbConflictException('Aggregate version is stale.', [
                'current_versions' => ["{$type}:{$record->id}" => (int) $record->version],
                'current_snapshot' => $this->decode($record),
            ]);
        }
    }

    /** @return array<string,mixed> */
    private function record(string $table, int $id): array
    {
        return $this->decode(DB::table($table)->where('id', $id)->firstOrFail());
    }

    /** @return array<string,mixed> */
    private function decode(object $record): array
    {
        $values = (array) $record;
        foreach ($values as $key => $value) {
            if (is_string($value) && (str_ends_with($key, '_snapshot') || in_array($key, ['payload', 'settings'], true))) {
                $values[$key] = json_decode($value, true) ?? $value;
            }
            $numericId = ($key === 'id' || str_ends_with($key, '_id'))
                && $value !== null && preg_match('/^\d+$/', (string) $value) === 1;
            if (str_ends_with($key, '_minor') || $numericId || in_array($key, ['version', 'sequence_no', 'tax_rate_bps_snapshot'], true)) {
                $values[$key] = $value === null ? null : (int) $value;
            }
        }

        return $values;
    }

    /** @param list<array<string,mixed>> $events @param array<string,mixed> $extraMeta */
    private function envelope(string $type, int $id, int $version, array $resource, array $events, array $extraMeta = []): array
    {
        return [
            'resource' => $resource,
            'events' => $events,
            'meta' => ['resource_type' => $type, 'resource_id' => $id, 'version' => $version] + $extraMeta,
        ];
    }
}
