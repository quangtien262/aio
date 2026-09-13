<?php

namespace Modules\FnbPos\Services;

use Modules\FnbPos\Domain\FnbContext;

/** Stable application facade used by HTTP, jobs and integration adapters. */
final class FnbService
{
    public function __construct(
        private readonly FnbSetupService $setup,
        private readonly FnbOperationsService $operations,
        private readonly FnbOrderService $orders,
    ) {}

    public function onboard(FnbContext $ctx, array $input, string $idempotencyKey): array
    {
        return $this->setup->onboard($ctx, $input, $idempotencyKey);
    }

    public function catalog(FnbContext $ctx, array $filters = []): array
    {
        return $this->setup->catalog($ctx, $filters);
    }

    public function saveCatalogItem(FnbContext $ctx, array $input, string $idempotencyKey, ?int $expectedVersion = null): array
    {
        return $this->setup->saveCatalogItem($ctx, $input, $idempotencyKey, $expectedVersion);
    }

    public function setAvailability(FnbContext $ctx, int $itemId, bool $available, ?string $soldOutUntil, ?string $reason, string $idempotencyKey, int $expectedVersion): array
    {
        return $this->setup->setAvailability($ctx, $itemId, $available, $soldOutUntil, $reason, $idempotencyKey, $expectedVersion);
    }

    public function searchCustomers(FnbContext $ctx, string $query, int $limit = 20): array
    {
        return $this->setup->searchCustomers($ctx, $query, $limit);
    }

    public function createCustomer(FnbContext $ctx, array $input, string $idempotencyKey): array
    {
        return $this->setup->createCustomer($ctx, $input, $idempotencyKey);
    }

    public function openBusinessDay(FnbContext $ctx, string $businessDate, string $idempotencyKey): array
    {
        return $this->operations->openBusinessDay($ctx, $businessDate, $idempotencyKey);
    }

    public function closeBusinessDay(FnbContext $ctx, int $businessDayId, string $idempotencyKey, int $expectedVersion): array
    {
        return $this->operations->closeBusinessDay($ctx, $businessDayId, $idempotencyKey, $expectedVersion);
    }

    public function openShift(FnbContext $ctx, int $businessDayId, int $openingFloatMinor, string $idempotencyKey): array
    {
        return $this->operations->openShift($ctx, $businessDayId, $openingFloatMinor, $idempotencyKey);
    }

    public function closeShift(FnbContext $ctx, int $shiftId, array $countedTenders, string $idempotencyKey, int $expectedVersion): array
    {
        return $this->operations->closeShift($ctx, $shiftId, $countedTenders, $idempotencyKey, $expectedVersion);
    }

    public function reconcileShift(FnbContext $ctx, int $shiftId, string $idempotencyKey, int $expectedVersion): array
    {
        return $this->operations->reconcileShift($ctx, $shiftId, $idempotencyKey, $expectedVersion);
    }

    public function recordCashMovement(FnbContext $ctx, int $shiftId, string $kind, int $amountMinor, string $reason, string $idempotencyKey, int $expectedVersion): array
    {
        return $this->operations->recordCashMovement($ctx, $shiftId, $kind, $amountMinor, $reason, $idempotencyKey, $expectedVersion);
    }

    public function openSession(FnbContext $ctx, array $input, string $idempotencyKey): array
    {
        return $this->operations->openSession($ctx, $input, $idempotencyKey);
    }

    public function transferSession(FnbContext $ctx, int $sessionId, int $tableId, string $idempotencyKey, int $expectedVersion): array
    {
        return $this->operations->transferSession($ctx, $sessionId, $tableId, $idempotencyKey, $expectedVersion);
    }

    public function settleSession(FnbContext $ctx, int $sessionId, string $idempotencyKey, int $expectedVersion): array
    {
        return $this->operations->settleSession($ctx, $sessionId, $idempotencyKey, $expectedVersion);
    }

    public function closeSession(FnbContext $ctx, int $sessionId, string $idempotencyKey, int $expectedVersion): array
    {
        return $this->operations->closeSession($ctx, $sessionId, $idempotencyKey, $expectedVersion);
    }

    public function createOrder(FnbContext $ctx, int $sessionId, int $shiftId, array $input, string $idempotencyKey, int $expectedSessionVersion): array
    {
        return $this->orders->createOrder($ctx, $sessionId, $shiftId, $input, $idempotencyKey, $expectedSessionVersion);
    }

    public function addOrderLine(FnbContext $ctx, int $orderId, array $input, string $idempotencyKey, int $expectedVersion): array
    {
        return $this->orders->addOrderLine($ctx, $orderId, $input, $idempotencyKey, $expectedVersion);
    }

    public function updateDraftOrderLine(FnbContext $ctx, int $orderLineId, array $input, string $idempotencyKey, int $expectedOrderVersion): array
    {
        return $this->orders->updateDraftOrderLine($ctx, $orderLineId, $input, $idempotencyKey, $expectedOrderVersion);
    }

    public function removeDraftOrderLine(FnbContext $ctx, int $orderLineId, string $idempotencyKey, int $expectedOrderVersion): array
    {
        return $this->orders->removeDraftOrderLine($ctx, $orderLineId, $idempotencyKey, $expectedOrderVersion);
    }

    public function applyOrderDiscount(FnbContext $ctx, int $orderId, array $input, string $idempotencyKey, int $expectedVersion): array
    {
        return $this->orders->applyOrderDiscount($ctx, $orderId, $input, $idempotencyKey, $expectedVersion);
    }

    public function submitOrder(FnbContext $ctx, int $orderId, string $idempotencyKey, int $expectedVersion): array
    {
        return $this->orders->submitOrder($ctx, $orderId, $idempotencyKey, $expectedVersion);
    }

    public function voidOrderLine(FnbContext $ctx, int $orderLineId, string $reason, string $idempotencyKey, int $expectedOrderVersion): array
    {
        return $this->orders->voidOrderLine($ctx, $orderLineId, $reason, $idempotencyKey, $expectedOrderVersion);
    }

    public function pollKitchen(FnbContext $ctx, int $cursor = 0, ?int $stationId = null, int $limit = 200): array
    {
        return $this->orders->pollKitchen($ctx, $cursor, $stationId, $limit);
    }

    public function transitionKitchenLine(FnbContext $ctx, int $ticketLineId, string $toStatus, string $idempotencyKey, int $expectedTicketVersion): array
    {
        return $this->orders->transitionKitchenLine($ctx, $ticketLineId, $toStatus, $idempotencyKey, $expectedTicketVersion);
    }

    public function createCheck(FnbContext $ctx, int $sessionId, array $allocations, string $idempotencyKey, int $expectedSessionVersion): array
    {
        return app(FnbSettlementService::class)->createCheck($ctx, $sessionId, $allocations, $idempotencyKey, $expectedSessionVersion);
    }

    public function finalizeCheck(FnbContext $ctx, int $checkId, string $idempotencyKey, int $expectedVersion): array
    {
        return app(FnbSettlementService::class)->finalizeCheck($ctx, $checkId, $idempotencyKey, $expectedVersion);
    }

    public function reopenCheck(FnbContext $ctx, int $checkId, string $reason, string $idempotencyKey, int $expectedVersion): array
    {
        return app(FnbSettlementService::class)->reopenCheck($ctx, $checkId, $reason, $idempotencyKey, $expectedVersion);
    }

    public function voidCheck(FnbContext $ctx, int $checkId, string $reason, string $idempotencyKey, int $expectedVersion): array
    {
        return app(FnbSettlementService::class)->voidCheck($ctx, $checkId, $reason, $idempotencyKey, $expectedVersion);
    }

    public function setSettlementPlan(FnbContext $ctx, int $checkId, string $mode, string $idempotencyKey, int $expectedVersion): array
    {
        return app(FnbSettlementService::class)->setSettlementPlan($ctx, $checkId, $mode, $idempotencyKey, $expectedVersion);
    }

    public function collectPayment(FnbContext $ctx, int $checkId, int $shiftId, array $input, string $idempotencyKey, int $expectedVersion): array
    {
        return app(FnbSettlementService::class)->collectPayment($ctx, $checkId, $shiftId, $input, $idempotencyKey, $expectedVersion);
    }

    public function cancelPayment(FnbContext $ctx, int $paymentId, string $reason, string $idempotencyKey): array
    {
        return app(FnbSettlementService::class)->cancelPayment($ctx, $paymentId, $reason, $idempotencyKey);
    }

    public function refundPayment(FnbContext $ctx, int $paymentId, int $shiftId, array $input, string $idempotencyKey): array
    {
        return app(FnbSettlementService::class)->refundPayment($ctx, $paymentId, $shiftId, $input, $idempotencyKey);
    }

    /** Internal compensation seam; never bind its component vector directly to an HTTP payload. */
    public function refundAllocated(FnbContext $ctx, int $paymentId, int $shiftId, array $input, string $idempotencyKey): array
    {
        return app(FnbSettlementService::class)->refundAllocated($ctx, $paymentId, $shiftId, $input, $idempotencyKey);
    }

    public function cancelRefund(FnbContext $ctx, int $refundId, string $reason, string $idempotencyKey): array
    {
        return app(FnbSettlementService::class)->cancelRefund($ctx, $refundId, $reason, $idempotencyKey);
    }

    public function summaryReport(FnbContext $ctx, array $filters = []): array
    {
        return app(FnbReportService::class)->summary($ctx, $filters);
    }
}
