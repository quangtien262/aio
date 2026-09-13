# F&B POS development runbook

Package: `fnb-pos`, development prerelease `0.1.0-dev.1`. This is not a Production RC declaration. See [implementation status](fnb-pos-implementation-status.md) for unfinished requirements and release gates.

## Installation boundary

1. Back up the existing deployment database and verify the application revision before upgrades.
2. Apply the additive core lifecycle/security migration through the normal core migration process. This creates `module_lifecycle_operations`, `module_role_definitions`, and `admin_reauth_proofs`; it does not install F&B.
3. Open App Store → Quản lý quán Cafe → Install → Enable. The package owns all `fnb_*` migrations. Installation is deployment-wide; onboarding/menu/customer data are website-scoped and operational records are outlet-scoped.
4. Select the intended website before onboarding. Reauthenticate, create the cafe preset, then configure menu, terminals, tables, Bar, methods and staff. Preset accounts are existing core admins: the F&B assignment screen does not create global accounts.
5. Open a business day and a terminal shift before creating orders or collecting manual tenders.

The baseline fresh-production SQLite migration test passes. A pre-existing core fresh-MySQL migration (`2026_07_17_000003_make_cms_media_file_path_nullable.php`) assumes `cms_media` already exists. The isolated F&B MySQL gate clones existing core table definitions without user rows to verify the supported existing-deployment upgrade path. Do not rewrite an already-applied historical migration to hide the fresh-MySQL issue; resolve that separately before a new MySQL deployment.

Local implementation session: the additive core migration was applied on 2026-09-05. F&B installation/onboarding and transaction examples were performed only in isolated test databases, not in the user's application database.

## Workers and exports

Run a worker subscribed to the named queue:

```text
php artisan queue:work --queue=fnb --timeout=60 --tries=5 --sleep=3
php artisan schedule:work
```

For a production process manager, use an equivalent managed worker and the standard scheduler; do not launch an unmanaged interactive process. `retry_after` must remain greater than 60 seconds (the current database/Redis default is 90). The job rechecks module state and the requester's live export/report/outlet permissions. Its unique key and row lock make duplicate execution harmless. Financial/provider mutations are never retried by the export recovery task.

CSV exports contain an immutable filtered report snapshot, live in the private local disk, are checksummed, have a one-day download expiry, and escape formula-leading strings. Download requires the original requester, correct website/outlet and current permissions. A five-minute scheduler recovers stale queued exports. Failed exports need investigation; retention/purge approval and storage cleanup remain release work, and transaction ledgers must not be deleted to resolve an export error.

## Diagnostics and recovery

```text
php artisan fnb:health --json
php artisan modules:lifecycle fnb-pos --json
php artisan modules:lifecycle fnb-pos --resume=OPERATION_UUID
php artisan fnb:recover-exports --limit=100
```

Lifecycle recovery uses the recorded operation identity and version, not a newly invented operation. An active operation keeps traffic drained. Advisory ownership and durable markers protect schema/security/version transitions. Never delete the lifecycle sentinel or run a destructive rollback to force an upgrade through.

Disable is blocked while shifts, service sessions, money reservations/refunds or compensations are nonterminal. Close/reconcile them first. Disable retains all records. Uninstall is deliberately denied because payment/stock/audit history must be preserved.

An uncertain payment/refund must not be treated as failure or sent again with a new identity. The current package supports operator-confirmed manual tenders only; no payment provider is connected. The provider-specific mutation/query/reconciliation adapter and certification are outstanding. Inventory/Accounting/HRM/Payroll readiness is reported explicitly as pending; an outbox event or theoretical consumption is not proof of a stock/accounting posting.

## Printing truth

Receipt state is `generated → print_requested → user_confirmed`. Browser print dialogs cannot prove paper delivery. Confirm only after the operator checks the physical result; reprints retain the snapshot and increment counters. These receipts are not electronic tax invoices. No direct Minvoice call is made by F&B.

## Verification commands

```text
php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result
php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result tests/Feature/FnbAuthorizationHttpTest.php tests/Feature/FnbPosApiTest.php tests/Feature/FnbSecurityFoundationTest.php tests/Feature/FnbCustomerServiceTest.php tests/Feature/FnbDomainInvariantTest.php tests/Unit/FnbDomainValueObjectsTest.php tests/Unit/FnbTenderAllocationTest.php tests/Unit/FnbPublicProjectionTest.php
php -d memory_limit=512M tests/Support/fnb-mysql-smoke.php
npm run build
```

The MySQL smoke script creates a randomly named `aio_fnb_smoke_*` database, copies schema definitions only, runs its checks, then drops only that exact generated database in `finally`. It never copies user rows. It requires local create/drop-database permission.

Browser smoke uses `tests/Support/fnb-browser-fixture.php` to create a new SQLite file under `storage/framework/testing/`. Start the application with the printed database path, `APP_ENV=testing`, SQLite, database sessions, array cache, and sync queue on an isolated loopback port. Point `PLAYWRIGHT_BASE_URL` at that port, explicitly set `PLAYWRIGHT_FNB_FIXTURE=1`, and run `tests/browser/fnb-pos.spec.js`; the suite refuses a non-loopback target. The synthetic fixture login is test-only; do not run this browser suite against production or change a real account to match the fixture. Stop the test server and remove only the exact generated fixture when finished.

## Production release is still gated

Require operational two-user UAT, multi-process MySQL contention/soak, measured throughput/latency, backup/restore rehearsal, support ownership, final tax/rounding/service-charge policies, PII retention/erasure policy, provider/device selections, and all remaining Pilot/RC requirements in the status document. A green build or happy-path API test does not establish these outcomes.
