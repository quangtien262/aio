<?php

namespace App\Http\Controllers\Admin\Api\AccountingTax;

use App\Models\AcctItem;
use App\Support\AccountingTax\AccountingDocumentService;
use App\Support\AccountingTax\AccountingItemSyncService;
use App\Support\AccountingTax\AccountingOrganizationResolver;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemController
{
    public function index(Request $request, AccountingOrganizationResolver $organizations): JsonResponse
    {
        $filters = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:acct_organizations,id'],
            'kind' => ['nullable', Rule::in(['goods', 'service', 'charge', 'asset', 'bundle'])],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $organization = $organizations->resolve((int) $filters['organization_id']);
        $query = AcctItem::query()->with('sources')->where('organization_id', $organization->id)->orderBy('name');

        if ($kind = $filters['kind'] ?? null) {
            $query->where('kind', $kind);
        }

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $page = $query->paginate((int) ($filters['per_page'] ?? 30));
        $items = collect($page->items())
            ->map(fn (AcctItem $item): array => AccountingTaxSerializer::item($item))
            ->values()
            ->all();

        return response()->json(['data' => [
            'items' => $items,
            'total' => $page->total(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
        ]]);
    }

    public function store(Request $request, AccountingOrganizationResolver $organizations, AuditLogger $audit): JsonResponse
    {
        $payload = $this->validated($request);
        $organization = $organizations->resolve($payload['organization_id'] ?? null);

        /** @var AcctItem $item */
        $item = AcctItem::query()->create([
            ...$payload,
            'organization_id' => $organization->id,
        ]);
        $audit->record('accounting.item.created', $item, null, $item->toArray(), moduleKey: 'accounting-tax');

        return response()->json(['data' => AccountingTaxSerializer::item($item->load('sources'))], 201);
    }

    public function update(Request $request, AcctItem $item, AuditLogger $audit): JsonResponse
    {
        $payload = $this->validated($request, false);
        $before = $item->toArray();
        $item->update(collect($payload)->except('organization_id')->all());
        $audit->record('accounting.item.updated', $item, $before, $item->fresh()->toArray(), moduleKey: 'accounting-tax');

        return response()->json(['data' => AccountingTaxSerializer::item($item->fresh('sources'))]);
    }

    public function sync(Request $request, AccountingOrganizationResolver $organizations, AccountingItemSyncService $sync): JsonResponse
    {
        $organization = $organizations->resolve($request->integer('organization_id') ?: null);

        return response()->json([
            'data' => [
                'organization' => AccountingTaxSerializer::organization($organization),
                'synced' => $sync->syncEnabledSources($organization->id),
            ],
        ]);
    }

    private function validated(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'organization_id' => [$creating ? 'sometimes' : 'prohibited', 'integer', 'exists:acct_organizations,id'],
            'kind' => [$creating ? 'required' : 'sometimes', Rule::in(['goods', 'service', 'charge', 'asset', 'bundle'])],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:120'],
            'unit' => [$creating ? 'required' : 'sometimes', 'string', 'max:40'],
            'default_price' => ['sometimes', 'decimal:0,2', 'min:0'],
            'tax_rate' => ['nullable', 'decimal:0,2', 'min:0', 'max:100'],
            'tax_category' => ['sometimes', Rule::in(AccountingDocumentService::TAX_CATEGORIES)],
            'revenue_account' => ['nullable', 'string', 'max:50'],
            'expense_account' => ['nullable', 'string', 'max:50'],
            'is_stock_tracked' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'metadata' => ['nullable', 'array'],
        ]);
    }
}
