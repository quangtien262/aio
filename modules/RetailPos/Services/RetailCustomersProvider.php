<?php

namespace Modules\RetailPos\Services;

use App\Models\Admin;
use App\Support\SiteContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Customers\Contracts\CustomerConsumerProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RetailCustomersProvider implements CustomerConsumerProvider
{
    public function authorize(Admin $actor, string $websiteKey, string $scopeKey, string $ability): void
    {
        abort_unless($websiteKey === app(SiteContext::class)->websiteKey(), 404);
        $permission = match ($ability) {
            'status' => $actor->hasPermission('retail.view') ? 'retail.view' : 'retail.sale.create',
            'configure' => 'retail.setup.manage',
            'lookup', 'view', 'history' => 'retail.customer.view',
            'create', 'update' => 'retail.customer.manage',
            'attach' => 'retail.sale.create',
            default => abort(403),
        };
        $live = Admin::query()->findOrFail($actor->id);
        abort_unless($live->isAvailable() && ! $live->must_change_password && (int) $live->auth_version === (int) $actor->auth_version, 403);
        $branch = app(RetailService::class)->branch($live, $scopeKey, $permission);
        if ($ability === 'configure') {
            abort_unless($live->canAccess('retail.setup.manage', 'global'), 403);
            abort_unless($live->isSuperAdmin() || DB::table('retail_memberships')->where('admin_id', $live->id)->where('chain_id', $branch->chain_id)->where('branch_id', 0)->where('preset', 'hq')->where('is_active', true)->exists(), 403);
        }
        if ($ability === 'history') {
            abort_unless($live->hasPermission('retail.sale.view'), 403);
        }
        if ($ability === 'attach') {
            abort_unless($live->hasPermission('retail.customer.view'), 403);
        }
    }

    public function bindingScope(string $websiteKey, string $scopeKey): array
    {
        $branch = DB::table('retail_branches as b')->join('retail_chains as c', 'c.id', '=', 'b.chain_id')->where('b.public_id', $scopeKey)->where('c.website_key', $websiteKey)->where('b.status', 'active')->first(['c.public_id']);
        abort_unless($branch, 404);

        return ['type' => 'chain', 'key' => $branch->public_id];
    }

    public function legacyProfiles(Admin $actor, string $websiteKey, string $scopeKey): iterable
    {
        $this->authorize($actor, $websiteKey, $scopeKey, 'view');
        $branch = app(RetailService::class)->branch($actor, $scopeKey, 'retail.customer.view');
        foreach (DB::table('retail_customers')->where('chain_id', $branch->chain_id)->orderBy('id')->cursor() as $profile) {
            yield ['source_id' => $profile->public_id, 'profile' => ['type' => 'person', 'name' => $profile->name, 'phone' => $profile->phone, 'email' => $profile->email, 'code' => null, 'status' => $profile->status]];
        }
    }

    public function activities(Admin $actor, string $websiteKey, string $scopeKey, string $profileId, int $limit = 20): array
    {
        abort_unless($websiteKey === app(SiteContext::class)->websiteKey(), 404);
        // Bindings are chain-owned; interactive callers supply a branch. Both expand only
        // into branches whose native history permissions/memberships still authorize.
        $branches = DB::table('retail_branches as b')->join('retail_chains as c', 'c.id', '=', 'b.chain_id')->where('c.website_key', $websiteKey)->where('b.status', 'active')->where(fn ($q) => $q->where('b.public_id', $scopeKey)->orWhere('c.public_id', $scopeKey))->get(['b.public_id']);
        $items = [];
        foreach ($branches as $branch) {
            try {
                $this->authorize($actor, $websiteKey, $branch->public_id, 'history');
                $history = app(RetailService::class)->customerHistory($actor, $branch->public_id, $profileId);
            } catch (HttpException $e) {
                if (in_array($e->getStatusCode(), [403, 404], true)) {
                    continue;
                }
                throw $e;
            }
            foreach ($history['items'] as $sale) {
                $items[] = ['event_type' => 'retail.sale', 'occurred_at' => CarbonImmutable::parse($sale['completed_at'])->utc()->toIso8601String(), 'source_module' => 'retail-pos', 'resource_id' => $sale['id'], 'branch_id' => $branch->public_id, 'amount_minor' => (string) $sale['total_minor'], 'currency' => 'VND', 'status' => $sale['status']];
            }
        }
        usort($items, fn ($a, $b) => strcmp($b['occurred_at'], $a['occurred_at']));

        return array_slice($items, 0, min(100, max(1, $limit)));
    }
}
