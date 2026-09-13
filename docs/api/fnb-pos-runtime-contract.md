# F&B POS runtime contract (`0.1.0-dev`)

This document is the integration boundary between the F&B domain, HTTP controllers and the React shell. It is intentionally explicit about scope: controllers authorize permissions, while every service command revalidates aggregate scope, state, version and monetary invariants.

## Scope and errors

```php
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\FnbService;

$context = new FnbContext(
    websiteKey: $websiteKey,
    outletId: $outletId,
    actorId: $adminId,
    terminalId: $terminalId,
);
```

`websiteKey`, `outletId`, `actorId` and `terminalId` are trusted values resolved by the authorized controller; none may be copied from an arbitrary payload. A command conflict throws `FnbConflictException` (HTTP 409), invalid domain input throws `FnbValidationException` (422), and a scoped missing record throws `FnbNotFoundException` (404). Provider or optional-module outages never turn a committed local sale into a rollback.

The optional fifth constructor argument is the in-transaction authorization callback. The optional sixth is a server-only `FnbAuthorizationEvidence`; HTTP code must populate it from the consumed approval record via `recordApproval(approvalId, approverId)`, never from body/header claims. Trusted nested compensation commands share this evidence object so refund/adjustment/event rows record the real approver. With no consumed approval, approver fields remain `NULL` rather than attributing self-approval to the executor.

All responses use:

```json
{
  "resource": {},
  "replayed": false,
  "events": [],
  "meta": { "version": 1 }
}
```

Every monetary field is an integer minor-unit value (`*_minor`). Quantities are decimal strings with at most six fractional places. Every creation/mutation command has a non-empty `idempotencyKey`; updates additionally carry the aggregate `expectedVersion` (or an explicit version map when multiple aggregates are mutated).

HTTP uses base `/admin/api/fnb`, returns `{data,meta}`, and requires `Idempotency-Key` (8–120 safe characters) for mutations. Outlet-bound calls use the route outlet or `X-FNB-Outlet`; terminal operations also use `X-FNB-Terminal`. Every resource `public_id` is an opaque UUID string and must never be coerced to a number.

## Facade signatures

```php
final class FnbService
{
    public function onboard(FnbContext $ctx, array $input, string $idempotencyKey): array;
    public function catalog(FnbContext $ctx, array $filters = []): array;
    public function saveCatalogItem(FnbContext $ctx, array $input, string $idempotencyKey, ?int $expectedVersion = null): array;
    public function setAvailability(FnbContext $ctx, int $itemId, bool $available, ?string $soldOutUntil, ?string $reason, string $idempotencyKey, int $expectedVersion): array;
    public function searchCustomers(FnbContext $ctx, string $query, int $limit = 20): array;
    public function createCustomer(FnbContext $ctx, array $input, string $idempotencyKey): array;

    public function openBusinessDay(FnbContext $ctx, string $businessDate, string $idempotencyKey): array;
    public function closeBusinessDay(FnbContext $ctx, int $businessDayId, string $idempotencyKey, int $expectedVersion): array;
    public function openShift(FnbContext $ctx, int $businessDayId, int $openingFloatMinor, string $idempotencyKey): array;
    public function closeShift(FnbContext $ctx, int $shiftId, array $countedTenders, string $idempotencyKey, int $expectedVersion): array;
    public function reconcileShift(FnbContext $ctx, int $shiftId, string $idempotencyKey, int $expectedVersion): array;
    public function recordCashMovement(FnbContext $ctx, int $shiftId, string $kind, int $amountMinor, string $reason, string $idempotencyKey, int $expectedVersion): array;

    public function openSession(FnbContext $ctx, array $input, string $idempotencyKey): array;
    public function transferSession(FnbContext $ctx, int $sessionId, int $tableId, string $idempotencyKey, int $expectedVersion): array;
    public function settleSession(FnbContext $ctx, int $sessionId, string $idempotencyKey, int $expectedVersion): array;
    public function closeSession(FnbContext $ctx, int $sessionId, string $idempotencyKey, int $expectedVersion): array;

    public function createOrder(FnbContext $ctx, int $sessionId, int $shiftId, array $input, string $idempotencyKey, int $expectedSessionVersion): array;
    public function addOrderLine(FnbContext $ctx, int $orderId, array $input, string $idempotencyKey, int $expectedVersion): array;
    public function updateDraftOrderLine(FnbContext $ctx, int $orderLineId, array $input, string $idempotencyKey, int $expectedOrderVersion): array;
    public function removeDraftOrderLine(FnbContext $ctx, int $orderLineId, string $idempotencyKey, int $expectedOrderVersion): array;
    public function applyOrderDiscount(FnbContext $ctx, int $orderId, array $input, string $idempotencyKey, int $expectedVersion): array;
    public function submitOrder(FnbContext $ctx, int $orderId, string $idempotencyKey, int $expectedVersion): array;
    public function voidOrderLine(FnbContext $ctx, int $orderLineId, string $reason, string $idempotencyKey, int $expectedOrderVersion): array;

    public function pollKitchen(FnbContext $ctx, int $cursor = 0, ?int $stationId = null, int $limit = 200): array;
    public function transitionKitchenLine(FnbContext $ctx, int $ticketLineId, string $toStatus, string $idempotencyKey, int $expectedTicketVersion): array;

    public function createCheck(FnbContext $ctx, int $sessionId, array $allocations, string $idempotencyKey, int $expectedSessionVersion): array;
    public function finalizeCheck(FnbContext $ctx, int $checkId, string $idempotencyKey, int $expectedVersion): array;
    public function reopenCheck(FnbContext $ctx, int $checkId, string $reason, string $idempotencyKey, int $expectedVersion): array;
    public function voidCheck(FnbContext $ctx, int $checkId, string $reason, string $idempotencyKey, int $expectedVersion): array;
    public function setSettlementPlan(FnbContext $ctx, int $checkId, string $mode, string $idempotencyKey, int $expectedVersion): array;
    public function collectPayment(FnbContext $ctx, int $checkId, int $shiftId, array $input, string $idempotencyKey, int $expectedVersion): array;
    public function cancelPayment(FnbContext $ctx, int $paymentId, string $reason, string $idempotencyKey): array;
    public function refundPayment(FnbContext $ctx, int $paymentId, int $shiftId, array $input, string $idempotencyKey): array;
    /** Internal compensation-only allocation vector; never bind directly to HTTP input. */
    public function refundAllocated(FnbContext $ctx, int $paymentId, int $shiftId, array $input, string $idempotencyKey): array;
    public function cancelRefund(FnbContext $ctx, int $refundId, string $reason, string $idempotencyKey): array;

    public function summaryReport(FnbContext $ctx, array $filters = []): array;
}
```

Additional bounded services used by dedicated controllers:

```php
FnbCustomerService::updateProfile(FnbContext $ctx, int $customerId, array $input, string $key, int $expectedVersion): array;
FnbCustomerService::attachToSession(FnbContext $ctx, int $sessionId, int $customerId, string $key, int $expectedSessionVersion): array;
FnbCustomerService::profile(FnbContext $ctx, int $customerId): array;
FnbCustomerService::purchaseHistory(FnbContext $ctx, int $customerId, int $limit = 25, ?int $beforeCheckId = null): array;
FnbCompensationService::compensate(FnbContext $ctx, int $checkLineId, array $input, string $key): array;
FnbReceiptService::generate(FnbContext $ctx, int $checkId, string $key): array;
FnbReceiptService::transition(FnbContext $ctx, int $printJobId, string $action, int $version, string $key): array;
FnbReceiptService::read(FnbContext $ctx, int $printJobId): array;
FnbReportExportService::request(FnbContext $ctx, array $input, string $key): array;
```

## Minimum command payloads

`onboard`:

```json
{
  "outlet": { "code": "Q1", "name": "Quán 1", "timezone": "Asia/Ho_Chi_Minh", "currency": "VND" },
  "terminal": { "code": "POS-01", "name": "Quầy 1", "type": "pos" },
  "station": { "code": "BAR", "name": "Bar" },
  "area": { "code": "MAIN", "name": "Khu vực chính" },
  "table_count": 5,
  "sample_menu": false,
  "payment_methods": [
    { "code": "CASH", "name": "Tiền mặt", "kind": "cash" },
    { "code": "TRANSFER", "name": "Chuyển khoản", "kind": "transfer" }
  ]
}
```

`saveCatalogItem` (create when `id` is absent, update otherwise). `variants` is
the complete desired variant set: omitted existing variants are retained for
history but made inactive. Exactly one active variant may have `is_default`;
when none is marked, the first active variant becomes the default. The legacy
singular `variant` form remains accepted and updates only that variant.
`category_ids` and `modifier_group_ids` replace their respective item links:

```json
{
  "id": null,
  "code": "CA-PHE-SUA",
  "name": "Cà phê sữa",
  "tax_category": "standard",
  "tax_rate_bps": 800,
  "tax_inclusive": true,
  "status": "active",
  "variants": [
    { "id": null, "code": "S", "name": "Nhỏ", "base_price_minor": 25000, "is_default": true, "sort_order": 0, "status": "active", "station_id": 1 },
    { "id": null, "code": "L", "name": "Lớn", "base_price_minor": 32000, "is_default": false, "sort_order": 1, "status": "active", "station_id": 1 }
  ],
  "category_ids": [2],
  "station_id": 1,
  "modifier_group_ids": []
}
```

The response returns the complete item resource, including `category_ids`,
`variants` (with each variant `id`, `version`, `is_default`, `status` and
`base_price_minor`), `station_routes`, `modifier_group_ids`, and outlet
`availability`.

`openSession`: `{ "business_day_id": 1, "service_type": "dine_in|counter|takeaway", "table_id": 5|null, "guest_count": 2, "customer_profile_id": null }`.

`createOrder`: `{ "note": null }`. `addOrderLine` and `updateDraftOrderLine`: `{ "variant_id": 10, "quantity": "2.000000", "modifier_option_ids": [4,7], "note": null }`. Prices, tax, route and recipe are always resolved server-side. Draft-line update is a full replacement of the priced line input; remove accepts no body beyond the route ID, idempotency key and expected order version.

`applyOrderDiscount`: `{ "amount_minor": 10000, "reason": "Khuyến mãi khai trương" }`. Pilot supports an additional fixed, order-level discount on a draft order only. The amount is a pre-tax net-item discount, allocated across remaining line subtotals by largest remainder with ascending `order_line.public_id` as the stable tie-break. The service rejects submitted/check-linked orders, negative net lines, and non-zero service-charge lines until the configurable taxable-service policy is implemented. To keep immutable adjustment allocations valid, add/update/remove line commands are rejected after the first pricing adjustment; create a replacement draft order if its item structure must change. The initial HTTP policy treats this command as critical (`fnb.discount.apply` plus re-auth/dual `fnb.discount.override`).

`createCheck.allocations`: `[{ "order_line_id": 20, "quantity": "1.000000" }]`. `collectPayment`: `{ "payment_method_id": 2, "amount_minor": 58000, "tendered_minor": 60000, "reference": null }`. `refundPayment`: `{ "amount_minor": 29000, "reason": "Khách trả món", "allocations": [{ "check_line_id": 30, "quantity": "1.000000" }] }`.

`closeShift.countedTenders`: `[{ "payment_method_id": 1, "counted_minor": 1000000 }]`.

`updateProfile`: any non-empty subset of `{ "code", "name", "phone", "email", "birthday", "notes", "status": "active|archived", "privacy_consent": true|false, "marketing_consent": true|false, "expected_version" }`. `attachToSession`: `{ "customer_profile_id": 12, "expected_version": 4 }`; attaching updates the open session and every still-open check, and is rejected once any check is finalized or payment history exists. Full profile/history reads require `fnb.customer.view`; history is cursor-paged with `limit` and `before_id` and only returns closed-check snapshots.

`compensate`: `{ "shift_id": 4, "quantity": "1.000000", "expected_version": 8, "reason": "Không thể phục vụ" }`. The source is a paid, unserved CheckLine. The command locks the order/KDS/check/payment chain, refunds one or more remaining manual tenders, links exact refund allocations, records sale reversal and preparing/ready waste, and then resolves exactly once. Provider-asynchronous compensation is not part of this Pilot endpoint.

Receipt commands are `POST /checks/{check}/receipts`, `POST /print-jobs/{job}/request` and `POST /print-jobs/{job}/confirm`; request/confirm use `{ "expected_version": 1 }`. `GET /print-jobs/{job}` verifies the canonical decoded JSON snapshot hash before returning it. Export creation is `{ "report_type": "financial|operations", "format": "csv", "from": "YYYY-MM-DD", "to": "YYYY-MM-DD", "business_day_id": null, "shift_id": null, "terminal_id": null }` at `POST /reports/exports`; filters use the same terminal-authority rules as the live report. Only the requesting actor can download a completed, unexpired, checksum-valid private export from `GET /reports/exports/{export}/download`.

Key runtime routes beyond basic POS commands:

| Route | Contract |
| --- | --- |
| `GET /outlets/{outlet}/customers`, `GET /customers/{id}`, `GET /customers/{id}/history` | Scoped lookup, full profile, immutable purchase history |
| `PUT /customers/{id}`, `POST /sessions/{session}/customer` | Optimistic customer update/attach |
| `PUT|DELETE /order-lines/{line}`, `POST /orders/{order}/discount` | Draft replacement/removal and fixed pre-tax discount |
| `POST /checks/{check}/reopen`, `POST /checks/{check}/void` | Structural guards; planned/attempted check cannot reopen |
| `POST /check-lines/{line}/compensations` | Paid-but-unfulfilled compensation with dual approval policy |
| `POST /checks/{check}/receipts`, `GET|POST /print-jobs/...` | Immutable receipt snapshot and print acknowledgement |
| `POST /reports/exports`, `GET /reports/exports/{export}/download` | Private asynchronous CSV export |

## Read projections

- `catalog.resource`: `{outlet, menus, categories, items:[{id,public_id,code,name,status,available,version,category_ids,variants:[{id,code,name,base_price_minor,is_default,status,version}],modifier_groups,station_routes}], stations, modifier_groups, payment_methods}`. By default only sellable active records are returned. `include_inactive=true` includes inactive items, variants, categories, stations, modifier groups and options (but not archived items); the controller may expose it only to `fnb.menu.manage`.
- `pollKitchen.resource`: `{cursor, has_more, tickets:[...]}`. Ticket/order labels, item/variant/modifier snapshots, quantities, notes and elapsed timestamps are allowed. Customer PII, prices, discounts, tender/payment details and cost are forbidden.
- `summaryReport.resource`: `{period,basis,totals,tenders,top_items,generated_at}`. Canonical cash-flow totals are `gross_collected_minor`, `refund_disbursed_minor`, and `net_collected_minor`; `gross_sales_minor`, `refunds_minor`, and `net_sales_minor` remain compatibility aliases. Closed-sale KPIs use `closed_sales_minor`, `checks`, and `average_check_minor`. Only closed CheckLine snapshots enter sales/item totals; open/finalized checks do not. `top_items[]` is `{item_id,item_code,item_name,quantity,refunded_quantity,net_quantity,gross_sales_minor,refunded_minor,net_sales_minor}`. A refund processed in a later period can legitimately produce a negative-net item row with zero sale quantity in that period.

Report filters are `from`, `to`, optional `business_day_id`, `shift_id`, and `terminal_id`; the service always adds the current website/outlet scope. A terminal-bound `FnbContext` forcibly scopes the report to that terminal even when the query omits `terminal_id`, and rejects a mismatching explicit terminal. Only an explicitly unbound, already-authorized context can report all terminals. Collections are selected by payment business day/shift/terminal, while refund cash flow and item refunds are selected by refund processing business day/shift/terminal. Closed sales use the check business day plus source-order shift/terminal. The response `basis` states these dimensions explicitly. Export jobs copy the normalized filter/report snapshot and expose `report_type`, `format`, `status`, `private_path`, `checksum`, `queued_at`, `completed_at`, and `expires_at` from `fnb_report_exports`.

## Reliability tables consumed by adapters

`fnb_outbox_events`: `event_id`, website/outlet scope, `aggregate_type`, `aggregate_id`, `aggregate_version`, `event_type`, `schema_version`, immutable `payload`, `occurred_at`.

`fnb_outbox_deliveries`: event/destination pair, stable semantic `idempotency_key`, `status` (`pending|processing|succeeded|failed|uncertain`), attempts, lease fields, external reference and scrubbed error. Destination availability is never required to commit the local POS transaction.

## Explicit development gaps

- Pricing currently implements fixed order-level, pre-tax discount only. Rate/line discount, discount reversal and configurable taxable service charge remain release work; a non-zero service charge is rejected instead of being calculated approximately.
- Quantity split is supported by immutable CheckLine allocation, but the dedicated source-check structural split command and order reopen command are not exposed. Session merge remains the versioned `0.1.1` increment.
- Compensation is synchronous for local/manual tenders. Provider-asynchronous refund, cancel/retry and uncertain-state reconciliation still require provider adapters and production failure tests.
- The core report reconciles closed sales, processing-period cash flow, tender mix, item totals and shift variance. Channel/hour plus explicit discount, void and compensation breakdowns remain to complete the full Pilot reporting matrix.
- Customer create/search/update/attach/history are available; customer-profile merge and survivor mapping are not yet implemented.
- Database constraints, optimistic versions and row locks are present, and the isolated real-MySQL workflow gate passes. True competing-process race/soak tests and production accounting/inventory/provider delivery adapters remain required gates.

This contract is a development contract. The isolated MySQL gate currently passes all 170 F&B foreign keys plus a real mixed-tender/refund/multi-tender-compensation/stock/receipt-checksum workflow. Contention races and asynchronous provider reconciliation gates remain required before a production-ready version declaration.
