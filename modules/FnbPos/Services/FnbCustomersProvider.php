<?php

namespace Modules\FnbPos\Services;

use App\Models\Admin;
use App\Support\SiteContext;
use Illuminate\Support\Facades\DB;
use Modules\Customers\Contracts\CustomerConsumerProvider;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;

final class FnbCustomersProvider implements CustomerConsumerProvider
{
    public function authorize(Admin $actor, string $websiteKey, string $scopeKey, string $ability): void
    {
        abort_unless(ctype_digit($scopeKey) && (int) $scopeKey > 0 && app(SiteContext::class)->websiteKey() === $websiteKey, 404);
        $live = Admin::query()->findOrFail($actor->id);
        abort_unless((int) $live->auth_version === (int) $actor->auth_version, 403);
        if ($ability === 'configure') {
            app(FnbOutletAccessService::class)->authorizeHq($live, $websiteKey, 'fnb.chain.manage');
            app(FnbOutletAccessService::class)->authorize($live, $websiteKey, (int) $scopeKey, 'fnb.outlet.view');

            return;
        }
        $permission = match ($ability) {
            'status' => 'fnb.outlet.view',
            'lookup' => 'fnb.customer.lookup',
            'create' => 'fnb.customer.create',
            'attach', 'snapshot' => 'fnb.customer.attach',
            'view', 'history', 'listing', 'migrate' => 'fnb.customer.view',
            'update' => 'fnb.customer.update',
            default => abort(403),
        };
        app(FnbOutletAccessService::class)->authorize($live, $websiteKey, (int) $scopeKey, $permission);
    }

    public function bindingScope(string $websiteKey, string $scopeKey): array
    {
        return ['type' => 'website', 'key' => $websiteKey];
    }

    public function legacyProfiles(Admin $actor, string $websiteKey, string $scopeKey): iterable
    {
        $this->authorize($actor, $websiteKey, $scopeKey, 'configure');
        $this->authorize($actor, $websiteKey, $scopeKey, 'migrate');
        foreach (DB::table('fnb_customer_profiles')->where('website_key', $websiteKey)->orderBy('id')->cursor() as $row) {
            yield ['source_id' => (string) $row->id, 'profile' => ['name' => $row->name, 'type' => 'person', 'code' => $row->code, 'phone' => $row->phone_normalized, 'email' => $row->email, 'status' => $row->status]];
        }
    }

    public function activities(Admin $actor, string $websiteKey, string $scopeKey, string $profileUUID, int $limit = 20): array
    {
        $this->authorize($actor, $websiteKey, $scopeKey, 'history');
        $result = app(FnbCustomerService::class)->purchaseHistory(new FnbContext($websiteKey, (int) $scopeKey, (int) $actor->id), $profileUUID, min(100, max(1, $limit)));

        return array_map(fn (array $check): array => ['event_type' => 'fnb.check', 'occurred_at' => $check['closed_at'], 'source_module' => 'fnb-pos', 'resource_id' => $check['public_id'], 'amount_minor' => (string) ($check['settlement_total_minor'] ?? $check['grand_total_minor']), 'currency' => $check['currency'], 'status' => $check['status'], 'refunded_minor' => (string) $check['refunded_total_minor']], $result['resource']['items']);
    }
}
