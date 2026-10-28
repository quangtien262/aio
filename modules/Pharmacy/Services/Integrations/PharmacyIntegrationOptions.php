<?php

namespace Modules\Pharmacy\Services\Integrations;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\Admin;
use App\Support\SiteContext;
use Illuminate\Support\Facades\DB;
use Modules\Customers\Services\CustomerAccessService;
use Modules\Customers\Services\CustomerRuntimeState;

/** Read options preserve the same native authority as the mutation adapters. */
final class PharmacyIntegrationOptions
{
    public function read(Admin $actor, string $kind, array $filters): array
    {
        if ($kind === 'customer-books') {
            if (! app(ModuleCapabilityChecker::class)->has('customers', 'customers.profiles.manage.v1')) {
                return ['records' => [], 'available' => false];
            }
            app(CustomerRuntimeState::class)->guard(false);
            $context = app(SiteContext::class);
            $site = $context->site();
            $website = $context->websiteKey();
            $rows = [];
            $query = DB::table('cust_books')->where('status', 'active');
            if (! empty($filters['q'])) {
                $query->where('name', 'like', '%'.$filters['q'].'%');
            }
            try {
                foreach ($query->orderBy('name')->limit(100)->get() as $book) {
                    $context->set(null, $book->website_key);
                    if ($actor->canAccessWebsite($book->website_key) && app(CustomerAccessService::class)->allowed($actor, $book, 'customers.settings', 'manager')) {
                        $rows[] = ['id' => $book->public_id, 'name' => $book->name, 'website_key' => $book->website_key];
                    }
                }
            } finally {
                $context->set($site, $website);
            }

            return ['records' => $rows, 'available' => true, 'meta' => ['limit' => 100]];
        }
        if (! app(ModuleCapabilityChecker::class)->has('accounting-tax', 'accounting.documents.manage.v1')) {
            return ['records' => [], 'available' => false];
        }
        $query = DB::table('acct_organizations')->where('status', 'active')->where('default_currency', 'VND');
        if ($kind === 'accounting-items') {
            validator($filters, ['organization_id' => 'required|integer|min:1'])->validate();
            $query->where('id', $filters['organization_id']);
        }
        $organizations = $query->orderBy('name')->limit(100)->get()->filter(fn ($org) => $actor->canAccess('accounting.document.create', 'organization', (string) $org->id) && $actor->canAccess('accounting.integration.sync', 'organization', (string) $org->id));
        if ($kind === 'accounting-organizations') {
            return ['records' => $organizations->map(fn ($org) => ['id' => $org->id, 'name' => $org->name])->values()->all(), 'available' => true];
        }
        abort_unless($kind === 'accounting-items', 404);
        abort_unless($organizations->isNotEmpty(), 403, 'Không có quyền kế toán của pháp nhân.');
        $items = DB::table('acct_items')->where('organization_id', (int) $filters['organization_id'])->where('status', 'active');
        if (! empty($filters['q'])) {
            $items->where(fn ($q) => $q->where('name', 'like', '%'.$filters['q'].'%')->orWhere('sku', 'like', '%'.$filters['q'].'%'));
        }

        return ['records' => $items->orderBy('name')->limit(100)->get(['id', 'name', 'sku', 'unit'])->toArray(), 'available' => true];
    }
}
