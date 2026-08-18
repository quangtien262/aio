<?php

namespace App\Http\Controllers\Admin\Api\AccountingTax;

use App\Models\AcctDocument;
use App\Support\AccountingTax\AccountingDocumentService;
use App\Support\AccountingTax\AccountingOrganizationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentController
{
    public function index(Request $request, AccountingOrganizationResolver $organizations): JsonResponse
    {
        $filters = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:acct_organizations,id'],
            'direction' => ['nullable', Rule::in(['inbound', 'outbound'])],
            'workflow_status' => ['nullable', Rule::in(['draft', 'approved', 'posted', 'voided'])],
            'legal_status' => ['nullable', 'string', 'max:30'],
            'payment_status' => ['nullable', Rule::in(['unpaid', 'partial', 'paid', 'overdue', 'refunded'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $organization = $organizations->resolve((int) $filters['organization_id']);
        $query = AcctDocument::query()
            ->with(['party', 'lines', 'payments'])
            ->where('organization_id', $organization->id)
            ->latest('document_date')
            ->latest('id');

        foreach (['direction', 'workflow_status', 'legal_status', 'payment_status'] as $filter) {
            if ($value = $filters[$filter] ?? null) {
                $query->where($filter, $value);
            }
        }

        $page = $query->paginate((int) ($filters['per_page'] ?? 30));
        $documents = collect($page->items())
            ->map(fn (AcctDocument $document): array => AccountingTaxSerializer::document($document))
            ->values()
            ->all();

        return response()->json(['data' => [
            'items' => $documents,
            'total' => $page->total(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
        ]]);
    }

    public function show(AcctDocument $document): JsonResponse
    {
        return response()->json([
            'data' => AccountingTaxSerializer::document($document->load(['party', 'lines', 'payments'])),
        ]);
    }

    public function store(
        Request $request,
        AccountingOrganizationResolver $organizations,
        AccountingDocumentService $documents,
    ): JsonResponse {
        $payload = $this->validated($request);
        $organization = $organizations->resolve($payload['organization_id'] ?? null);

        $document = $documents->create([
            ...collect($payload)->except('organization_id')->all(),
            'organization_id' => $organization->id,
        ], $request->user('admin')?->id);

        return response()->json(['data' => AccountingTaxSerializer::document($document->load(['party', 'lines', 'payments']))], 201);
    }

    public function update(
        Request $request,
        AcctDocument $document,
        AccountingDocumentService $documents,
    ): JsonResponse {
        $payload = $this->validated($request, false);
        $updated = $documents->updateDraft(
            $document,
            collect($payload)->except('version')->all(),
            $request->user('admin')?->id,
            isset($payload['version']) ? (int) $payload['version'] : null,
        );

        return response()->json(['data' => AccountingTaxSerializer::document($updated)]);
    }

    public function approve(AcctDocument $document, AccountingDocumentService $documents, Request $request): JsonResponse
    {
        $action = $this->validatedAction($request);
        $document = $documents->approve(
            $document,
            $request->user('admin')?->id,
            $action['version'] ?? null,
            $action['idempotency_key'] ?? null,
        );

        return response()->json(['data' => AccountingTaxSerializer::document($document)]);
    }

    public function post(AcctDocument $document, AccountingDocumentService $documents, Request $request): JsonResponse
    {
        $action = $this->validatedAction($request);
        $document = $documents->post(
            $document,
            $request->user('admin')?->id,
            $action['version'] ?? null,
            $action['idempotency_key'] ?? null,
        );

        return response()->json(['data' => AccountingTaxSerializer::document($document)]);
    }

    public function voidDocument(Request $request, AcctDocument $document, AccountingDocumentService $documents): JsonResponse
    {
        $payload = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'version' => ['sometimes', 'integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);
        $document = $documents->void(
            $document,
            $request->user('admin')?->id,
            $payload['reason'],
            $payload['version'] ?? null,
            $payload['idempotency_key'] ?? null,
        );

        return response()->json(['data' => AccountingTaxSerializer::document($document)]);
    }

    public function reverse(Request $request, AcctDocument $document, AccountingDocumentService $documents): JsonResponse
    {
        $payload = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'document_no' => ['nullable', 'string', 'max:120'],
            'document_date' => ['nullable', 'date'],
            'version' => ['sometimes', 'integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);
        $reversal = $documents->createReversal(
            $document,
            $request->user('admin')?->id,
            $payload['reason'],
            $payload['document_no'] ?? null,
            $payload['version'] ?? null,
            $payload['idempotency_key'] ?? null,
            $payload['document_date'] ?? null,
        );

        return response()->json(['data' => AccountingTaxSerializer::document($reversal)], 201);
    }

    public function payment(Request $request, AcctDocument $document, AccountingDocumentService $documents): JsonResponse
    {
        $payload = $request->validate([
            'kind' => ['sometimes', Rule::in(['payment', 'refund'])],
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
            'paid_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'version' => ['sometimes', 'integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);
        $payment = $documents->recordPayment(
            $document,
            collect($payload)->except('version')->all(),
            $request->user('admin')?->id,
            $payload['version'] ?? null,
        );

        return response()->json([
            'data' => [
                'payment' => AccountingTaxSerializer::payment($payment),
                'document' => AccountingTaxSerializer::document($document->fresh(['party', 'lines', 'payments'])),
            ],
        ], 201);
    }

    public function assessTax(Request $request, AcctDocument $document, AccountingDocumentService $documents): JsonResponse
    {
        $payload = $request->validate([
            'tax_eligibility' => ['required', Rule::in(['eligible', 'ineligible'])],
            'reason' => ['required', 'string', 'max:2000'],
            'version' => ['sometimes', 'integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);
        $document = $documents->assessTaxEligibility(
            $document,
            $payload['tax_eligibility'],
            $payload['reason'],
            $request->user('admin')?->id,
            $payload['version'] ?? null,
            $payload['idempotency_key'] ?? null,
        );

        return response()->json(['data' => AccountingTaxSerializer::document($document)]);
    }

    private function validated(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'organization_id' => [$creating ? 'sometimes' : 'prohibited', 'integer', 'exists:acct_organizations,id'],
            'party_id' => ['nullable', 'integer', 'exists:acct_parties,id'],
            'direction' => [$creating ? 'required' : 'sometimes', Rule::in(['inbound', 'outbound'])],
            'document_type' => ['sometimes', Rule::in(['internal_invoice', 'tax_invoice', 'credit_note', 'debit_note', 'receipt', 'expense'])],
            'original_document_id' => [$creating ? 'nullable' : 'prohibited', 'integer', 'exists:acct_documents,id'],
            'correction_type' => [$creating ? 'nullable' : 'prohibited', Rule::in(['adjustment', 'replacement'])],
            'effect_sign' => ['prohibited'],
            'document_no' => ['nullable', 'string', 'max:120'],
            'document_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'decimal:0,8', 'gt:0'],
            'tax_period' => ['nullable', 'date_format:Y-m'],
            'tax_eligibility' => ['prohibited'],
            'payment_status' => ['prohibited'],
            'website_key' => ['nullable', 'string', 'max:100'],
            'source_module' => ['nullable', 'string', 'max:50'],
            'source_type' => ['nullable', 'string', 'max:80'],
            'source_id' => ['nullable', 'string', 'max:80'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'version' => [$creating ? 'prohibited' : 'sometimes', 'integer', 'min:1'],
            'lines' => [$creating ? 'required' : 'sometimes', 'array', 'min:1'],
            'lines.*.accounting_item_id' => ['nullable', 'integer', 'exists:acct_items,id'],
            'lines.*.line_type' => ['sometimes', Rule::in(['item', 'discount', 'adjustment', 'note'])],
            'lines.*.item_kind' => ['nullable', Rule::in(['goods', 'service', 'charge', 'asset', 'bundle'])],
            'lines.*.name' => ['required_without:lines.*.accounting_item_id', 'string', 'max:255'],
            'lines.*.sku' => ['nullable', 'string', 'max:120'],
            'lines.*.unit' => ['nullable', 'string', 'max:40'],
            'lines.*.quantity' => ['sometimes', 'decimal:0,4', 'min:0'],
            'lines.*.unit_price' => ['sometimes', 'decimal:0,2', 'min:0'],
            'lines.*.discount_amount' => ['sometimes', 'decimal:0,2', 'min:0'],
            'lines.*.tax_category' => ['sometimes', Rule::in(AccountingDocumentService::TAX_CATEGORIES)],
            'lines.*.tax_rate' => ['nullable', 'decimal:0,2', 'min:0', 'max:100'],
            'lines.*.snapshot' => ['nullable', 'array'],
        ]);
    }

    private function validatedAction(Request $request): array
    {
        return $request->validate([
            'version' => ['sometimes', 'integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
