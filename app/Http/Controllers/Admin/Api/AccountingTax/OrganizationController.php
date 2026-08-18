<?php

namespace App\Http\Controllers\Admin\Api\AccountingTax;

use App\Models\AcctOrganization;
use App\Models\AcctOrganizationWebsite;
use App\Models\Admin;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrganizationController
{
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);
        $organizationIds = $admin->organizationIdsForPermission('accounting.view');

        return response()->json([
            'data' => [
                'items' => AcctOrganization::query()
                    ->when($organizationIds !== null, fn ($query) => $query->whereIn('id', $organizationIds))
                    ->with('websites')
                    ->orderByDesc('is_default')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (AcctOrganization $organization): array => AccountingTaxSerializer::organization($organization))
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $payload = $this->validated($request);

        $organization = DB::transaction(function () use ($payload): AcctOrganization {
            $this->assertWebsiteKeysAvailable($payload['website_keys'] ?? []);

            if (($payload['is_default'] ?? false) === true) {
                AcctOrganization::query()->update(['is_default' => false, 'default_slot' => null]);
            }

            if (! AcctOrganization::query()->exists()) {
                $payload['is_default'] = true;
            }

            /** @var AcctOrganization $organization */
            $organization = AcctOrganization::query()->create(collect($payload)->except('website_keys')->all());

            foreach (array_values($payload['website_keys'] ?? []) as $index => $websiteKey) {
                AcctOrganizationWebsite::query()->create([
                    'organization_id' => $organization->id,
                    'website_key' => $websiteKey,
                    'is_primary' => $index === 0,
                ]);
            }

            return $organization->load('websites');
        });

        $audit->record('accounting.organization.created', $organization, null, $organization->toArray(), moduleKey: 'accounting-tax');

        return response()->json(['data' => AccountingTaxSerializer::organization($organization)], 201);
    }

    public function update(Request $request, AcctOrganization $organization, AuditLogger $audit): JsonResponse
    {
        $payload = $this->validated($request);

        $before = $organization->load('websites')->toArray();

        DB::transaction(function () use ($organization, $payload): void {
            $this->assertWebsiteKeysAvailable($payload['website_keys'] ?? [], $organization->id);

            if (($payload['is_default'] ?? false) === true) {
                AcctOrganization::query()->whereKeyNot($organization->id)->update([
                    'is_default' => false,
                    'default_slot' => null,
                ]);
            }

            if ($organization->is_default && array_key_exists('is_default', $payload) && $payload['is_default'] === false) {
                throw ValidationException::withMessages([
                    'is_default' => ['Hãy đặt một pháp nhân khác làm mặc định thay vì bỏ pháp nhân mặc định hiện tại.'],
                ]);
            }

            $organization->update(collect($payload)->except('website_keys')->all());

            if (array_key_exists('website_keys', $payload)) {
                AcctOrganizationWebsite::query()->where('organization_id', $organization->id)->delete();

                foreach (array_values($payload['website_keys'] ?? []) as $index => $websiteKey) {
                    AcctOrganizationWebsite::query()->create([
                        'organization_id' => $organization->id,
                        'website_key' => $websiteKey,
                        'is_primary' => $index === 0,
                    ]);
                }
            }
        });

        $organization = $organization->fresh('websites');
        $audit->record('accounting.organization.updated', $organization, $before, $organization->toArray(), moduleKey: 'accounting-tax');

        return response()->json(['data' => AccountingTaxSerializer::organization($organization)]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tax_code' => ['nullable', 'string', 'max:32'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'default_currency' => ['nullable', 'string', 'size:3'],
            'settings' => ['nullable', 'array'],
            'is_default' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'website_keys' => ['sometimes', 'array'],
            'website_keys.*' => ['string', 'max:100', 'distinct'],
        ]);
    }

    /** @param list<string> $websiteKeys */
    private function assertWebsiteKeysAvailable(array $websiteKeys, ?int $organizationId = null): void
    {
        $conflicts = AcctOrganizationWebsite::query()
            ->whereIn('website_key', $websiteKeys)
            ->when($organizationId !== null, fn ($query) => $query->where('organization_id', '!=', $organizationId))
            ->pluck('website_key')
            ->all();

        if ($conflicts !== []) {
            throw ValidationException::withMessages([
                'website_keys' => ['Website đã được ánh xạ với pháp nhân khác: '.implode(', ', $conflicts)],
            ]);
        }
    }
}
