<?php

namespace Modules\RetailPos\Services;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\AcctOrganization;
use App\Models\AcctParty;
use App\Models\AcctPartySource;
use App\Models\Admin;
use App\Support\AuditLogger;
use App\Support\SiteContext;
use Illuminate\Support\Facades\DB;
use Modules\Customers\Services\ConsumerCustomerService;

/** Explicit master mapping only. It never changes an issued document's snapshots. */
final class RetailCustomerAccountingAdapter
{
    public function sync(Admin $actor, string $branchId, string $profileId, int $organizationId, string $key): array
    {
        validator(['branch' => $branchId, 'profile' => $profileId, 'organization' => $organizationId, 'key' => $key], ['branch' => 'required|uuid', 'profile' => 'required|uuid', 'organization' => 'required|integer|min:1', 'key' => ['required', 'string', 'min:8', 'max:120', 'regex:/^[A-Za-z0-9_.:\-]+$/']])->validate();

        return DB::transaction(function () use ($actor, $branchId, $profileId, $organizationId, $key): array {
            $live = Admin::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($live->isAvailable() && ! $live->must_change_password && (int) $live->auth_version === (int) $actor->auth_version, 403);
            app(RetailRuntimeState::class)->guard();
            $branch = app(RetailService::class)->branch($live, $branchId, 'retail.customer.view', true);
            abort_unless($live->canAccess('accounting.party.manage', 'organization', (string) $organizationId) && $live->canAccess('accounting.integration.sync', 'organization', (string) $organizationId), 403);
            $installation = DB::table('module_installations')->where('key', 'accounting-tax')->lockForUpdate()->first();
            abort_unless($installation?->status === 'enabled', 503, 'Accounting chưa được bật.');
            abort_unless(app(ModuleCapabilityChecker::class)->has('accounting-tax', 'accounting.documents.manage.v1'), 503, 'Accounting chưa sẵn sàng.');
            $organization = AcctOrganization::query()->whereKey($organizationId)->where('status', 'active')->lockForUpdate()->first();
            abort_unless($organization, 422);
            abort_unless(DB::table('retail_legal_accounting_links')->where('legal_entity_id', $branch->legal_entity_id)->where('organization_id', $organizationId)->exists(), 422, 'Cần ánh xạ pháp nhân Retail với pháp nhân kế toán trước.');
            $customers = app(ConsumerCustomerService::class);
            $context = $customers->status($live, 'retail-pos', $branchId, true);
            abort_unless($context['mode'] === 'shared' && $context['ready'], 503, 'Customers dùng chung chưa sẵn sàng.');
            $customers->assertAccess($live, 'retail-pos', $branchId, 'view');
            $profile = $customers->profile($live, 'retail-pos', $branchId, $profileId);
            abort_unless(($profile['status'] ?? null) === 'active', 422);
            validator(['tax_code' => $profile['tax_code'] ?? null], ['tax_code' => 'nullable|string|max:32'])->validate();
            $fingerprint = hash('sha256', json_encode([app(SiteContext::class)->websiteKey(), $branchId, $profileId, $organizationId], JSON_THROW_ON_ERROR));
            $previous = DB::table('retail_customer_accounting_syncs')->where('actor_id', $live->id)->where('command_key', $key)->first();
            if ($previous) {
                abort_unless(hash_equals($previous->fingerprint, $fingerprint), 409);

                return json_decode($previous->response, true, flags: JSON_THROW_ON_ERROR);
            }
            $source = AcctPartySource::query()->where('organization_id', $organizationId)->where('source_module', 'customers')->where('source_type', 'profile')->where('source_id', $profileId)->lockForUpdate()->first();
            $created = false;
            if ($source) {
                $party = AcctParty::query()->whereKey($source->party_id)->where('organization_id', $organizationId)->firstOrFail();
            } else {
                $party = AcctParty::query()->create(['organization_id' => $organizationId, 'type' => 'customer', 'name' => $profile['name'], 'email' => $profile['email'] ?? null, 'phone' => $profile['phone_normalized'] ?? $profile['phone'] ?? null, 'tax_code' => $profile['tax_code'] ?? null, 'metadata' => ['source' => 'customers', 'customer_type' => $profile['type'] ?? 'person']]);
                AcctPartySource::query()->create(['organization_id' => $organizationId, 'party_id' => $party->id, 'source_module' => 'customers', 'source_type' => 'profile', 'source_id' => $profileId, 'source_key' => $profile['code'] ?? null, 'snapshot' => array_intersect_key($profile, array_flip(['id', 'public_id', 'code', 'type', 'name', 'phone', 'email', 'tax_code', 'version'])), 'synced_at' => now()]);
                $created = true;
            }
            // Existing Accounting master values belong to Accounting; explicit sync never clobbers them.
            $result = ['customer_id' => $profileId, 'organization_id' => $organizationId, 'party_id' => $party->id, 'created' => $created, 'mode' => 'explicit_source_link'];
            DB::table('retail_customer_accounting_syncs')->insert(['actor_id' => $live->id, 'command_key' => $key, 'fingerprint' => $fingerprint, 'response' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
            app(AuditLogger::class)->record('retail.customer.accounting_linked', 'customer-accounting-link', after: $result, actor: $live, moduleKey: 'retail-pos');

            return $result;
        }, 3);
    }
}
