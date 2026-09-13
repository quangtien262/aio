<?php

namespace Modules\FnbPos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Domain\FnbNotFoundException;
use Modules\FnbPos\Domain\FnbValidationException;
use Modules\FnbPos\Domain\MinorMoney;
use Modules\FnbPos\Domain\RecipeConsumption;

final class FnbSettlementService
{
    private const PAYMENT_CAP_STATUSES = ['reserved', 'processing', 'uncertain', 'reconciling', 'succeeded'];

    private const REFUND_CAP_STATUSES = ['requested', 'reserved', 'processing', 'uncertain', 'reconciling', 'attention', 'succeeded'];

    public function __construct(
        private readonly FnbCommandRunner $commands,
        private readonly FnbSequenceService $sequences,
        private readonly FnbOutboxService $outbox,
    ) {}

    /** @param list<array<string,mixed>> $allocations */
    public function createCheck(FnbContext $context, int $sessionId, array $allocations, string $idempotencyKey, int $expectedSessionVersion): array
    {
        foreach ($allocations as $index => $allocation) {
            if (! isset($allocation['order_line_id'], $allocation['quantity'])) {
                throw new FnbValidationException("Invalid check allocation at index {$index}.");
            }
            MinorMoney::quantityMicros($allocation['quantity'], "allocations.{$index}.quantity");
        }

        return $this->commands->run($context, 'check.create', compact('sessionId', 'allocations', 'expectedSessionVersion'), $idempotencyKey, function () use ($context, $sessionId, $allocations, $idempotencyKey, $expectedSessionVersion): array {
            $session = $this->locked('fnb_service_sessions', $context, $sessionId);
            $this->assertVersion('service_session', $session, $expectedSessionVersion);
            if (! in_array($session->status, ['open', 'settling'], true)) {
                throw new FnbConflictException('Checks require an open or settling service session.');
            }
            $day = $this->locked('fnb_business_days', $context, (int) $session->business_day_id);
            if ($day->status !== 'open') {
                throw new FnbConflictException('Business day is not open.');
            }

            $sourceLines = DB::table('fnb_order_lines as line')
                ->join('fnb_orders as orders', 'orders.id', '=', 'line.order_id')
                ->where('line.website_key', $context->websiteKey)
                ->where('line.outlet_id', $context->outletId)
                ->where('orders.session_id', $session->id)
                ->whereIn('orders.lifecycle_status', ['active', 'completed'])
                ->whereNotIn('line.status', ['draft', 'voided', 'cancelled_compensated'])
                ->select('line.*', 'orders.session_id', 'orders.currency')
                ->orderBy('line.id')->lockForUpdate()->get()->keyBy('id');
            if ($sourceLines->isEmpty()) {
                throw new FnbValidationException('No submitted order line is available for a check.');
            }

            $requested = collect($allocations)->mapWithKeys(function (array $allocation): array {
                return [(int) $allocation['order_line_id'] => MinorMoney::quantityMicros($allocation['quantity'])];
            });
            if ($requested->count() !== count($allocations)) {
                throw new FnbValidationException('Each order line may occur only once in a check command.');
            }

            $prepared = [];
            foreach ($sourceLines as $lineId => $line) {
                $prior = DB::table('fnb_check_lines as allocation')
                    ->join('fnb_checks as checks', 'checks.id', '=', 'allocation.check_id')
                    ->where('allocation.website_key', $context->websiteKey)->where('allocation.outlet_id', $context->outletId)
                    ->where('allocation.order_line_id', $lineId)->where('checks.status', '!=', 'void')
                    ->selectRaw('COALESCE(SUM(allocated_quantity),0) quantity, COALESCE(SUM(allocated_subtotal_minor),0) subtotal_minor, COALESCE(SUM(allocated_discount_minor),0) discount_minor, COALESCE(SUM(allocated_service_charge_minor),0) service_charge_minor, COALESCE(SUM(allocated_tax_minor),0) tax_minor, COALESCE(SUM(allocated_pricing_rounding_minor),0) pricing_rounding_minor, COALESCE(SUM(allocated_total_minor),0) total_minor')
                    ->first();
                $effectiveMicros = $this->quantityMicrosAllowZero(bcsub((string) $line->ordered_quantity, bcadd((string) $line->voided_quantity, (string) $line->compensated_quantity, 6), 6));
                $priorMicros = $this->quantityMicrosAllowZero((string) $prior->quantity);
                $remainingMicros = $effectiveMicros - $priorMicros;
                $requestedMicros = $allocations === [] ? $remainingMicros : (int) ($requested[$lineId] ?? 0);
                if ($requestedMicros === 0) {
                    continue;
                }
                if ($requestedMicros < 0 || $requestedMicros > $remainingMicros) {
                    throw new FnbConflictException('Check allocation exceeds remaining line quantity.', [
                        'order_line_id' => (int) $lineId,
                        'remaining_quantity' => MinorMoney::formatQuantity(max(0, $remainingMicros)),
                    ]);
                }

                $sourceMicros = MinorMoney::quantityMicros((string) $line->ordered_quantity);
                $isRemainder = $requestedMicros === $remainingMicros;
                $components = [
                    'allocated_subtotal_minor' => $this->allocateComponent((int) $line->subtotal_minor, (int) $prior->subtotal_minor, $requestedMicros, $sourceMicros, $isRemainder),
                    'allocated_discount_minor' => $this->allocateComponent((int) $line->discount_total_minor, (int) $prior->discount_minor, $requestedMicros, $sourceMicros, $isRemainder),
                    'allocated_service_charge_minor' => $this->allocateComponent((int) $line->service_charge_total_minor, (int) $prior->service_charge_minor, $requestedMicros, $sourceMicros, $isRemainder),
                    'allocated_tax_minor' => $this->allocateComponent((int) $line->tax_total_minor, (int) $prior->tax_minor, $requestedMicros, $sourceMicros, $isRemainder),
                    'allocated_pricing_rounding_minor' => $this->allocateComponent((int) $line->pricing_rounding_minor, (int) $prior->pricing_rounding_minor, $requestedMicros, $sourceMicros, $isRemainder),
                ];
                $components['allocated_total_minor'] = $components['allocated_subtotal_minor']
                    - $components['allocated_discount_minor'] + $components['allocated_service_charge_minor']
                    + $components['allocated_tax_minor'] + $components['allocated_pricing_rounding_minor'];
                $prepared[] = [
                    'business_day_id' => (int) $line->business_day_id,
                    'currency' => $line->currency,
                    'session_id' => (int) $line->session_id,
                    'order_id' => (int) $line->order_id,
                    'order_line_id' => (int) $line->id,
                    'allocated_quantity' => MinorMoney::formatQuantity($requestedMicros),
                    ...$components,
                ];
            }
            if ($allocations !== [] && count($prepared) !== count($allocations)) {
                throw new FnbNotFoundException('An allocated order line is outside this session or is not settleable.');
            }
            if ($prepared === []) {
                throw new FnbValidationException('Check allocation cannot be empty.');
            }

            $number = $this->sequences->next($context, $day->business_date, 'check', 'CHK');
            $totals = $this->sumAllocatedComponents($prepared);
            $now = now();
            $checkId = DB::table('fnb_checks')->insertGetId([
                'website_key' => $context->websiteKey,
                'outlet_id' => $context->outletId,
                'public_id' => (string) Str::uuid(),
                'creation_key' => $idempotencyKey,
                'business_day_id' => $session->business_day_id,
                'session_id' => $session->id,
                'customer_profile_id' => $session->customer_profile_id,
                'sequence_no' => $number['sequence_no'],
                'check_no' => $number['document_no'],
                'status' => 'open',
                'currency' => $session->currency,
                'timezone_snapshot' => $session->timezone_snapshot,
                'subtotal_minor' => $totals['subtotal_minor'],
                'discount_total_minor' => $totals['discount_minor'],
                'tax_total_minor' => $totals['tax_minor'],
                'service_charge_total_minor' => $totals['service_charge_minor'],
                'pricing_rounding_minor' => $totals['pricing_rounding_minor'],
                'grand_total_minor' => $totals['total_minor'],
                'buyer_snapshot' => $session->customer_snapshot,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($prepared as $line) {
                DB::table('fnb_check_lines')->insert([
                    'website_key' => $context->websiteKey,
                    'outlet_id' => $context->outletId,
                    'check_id' => $checkId,
                    ...$line,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            DB::table('fnb_check_financial_projections')->insert([
                'website_key' => $context->websiteKey,
                'outlet_id' => $context->outletId,
                'check_id' => $checkId,
                'gross_paid_total_minor' => 0,
                'refunded_total_minor' => 0,
                'net_collected_total_minor' => 0,
                'payment_status' => 'unpaid',
                'refund_status' => 'none',
                'source_watermark' => 0,
                'version' => 1,
                'updated_at' => $now,
            ]);
            $sessionVersion = (int) $session->version + 1;
            DB::table('fnb_service_sessions')->where('id', $session->id)->update(['version' => $sessionVersion, 'updated_at' => now()]);
            $event = $this->outbox->emit($context, 'check', $checkId, 1, 'fnb.check.created', [
                'check_id' => $checkId,
                'check_no' => $number['document_no'],
                'session_id' => (int) $session->id,
                'grand_total_minor' => $totals['total_minor'],
                'currency' => $session->currency,
            ]);

            return $this->envelope('check', $checkId, 1, $this->checkResource($context, $checkId), [$event], ['session_version' => $sessionVersion]);
        });
    }

    public function finalizeCheck(FnbContext $context, int $checkId, string $idempotencyKey, int $expectedVersion): array
    {
        return $this->commands->run($context, 'check.finalize', compact('checkId', 'expectedVersion'), $idempotencyKey, function () use ($context, $checkId, $expectedVersion): array {
            $check = $this->locked('fnb_checks', $context, $checkId);
            $this->assertVersion('check', $check, $expectedVersion);
            if ($check->status !== 'open') {
                throw new FnbConflictException('Only an open check can be finalized.');
            }
            if (! DB::table('fnb_check_lines')->where('check_id', $check->id)->exists() || (int) $check->grand_total_minor < 0) {
                throw new FnbValidationException('Check has no valid allocation.');
            }
            if (DB::table('fnb_payments')->where('check_id', $check->id)->exists()) {
                throw new FnbConflictException('A check with payment history cannot be structurally finalized again.');
            }
            $this->assertCheckEquation($check);
            $version = (int) $check->version + 1;
            DB::table('fnb_checks')->where('id', $check->id)->update([
                'status' => 'finalized',
                'finalized_at' => now(),
                'finalized_by' => $context->actorId,
                'version' => $version,
                'updated_at' => now(),
            ]);
            $event = $this->outbox->emit($context, 'check', $check->id, $version, 'fnb.check.finalized', [
                'check_id' => (int) $check->id,
                'grand_total_minor' => (int) $check->grand_total_minor,
                'currency' => $check->currency,
            ]);

            return $this->envelope('check', (int) $check->id, $version, $this->checkResource($context, $check->id), [$event]);
        });
    }

    public function reopenCheck(FnbContext $context, int $checkId, string $reason, string $idempotencyKey, int $expectedVersion): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw new FnbValidationException('A check reopen reason between 3 and 500 characters is required.');
        }

        return $this->commands->run($context, 'check.reopen', compact('checkId', 'reason', 'expectedVersion'), $idempotencyKey, function () use ($context, $checkId, $reason, $expectedVersion): array {
            $check = $this->locked('fnb_checks', $context, $checkId);
            $this->assertVersion('check', $check, $expectedVersion);
            if ($check->status !== 'finalized') {
                throw new FnbConflictException('Only a finalized unpaid check can be reopened.');
            }
            if ($check->settlement_mode !== null || $check->cash_rounding_minor !== null
                || $check->settlement_total_minor !== null || $check->settlement_hash !== null
                || DB::table('fnb_payments')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('check_id', $check->id)->exists()) {
                throw new FnbConflictException('A settlement-planned or payment-attempted check cannot be reopened; void and replace it.');
            }
            $version = (int) $check->version + 1;
            DB::table('fnb_checks')->where('id', $check->id)->update([
                'status' => 'open',
                'finalized_at' => null,
                'finalized_by' => null,
                'version' => $version,
                'updated_at' => now(),
            ]);
            $event = $this->outbox->emit($context, 'check', $check->id, $version, 'fnb.check.reopened', [
                'check_id' => (int) $check->id, 'reason' => $reason,
            ]);

            return $this->envelope('check', (int) $check->id, $version, $this->checkResource($context, (int) $check->id), [$event]);
        });
    }

    public function voidCheck(FnbContext $context, int $checkId, string $reason, string $idempotencyKey, int $expectedVersion): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw new FnbValidationException('A check void reason between 3 and 500 characters is required.');
        }

        return $this->commands->run($context, 'check.void', compact('checkId', 'reason', 'expectedVersion'), $idempotencyKey, function () use ($context, $checkId, $reason, $expectedVersion): array {
            $check = $this->locked('fnb_checks', $context, $checkId);
            $this->assertVersion('check', $check, $expectedVersion);
            if (! in_array($check->status, ['open', 'finalized'], true)) {
                throw new FnbConflictException('Only an open or finalized check can be voided.');
            }
            $payments = DB::table('fnb_payments')->where('website_key', $context->websiteKey)
                ->where('outlet_id', $context->outletId)->where('check_id', $check->id)->lockForUpdate()->get();
            if ($payments->contains(fn (object $payment): bool => in_array($payment->status, self::PAYMENT_CAP_STATUSES, true))) {
                throw new FnbConflictException('A check with active or succeeded payment cannot be voided.');
            }
            $version = (int) $check->version + 1;
            DB::table('fnb_checks')->where('id', $check->id)->update([
                'status' => 'void',
                'voided_at' => now(),
                'voided_by' => $context->actorId,
                'version' => $version,
                'updated_at' => now(),
            ]);
            $event = $this->outbox->emit($context, 'check', $check->id, $version, 'fnb.check.voided', [
                'check_id' => (int) $check->id, 'reason' => $reason,
            ]);

            return $this->envelope('check', (int) $check->id, $version, $this->checkResource($context, (int) $check->id), [$event]);
        });
    }

    public function setSettlementPlan(FnbContext $context, int $checkId, string $mode, string $idempotencyKey, int $expectedVersion): array
    {
        if (! in_array($mode, ['single_cash', 'single_non_cash', 'mixed'], true)) {
            throw new FnbValidationException('Unsupported settlement mode.');
        }

        return $this->commands->run($context, 'check.settlement_plan', compact('checkId', 'mode', 'expectedVersion'), $idempotencyKey, function () use ($context, $checkId, $mode, $idempotencyKey, $expectedVersion): array {
            $check = $this->locked('fnb_checks', $context, $checkId);
            $this->assertVersion('check', $check, $expectedVersion);
            if ($check->status !== 'finalized') {
                throw new FnbConflictException('Settlement plan requires a finalized check.');
            }
            if ($check->settlement_mode !== null || $check->cash_rounding_minor !== null || $check->settlement_total_minor !== null || $check->settlement_hash !== null) {
                throw new FnbConflictException('Settlement fields are immutable once selected.');
            }
            if (DB::table('fnb_payments')->where('check_id', $check->id)->exists()) {
                throw new FnbConflictException('Settlement plan must be selected before the first payment attempt.');
            }

            if ($mode === 'single_cash') {
                $outlet = DB::table('fnb_outlets')->where('website_key', $context->websiteKey)->where('id', $context->outletId)->firstOrFail();
                $settings = json_decode((string) ($outlet->settings ?? '{}'), true) ?: [];
                $step = max(1, (int) ($settings['cash_rounding_step_minor'] ?? ($check->currency === 'VND' ? 500 : 1)));
                $rounded = MinorMoney::roundCash((int) $check->grand_total_minor, $step);
                $rounding = $rounded['rounding_minor'];
                $settlementTotal = $rounded['settlement_total_minor'];
            } else {
                $rounding = 0;
                $settlementTotal = (int) $check->grand_total_minor;
            }
            $settlement = [
                'check_id' => (int) $check->id,
                'check_version' => (int) $check->version,
                'mode' => $mode,
                'grand_total_minor' => (int) $check->grand_total_minor,
                'cash_rounding_minor' => $rounding,
                'settlement_total_minor' => $settlementTotal,
                'currency' => $check->currency,
            ];
            $hash = hash('sha256', json_encode($settlement, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            if ($rounding !== 0) {
                DB::table('fnb_check_adjustments')->insert([
                    'website_key' => $context->websiteKey,
                    'outlet_id' => $context->outletId,
                    'check_id' => $check->id,
                    'kind' => 'cash_rounding',
                    'amount_minor' => $rounding,
                    'policy_snapshot' => json_encode(['mode' => 'half_up'], JSON_THROW_ON_ERROR),
                    'reason' => 'cash_settlement_rounding',
                    'actor_id' => $context->actorId,
                    'idempotency_key' => $idempotencyKey,
                    'occurred_at' => now(),
                ]);
            }
            $version = (int) $check->version + 1;
            DB::table('fnb_checks')->where('id', $check->id)->update([
                'settlement_mode' => $mode,
                'cash_rounding_minor' => $rounding,
                'settlement_total_minor' => $settlementTotal,
                'settlement_hash' => $hash,
                'settlement_planned_at' => now(),
                'version' => $version,
                'updated_at' => now(),
            ]);
            $event = $this->outbox->emit($context, 'check', $check->id, $version, 'fnb.check.settlement_planned', $settlement + ['settlement_hash' => $hash]);

            return $this->envelope('check', (int) $check->id, $version, $this->checkResource($context, $check->id), [$event]);
        });
    }

    /** @param array<string,mixed> $input */
    public function collectPayment(FnbContext $context, int $checkId, int $shiftId, array $input, string $idempotencyKey, int $expectedVersion): array
    {
        $data = validator($input, [
            'payment_method_id' => ['required', 'integer', 'min:1'],
            'amount_minor' => ['required', 'integer', 'min:1', 'max:9000000000000'],
            'tendered_minor' => ['nullable', 'integer', 'min:0', 'max:9000000000000'],
            'reference' => ['nullable', 'string', 'max:160'],
        ])->validate();

        return $this->commands->run($context, 'payment.collect', $data + compact('checkId', 'shiftId', 'expectedVersion'), $idempotencyKey, function () use ($context, $checkId, $shiftId, $data, $idempotencyKey, $expectedVersion): array {
            $check = $this->locked('fnb_checks', $context, $checkId);
            $this->assertVersion('check', $check, $expectedVersion);
            if ($check->status !== 'finalized' || $check->settlement_total_minor === null || $check->settlement_hash === null) {
                throw new FnbConflictException('Payment requires a finalized check with a locked settlement plan.');
            }
            $shift = $this->locked('fnb_shifts', $context, $shiftId);
            if ($shift->status !== 'open'
                || (int) $shift->business_day_id !== (int) $check->business_day_id
                || $shift->currency !== $check->currency
                || ($context->terminalId !== null && (int) $shift->terminal_id !== $context->terminalId)) {
                throw new FnbConflictException('Payment processing shift does not match check day, currency and terminal.');
            }
            $method = DB::table('fnb_payment_methods')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)
                ->where('id', $data['payment_method_id'])->where('status', 'active')->lockForUpdate()->first();
            if ($method === null) {
                throw new FnbNotFoundException('Active payment method was not found.');
            }
            if ((bool) $method->requires_reference && trim((string) ($data['reference'] ?? '')) === '') {
                throw new FnbValidationException('This payment method requires a reference.');
            }
            if ($check->settlement_mode === 'single_cash' && $method->kind !== 'cash') {
                throw new FnbConflictException('Single-cash settlement only accepts a cash method.');
            }
            if ($check->settlement_mode === 'single_non_cash' && $method->kind === 'cash') {
                throw new FnbConflictException('Single-non-cash settlement cannot accept cash.');
            }

            $reserved = (int) DB::table('fnb_payments')->where('check_id', $check->id)->whereIn('status', self::PAYMENT_CAP_STATUSES)->lockForUpdate()->sum('amount_minor');
            $remaining = (int) $check->settlement_total_minor - $reserved;
            $amount = MinorMoney::assertMinor((int) $data['amount_minor']);
            if ($amount <= 0 || $amount > $remaining) {
                throw new FnbConflictException('Payment would exceed the remaining settlement amount.', [
                    'remaining_minor' => $remaining,
                ]);
            }
            if (in_array($check->settlement_mode, ['single_cash', 'single_non_cash'], true) && $amount !== $remaining) {
                throw new FnbConflictException('Single-tender settlement must collect the full remaining amount.');
            }
            $tendered = isset($data['tendered_minor']) ? (int) $data['tendered_minor'] : $amount;
            if ($method->kind === 'cash' && $tendered < $amount) {
                throw new FnbValidationException('Tendered cash cannot be below the payment amount.');
            }
            if ($method->kind !== 'cash' && $tendered !== $amount) {
                throw new FnbValidationException('Non-cash tendered amount must equal payment amount.');
            }

            $fingerprint = $this->commands->fingerprint($data + ['check_id' => $checkId, 'shift_id' => $shiftId]);
            $now = now();
            $paymentId = DB::table('fnb_payments')->insertGetId([
                'website_key' => $context->websiteKey,
                'outlet_id' => $context->outletId,
                'public_id' => (string) Str::uuid(),
                'business_day_id' => $check->business_day_id,
                'currency' => $check->currency,
                'check_id' => $check->id,
                'shift_id' => $shift->id,
                'terminal_id' => $shift->terminal_id,
                'payment_method_id' => $method->id,
                'method_code_snapshot' => $method->code,
                'method_name_snapshot' => $method->name,
                'method_kind_snapshot' => $method->kind,
                'provider_operation_key' => 'fnb-payment-'.$context->outletId.'-'.$idempotencyKey,
                'status' => 'reserved',
                'amount_minor' => $amount,
                'tendered_minor' => $tendered,
                'change_minor' => $method->kind === 'cash' ? $tendered - $amount : 0,
                'reference' => $data['reference'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'reserved_at' => $now,
                'created_by' => $context->actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            // Pilot methods are operator-confirmed records. Provider-backed
            // dispatch is intentionally gated until the M6 adapter contract.
            DB::table('fnb_payment_attempts')->insert([
                'website_key' => $context->websiteKey,
                'outlet_id' => $context->outletId,
                'payment_id' => $paymentId,
                'attempt_no' => 1,
                'status' => 'succeeded',
                'request_hash' => $fingerprint,
                'response_hash' => hash('sha256', 'operator-confirmed'),
                'response_code' => 'operator_confirmed',
                'started_at' => $now,
                'resolved_at' => $now,
            ]);
            DB::table('fnb_payments')->where('id', $paymentId)->update(['status' => 'succeeded', 'processed_at' => $now, 'updated_at' => $now]);

            $grossPaid = $reserved + $amount;
            $projection = DB::table('fnb_check_financial_projections')->where('check_id', $check->id)->lockForUpdate()->firstOrFail();
            $refunded = (int) $projection->refunded_total_minor;
            $paymentStatus = $grossPaid === (int) $check->settlement_total_minor ? 'paid' : 'partially_paid';
            DB::table('fnb_check_financial_projections')->where('id', $projection->id)->update([
                'gross_paid_total_minor' => $grossPaid,
                'net_collected_total_minor' => $grossPaid - $refunded,
                'payment_status' => $paymentStatus,
                'source_watermark' => $paymentId,
                'version' => (int) $projection->version + 1,
                'updated_at' => now(),
            ]);

            $events = [];
            $shiftVersion = (int) $shift->version;
            if ($method->kind === 'cash') {
                $movementId = DB::table('fnb_cash_movements')->insertGetId([
                    'website_key' => $context->websiteKey,
                    'outlet_id' => $context->outletId,
                    'business_day_id' => $shift->business_day_id,
                    'currency' => $shift->currency,
                    'terminal_id' => $shift->terminal_id,
                    'shift_id' => $shift->id,
                    'payment_id' => $paymentId,
                    'kind' => 'cash_sale',
                    'direction' => 'in',
                    'amount_minor' => $amount,
                    'reason' => 'check_payment',
                    'actor_id' => $context->actorId,
                    'idempotency_key' => 'payment:'.$paymentId,
                    'occurred_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $shiftVersion++;
                DB::table('fnb_shifts')->where('id', $shift->id)->update([
                    'expected_cash_minor' => (int) $shift->expected_cash_minor + $amount,
                    'version' => $shiftVersion,
                    'updated_at' => now(),
                ]);
                $events[] = $this->outbox->emit($context, 'cash_movement', $movementId, 1, 'fnb.cash_movement.payment_recorded', [
                    'cash_movement_id' => $movementId, 'payment_id' => $paymentId, 'amount_minor' => $amount,
                ]);
            }

            $checkVersion = (int) $check->version + 1;
            $checkUpdates = ['version' => $checkVersion, 'updated_at' => now()];
            if ($paymentStatus === 'paid') {
                $checkUpdates += [
                    'status' => 'closed',
                    'paid_at' => now(),
                    'closed_at' => now(),
                    'closed_by' => $context->actorId,
                ];
            }
            DB::table('fnb_checks')->where('id', $check->id)->update($checkUpdates);
            if ($paymentStatus === 'paid') {
                $this->recordSaleConsumption($context, $check, $checkVersion);
            }
            $this->rebuildOrderProjections($context, (int) $check->id, $paymentId);
            $events[] = $this->outbox->emit($context, 'payment', $paymentId, 1, 'fnb.payment.succeeded', [
                'payment_id' => $paymentId,
                'check_id' => (int) $check->id,
                'amount_minor' => $amount,
                'currency' => $check->currency,
                'method_code' => $method->code,
            ]);
            if ($paymentStatus === 'paid') {
                $events[] = $this->outbox->emit($context, 'check', $check->id, $checkVersion, 'fnb.check.closed', [
                    'check_id' => (int) $check->id,
                    'settlement_total_minor' => (int) $check->settlement_total_minor,
                    'currency' => $check->currency,
                ]);
            }

            return [
                'resource' => [
                    'payment' => $this->record('fnb_payments', $paymentId),
                    'check' => $this->checkResource($context, (int) $check->id),
                ],
                'events' => $events,
                'meta' => [
                    'resource_type' => 'payment', 'resource_id' => $paymentId, 'version' => 1,
                    'check_version' => $checkVersion, 'shift_version' => $shiftVersion,
                ],
            ];
        });
    }

    public function cancelPayment(FnbContext $context, int $paymentId, string $reason, string $idempotencyKey): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw new FnbValidationException('A payment cancellation reason is required.');
        }

        return $this->commands->run($context, 'payment.cancel', compact('paymentId', 'reason'), $idempotencyKey, function () use ($context, $paymentId, $reason): array {
            $payment = $this->locked('fnb_payments', $context, $paymentId);
            if (! in_array($payment->status, ['created', 'reserved'], true) || $payment->provider_dispatched_at !== null) {
                throw new FnbConflictException('Only a pre-dispatch created/reserved payment can be cancelled.');
            }
            DB::table('fnb_payments')->where('id', $payment->id)->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $context->actorId,
                'cancel_reason' => $reason,
                'updated_at' => now(),
            ]);
            $event = $this->outbox->emit($context, 'payment', $payment->id, 1, 'fnb.payment.cancelled', [
                'payment_id' => (int) $payment->id, 'check_id' => (int) $payment->check_id, 'reason' => $reason,
            ]);

            return $this->envelope('payment', (int) $payment->id, 1, $this->record('fnb_payments', $payment->id), [$event]);
        });
    }

    /** @param array<string,mixed> $input */
    public function refundPayment(FnbContext $context, int $paymentId, int $shiftId, array $input, string $idempotencyKey): array
    {
        $data = validator($input, [
            'amount_minor' => ['required', 'integer', 'min:1', 'max:9000000000000'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'allocations' => ['sometimes', 'array', 'max:500'],
            'allocations.*.check_line_id' => ['required', 'integer', 'min:1', 'distinct'],
            'allocations.*.quantity' => ['required', 'regex:/^(?:0|[1-9]\d*)(?:\.\d{1,6})?$/'],
        ])->validate();

        return $this->commands->run(
            $context,
            'payment.refund',
            $data + compact('paymentId', 'shiftId'),
            $idempotencyKey,
            fn (): array => $this->performRefund($context, $paymentId, $shiftId, $data, $idempotencyKey),
        );
    }

    /**
     * Internal boundary for compensation orchestration. The component vector
     * is never exposed as an HTTP contract and is revalidated against locked
     * CheckLine sources and all prior active refund allocations.
     *
     * @param  array<string,mixed>  $input
     */
    public function refundAllocated(FnbContext $context, int $paymentId, int $shiftId, array $input, string $idempotencyKey): array
    {
        $data = validator($input, [
            'amount_minor' => ['required', 'integer', 'min:1', 'max:9000000000000'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'allocations' => ['required', 'array', 'min:1', 'max:500'],
            'allocations.*.check_line_id' => ['nullable', 'integer', 'min:1'],
            'allocations.*.allocation_kind' => ['required', Rule::in(['line', 'cash_rounding'])],
            'allocations.*.quantity' => ['nullable', 'regex:/^(?:0|[1-9]\d*)(?:\.\d{1,6})?$/'],
            'allocations.*.allocated_subtotal_minor' => ['required', 'integer', 'min:0', 'max:9000000000000'],
            'allocations.*.allocated_discount_minor' => ['required', 'integer', 'min:0', 'max:9000000000000'],
            'allocations.*.allocated_service_charge_minor' => ['required', 'integer', 'min:0', 'max:9000000000000'],
            'allocations.*.allocated_tax_minor' => ['required', 'integer', 'min:0', 'max:9000000000000'],
            'allocations.*.allocated_pricing_rounding_minor' => ['required', 'integer', 'min:-9000000000000', 'max:9000000000000'],
            'allocations.*.allocated_cash_rounding_minor' => ['required', 'integer', 'min:-9000000000000', 'max:9000000000000'],
            'allocations.*.allocated_total_minor' => ['required', 'integer', 'min:-9000000000000', 'max:9000000000000'],
        ])->validate();

        return $this->commands->run(
            $context,
            'payment.refund',
            $data + compact('paymentId', 'shiftId'),
            $idempotencyKey,
            fn (): array => $this->performRefund($context, $paymentId, $shiftId, $data, $idempotencyKey, $data['allocations']),
        );
    }

    /** @param array<string,mixed> $data @param null|list<array<string,mixed>> $trustedAllocations */
    private function performRefund(
        FnbContext $context,
        int $paymentId,
        int $shiftId,
        array $data,
        string $idempotencyKey,
        ?array $trustedAllocations = null,
    ): array {
        $payment = $this->locked('fnb_payments', $context, $paymentId);
        if ($payment->status !== 'succeeded') {
            throw new FnbConflictException('Only a succeeded payment can be refunded.');
        }
        $check = $this->locked('fnb_checks', $context, (int) $payment->check_id);
        $shift = $this->locked('fnb_shifts', $context, $shiftId);
        if ($shift->status !== 'open' || $shift->currency !== $payment->currency
            || ($context->terminalId !== null && (int) $shift->terminal_id !== $context->terminalId)) {
            throw new FnbConflictException('Refund processing requires a matching open shift and terminal.');
        }
        $activeRefunds = (int) DB::table('fnb_refunds')->where('website_key', $context->websiteKey)
            ->where('outlet_id', $context->outletId)->where('payment_id', $payment->id)
            ->whereIn('status', self::REFUND_CAP_STATUSES)->lockForUpdate()->sum('amount_minor');
        $available = (int) $payment->amount_minor - $activeRefunds;
        $amount = (int) $data['amount_minor'];
        if ($amount > $available) {
            throw new FnbConflictException('Refund would exceed the payment cap.', ['refundable_minor' => $available]);
        }

        $allocations = $trustedAllocations === null
            ? $this->prepareRefundAllocations($context, $check, $amount, $data['allocations'] ?? [])
            : $this->validateStoredRefundAllocations($context, $check, $amount, $trustedAllocations);
        if (array_sum(array_column($allocations, 'allocated_total_minor')) !== $amount) {
            throw new FnbValidationException('Refund allocation vector must equal refund amount.');
        }

        $processingDay = $this->locked('fnb_business_days', $context, (int) $shift->business_day_id);
        if ($processingDay->status !== 'open') {
            throw new FnbConflictException('Refund processing business day is not open.');
        }
        $number = $this->sequences->next($context, $processingDay->business_date, 'refund', 'REF');
        $fingerprint = $this->commands->fingerprint($data + ['payment_id' => $paymentId, 'shift_id' => $shiftId]);
        $now = now();
        $refundId = DB::table('fnb_refunds')->insertGetId([
            'website_key' => $context->websiteKey,
            'outlet_id' => $context->outletId,
            'public_id' => (string) Str::uuid(),
            'original_business_day_id' => $payment->business_day_id,
            'processing_business_day_id' => $shift->business_day_id,
            'currency' => $payment->currency,
            'processed_shift_id' => $shift->id,
            'processed_terminal_id' => $shift->terminal_id,
            'payment_id' => $payment->id,
            'check_id' => $check->id,
            'method_code_snapshot' => $payment->method_code_snapshot,
            'method_name_snapshot' => $payment->method_name_snapshot,
            'method_kind_snapshot' => $payment->method_kind_snapshot,
            'sequence_no' => $number['sequence_no'],
            'refund_no' => $number['document_no'],
            'status' => 'reserved',
            'amount_minor' => $amount,
            'reason' => $data['reason'],
            'idempotency_key' => $idempotencyKey,
            'request_fingerprint' => $fingerprint,
            'provider_operation_key' => 'fnb-refund-'.$context->outletId.'-'.$idempotencyKey,
            'requested_at' => $now,
            'requested_by' => $context->actorId,
            'approved_at' => $context->authorizationEvidence->approverId() === null ? null : $now,
            'approved_by' => $context->authorizationEvidence->approverId(),
            'reserved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        foreach ($allocations as $allocation) {
            DB::table('fnb_refund_allocations')->insert([
                'website_key' => $context->websiteKey,
                'outlet_id' => $context->outletId,
                'refund_id' => $refundId,
                'check_id' => $check->id,
                ...$allocation,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        DB::table('fnb_refund_attempts')->insert([
            'website_key' => $context->websiteKey,
            'outlet_id' => $context->outletId,
            'refund_id' => $refundId,
            'attempt_no' => 1,
            'status' => 'succeeded',
            'request_hash' => $fingerprint,
            'response_hash' => hash('sha256', 'operator-confirmed'),
            'response_code' => 'operator_confirmed',
            'started_at' => $now,
            'resolved_at' => $now,
        ]);
        DB::table('fnb_refunds')->where('id', $refundId)->update(['status' => 'succeeded', 'processed_at' => $now, 'updated_at' => $now]);

        $projection = DB::table('fnb_check_financial_projections')->where('website_key', $context->websiteKey)
            ->where('outlet_id', $context->outletId)->where('check_id', $check->id)->lockForUpdate()->firstOrFail();
        $refunded = (int) $projection->refunded_total_minor + $amount;
        if ($refunded > (int) $projection->gross_paid_total_minor) {
            throw new FnbConflictException('Refund projection would exceed gross collected amount.');
        }
        DB::table('fnb_check_financial_projections')->where('id', $projection->id)->update([
            'refunded_total_minor' => $refunded,
            'net_collected_total_minor' => (int) $projection->gross_paid_total_minor - $refunded,
            'refund_status' => $refunded === (int) $projection->gross_paid_total_minor ? 'refunded' : 'partially_refunded',
            'source_watermark' => max((int) $projection->source_watermark, $refundId),
            'version' => (int) $projection->version + 1,
            'updated_at' => now(),
        ]);
        $events = [];
        $shiftVersion = (int) $shift->version;
        if ($payment->method_kind_snapshot === 'cash') {
            if ((int) $shift->expected_cash_minor < $amount) {
                throw new FnbConflictException('Refund would make expected shift cash negative.');
            }
            $movementId = DB::table('fnb_cash_movements')->insertGetId([
                'website_key' => $context->websiteKey,
                'outlet_id' => $context->outletId,
                'business_day_id' => $shift->business_day_id,
                'currency' => $shift->currency,
                'terminal_id' => $shift->terminal_id,
                'shift_id' => $shift->id,
                'refund_id' => $refundId,
                'kind' => 'cash_refund',
                'direction' => 'out',
                'amount_minor' => $amount,
                'reason' => $data['reason'],
                'actor_id' => $context->actorId,
                'idempotency_key' => 'refund:'.$refundId,
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $shiftVersion++;
            DB::table('fnb_shifts')->where('id', $shift->id)->update([
                'expected_cash_minor' => (int) $shift->expected_cash_minor - $amount,
                'version' => $shiftVersion,
                'updated_at' => now(),
            ]);
            $events[] = $this->outbox->emit($context, 'cash_movement', $movementId, 1, 'fnb.cash_movement.refund_recorded', [
                'cash_movement_id' => $movementId, 'refund_id' => $refundId, 'amount_minor' => $amount,
            ]);
        }
        $this->rebuildOrderProjections($context, (int) $check->id, $refundId);
        $events[] = $this->outbox->emit($context, 'refund', $refundId, 1, 'fnb.refund.succeeded', [
            'refund_id' => $refundId,
            'refund_no' => $number['document_no'],
            'payment_id' => (int) $payment->id,
            'check_id' => (int) $check->id,
            'amount_minor' => $amount,
            'currency' => $payment->currency,
        ]);

        return [
            'resource' => [
                'refund' => $this->refundResource($context, $refundId),
                'check' => $this->checkResource($context, (int) $check->id),
            ],
            'events' => $events,
            'meta' => ['resource_type' => 'refund', 'resource_id' => $refundId, 'version' => 1, 'shift_version' => $shiftVersion],
        ];
    }

    public function cancelRefund(FnbContext $context, int $refundId, string $reason, string $idempotencyKey): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw new FnbValidationException('A refund cancellation reason is required.');
        }

        return $this->commands->run($context, 'refund.cancel', compact('refundId', 'reason'), $idempotencyKey, function () use ($context, $refundId, $reason): array {
            $refund = $this->locked('fnb_refunds', $context, $refundId);
            if (! in_array($refund->status, ['requested', 'reserved'], true) || $refund->provider_dispatched_at !== null) {
                throw new FnbConflictException('Only a pre-dispatch requested/reserved refund can be cancelled.');
            }
            DB::table('fnb_refunds')->where('id', $refund->id)->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $context->actorId,
                'cancel_reason' => $reason,
                'updated_at' => now(),
            ]);
            $event = $this->outbox->emit($context, 'refund', $refund->id, 1, 'fnb.refund.cancelled', [
                'refund_id' => (int) $refund->id, 'payment_id' => (int) $refund->payment_id, 'reason' => $reason,
            ]);

            return $this->envelope('refund', (int) $refund->id, 1, $this->refundResource($context, (int) $refund->id), [$event]);
        });
    }

    /** @param list<array<string,mixed>> $requested @return list<array<string,mixed>> */
    private function prepareRefundAllocations(FnbContext $context, object $check, int $amount, array $requested): array
    {
        $lines = DB::table('fnb_check_lines')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)
            ->where('check_id', $check->id)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $priorRows = DB::table('fnb_refund_allocations as allocation')
            ->join('fnb_refunds as refund', 'refund.id', '=', 'allocation.refund_id')
            ->where('allocation.check_id', $check->id)->whereIn('refund.status', self::REFUND_CAP_STATUSES)
            ->get(['allocation.*']);
        $remainingGross = (int) DB::table('fnb_check_financial_projections')->where('check_id', $check->id)->value('gross_paid_total_minor')
            - (int) DB::table('fnb_check_financial_projections')->where('check_id', $check->id)->value('refunded_total_minor');

        if ($requested === [] && $amount !== $remainingGross) {
            throw new FnbValidationException('Partial refunds require explicit check-line quantity allocations.');
        }

        $requestedByLine = collect($requested)->mapWithKeys(fn (array $row): array => [
            (int) $row['check_line_id'] => MinorMoney::quantityMicros($row['quantity']),
        ]);
        if ($requestedByLine->count() !== count($requested)) {
            throw new FnbValidationException('Each check line may occur only once in a refund command.');
        }

        $allocations = [];
        foreach ($lines as $lineId => $line) {
            $prior = $priorRows->where('check_line_id', $lineId);
            $priorQuantity = $this->quantityMicrosAllowZero((string) $prior->sum('quantity'));
            $sourceQuantity = MinorMoney::quantityMicros((string) $line->allocated_quantity);
            $remainingQuantity = $sourceQuantity - $priorQuantity;
            $quantity = $requested === [] ? $remainingQuantity : (int) ($requestedByLine[$lineId] ?? 0);
            if ($quantity === 0) {
                continue;
            }
            if ($quantity < 0 || $quantity > $remainingQuantity) {
                throw new FnbConflictException('Refund quantity exceeds the check-line cap.', ['check_line_id' => (int) $lineId]);
            }
            $isRemainder = $quantity === $remainingQuantity;
            $components = [
                'allocated_subtotal_minor' => $this->allocateComponent((int) $line->allocated_subtotal_minor, (int) $prior->sum('allocated_subtotal_minor'), $quantity, $sourceQuantity, $isRemainder),
                'allocated_discount_minor' => $this->allocateComponent((int) $line->allocated_discount_minor, (int) $prior->sum('allocated_discount_minor'), $quantity, $sourceQuantity, $isRemainder),
                'allocated_service_charge_minor' => $this->allocateComponent((int) $line->allocated_service_charge_minor, (int) $prior->sum('allocated_service_charge_minor'), $quantity, $sourceQuantity, $isRemainder),
                'allocated_tax_minor' => $this->allocateComponent((int) $line->allocated_tax_minor, (int) $prior->sum('allocated_tax_minor'), $quantity, $sourceQuantity, $isRemainder),
                'allocated_pricing_rounding_minor' => $this->allocateComponent((int) $line->allocated_pricing_rounding_minor, (int) $prior->sum('allocated_pricing_rounding_minor'), $quantity, $sourceQuantity, $isRemainder),
                'allocated_cash_rounding_minor' => 0,
            ];
            $components['allocated_total_minor'] = $components['allocated_subtotal_minor'] - $components['allocated_discount_minor']
                + $components['allocated_service_charge_minor'] + $components['allocated_tax_minor'] + $components['allocated_pricing_rounding_minor'];
            $allocations[] = [
                'check_line_id' => (int) $lineId,
                'allocation_kind' => 'line',
                'quantity' => MinorMoney::formatQuantity($quantity),
                ...$components,
            ];
        }
        if ($requested !== [] && count($allocations) !== count($requested)) {
            throw new FnbNotFoundException('Refund check-line allocation is outside the check.');
        }

        $allocated = array_sum(array_column($allocations, 'allocated_total_minor'));
        $cashRoundingRemaining = (int) ($check->cash_rounding_minor ?? 0) - (int) $priorRows->where('allocation_kind', 'cash_rounding')->sum('allocated_cash_rounding_minor');
        if ($amount === $remainingGross && $cashRoundingRemaining !== 0) {
            $allocations[] = [
                'check_line_id' => null,
                'allocation_kind' => 'cash_rounding',
                'quantity' => null,
                'allocated_subtotal_minor' => 0,
                'allocated_discount_minor' => 0,
                'allocated_service_charge_minor' => 0,
                'allocated_tax_minor' => 0,
                'allocated_pricing_rounding_minor' => 0,
                'allocated_cash_rounding_minor' => $cashRoundingRemaining,
                'allocated_total_minor' => $cashRoundingRemaining,
            ];
            $allocated += $cashRoundingRemaining;
        }
        if ($allocated !== $amount) {
            throw new FnbValidationException('Refund amount must equal its component allocation.', [
                'allocated_minor' => $allocated, 'amount_minor' => $amount,
            ]);
        }

        return $allocations;
    }

    /**
     * @param  list<array<string,mixed>>  $requested
     * @return list<array<string,mixed>>
     */
    private function validateStoredRefundAllocations(FnbContext $context, object $check, int $amount, array $requested): array
    {
        $lines = DB::table('fnb_check_lines')->where('website_key', $context->websiteKey)
            ->where('outlet_id', $context->outletId)->where('check_id', $check->id)
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $priorRows = DB::table('fnb_refund_allocations as allocation')
            ->join('fnb_refunds as refund', function ($join): void {
                $join->on('refund.website_key', '=', 'allocation.website_key')
                    ->on('refund.outlet_id', '=', 'allocation.outlet_id')
                    ->on('refund.id', '=', 'allocation.refund_id');
            })
            ->where('allocation.website_key', $context->websiteKey)->where('allocation.outlet_id', $context->outletId)
            ->where('allocation.check_id', $check->id)->whereIn('refund.status', self::REFUND_CAP_STATUSES)
            ->select('allocation.*')->lockForUpdate()->get();

        $componentMap = [
            'allocated_subtotal_minor' => 'allocated_subtotal_minor',
            'allocated_discount_minor' => 'allocated_discount_minor',
            'allocated_service_charge_minor' => 'allocated_service_charge_minor',
            'allocated_tax_minor' => 'allocated_tax_minor',
            'allocated_pricing_rounding_minor' => 'allocated_pricing_rounding_minor',
        ];
        $seenLines = [];
        $cashRows = 0;
        $normalized = [];
        foreach ($requested as $index => $allocation) {
            $kind = (string) $allocation['allocation_kind'];
            $lineId = isset($allocation['check_line_id']) ? (int) $allocation['check_line_id'] : null;
            $row = [
                'check_line_id' => $lineId,
                'allocation_kind' => $kind,
                'quantity' => $allocation['quantity'] ?? null,
                'allocated_subtotal_minor' => (int) $allocation['allocated_subtotal_minor'],
                'allocated_discount_minor' => (int) $allocation['allocated_discount_minor'],
                'allocated_service_charge_minor' => (int) $allocation['allocated_service_charge_minor'],
                'allocated_tax_minor' => (int) $allocation['allocated_tax_minor'],
                'allocated_pricing_rounding_minor' => (int) $allocation['allocated_pricing_rounding_minor'],
                'allocated_cash_rounding_minor' => (int) $allocation['allocated_cash_rounding_minor'],
                'allocated_total_minor' => (int) $allocation['allocated_total_minor'],
            ];
            $equation = $row['allocated_subtotal_minor'] - $row['allocated_discount_minor']
                + $row['allocated_service_charge_minor'] + $row['allocated_tax_minor']
                + $row['allocated_pricing_rounding_minor'] + $row['allocated_cash_rounding_minor'];
            if ($equation !== $row['allocated_total_minor']) {
                throw new FnbValidationException("Refund allocation {$index} has an invalid component equation.");
            }

            if ($kind === 'cash_rounding') {
                $cashRows++;
                if ($cashRows > 1 || $lineId !== null || $row['quantity'] !== null
                    || $row['allocated_subtotal_minor'] !== 0 || $row['allocated_discount_minor'] !== 0
                    || $row['allocated_service_charge_minor'] !== 0 || $row['allocated_tax_minor'] !== 0
                    || $row['allocated_pricing_rounding_minor'] !== 0) {
                    throw new FnbValidationException('Cash-rounding allocation must be one NULL-line component-only row.');
                }
                $priorCash = (int) $priorRows->where('allocation_kind', 'cash_rounding')->sum('allocated_cash_rounding_minor');
                $this->assertSignedComponentCap(
                    $row['allocated_cash_rounding_minor'],
                    $priorCash,
                    (int) ($check->cash_rounding_minor ?? 0),
                    'allocated_cash_rounding_minor',
                );
                $normalized[] = $row;

                continue;
            }

            if ($lineId === null || isset($seenLines[$lineId]) || $row['quantity'] === null) {
                throw new FnbValidationException('Each line allocation requires one distinct CheckLine and quantity.');
            }
            $seenLines[$lineId] = true;
            $line = $lines->get($lineId);
            if ($line === null) {
                throw new FnbNotFoundException('Refund allocation CheckLine is outside the locked check.');
            }
            if ($row['allocated_cash_rounding_minor'] !== 0) {
                throw new FnbValidationException('A line allocation cannot carry cash rounding.');
            }
            $quantity = MinorMoney::quantityMicros((string) $row['quantity'], "allocations.{$index}.quantity");
            $prior = $priorRows->where('check_line_id', $lineId);
            $priorQuantity = $this->quantityMicrosAllowZero((string) $prior->sum('quantity'));
            $sourceQuantity = MinorMoney::quantityMicros((string) $line->allocated_quantity);
            if ($priorQuantity + $quantity > $sourceQuantity) {
                throw new FnbConflictException('Refund quantity exceeds the CheckLine cap.', ['check_line_id' => $lineId]);
            }
            $row['quantity'] = MinorMoney::formatQuantity($quantity);
            foreach ($componentMap as $allocationField => $sourceField) {
                $this->assertSignedComponentCap(
                    $row[$allocationField],
                    (int) $prior->sum($allocationField),
                    (int) $line->{$sourceField},
                    $allocationField,
                );
            }
            $normalized[] = $row;
        }
        if (array_sum(array_column($normalized, 'allocated_total_minor')) !== $amount) {
            throw new FnbValidationException('Trusted refund allocation vector does not equal amount_minor.');
        }

        return $normalized;
    }

    private function assertSignedComponentCap(int $candidate, int $prior, int $source, string $field): void
    {
        $total = $prior + $candidate;
        $valid = $source >= 0
            ? $candidate >= 0 && $total >= 0 && $total <= $source
            : $candidate <= 0 && $total <= 0 && $total >= $source;
        if (! $valid) {
            throw new FnbConflictException('Refund allocation exceeds a source component cap.', ['component' => $field]);
        }
    }

    private function recordSaleConsumption(FnbContext $context, object $check, int $checkVersion): void
    {
        $sourceEventId = "check:{$check->id}:closed";
        if (DB::table('fnb_stock_consumptions')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('kind', 'sale')->where('source_event_id', $sourceEventId)->exists()) {
            return;
        }
        $lines = DB::table('fnb_check_lines as check_line')->join('fnb_order_lines as order_line', 'order_line.id', '=', 'check_line.order_line_id')
            ->where('check_line.check_id', $check->id)->get([
                'check_line.id as check_line_id', 'check_line.allocated_quantity', 'check_line.order_line_id',
                'order_line.recipe_snapshot', 'order_line.recipe_snapshot_hash',
            ]);
        $modifiers = DB::table('fnb_order_line_modifiers')->where('website_key', $context->websiteKey)
            ->where('outlet_id', $context->outletId)->whereIn('order_line_id', $lines->pluck('order_line_id'))
            ->whereNotNull('recipe_snapshot')->get([
                'order_line_id', 'modifier_option_id', 'quantity', 'recipe_snapshot', 'recipe_snapshot_hash',
            ])->groupBy('order_line_id');
        $snapshot = [
            'check_id' => (int) $check->id,
            'check_no' => $check->check_no,
            'check_version' => $checkVersion,
            'lines' => $lines->map(fn (object $line): array => [
                'check_line_id' => (int) $line->check_line_id,
                'order_line_id' => (int) $line->order_line_id,
                'quantity' => (string) $line->allocated_quantity,
                'recipe_snapshot_hash' => $line->recipe_snapshot_hash,
                'modifier_recipe_snapshots' => ($modifiers[$line->order_line_id] ?? collect())->map(fn (object $modifier): array => [
                    'modifier_option_id' => (int) $modifier->modifier_option_id,
                    'quantity' => (string) $modifier->quantity,
                    'recipe_snapshot_hash' => $modifier->recipe_snapshot_hash,
                ])->all(),
            ])->all(),
        ];
        $encoded = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $consumptionId = DB::table('fnb_stock_consumptions')->insertGetId([
            'website_key' => $context->websiteKey,
            'outlet_id' => $context->outletId,
            'public_id' => (string) Str::uuid(),
            'source_type' => 'check',
            'source_id' => (string) $check->id,
            'source_version' => $checkVersion,
            'source_event_id' => $sourceEventId,
            'kind' => 'sale',
            'status' => 'pending',
            'idempotency_key' => hash('sha256', $sourceEventId),
            'payload_snapshot' => $encoded,
            'payload_hash' => hash('sha256', $encoded),
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach ($lines as $line) {
            if ($line->recipe_snapshot !== null) {
                $this->insertRecipeConsumptionLines(
                    $context,
                    $consumptionId,
                    (int) $line->order_line_id,
                    (string) $line->recipe_snapshot,
                    (string) $line->allocated_quantity,
                );
            }
            foreach ($modifiers[$line->order_line_id] ?? collect() as $modifier) {
                $this->insertRecipeConsumptionLines(
                    $context,
                    $consumptionId,
                    (int) $line->order_line_id,
                    (string) $modifier->recipe_snapshot,
                    (string) $line->allocated_quantity,
                    (string) $modifier->quantity,
                    ['modifier_option_id' => (int) $modifier->modifier_option_id],
                );
            }
        }
    }

    /** @param array<string,mixed> $source */
    private function insertRecipeConsumptionLines(
        FnbContext $context,
        int $consumptionId,
        int $orderLineId,
        string $encodedRecipe,
        string $saleQuantity,
        string $selectionQuantity = '1.000000',
        array $source = [],
    ): void {
        $recipe = json_decode($encodedRecipe, true, 512, JSON_THROW_ON_ERROR);
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

    private function rebuildOrderProjections(FnbContext $context, int $checkId, int $watermark): void
    {
        $orderIds = DB::table('fnb_check_lines')->where('website_key', $context->websiteKey)
            ->where('outlet_id', $context->outletId)->where('check_id', $checkId)->pluck('order_id')->unique();
        foreach ($orderIds as $orderId) {
            $checkIds = DB::table('fnb_check_lines')->where('website_key', $context->websiteKey)
                ->where('outlet_id', $context->outletId)->where('order_id', $orderId)->pluck('check_id')->unique();
            $gross = 0;
            $refund = 0;
            foreach ($checkIds as $relatedCheckId) {
                $lineRows = DB::table('fnb_check_lines')->where('website_key', $context->websiteKey)
                    ->where('outlet_id', $context->outletId)->where('check_id', $relatedCheckId)
                    ->get(['id', 'order_id', 'allocated_total_minor']);
                $weights = $lineRows->groupBy('order_id')->map(
                    fn ($rows): int => max(0, (int) $rows->sum('allocated_total_minor')),
                )->all();
                if (array_sum($weights) <= 0) {
                    continue;
                }

                $checkGross = (int) DB::table('fnb_payments')->where('website_key', $context->websiteKey)
                    ->where('outlet_id', $context->outletId)->where('check_id', $relatedCheckId)
                    ->where('status', 'succeeded')->sum('amount_minor');
                $grossAllocation = MinorMoney::allocate($checkGross, $weights);
                $gross += (int) ($grossAllocation[$orderId] ?? 0);

                $refundRows = DB::table('fnb_refund_allocations as allocation')
                    ->join('fnb_refunds as refund', function ($join): void {
                        $join->on('refund.website_key', '=', 'allocation.website_key')
                            ->on('refund.outlet_id', '=', 'allocation.outlet_id')
                            ->on('refund.id', '=', 'allocation.refund_id');
                    })
                    ->leftJoin('fnb_check_lines as check_line', function ($join): void {
                        $join->on('check_line.website_key', '=', 'allocation.website_key')
                            ->on('check_line.outlet_id', '=', 'allocation.outlet_id')
                            ->on('check_line.id', '=', 'allocation.check_line_id');
                    })
                    ->where('allocation.website_key', $context->websiteKey)->where('allocation.outlet_id', $context->outletId)
                    ->where('allocation.check_id', $relatedCheckId)->where('refund.status', 'succeeded')
                    ->get(['allocation.refund_id', 'allocation.allocation_kind', 'allocation.allocated_total_minor', 'check_line.order_id']);
                foreach ($refundRows->groupBy('refund_id') as $allocations) {
                    $byOrder = $allocations->whereNotNull('order_id')->groupBy('order_id')
                        ->map(fn ($rows): int => (int) $rows->sum('allocated_total_minor'))->all();
                    $checkLevel = (int) $allocations->whereNull('order_id')->sum('allocated_total_minor');
                    if ($checkLevel !== 0) {
                        $checkLevelAllocation = $this->allocateSigned($checkLevel, $weights);
                        foreach ($checkLevelAllocation as $allocatedOrderId => $amount) {
                            $byOrder[$allocatedOrderId] = (int) ($byOrder[$allocatedOrderId] ?? 0) + $amount;
                        }
                    }
                    $refund += (int) ($byOrder[$orderId] ?? 0);
                }
            }
            $projection = DB::table('fnb_order_financial_projections')->where('website_key', $context->websiteKey)
                ->where('outlet_id', $context->outletId)->where('order_id', $orderId)->lockForUpdate()->first();
            if ($projection === null) {
                continue;
            }
            $orderTotal = (int) DB::table('fnb_orders')->where('website_key', $context->websiteKey)
                ->where('outlet_id', $context->outletId)->where('id', $orderId)->value('grand_total_minor');
            $gross = max(0, $gross);
            $refund = max(0, min($refund, $gross));
            DB::table('fnb_order_financial_projections')->where('id', $projection->id)->update([
                'gross_paid_total_minor' => $gross,
                'refunded_total_minor' => $refund,
                'net_collected_total_minor' => $gross - $refund,
                'payment_status' => $gross >= $orderTotal ? 'paid' : ($gross > 0 ? 'partially_paid' : 'unpaid'),
                'refund_status' => $refund >= $gross && $gross > 0 ? 'refunded' : ($refund > 0 ? 'partially_refunded' : 'none'),
                'source_watermark' => max((int) $projection->source_watermark, $watermark),
                'version' => (int) $projection->version + 1,
                'updated_at' => now(),
            ]);
        }
    }

    /** @param array<int|string,int> $weights @return array<int|string,int> */
    private function allocateSigned(int $total, array $weights): array
    {
        $sign = $total < 0 ? -1 : 1;
        $allocated = MinorMoney::allocate(abs($total), $weights);

        return array_map(fn (int $amount): int => $amount * $sign, $allocated);
    }

    /** @param list<array<string,mixed>> $lines @return array<string,int> */
    private function sumAllocatedComponents(array $lines): array
    {
        return [
            'subtotal_minor' => array_sum(array_column($lines, 'allocated_subtotal_minor')),
            'discount_minor' => array_sum(array_column($lines, 'allocated_discount_minor')),
            'service_charge_minor' => array_sum(array_column($lines, 'allocated_service_charge_minor')),
            'tax_minor' => array_sum(array_column($lines, 'allocated_tax_minor')),
            'pricing_rounding_minor' => array_sum(array_column($lines, 'allocated_pricing_rounding_minor')),
            'total_minor' => array_sum(array_column($lines, 'allocated_total_minor')),
        ];
    }

    private function allocateComponent(int $source, int $prior, int $quantityMicros, int $sourceMicros, bool $isRemainder): int
    {
        if ($isRemainder) {
            return $source - $prior;
        }
        $sign = $source < 0 ? -1 : 1;
        $absolute = abs($source);
        $allocated = intdiv($absolute * $quantityMicros + intdiv($sourceMicros, 2), $sourceMicros) * $sign;
        if ($source >= 0) {
            return min($allocated, $source - $prior);
        }

        return max($allocated, $source - $prior);
    }

    private function quantityMicrosAllowZero(string $quantity): int
    {
        if (bccomp($quantity, '0', 6) === 0) {
            return 0;
        }

        return MinorMoney::quantityMicros($quantity);
    }

    private function assertCheckEquation(object $check): void
    {
        $calculated = (int) $check->subtotal_minor - (int) $check->discount_total_minor
            + (int) $check->service_charge_total_minor + (int) $check->tax_total_minor
            + (int) $check->pricing_rounding_minor;
        if ($calculated !== (int) $check->grand_total_minor) {
            throw new FnbConflictException('Check component vector does not equal grand total.');
        }
    }

    /** @return array<string,mixed> */
    private function checkResource(FnbContext $context, int $checkId): array
    {
        $check = $this->record('fnb_checks', $checkId);
        $check['lines'] = DB::table('fnb_check_lines')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('check_id', $checkId)->orderBy('id')->get()->map(fn ($row) => $this->decode($row))->all();
        $check['projection'] = $this->decode(DB::table('fnb_check_financial_projections')->where('check_id', $checkId)->firstOrFail());

        return $check;
    }

    /** @return array<string,mixed> */
    private function refundResource(FnbContext $context, int $refundId): array
    {
        $refund = $this->record('fnb_refunds', $refundId);
        $refund['allocations'] = DB::table('fnb_refund_allocations')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('refund_id', $refundId)->orderBy('id')->get()->map(fn ($row) => $this->decode($row))->all();

        return $refund;
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
            if (is_string($value) && (str_ends_with($key, '_snapshot') || in_array($key, ['payload', 'metadata'], true))) {
                $values[$key] = json_decode($value, true) ?? $value;
            }
            $numericId = ($key === 'id' || str_ends_with($key, '_id'))
                && $value !== null && preg_match('/^\d+$/', (string) $value) === 1;
            if (str_ends_with($key, '_minor') || $numericId || in_array($key, ['version', 'sequence_no'], true)) {
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
