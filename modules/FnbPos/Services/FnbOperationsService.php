<?php

namespace Modules\FnbPos\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Domain\FnbNotFoundException;
use Modules\FnbPos\Domain\FnbValidationException;
use Modules\FnbPos\Domain\MinorMoney;

final class FnbOperationsService
{
    public function __construct(
        private readonly FnbCommandRunner $commands,
        private readonly FnbOutboxService $outbox,
    ) {}

    public function openBusinessDay(FnbContext $context, string $businessDate, string $idempotencyKey): array
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $businessDate);
        if ($date === false || $date->format('Y-m-d') !== $businessDate) {
            throw new FnbValidationException('businessDate must use YYYY-MM-DD.');
        }

        return $this->commands->run($context, 'business_day.open', ['business_date' => $businessDate], $idempotencyKey, function () use ($context, $businessDate): array {
            $outlet = $this->outlet($context, true);
            if (DB::table('fnb_business_days')->where('outlet_id', $context->outletId)->whereNotNull('open_slot')->lockForUpdate()->exists()) {
                throw new FnbConflictException('The outlet already has an open business day.');
            }
            $id = DB::table('fnb_business_days')->insertGetId([
                'website_key' => $context->websiteKey,
                'outlet_id' => $context->outletId,
                'business_date' => $businessDate,
                'currency' => $outlet->currency,
                'timezone_snapshot' => $outlet->timezone,
                'status' => 'open',
                'open_slot' => 'open',
                'opened_at' => now(),
                'opened_by' => $context->actorId,
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $event = $this->outbox->emit($context, 'business_day', $id, 1, 'fnb.business_day.opened', [
                'business_day_id' => $id,
                'business_date' => $businessDate,
                'currency' => $outlet->currency,
                'timezone' => $outlet->timezone,
            ]);

            return $this->envelope('business_day', $id, 1, $this->record('fnb_business_days', $id), [$event]);
        });
    }

    public function closeBusinessDay(FnbContext $context, int $businessDayId, string $idempotencyKey, int $expectedVersion): array
    {
        return $this->commands->run($context, 'business_day.close', compact('businessDayId', 'expectedVersion'), $idempotencyKey, function () use ($context, $businessDayId, $expectedVersion): array {
            $day = $this->locked('fnb_business_days', $context, $businessDayId);
            $this->assertVersion('business_day', $day, $expectedVersion);
            if ($day->status !== 'open') {
                throw new FnbConflictException('Only an open business day can be closed.');
            }

            DB::table('fnb_business_days')->where('id', $day->id)->update([
                'status' => 'closing',
                'version' => (int) $day->version + 1,
                'updated_at' => now(),
            ]);

            $blockers = [
                'shifts' => DB::table('fnb_shifts')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('business_day_id', $day->id)->whereIn('status', ['opening', 'open', 'closing'])->count(),
                'sessions' => DB::table('fnb_service_sessions')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('business_day_id', $day->id)->whereIn('status', ['open', 'settling'])->count(),
                'checks' => DB::table('fnb_checks')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('business_day_id', $day->id)->whereIn('status', ['open', 'finalized'])->count(),
                'payments' => DB::table('fnb_payments')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('business_day_id', $day->id)->whereIn('status', ['reserved', 'processing', 'uncertain', 'reconciling'])->count(),
                'refunds' => DB::table('fnb_refunds')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('processing_business_day_id', $day->id)->whereIn('status', ['requested', 'reserved', 'processing', 'uncertain', 'reconciling', 'attention'])->count(),
                'compensations' => DB::table('fnb_fulfillment_compensations')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('business_day_id', $day->id)->whereNotNull('active_slot')->count(),
            ];
            if (array_sum($blockers) > 0) {
                throw new FnbConflictException('Business day has active operational records.', ['blockers' => $blockers]);
            }

            $version = (int) $day->version + 2;
            DB::table('fnb_business_days')->where('id', $day->id)->update([
                'status' => 'closed',
                'open_slot' => null,
                'closed_at' => now(),
                'closed_by' => $context->actorId,
                'version' => $version,
                'updated_at' => now(),
            ]);
            $event = $this->outbox->emit($context, 'business_day', $day->id, $version, 'fnb.business_day.closed', [
                'business_day_id' => (int) $day->id,
                'business_date' => $day->business_date,
            ]);

            return $this->envelope('business_day', (int) $day->id, $version, $this->record('fnb_business_days', $day->id), [$event]);
        });
    }

    public function openShift(FnbContext $context, int $businessDayId, int $openingFloatMinor, string $idempotencyKey): array
    {
        MinorMoney::assertMinor($openingFloatMinor, 'opening_float_minor');
        if ($context->terminalId === null) {
            throw new FnbValidationException('A terminal is required to open a shift.');
        }

        return $this->commands->run($context, 'shift.open', compact('businessDayId', 'openingFloatMinor'), $idempotencyKey, function () use ($context, $businessDayId, $openingFloatMinor): array {
            $day = $this->locked('fnb_business_days', $context, $businessDayId);
            if ($day->status !== 'open') {
                throw new FnbConflictException('A shift can only open inside an open business day.');
            }
            if (DB::table('fnb_shifts')->where('terminal_id', $context->terminalId)->whereNotNull('open_slot')->lockForUpdate()->exists()) {
                throw new FnbConflictException('This terminal already has an open shift.');
            }

            $id = DB::table('fnb_shifts')->insertGetId([
                'website_key' => $context->websiteKey,
                'outlet_id' => $context->outletId,
                'public_id' => (string) Str::uuid(),
                'business_day_id' => $day->id,
                'terminal_id' => $context->terminalId,
                'currency' => $day->currency,
                'status' => 'open',
                'open_slot' => 'open',
                'opening_float_minor' => $openingFloatMinor,
                'expected_cash_minor' => $openingFloatMinor,
                'opened_at' => now(),
                'opened_by' => $context->actorId,
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $event = $this->outbox->emit($context, 'shift', $id, 1, 'fnb.shift.opened', [
                'shift_id' => $id,
                'business_day_id' => (int) $day->id,
                'terminal_id' => $context->terminalId,
                'opening_float_minor' => $openingFloatMinor,
            ]);

            return $this->envelope('shift', $id, 1, $this->record('fnb_shifts', $id), [$event]);
        });
    }

    /** @param list<array<string,mixed>> $countedTenders */
    public function closeShift(FnbContext $context, int $shiftId, array $countedTenders, string $idempotencyKey, int $expectedVersion): array
    {
        foreach ($countedTenders as $index => $tender) {
            if (! isset($tender['payment_method_id'], $tender['counted_minor']) || (int) $tender['payment_method_id'] < 1 || (int) $tender['counted_minor'] < 0) {
                throw new FnbValidationException("Invalid counted tender at index {$index}.");
            }
        }

        return $this->commands->run($context, 'shift.close', compact('shiftId', 'countedTenders', 'expectedVersion'), $idempotencyKey, function () use ($context, $shiftId, $countedTenders, $expectedVersion): array {
            $shift = $this->locked('fnb_shifts', $context, $shiftId);
            $this->assertVersion('shift', $shift, $expectedVersion);
            if ($shift->status !== 'open') {
                throw new FnbConflictException('Only an open shift can be closed.');
            }
            if ($context->terminalId !== null && (int) $shift->terminal_id !== $context->terminalId) {
                throw new FnbConflictException('Shift belongs to another terminal.');
            }

            $blockers = [
                'orders' => DB::table('fnb_orders')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('shift_id', $shift->id)->whereIn('lifecycle_status', ['draft', 'active'])->count(),
                'payments' => DB::table('fnb_payments')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('shift_id', $shift->id)->whereIn('status', ['reserved', 'processing', 'uncertain', 'reconciling'])->count(),
                'refunds' => DB::table('fnb_refunds')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->where('processed_shift_id', $shift->id)->whereIn('status', ['requested', 'reserved', 'processing', 'uncertain', 'reconciling', 'attention'])->count(),
            ];
            if (array_sum($blockers) > 0) {
                throw new FnbConflictException('Shift still has active transactions.', ['blockers' => $blockers]);
            }

            $methods = DB::table('fnb_payment_methods')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)->get()->keyBy('id');
            $counted = collect($countedTenders)->keyBy(fn (array $row): int => (int) $row['payment_method_id']);
            $payments = DB::table('fnb_payments')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)
                ->where('shift_id', $shift->id)->where('status', 'succeeded')->selectRaw('payment_method_id, SUM(amount_minor) amount_minor')->groupBy('payment_method_id')->pluck('amount_minor', 'payment_method_id');
            $refunds = DB::table('fnb_refunds as refunds')->join('fnb_payments as payments', 'payments.id', '=', 'refunds.payment_id')
                ->where('refunds.website_key', $context->websiteKey)->where('refunds.outlet_id', $context->outletId)
                ->where('refunds.processed_shift_id', $shift->id)->where('refunds.status', 'succeeded')
                ->selectRaw('payments.payment_method_id, SUM(refunds.amount_minor) amount_minor')->groupBy('payments.payment_method_id')->pluck('amount_minor', 'payment_method_id');

            $snapshotRows = [];
            $hasVariance = false;
            foreach ($methods as $methodId => $method) {
                $expected = (int) ($payments[$methodId] ?? 0) - (int) ($refunds[$methodId] ?? 0);
                if ($method->kind === 'cash') {
                    $expected = (int) $shift->expected_cash_minor;
                }
                $countedMinor = (int) (($counted[$methodId]['counted_minor'] ?? 0));
                $variance = $countedMinor - $expected;
                $hasVariance = $hasVariance || $variance !== 0;
                $snapshotRows[] = [
                    'method_id' => (int) $methodId,
                    'code' => $method->code,
                    'kind' => $method->kind,
                    'expected_minor' => $expected,
                    'counted_minor' => $countedMinor,
                    'variance_minor' => $variance,
                ];
            }
            $snapshot = [
                'shift_id' => (int) $shift->id,
                'business_day_id' => (int) $shift->business_day_id,
                'currency' => $shift->currency,
                'tenders' => $snapshotRows,
                'closed_at' => now()->toISOString(),
            ];
            $hash = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            foreach ($snapshotRows as $row) {
                DB::table('fnb_shift_tender_totals')->insert([
                    'website_key' => $context->websiteKey,
                    'outlet_id' => $context->outletId,
                    'shift_id' => $shift->id,
                    'payment_method_id' => $row['method_id'],
                    'method_code_snapshot' => $row['code'],
                    'method_kind_snapshot' => $row['kind'],
                    'expected_minor' => $row['expected_minor'],
                    'counted_minor' => $row['counted_minor'],
                    'variance_minor' => $row['variance_minor'],
                    'closing_snapshot' => json_encode($row, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $cashRow = collect($snapshotRows)->firstWhere('kind', 'cash');
            $version = (int) $shift->version + 1;
            $status = $hasVariance ? 'closed_pending_reconciliation' : 'reconciled';
            DB::table('fnb_shifts')->where('id', $shift->id)->update([
                'status' => $status,
                'open_slot' => null,
                'counted_cash_minor' => $cashRow['counted_minor'] ?? 0,
                'variance_minor' => $cashRow['variance_minor'] ?? 0,
                'closing_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'closing_hash' => $hash,
                'closed_at' => now(),
                'closed_by' => $context->actorId,
                'reconciled_at' => $status === 'reconciled' ? now() : null,
                'reconciled_by' => $status === 'reconciled' ? $context->actorId : null,
                'version' => $version,
                'updated_at' => now(),
            ]);
            $event = $this->outbox->emit($context, 'shift', $shift->id, $version, 'fnb.shift.closed', [
                'shift_id' => (int) $shift->id,
                'status' => $status,
                'closing_hash' => $hash,
            ]);

            return $this->envelope('shift', (int) $shift->id, $version, $this->record('fnb_shifts', $shift->id), [$event]);
        });
    }

    public function reconcileShift(FnbContext $context, int $shiftId, string $idempotencyKey, int $expectedVersion): array
    {
        return $this->commands->run($context, 'shift.reconcile', compact('shiftId', 'expectedVersion'), $idempotencyKey, function () use ($context, $shiftId, $expectedVersion): array {
            $shift = $this->locked('fnb_shifts', $context, $shiftId);
            $this->assertVersion('shift', $shift, $expectedVersion);
            if ($shift->status !== 'closed_pending_reconciliation') {
                throw new FnbConflictException('Only a variance-pending shift can be reconciled.');
            }
            $version = (int) $shift->version + 1;
            DB::table('fnb_shifts')->where('id', $shift->id)->update([
                'status' => 'reconciled',
                'reconciled_at' => now(),
                'reconciled_by' => $context->actorId,
                'version' => $version,
                'updated_at' => now(),
            ]);

            $day = DB::table('fnb_business_days')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)
                ->where('id', $shift->business_day_id)->lockForUpdate()->first();
            if ($day !== null && $day->status === 'closed'
                && ! DB::table('fnb_shifts')->where('business_day_id', $day->id)->where('status', '!=', 'reconciled')->exists()) {
                DB::table('fnb_business_days')->where('id', $day->id)->update([
                    'status' => 'reconciled',
                    'reconciled_at' => now(),
                    'reconciled_by' => $context->actorId,
                    'version' => (int) $day->version + 1,
                    'updated_at' => now(),
                ]);
            }

            $event = $this->outbox->emit($context, 'shift', $shift->id, $version, 'fnb.shift.reconciled', ['shift_id' => (int) $shift->id]);

            return $this->envelope('shift', (int) $shift->id, $version, $this->record('fnb_shifts', $shift->id), [$event]);
        });
    }

    public function recordCashMovement(FnbContext $context, int $shiftId, string $kind, int $amountMinor, string $reason, string $idempotencyKey, int $expectedVersion): array
    {
        if (! in_array($kind, ['cash_in', 'cash_out', 'adjustment_in', 'adjustment_out'], true)) {
            throw new FnbValidationException('Unsupported cash movement kind.');
        }
        MinorMoney::assertMinor($amountMinor, 'amount_minor');
        if ($amountMinor === 0 || trim($reason) === '') {
            throw new FnbValidationException('A positive amount and reason are required.');
        }

        return $this->commands->run($context, 'cash_movement.record', compact('shiftId', 'kind', 'amountMinor', 'reason', 'expectedVersion'), $idempotencyKey, function () use ($context, $shiftId, $kind, $amountMinor, $reason, $idempotencyKey, $expectedVersion): array {
            $shift = $this->locked('fnb_shifts', $context, $shiftId);
            $this->assertVersion('shift', $shift, $expectedVersion);
            if ($shift->status !== 'open') {
                throw new FnbConflictException('Cash movements require an open shift.');
            }
            $direction = str_ends_with($kind, '_in') ? 'in' : 'out';
            $id = DB::table('fnb_cash_movements')->insertGetId([
                'website_key' => $context->websiteKey,
                'outlet_id' => $context->outletId,
                'business_day_id' => $shift->business_day_id,
                'currency' => $shift->currency,
                'terminal_id' => $shift->terminal_id,
                'shift_id' => $shift->id,
                'kind' => $kind,
                'direction' => $direction,
                'amount_minor' => $amountMinor,
                'reason' => $reason,
                'actor_id' => $context->actorId,
                'idempotency_key' => $idempotencyKey,
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $expectedCash = (int) $shift->expected_cash_minor + ($direction === 'in' ? $amountMinor : -$amountMinor);
            if ($expectedCash < 0) {
                throw new FnbConflictException('Cash movement would make expected cash negative.');
            }
            $version = (int) $shift->version + 1;
            DB::table('fnb_shifts')->where('id', $shift->id)->update([
                'expected_cash_minor' => $expectedCash,
                'version' => $version,
                'updated_at' => now(),
            ]);
            $event = $this->outbox->emit($context, 'cash_movement', $id, 1, 'fnb.cash_movement.recorded', [
                'cash_movement_id' => $id,
                'shift_id' => (int) $shift->id,
                'kind' => $kind,
                'direction' => $direction,
                'amount_minor' => $amountMinor,
            ]);

            return $this->envelope('cash_movement', $id, 1, $this->record('fnb_cash_movements', $id), [$event], ['shift_version' => $version]);
        });
    }

    /** @param array<string,mixed> $input */
    public function openSession(FnbContext $context, array $input, string $idempotencyKey): array
    {
        $data = validator($input, [
            'business_day_id' => ['required', 'integer', 'min:1'],
            'service_type' => ['required', 'in:dine_in,counter,takeaway'],
            'source_channel' => ['sometimes', 'in:pos,waiter'],
            'table_id' => ['nullable', 'integer', 'min:1'],
            'guest_count' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'customer_profile_id' => ['nullable', 'integer', 'min:1'],
        ])->validate();
        if ($data['service_type'] === 'dine_in' && empty($data['table_id'])) {
            throw new FnbValidationException('Dine-in sessions require a table.');
        }

        return $this->commands->run($context, 'session.open', $data, $idempotencyKey, function () use ($context, $data, $idempotencyKey): array {
            $day = $this->locked('fnb_business_days', $context, (int) $data['business_day_id']);
            if ($day->status !== 'open') {
                throw new FnbConflictException('Service session requires an open business day.');
            }

            $customer = null;
            if (isset($data['customer_profile_id'])) {
                $customer = DB::table('fnb_customer_profiles')->where('website_key', $context->websiteKey)
                    ->where('id', $data['customer_profile_id'])->where('status', 'active')->lockForUpdate()->first();
                if ($customer === null) {
                    throw new FnbNotFoundException('Customer profile was not found.');
                }
            }
            $table = null;
            if (isset($data['table_id'])) {
                $table = DB::table('fnb_dining_tables')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)
                    ->where('id', $data['table_id'])->where('status', 'available')->lockForUpdate()->first();
                if ($table === null) {
                    throw new FnbConflictException('Dining table is unavailable or outside this outlet.');
                }
                if (DB::table('fnb_service_session_tables')->where('table_id', $table->id)->whereNotNull('active_slot')->lockForUpdate()->exists()) {
                    throw new FnbConflictException('Dining table already has an active session.');
                }
            }

            $now = now();
            $sessionId = DB::table('fnb_service_sessions')->insertGetId([
                'website_key' => $context->websiteKey,
                'outlet_id' => $context->outletId,
                'public_id' => (string) Str::uuid(),
                'creation_key' => $idempotencyKey,
                'business_day_id' => $day->id,
                'currency' => $day->currency,
                'timezone_snapshot' => $day->timezone_snapshot,
                'service_type' => $data['service_type'],
                'source_channel' => $data['source_channel'] ?? 'pos',
                'customer_profile_id' => $customer?->id,
                'status' => 'open',
                'guest_count' => $data['guest_count'] ?? 1,
                'customer_snapshot' => $customer ? json_encode([
                    'customer_profile_id' => (int) $customer->id,
                    'code' => $customer->code,
                    'name' => $customer->name,
                    'phone_normalized' => $customer->phone_normalized,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
                'opened_at' => $now,
                'opened_by' => $context->actorId,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($table !== null) {
                DB::table('fnb_service_session_tables')->insert([
                    'website_key' => $context->websiteKey,
                    'outlet_id' => $context->outletId,
                    'session_id' => $sessionId,
                    'table_id' => $table->id,
                    'joined_at' => $now,
                    'active_slot' => 'active',
                    'changed_by' => $context->actorId,
                    'idempotency_key' => $idempotencyKey,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $event = $this->outbox->emit($context, 'service_session', $sessionId, 1, 'fnb.service_session.opened', [
                'session_id' => $sessionId,
                'business_day_id' => (int) $day->id,
                'service_type' => $data['service_type'],
                'table_id' => $table?->id,
            ]);

            return $this->envelope('service_session', $sessionId, 1, $this->sessionResource($context, $sessionId), [$event]);
        });
    }

    public function transferSession(FnbContext $context, int $sessionId, int $tableId, string $idempotencyKey, int $expectedVersion): array
    {
        return $this->commands->run($context, 'session.transfer', compact('sessionId', 'tableId', 'expectedVersion'), $idempotencyKey, function () use ($context, $sessionId, $tableId, $idempotencyKey, $expectedVersion): array {
            $session = $this->locked('fnb_service_sessions', $context, $sessionId);
            $this->assertVersion('service_session', $session, $expectedVersion);
            if ($session->status !== 'open' || $session->service_type !== 'dine_in') {
                throw new FnbConflictException('Only an open dine-in session can transfer tables.');
            }
            $target = DB::table('fnb_dining_tables')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)
                ->where('id', $tableId)->where('status', 'available')->lockForUpdate()->first();
            if ($target === null || DB::table('fnb_service_session_tables')->where('table_id', $tableId)->whereNotNull('active_slot')->lockForUpdate()->exists()) {
                throw new FnbConflictException('Target table is unavailable.');
            }

            DB::table('fnb_service_session_tables')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)
                ->where('session_id', $session->id)->whereNotNull('active_slot')->update([
                    'left_at' => now(), 'active_slot' => null, 'updated_at' => now(),
                ]);
            DB::table('fnb_service_session_tables')->insert([
                'website_key' => $context->websiteKey,
                'outlet_id' => $context->outletId,
                'session_id' => $session->id,
                'table_id' => $target->id,
                'joined_at' => now(),
                'active_slot' => 'active',
                'changed_by' => $context->actorId,
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $version = (int) $session->version + 1;
            DB::table('fnb_service_sessions')->where('id', $session->id)->update(['version' => $version, 'updated_at' => now()]);
            $event = $this->outbox->emit($context, 'service_session', $session->id, $version, 'fnb.service_session.transferred', [
                'session_id' => (int) $session->id, 'table_id' => (int) $target->id,
            ]);

            return $this->envelope('service_session', (int) $session->id, $version, $this->sessionResource($context, $session->id), [$event]);
        });
    }

    public function settleSession(FnbContext $context, int $sessionId, string $idempotencyKey, int $expectedVersion): array
    {
        return $this->transitionSession($context, $sessionId, 'settling', $idempotencyKey, $expectedVersion);
    }

    public function closeSession(FnbContext $context, int $sessionId, string $idempotencyKey, int $expectedVersion): array
    {
        return $this->commands->run($context, 'session.close', compact('sessionId', 'expectedVersion'), $idempotencyKey, function () use ($context, $sessionId, $expectedVersion): array {
            $session = $this->locked('fnb_service_sessions', $context, $sessionId);
            $this->assertVersion('service_session', $session, $expectedVersion);
            if ($session->status !== 'settling') {
                throw new FnbConflictException('Only a settling session can close.');
            }
            if (DB::table('fnb_orders')->where('session_id', $session->id)->whereIn('lifecycle_status', ['draft', 'active'])->exists()) {
                throw new FnbConflictException('Session still has an active order.');
            }
            if (DB::table('fnb_checks')->where('session_id', $session->id)->whereNotIn('status', ['closed', 'void'])->exists()) {
                throw new FnbConflictException('Every check must be closed or void before closing the session.');
            }

            $version = (int) $session->version + 1;
            DB::table('fnb_service_sessions')->where('id', $session->id)->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => $context->actorId,
                'version' => $version,
                'updated_at' => now(),
            ]);
            DB::table('fnb_service_session_tables')->where('session_id', $session->id)->whereNotNull('active_slot')->update([
                'left_at' => now(), 'active_slot' => null, 'updated_at' => now(),
            ]);
            $event = $this->outbox->emit($context, 'service_session', $session->id, $version, 'fnb.service_session.closed', ['session_id' => (int) $session->id]);

            return $this->envelope('service_session', (int) $session->id, $version, $this->sessionResource($context, $session->id), [$event]);
        });
    }

    private function transitionSession(FnbContext $context, int $sessionId, string $toStatus, string $idempotencyKey, int $expectedVersion): array
    {
        return $this->commands->run($context, 'session.'.$toStatus, compact('sessionId', 'toStatus', 'expectedVersion'), $idempotencyKey, function () use ($context, $sessionId, $toStatus, $expectedVersion): array {
            $session = $this->locked('fnb_service_sessions', $context, $sessionId);
            $this->assertVersion('service_session', $session, $expectedVersion);
            if ($session->status !== 'open' || $toStatus !== 'settling') {
                throw new FnbConflictException('Invalid session transition.');
            }
            if (DB::table('fnb_orders')->where('session_id', $session->id)->where('lifecycle_status', 'draft')->exists()) {
                throw new FnbConflictException('Submit or cancel every draft order before settlement.');
            }
            $version = (int) $session->version + 1;
            DB::table('fnb_service_sessions')->where('id', $session->id)->update([
                'status' => 'settling', 'settling_at' => now(), 'version' => $version, 'updated_at' => now(),
            ]);
            $event = $this->outbox->emit($context, 'service_session', $session->id, $version, 'fnb.service_session.settling', ['session_id' => (int) $session->id]);

            return $this->envelope('service_session', (int) $session->id, $version, $this->sessionResource($context, $session->id), [$event]);
        });
    }

    private function outlet(FnbContext $context, bool $lock = false): object
    {
        $query = DB::table('fnb_outlets')->where('website_key', $context->websiteKey)->where('id', $context->outletId)->where('status', 'active');
        $outlet = ($lock ? $query->lockForUpdate() : $query)->first();
        if ($outlet === null) {
            throw new FnbNotFoundException('Active outlet was not found.');
        }

        return $outlet;
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
    private function sessionResource(FnbContext $context, int $id): array
    {
        $session = $this->record('fnb_service_sessions', $id);
        $session['tables'] = DB::table('fnb_service_session_tables')->where('website_key', $context->websiteKey)->where('outlet_id', $context->outletId)
            ->where('session_id', $id)->orderBy('id')->get()->map(fn ($row) => $this->decode($row))->all();

        return $session;
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
            if (is_string($value) && (str_ends_with($key, '_snapshot') || $key === 'settings')) {
                $values[$key] = json_decode($value, true) ?? $value;
            }
            $numericId = ($key === 'id' || str_ends_with($key, '_id'))
                && $value !== null && preg_match('/^\d+$/', (string) $value) === 1;
            if (str_ends_with($key, '_minor') || $numericId || $key === 'version') {
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
