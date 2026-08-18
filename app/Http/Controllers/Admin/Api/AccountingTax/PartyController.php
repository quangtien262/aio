<?php

namespace App\Http\Controllers\Admin\Api\AccountingTax;

use App\Models\AcctParty;
use App\Support\AccountingTax\AccountingOrganizationResolver;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartyController
{
    public function index(Request $request, AccountingOrganizationResolver $organizations): JsonResponse
    {
        $filters = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:acct_organizations,id'],
            'type' => ['nullable', Rule::in(['customer', 'supplier', 'both'])],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $organization = $organizations->resolve((int) $filters['organization_id']);
        $query = AcctParty::query()
            ->where('organization_id', $organization->id)
            ->orderBy('name');

        if ($type = $filters['type'] ?? null) {
            $query->where('type', $type);
        }

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('tax_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $page = $query->paginate((int) ($filters['per_page'] ?? 30));
        $items = collect($page->items())
            ->map(fn (AcctParty $party): array => AccountingTaxSerializer::party($party))
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

    public function store(
        Request $request,
        AccountingOrganizationResolver $organizations,
        AuditLogger $audit,
    ): JsonResponse {
        $payload = $this->validated($request);
        $organization = $organizations->resolve($payload['organization_id'] ?? null);
        /** @var AcctParty $party */
        $party = AcctParty::query()->create([
            ...collect($payload)->except('organization_id')->all(),
            'organization_id' => $organization->id,
        ]);
        $audit->record('accounting.party.created', $party, null, $party->toArray(), moduleKey: 'accounting-tax');

        return response()->json(['data' => AccountingTaxSerializer::party($party)], 201);
    }

    public function update(Request $request, AcctParty $party, AuditLogger $audit): JsonResponse
    {
        $payload = $this->validated($request, false);
        $before = $party->toArray();
        $party->update($payload);
        $audit->record('accounting.party.updated', $party, $before, $party->fresh()->toArray(), moduleKey: 'accounting-tax');

        return response()->json(['data' => AccountingTaxSerializer::party($party->fresh())]);
    }

    private function validated(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'organization_id' => [$creating ? 'sometimes' : 'prohibited', 'integer', 'exists:acct_organizations,id'],
            'type' => [$creating ? 'required' : 'sometimes', Rule::in(['customer', 'supplier', 'both'])],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'tax_code' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);
    }
}
