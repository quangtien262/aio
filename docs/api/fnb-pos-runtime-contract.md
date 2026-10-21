# F&B POS runtime contract (`0.2.0-dev.3`)

This document is the integration boundary between the F&B domain, HTTP controllers and the React shell. It is intentionally explicit about scope: controllers authorize permissions, while every service command revalidates aggregate scope, state, version and monetary invariants.

## App navigation (2026-10-20)

The Admin workspace menu is the only app-level navigation bar for F&B. Main destinations are **Tổng quan** (Hôm nay / Báo cáo), **Bán hàng**, **Bar / Bếp**, **Thực đơn**, **Ca bán hàng**, **Khách hàng**, and **Cấu hình** (onboarding, staff, chain, integrations, settings, guide), filtered by live outlet/HQ permissions. `FnbPosManagerPage` publishes the filtered navigation to `AdminLayout` through `onWorkspaceNavigationChange`; registration is scoped to module + website + actor and removed on unmount. It changes presentation, not API authorization. `FnbWorkspaceShell` retains outlet/terminal selectors and operational alerts, without a duplicate desktop menu or mobile drawer. Overview no longer repeats Today/Reports tabs in its content.

Routes retain `/admin/modules/fnb-pos/{section}` and `/dashboard?view=today|reports`; `/reports` remains a legacy alias. Root overview query parameters survive canonicalization. The shell resolves the longest matching route prefix, so `/modules` (App Store) cannot override deeper app routes. While inside an app, the workspace navigation and mobile drawer show that app's menu; platform section switching is hidden, with the home logo/app switcher as appropriate entry points to other workspaces. Outlet/terminal selection and native guards remain unchanged.

Navigation policy lives in `resources/admin/src/modules/fnb-pos/utils/fnbWorkspaceNavigation.js`; generic route matching and registration ownership checks live in `resources/admin/src/shared/utils/workspaceNavigation.js`. Keep navigation registration after bootstrap/selection readiness and never replace native permissions with menu visibility. Regression tests: `tests/UnitJavaScript/fnb-workspace-navigation.test.js`, `tests/UnitJavaScript/workspace-navigation.test.js`, plus existing overview-navigation tests.

## Additional orders after payment

`order.create` accepts service sessions in `open` or `settling` state while the business day and bound shift remain open. Creating a new order round atomically resumes a settling session (`open`, clears `settling_at`, increments the session version and emits `fnb.service_session.resumed`). The command remains version checked and idempotent. Closed/cancelled/merged sessions reject new orders. Previously finalized bills, their allocations and payment records remain unchanged; additional quantities are billed separately. POS keeps **Thêm món** enabled in both active session states.

## Return table / end service session

`POST sessions/{id}/close` (`fnb.order.update`) accepts `open` and `settling` sessions. POS exposes **Trả bàn** next to the selected table, with confirmation; counter/takeaway sessions use **Kết thúc phiên**. An empty session can close directly without requesting settlement. Active/draft orders, open bills and quantities without settled payment evidence still block closure. Version checks and idempotency apply; session closure and release of all active table assignments commit atomically. Historical orders and payments remain intact.

POS also offers **Thanh toán & trả bàn** for dine-in sessions. It uses the existing payment workflow, preserves this intent through settlement rounding and bank intent creation, then refreshes the session before attempting the separate idempotent close command. Partial/pending payments and unfinished orders keep the table occupied with an explicit message. A failed close never repeats an acknowledged payment.

The **Thu tiền** modal accepts several payment methods in one confirmation. Each selected method has its own amount, cash tendered/change or transaction reference. Editing either amount in a two-method split automatically fills the other amount; with more methods, the final other method balances the split, reducing earlier other allocations only if necessary. Manually overridden cash tendered values are preserved. Empty or invalid edits remain visible for correction, and every selected method must retain a positive amount before collection. Transfer QR previews use only that method's assigned amount. The assigned total must equal the current collectible balance; incomplete or excessive allocations cannot be submitted. Existing immutable single-tender plans retain their restrictions; new combinations select `mixed` before collection.

Menu items support `gallery_images` (up to 12 distinct image URLs) alongside `image_url` (the selected cover, which must belong to the gallery). An omitted gallery preserves existing photos; an explicit empty list clears them. Legacy items with only `image_url` expose that image as their gallery. Published menu snapshots freeze both fields; selecting or removing photos in a draft does not alter existing publications.

`POST menu/images` accepts multipart `file`, scoped outlet/terminal headers and an idempotency key. It requires both outlet `fnb.menu.manage` and HQ `fnb.menu.master.manage`; it uses the public storage disk without depending on CMS module access. Server validation accepts actual JPEG, PNG or WebP images, up to 5 MiB and 8000×8000 pixels. The result contains `resource.url`; replaying the same key and file returns the original URL. The editor supports multi-file selection, previews and cover selection, and blocks saving until uploads finish. Removing a photo detaches it from the draft item and retains the stored file for publications that may still reference it.

`POST checks/{id}/payments` also accepts `{shift_id, payments: [{payment_method_id, amount_minor, tendered_minor?, reference?}], expected_version}`. The existing single-payment body remains supported. The list contains 1–10 distinct active methods and must cover the entire remaining balance, including reservations for unresolved payments. Multiple entries require a locked `mixed` plan. The `payment.collect_batch` command records every tender, cash movement, projection, check closure and outbox event in one database transaction using the existing operator-confirmed collection rules. Any invalid entry rolls back the entire batch. Retrying the same idempotency key and body returns the original batch, including after closure; browser command recovery uses the same protected `/payments` route and stores no tender payloads.

The **Khách hàng** workspace section (`/admin/modules/fnb-pos/customers`) requires `fnb.customer.view` and provides searchable, paginated profiles, details and outlet-scoped purchase history. `GET outlets/{outlet}/customer-profiles` accepts `query`, `status=active|archived|all`, `page` and `per_page=10|20|50|100`; it returns website-scoped full profiles plus pagination metadata and uses `Cache-Control: no-store`. The existing POS customer lookup retains its separate permission and masking rules.

The management page reuses `POST customers` and `PUT customers/{id}` for create/edit. `DELETE customers/{id}` requires `fnb.customer.update`, an idempotency key and `expected_version`; it archives the profile through the existing versioned customer update command and audit events. **Đã xóa** lists archived profiles, which can be restored with `PUT customers/{id}` and `status=active`. Neither action deletes transaction records or rewrites buyer snapshots. Archived profiles are excluded from the POS picker. Optional birthday, notes and consent fields require `fnb.customer.update` during creation as before; no profile payload is saved in the browser command journal.

## Scope and errors

`POST menu/items/archive` removes 1–500 selected items from the shared menu draft using soft deletion. It requires outlet `fnb.menu.manage`, HQ `fnb.menu.master.manage`, `Idempotency-Key`, and `{items: [{id, expected_version}]}` with distinct item IDs. The whole batch is atomic: a missing/cross-website item or stale version prevents every deletion. Successful commands increment each item version, emit archive events and record the affected IDs in audit; retries with the same key and body replay the result. Variants, recipes, order/bill history and existing published menu snapshots remain intact. Publish a new menu to apply the draft changes to outlets. The Menu table exposes permission-gated row selection, selected count, clear selection and a confirmed **Xóa đã chọn** action.

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
    public function closeZeroChargeCheck(FnbContext $ctx, int $checkId, int $shiftId, string $reason, string $idempotencyKey, int $expectedVersion): array;
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
| `POST /checks/{check}/close-zero-charge` | Finalized zero-plan closure with `shift_id`, `reason`, `expected_version`; re-authenticated, no synthetic payment |
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

`fnb_outbox_deliveries`: event/destination pair, stable semantic `idempotency_key`, `status` (`pending|processing|delivered|failed|dead_letter|superseded`; legacy `succeeded|uncertain` are retained), attempts, availability/lease/token fields, external reference and scrubbed error. Destination availability is never required to commit the local POS transaction.

## Explicit development gaps

- Pricing currently implements fixed order-level, pre-tax discount only. Rate/line discount, discount reversal and configurable taxable service charge remain release work; a non-zero service charge is rejected instead of being calculated approximately.
- Quantity split is supported by immutable CheckLine allocation, but the dedicated source-check structural split command and order reopen command are not exposed. Session merge remains the versioned `0.1.1` increment.
- Compensation is synchronous for local/manual tenders. Provider-asynchronous refund, cancel/retry and uncertain-state reconciliation still require provider adapters and production failure tests.
- The core report reconciles closed sales, processing-period cash flow, tender mix, item totals and shift variance. Channel/hour plus explicit discount, void and compensation breakdowns remain to complete the full Pilot reporting matrix.
- Customer create/search/update/attach/history are available; customer-profile merge and survivor mapping are not yet implemented.
- Local Inventory v1 and Accounting draft adapters exist under the explicit contract below. Inventory v2, accounting refund credit notes, provider delivery/reconciliation and current-release MySQL competing-process/soak gates remain incomplete.

This is a development contract. The recorded `0.1.0-dev.1` MySQL smoke covered 170 foreign keys and a manual mixed-tender/refund/compensation/stock/receipt workflow. It does not certify the `0.2.0-dev.1` schema, local adapters, true contention races, real bank/provider integration or production throughput.


## Chain and local integration additions (`0.2.0-dev.1`)

The package targets a multi-outlet chain within one website. HQ scope is explicit; a website role alone does not widen an outlet assignment. Existing `0.1.x` security definitions remain versioned for upgrade/backfill. Operational money calls retain their outlet/terminal authority. Native Inventory/Accounting permissions are additional requirements, not inherited from an F&B preset.

Chain routes below are relative to `/admin/api/fnb/chain`:

| Route | Boundary |
| --- | --- |
| `GET /bootstrap`, `POST|PUT /brands`, `/regions`, `/outlets` | HQ website scope; provisioning adds an outlet with terminal/station/table/payment defaults; updates require versions |
| `POST /staff`, `DELETE /staff/{resource}` | HQ preset assignment/revocation with re-auth; protects the last HQ owner |
| `POST /menus`, `GET /menu` | HQ menu master data |
| `POST /menu/publications`, `POST /menu/publications/{resource}/rollback` | Immutable menu/recipe-binding revision and selected outlet assignment; rollback references an existing publication |
| `POST /price-books` | VND POS variant prices, selected outlets and optional offset-qualified validity timestamps |
| `GET /audit` | Authorized HQ audit scope; no cross-website widening |
| `GET /integrations` | Mapping, source/destination options, backlog, status totals; `configured` does not establish production readiness |
| `PUT /integrations/mapping`, `PUT /integrations/items` | Re-authenticated destination/item configuration with optimistic version |
| `POST /integrations/reconcile` | Re-authenticated durable preparation, followed by independently atomic local deliveries |

`closeSession` and `closeShift` reject unbilled/partially billed effective quantities even when fulfillment is completed. Voided quantity is excluded; compensated quantity retains its closed source check. Closed checks must carry matching paid settlement evidence. Order payment status is based on complete closed quantity allocations, so cash rounding and a partial zero-value allocation cannot create false paid/unpaid states.

KDS current tickets include `lines[].modifiers[]` with `group_code_snapshot`, `group_name_snapshot`, `option_code_snapshot`, `option_name_snapshot`, `quantity`, and compatibility alias `name_snapshot`. This survives cursor advancement and catalogue edits; fired events are not the only source of modifier display. Snapshot queries are batched, and exclude modifier prices/costs/recipes/PII.

### Mapping and reconciliation payloads

Destination configuration:

```json
{
  "destination": "inventory",
  "enabled": true,
  "automatic": false,
  "config": { "warehouse_id": 1 },
  "expected_version": 2
}
```

For Accounting use `destination=accounting-tax` and `config.organization_id`. Both source outlet and organization must use VND; no foreign minor-unit scale or exchange rate is inferred. `expected_version` is required when a mapping already exists. Disabling a mapping clears the standing automation grant and is allowed while the destination module is off.

Item configuration:

```json
{
  "destination": "inventory",
  "source_type": "ingredient",
  "source_id": 12,
  "destination_id": 31,
  "unit": "kg",
  "factor": "0.001000",
  "expected_version": 1
}
```

Inventory mapping requires an active untracked item whose unit matches `unit`, and native issue **and** receipt permissions. Accounting uses `source_type=variant`, factor 1 and an active item belonging to the authorized configured organization. Changing an item mapping pauses destination automation and increments its version, requiring review and fresh explicit opt-in.

Reconcile payload: `{ "destination": "inventory|accounting-tax", "retry_dead_letter": false }`. `outlet_id`/`X-FNB-Outlet` select the authorized outlet; they never grant scope. The HTTP response is `{data:{delivered,failed},meta:{replayed}}`. Preparation runs under `FnbCommandRunner`, so re-auth proof consumption and explicit dead-letter reset are transactional/idempotent. Delivery failures do not undo already committed POS sales or other successful deliveries.

### Local adapter invariants

- Inventory delivery identity is one `fnb.stock_consumption.recorded` outbox event per consumption ID, including `sale`, `reversal`, and `waste`. It is derived from the durable source ledger even when no check-close event exists. At most 500 missing events/deliveries are prepared and 100 due deliveries processed per reconciliation.
- A delivery claim has a token, two-minute lease and durable attempt count. Retry backoff starts at five seconds, doubles and caps at one hour; ten unsuccessful attempts produce `dead_letter`. Expired leases recover, including a final-attempt crash. An explicit `retry_dead_letter=true` resets poison attempts once under its command identity; automatic execution never does this.
- Stock posting, source `posted` state/reference, immutable `fnb_integration_stock_lines` lineage/cost and delivery `delivered` acknowledgement share the same database transaction. Deterministic document identity and source row locks make retries harmless despite Inventory v1 lacking its own integration idempotency contract. No external network call is performed.
- Stock quantities must be nonnegative, fit `decimal(14,3)` exactly after applying the mapping factor, and use no batch/serial tracking. No lossy rounding is permitted. Reversals and linked waste use original item/factor/warehouse/cost lineage; a missing original posting blocks them. Current permissions, active warehouse/items and module capability are rechecked during posting.
- Stored consumption checksums are verified using canonical hashes or recognized legacy raw/JSON-key-order encodings. A mismatch is rejected, not silently migrated to a new checksum.
- Accounting delivery creates one `internal_invoice` **draft** per closed check. Each mapped source line is represented by its exact gross value with quantity 1; original quantity, name and subtotal/discount/tax/service/rounding vector remain in client metadata. This avoids silently rounding six-place POS quantity into Accounting's four-place quantity/two-place unit-price model. Lines use `tax_category=not_declared` and `requires_tax_classification=true`; total must match source `grand_total_minor`. There is no auto-approval, posting, tax eligibility or invoice issuance. Accounting refund credit notes remain manual release work.

### Standing automation grant

`automatic=true` is an explicit configuration request, not a default. The mapping records `authorized_by` and `authorized_auth_version`. `fnb:dispatch-integrations --limit=50` runs each minute with overlap prevention and dispatches unique `ReconcileFnbIntegration` jobs on queue `fnb` only for enabled opted-in mappings. Workers recheck the actor's active state/auth-version, HQ/outlet/native permissions, module/lifecycle capability, mapping and source evidence for every delivery. Revocation, password/auth-version change, mapping disable or item-mapping edit prevents further side effects. Dispatch rotates by `last_dispatched_at` to avoid starving later outlet mappings under the batch limit. Identity/authority revocation pauses the grant and records `automatic_paused_reason`; destination outages retain pending work. The two-minute unique window/job lease is not a substitute for delivery row locking/idempotency.

TPBank QR configuration and VND transfer intent payloads are operator-assisted only. `provider_connected=false` and manual confirmation remain the truth; QR generation does not establish a real bank connection, incoming payment confirmation or hardware certification. Receipt printing remains operator-confirmed browser printing.

## Ingredient units (`0.2.0-dev.2`)

- `GET outlets/{outlet}/recipes` also returns `units`: website-scoped, non-archived records with `code`, `name`, `dimension` (`mass`, `volume`, `count`), decimal `factor`, `status`, `is_system`, `version`, and computed `in_use`.
- `POST units` and `PUT units/{id}` use the normal configuration contract. `POST units/{id}/archive` soft-deletes an unused custom unit. All writes require `fnb.recipe.manage` plus HQ `fnb.recipe.master.manage`, `Idempotency-Key`, and `expected_version` on updates/archive. Used/system unit deletion and stale updates return 409. Codes are immutable; dimension/factor become immutable for system or referenced units. Status/name remain editable.
- Seeded units: `g`, `kg`, `ml`, `l`, `piece`. Factors are relative to g/ml/piece, with up to six fractional digits, greater than zero and at most 1,000,000. Publishing converts quantity using BCMath directly into the ingredient base unit; cross-dimension, inactive source, zero-after-rounding, and overflow results are rejected. Existing recipe snapshots are unchanged. An inactive target unit may remain the immutable base of an existing ingredient.
- Ingredient and topping-group codes are optional on create, generated from the Vietnamese name with a collision suffix within their website. Blank codes on edit preserve the existing code. Category codes follow the same behavior within their menu. Explicit invalid/duplicate codes return validation errors.

### Shared size and topping names

`GET outlets/{outlet}/menu?draft=1` includes website-scoped `size_definitions` and `topping_definitions` (active definitions only). These are reusable code/name templates. Selecting one copies its code/name to an item variant or group option; price and station remain specific to the item/group. Updating or archiving a template does not change existing assignments, released menus or order snapshots.

For `kind = size | topping`:

- `POST menu/{kind}-definitions`: create with required `name`, optional `code` (generated from name when blank).
- `PUT menu/{kind}-definitions/{id}`: update with `name`, optional `code` (blank preserves code), and required `expected_version`.
- `POST menu/{kind}-definitions/{id}/archive`: remove from the selectable catalog with required `expected_version`.

Commands require `Idempotency-Key`, `fnb.menu.manage` and HQ `fnb.menu.master.manage`; definitions are isolated by website. Names are unique after whitespace/case normalization; codes are unique ignoring case within each kind, including archived entries. The additive migration imports existing variant/option names without changing their source records or prices.

### Complete service for an entire order

`POST kitchen/orders/{order}/complete` requires `fnb.kitchen.update`, an `Idempotency-Key` and `expected_ticket_versions` keyed by every outstanding kitchen ticket ID for that order. It confirms delivery of all remaining items across stations. The POS completion drawer and kitchen board show a confirmation before sending this command; station/status filters do not limit its scope.

The server validates outlet ownership, active order state, exact outstanding ticket set and versions, then advances each outstanding line through the existing transition and quantity-ledger checks within one transaction. Stale/missing tickets or an invalid ledger roll back all changes. Replays do not increase served quantities again. Previously served, voided and compensated lines are preserved. Payment and table release remain separate actions.

`GET outlets/{outlet}/kitchen/tickets?view=completed` returns the latest 300 website/outlet-scoped tickets containing served lines, including partially served tickets. The default `view=active` retains the live work queue behavior. `station_id` also applies to history; the kitchen UI displays only served lines in the Hoàn thành tab, with their `served_at` timestamps and without transition/bulk-completion actions.

### Multiple receiving banks and payment QR

Migration `000014_expand_fnb_bank_accounts` preserves encrypted legacy TPBank accounts, removes the one-account-per-outlet restriction and adds a unique default slot. Account changes are serialized by the outlet lock; changing a default bumps the displaced account version. At most one enabled account is default; the first enabled account is selected automatically when needed. Accounts can be disabled without deleting past payment intents. The migration is forward-only to avoid silently discarding multiple accounts on downgrade.

- `GET outlets/{outlet}/bank-accounts`: HQ configuration list with masked numbers plus the supported bank directory.
- `POST outlets/{outlet}/bank-accounts`: add `bank_code`, `account_number`, `account_name`, `enabled`, optional `is_default`.
- `PUT outlets/{outlet}/bank-accounts/{id}`: edit with `expected_version`; blank account number preserves it. Changing bank requires re-entering the account number.
- `GET outlets/{outlet}/bank-accounts/payment-options`: enabled masked accounts for cashiers with `fnb.payment.collect`, without granting configuration access.
- Existing singleton `bank-account` endpoints remain compatible and operate on the default account.
- `POST checks/{check}/bank-intents`: accepts `amount_minor` and optional `account_id`; defaults to the enabled default account. The response includes `check_id`, `account_id`, bank identity, number, owner, reference, expiry and local VietQR payload.

Configuration retains HQ `fnb.chain.manage`, outlet settings permissions, reauthentication, version checks and idempotency. The account-number and owner-name columns in the bank configuration table remain encrypted. Bank code/BIN directory was verified against [VietQR's public bank directory](https://api.vietqr.io/v2/banks) on 2026-10-19; only transfer-supported entries are offered. QR generation uses the existing local TLV/CRC encoder with the chosen bank BIN and sends no account details to an external image service.

A QR preview may be created for a finalized VND check before choosing a settlement plan; this does not lock its plan, reserve funds or credit a payment. Cash-only plans and amounts above the outstanding balance are rejected. The cashier must verify receipt before explicitly collecting payment. The UI invalidates previews when check, method, amount or bank changes and rejects late responses from earlier requests. Reference input is hidden in the QR flow and auto-filled with its transfer reference; required manual transaction IDs remain available for methods configured to require them.
