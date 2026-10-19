# F&B POS development runbook

Package: `fnb-pos@0.2.0-dev.2`. This is a development release, not a Production RC declaration. See [implementation status](fnb-pos-implementation-status.md) and the [runtime/API contract](../api/fnb-pos-runtime-contract.md).

## Install and upgrade

1. Back up the deployment and record the application/module revisions before upgrading.
2. Apply additive core migrations through the normal process, including lifecycle/security and the audit-chain partition migration. Core migration does not install F&B. Preserve the old audit chain; do not rewrite existing hashes to force verification to pass. After partitioned events exist, the audit migration deliberately refuses rollback; keep a partition-aware logger/verifier when rolling application code back. Do not deploy an older global-sequence verifier against partitioned history.
3. Install/upgrade/enable through App Store. The package owns its `fnb_*` migrations and exact installed-version security map. `0.2.0-dev.1` adds chain scopes and integration/bank configuration; legacy outlet memberships are backfilled into chain scopes idempotently. `0.2.0-dev.2` adds `fnb_units` and seeds standard units for existing websites; installing/upgrading through ModuleManager is required before using unit CRUD. It keeps the previous permission/role sets and immutable recipe snapshots.
4. Select the intended website. Reauthenticate for onboarding/HQ configuration, create the first cafe preset, then add brands/regions/outlets from chain management. Configure station routing, menu/recipes, terminals, tables, methods and existing admin accounts. F&B does not create global identities or grant Inventory/Accounting permissions implicitly.
5. Publish a menu revision to selected outlets. Keep a small canary outlet set for operational validation; rollback selects an existing immutable publication. Local sold-out state and station routes remain per outlet. Price books currently target VND POS; use timestamps with explicit offsets for scheduled validity.
6. Open the business day and terminal shift before order/payment operations. Closed session/shift must have all non-voided quantities in settled closed bills, including free/compensated quantities with their source lineage.

Historical SQLite/MySQL smoke results belong to their recorded revision. The prior CMS fresh-MySQL issue has a source guard now, but the current package still needs a fresh-production and competing-process MySQL gate; do not infer readiness from old smoke results.

Disable is blocked while operational/money state is active and retains records. Uninstall is denied to preserve history. Lifecycle recovery must use its recorded operation identity; never delete the sentinel, edit payment rows or perform destructive migration rollback to unblock an upgrade.

## Worker and scheduler

Use a managed worker subscribed to the named queue and the normal scheduler:

```text
php artisan queue:work --queue=fnb --timeout=60 --tries=3 --sleep=3
php artisan schedule:work
```

Keep queue `retry_after` greater than 60 seconds; the database/Redis default is 90. `ReconcileFnbIntegration` is unique per mapping for up to 120 seconds, runs on `fnb`, has a 60-second timeout and three job attempts. Delivery attempts have separate durable counters/backoff. A job retry never creates a new stock identity.

The scheduler runs `fnb:dispatch-integrations --limit=50` each minute with overlap prevention, and recovers stale queued exports every five minutes. Dispatch selects only mappings with `enabled=true`, `automatic=true` and a stored authorizing admin. Dispatch rotates by `last_dispatched_at`, so a batch limit cannot permanently starve later outlets. There is no automatic opt-in during install, onboarding or mapping creation.

## Configure local integrations

1. Enable the destination module through its lifecycle. Use an HQ account with F&B chain/settings permissions and the destination's native permissions. Inventory delivery requires **both** `inventory.issue.manage` and `inventory.receipt.manage`; Accounting requires `accounting.document.create` for the selected active legal entity, which must be associated with the website.
2. Map each F&B ingredient to an active non-batch/non-serial Inventory item, target unit and positive conversion factor. Choose the active warehouse. Converted quantities must fit Inventory v1's `decimal(14,3)` without rounding; unsupported precision fails safely instead of silently losing consumption.
3. Map each variant to an active Accounting item in the selected legal entity. The local adapter currently supports **VND source outlets and VND legal entities only**, with factor 1. It creates `internal_invoice` drafts whose gross total exactly matches the source bill. Quantity/component snapshots stay attached; draft lines remain `not_declared` with `requires_tax_classification=true`. They are neither posted revenue nor tax invoices. Refund credit-note automation is not implemented.
4. Reconcile manually first. Inventory dispatch is **per consumption** (`sale`, `reversal`, `waste`), not per bill. Each successful delivery atomically commits the real Inventory document, source status/reference, line mapping/cost lineage and delivery status. Reversal/waste referencing a prior consumption use its original mapping and warehouse; changing the current mapping does not redirect history. Missing source delivery/lineage, inactive items or insufficient stock/cost layers block the delivery.
5. Only after manual reconciliation, optionally enable **automatic** synchronization. This standing grant stores the authorizing admin and `auth_version`; workers revalidate live HQ/F&B permissions, native module permissions, module capability/state, mapping and source evidence on every delivery. Password/auth-version change, inactive admin or revoked rights pauses the standing grant; `automatic_paused_reason` records why. Destination outages hold pending work without a fake success. Editing item mappings pauses automation and increments the destination mapping version; review and opt in again explicitly.
6. To stop synchronization, disable the destination mapping. This kill switch works even when the external module or warehouse is unavailable. It clears the automatic grant and retains events, delivery history and posted documents.

Readiness deliberately remains `ready=false`; `status=configured` means a local adapter has configuration, not production acceptance. Source consumption alone is theoretical. Confirm the consumption's `posted` state and actual document reference plus the delivery's `delivered` state before treating it as a stock posting. F&B never calls Minvoice directly.

## Retry and incident recovery

```text
php artisan fnb:health --json
php artisan modules:lifecycle fnb-pos --json
php artisan modules:lifecycle fnb-pos --resume=OPERATION_UUID
php artisan fnb:recover-exports --limit=100
php artisan fnb:dispatch-integrations --limit=50
```

Use the integration overview for destination-specific backlog/status/attempts. A preparation batch derives at most 500 missing consumption events/deliveries; one reconciliation processes at most 100 due deliveries. Batches continue on later dispatches. Existing history is not re-emitted row by row.

Delivery lifecycle: `pending → processing → delivered`, or `processing → failed` with 5-second exponential backoff capped at one hour. A claim has a token and two-minute lease. Expired leases can be reclaimed; after ten attempts, including a final-attempt crash, the delivery becomes `dead_letter`. Local Inventory/Accounting work and successful acknowledgement share one database transaction, so a crash cannot leave an unacknowledged external success. No network provider is invoked by these adapters.

For failures, inspect private application logs and correct permissions/module state, mapping/unit precision, stock/cost layers or checksum evidence. The public error is scrubbed. Never bypass a checksum, round quantity to force a posting, alter old source snapshots or mark a failed delivery delivered manually. Legacy raw/canonical checksums and known MySQL JSON key-order variants are verified; unknown mismatches remain blocked.

After correcting a poison delivery, an authorized operator can reconcile with `retry_dead_letter=true`. This reset is inside the idempotent, re-authenticated preparation command and happens once per command identity. Replaying the same request cannot repeatedly reset attempt limits. Automatic dispatch never resets dead letters. Development bill-based stock deliveries still pending are superseded by consumption-based identities; posted legacy history is retained and missing lineage requires review.

## Payments, receipts and exports

Manual tender confirmation requires the operator to verify actual cash/transfer receipt. TPBank/VietQR payload generation does **not** connect a bank account, verify incoming funds or certify the payload on a bank application. Do not mark payment succeeded because a QR was displayed. Provider uncertain/query/reconcile adapters remain release work.

For a finalized bill with a zero settlement plan, use the explicit zero-charge close command with a reason and re-auth. It creates audit/consumption without inventing a positive payment. Do not insert a one-unit payment to close a free bill.

Receipt state is `generated → print_requested → user_confirmed`. Browser print cannot prove paper delivery; confirm only after physical inspection. These receipts are not electronic tax invoices. CSV exports are private, checksummed, formula-safe, requester-bound and expire after one day. Retention/purge approval and cleanup remain release work; do not delete ledgers to resolve export errors.

## Verification and remaining release gates

```text
APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result tests/Feature/FnbIntegrationAdapterTest.php tests/Feature/FnbTransactionSafetyTest.php tests/Feature/FnbDomainInvariantTest.php tests/Feature/FnbPosApiTest.php
php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result
php -d memory_limit=512M tests/Support/fnb-mysql-smoke.php --allow-isolated-mysql
node --test tests/UnitJavaScript/fnb-command-unit.test.js
npm run build
```

The synthetic key above is for isolated tests only. PHPUnit uses SQLite in-memory; never point it at an operational database. MySQL scripts require the explicit opt-in flag and a loopback test server; they create a randomly named scratch database, verify an ownership nonce, copy schema definitions without user rows and drop only that database in `finally`. See [isolated MySQL gates](../../tests/Support/fnb-mysql-gates.md) for concurrency/load commands and their measurement limits.

The browser fixture support script creates an isolated SQLite file and loopback server. Use its explicit fixture flag and synthetic login, then stop the server and remove only that generated fixture. Never run the fixture suite against production or change a real admin to match test credentials.

Before deployment acceptance require two-user/two-browser UAT, MySQL competing-process and crash/lease tests, measured throughput/latency for the target outlet scale, backup/restore and disable/upgrade drills, monitoring/support ownership, tax/rounding/service-charge policies, PII retention/erasure and the device/provider support matrix. A green local build or adapter test does not prove real bank integration, paper delivery or production chain capacity.
