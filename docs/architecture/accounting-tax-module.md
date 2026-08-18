# AccountingTax Module

## Status

`accounting-tax` 0.2.0 is the internal accounting and tax subledger. `minvoice-connector`
0.2.0 is an optional provider adapter for Minvoice outbound invoices and mSMI inbound
invoice mirrors.

The internal workflow is usable without CMS, Catalog, Inventory or Minvoice. Real provider
traffic is disabled by default and is not production-approved merely because the connector is
installed or enabled.

`docs/API_HOA_DON_DIEN_TU.md` is legacy research input. It is not an authoritative API or
legal contract.

The versioned internal admin contract is `docs/api/accounting-tax-v1.openapi.json`. Sanitized
provider examples used by tests live under `tests/Fixtures/minvoice`; they contain no credentials
or production identifiers and do not authorize provider endpoints.

## Boundaries And Ownership

- `acct_organizations` is the legal/accounting partition. A website is a sales channel, not a
  legal-entity boundary, and can have only one effective organization mapping.
- Accounting owns parties, accounting/tax item fields, document numbering, approval and posting,
  tax eligibility, immutable document snapshots, periods, exports and email-delivery history.
- Catalog owns product identity, marketing content and suggested selling price.
- CMS owns service content. Products exposed from CMS already use the Catalog product table.
- Inventory owns warehouses, on-hand quantity, cost, batch and serial state.
- Provider mirrors and transmissions are separate from internal documents. Provider data is never
  treated as deductible input VAT until it has been reviewed, mapped and posted internally.

There are no foreign keys from AccountingTax to optional-module tables. Source relationships use
stable mapping records scoped by organization, and document lines retain immutable snapshots.

## Optional Capabilities

Optional integrations are resolved by `App\Support\AccountingTax\ModuleCapabilityService`. A
table remaining after module disable/uninstall is not evidence that the integration is available.

Current capability keys are:

- `catalog.items.read.v1`
- `cms.services.read.v1`
- `inventory.stock.read.v1`
- `inventory.documents.write.v1`
- `accounting.documents.manage.v1`
- `accounting.tax.reports.v1`
- `einvoice.minvoice.outbound.v1`
- `einvoice.minvoice.inbound.v1`

For provider connectors the runtime reports separate `installed`, `enabled`, `configured`,
`healthy`, `production_allowed` and `ready` states. Callers must check readiness for the selected
organization, channel and environment, not just the manifest capability.

## Internal Document Workflow

Supported master item kinds are `goods`, `service`, `charge`, `asset` and `bundle`. Line semantics
are separate: `item`, `discount`, `adjustment` and `note`.

The normal state path is:

`draft -> approved -> posted`

- Creating and updating a draft validates that parties, items and website mapping belong to the
  same organization.
- Approval requires complete legal headers and lines, captures seller/buyer and line snapshots,
  allocates an organization/year/type document number, and enforces maker-checker separation.
- Posting is allowed only from `approved`, uses an optimistic version plus row lock, and is blocked
  in locked or filed periods.
- Draft/approved documents may be voided with a reason. Posted documents must use a reversal or a
  provider-approved adjustment/replacement workflow.
- Credit notes and reversals carry negative economic effect; debit notes carry positive effect and
  reference an original posted document in the same organization.
- Payments/refunds are append-only, idempotent records and update payment state without changing
  the issued snapshot.
- The order adapter creates an idempotent internal draft. Lines without an accounting tax mapping
  are marked for review and cannot be approved until an accountant classifies them explicitly.

All monetary and VAT calculations use decimal arithmetic with explicit rounding. VAT categories
are `standard`, `zero_rated`, `not_subject`, `not_declared` and `exempt`; a numeric rate alone is
not sufficient to express tax meaning.

## Reports, Periods And Retention

The report API has two explicit modes:

- `operational`: a management estimate from posted documents. It must not be used as a tax filing.
- `tax`: includes legally valid posted output tax invoices/credit notes/debit notes, plus posted
  input documents that were explicitly assessed as eligible. Internal invoices, unassessed input
  invoices and invalid/rejected provider states are excluded.

Period locking is blocked while an input remains unassessed, a previously eligible input has an
invalid legal status, or an output tax invoice is not yet legally verified. A report becomes
filing-ready only from the immutable checksum snapshot of an authorized locked/filed period.

Reports use base-currency totals, exchange rates and correction signs. Exports run asynchronously
and support CSV, XLSX and PDF. Generated files are immutable, checksum-protected, stored on the
private accounting disk and removed after the configured operational retention window. Legal
provider PDF/XML artifacts are retained separately and are not pruned as report exports.

Document email delivery is queued after the database transaction commits. Each delivery has an
immutable payload/artifact snapshot plus append-only attempts, status, provider message ID and
retry history.

## Minvoice Outbound

Connections are encrypted and scoped by organization, channel and environment. URLs must be HTTPS
and match the configured Minvoice host allowlist. Network access and production access are governed
by separate global kill switches.

Production enablement requires all of the following:

1. the connector module and global network gate are enabled;
2. a configured and recently healthy sandbox sibling for the same organization/channel;
3. the exact reviewed contract version and an explicit confirmation phrase;
4. the global production gate and connection-level production approval;
5. a valid posted internal document, series and tax-point metadata.

The reviewed contract version is blank by default and must be set explicitly after contract/UAT
acceptance. The standard issue path accepts only `tax_invoice`; internal invoices and advanced
credit/debit correction documents cannot enter it accidentally.

Outbound work uses an idempotent transmission outbox. A worker performs create-draft, create/sign,
sign/send, status and artifact operations with bounded retries. PDF/XML responses are validated,
written atomically to private storage and checksum-verified on download. Adjustment, replacement
and cancellation endpoints remain blocked previews until their current provider contracts and
signing semantics have been accepted; the implementation does not guess legal endpoints.

## mSMI Inbound

Inbound sync is read-only at the provider boundary and upserts by connection plus provider invoice
ID. It stores headers, lines, VAT breakdown, warnings, provider timestamps and validated raw
artifacts. Sync can be scheduled or requested manually and is safe to retry.

An inbound mirror may create an idempotent internal draft with `tax_eligibility=not_assessed`, or be
matched/unmatched during review. It is never included as deductible VAT merely because mSMI
returned it.

## Inventory Bridge

Inventory actions are available only when the Inventory module is installed, enabled and provides
the required capability. Warehouses are explicitly mapped to one accounting organization, with at
most one default warehouse per organization; a stock proposal cannot be posted after its warehouse
mapping is removed or reassigned.

An invoice first creates an idempotent proposed receipt/issue snapshot; posting the stock document
requires an explicit action. The bridge does not perform blind bidirectional synchronization. When
Inventory is enabled and its read capability/schema are available, storefront availability is read
from Inventory and limited to warehouses mapped to the website's organization. Catalog stock is a
legacy fallback only while Inventory is disabled or incomplete. Missing/ambiguous organization
mapping fails closed instead of exposing stock from another organization.

Checkout reloads price and availability immediately before creating an order, then writes the order
header and lines atomically. This does not yet provide a hard stock reservation across concurrent
checkout and warehouse transactions; deployments requiring zero oversell must add a reservation
workflow before accepting high-concurrency sales.

## Authorization And Audit

Accounting and Minvoice permissions may be assigned globally or to an organization. Every
organization-scoped route resolves the organization from its request or route model and rejects
cross-organization access. Organization-only admins cannot use their assignment on unrelated
website or platform APIs.

Sensitive and critical permissions are classified by module lifecycle hooks. Critical transitions
remain server-side; clients cannot self-grant tax eligibility or economic signs.

Domain transitions, provider configuration and transmissions write sanitized audit events. The
core audit log is append-only and hash-chained; `php artisan audit:verify-chain --json` verifies the
chain and is scheduled daily. Credentials, tokens, provider payloads and document binary content
are recursively masked.

## Operations

Required production processes:

- a queue worker for exports, email, outbound transmissions and inbound sync;
- Laravel scheduler for outbound dispatch, daily mSMI sync, export pruning and audit verification;
- private-disk backup and restore procedures;
- monitoring for failed jobs, provider health, blocked transmissions and audit-chain failures.

Safe defaults in `.env.example` keep provider network and production calls disabled. Before any
real issue/send action, complete sandbox fixtures, signing acceptance, current Minvoice/mSMI
contract review, configure the reviewed contract version, credential provisioning and legal/UAT
sign-off for the relevant organization.
