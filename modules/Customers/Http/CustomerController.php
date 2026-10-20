<?php

namespace Modules\Customers\Http;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\Admin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Customers\Services\ConsumerCustomerService;
use Modules\Customers\Services\CustomerAccessService;
use Modules\Customers\Services\CustomerAccountLinkService;
use Modules\Customers\Services\CustomerCommandService;
use Modules\Customers\Services\CustomerExportService;
use Modules\Customers\Services\CustomerImportService;
use Modules\Customers\Services\CustomerIntegrationService;
use Modules\Customers\Services\CustomerSavedFilterService;
use Modules\Customers\Services\CustomerService;
use Modules\RetailPos\Services\RetailCustomerAccountingAdapter;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CustomerController
{
    public function __construct(private readonly CustomerService $service, private readonly CustomerIntegrationService $integrations) {}

    private function key(Request $r): string
    {
        return (string) $r->header('Idempotency-Key');
    }

    private function data(mixed $data): JsonResponse
    {
        return response()->json(['data' => $data])->header('Cache-Control', 'private, no-store');
    }

    public function books(Request $r)
    {
        return $this->data($this->service->books($r->user('admin')));
    }

    public function createBook(Request $r)
    {
        return $this->data($this->service->createBook($r->user('admin'), $r->all(), $this->key($r)));
    }

    public function listing(Request $r, string $book)
    {
        return $this->data($this->service->listing($r->user('admin'), $book, $r->query()));
    }

    public function show(Request $r, string $book, string $profile)
    {
        return $this->data($this->service->profile($r->user('admin'), $book, $profile));
    }

    public function save(Request $r, string $book, ?string $profile = null)
    {
        return $this->data($this->service->save($r->user('admin'), $book, $r->all(), $profile, $this->key($r)));
    }

    public function archive(Request $r, string $book, string $profile)
    {
        return $this->data($this->service->archive($r->user('admin'), $book, $profile, $r->all(), $this->key($r)));
    }

    public function timeline(Request $r, string $book, string $profile)
    {
        return $this->data($this->service->timeline($r->user('admin'), $book, $profile, $r->query()));
    }

    public function consents(Request $r, string $book, string $profile)
    {
        return $this->data($this->service->profile($r->user('admin'), $book, $profile)['consents']);
    }

    public function consent(Request $r, string $book, string $profile)
    {
        return $this->data($this->service->consent($r->user('admin'), $book, $profile, $r->all(), $this->key($r)));
    }

    public function relationship(Request $r, string $book, string $profile)
    {
        return $this->data($this->service->relationship($r->user('admin'), $book, $profile, $r->all(), $this->key($r)));
    }

    public function duplicates(Request $r, string $book)
    {
        return $this->data($this->service->duplicates($r->user('admin'), $book));
    }

    public function merge(Request $r, string $book, string $profile)
    {
        return $this->data($this->service->merge($r->user('admin'), $book, $profile, $r->all(), $this->key($r)));
    }

    public function fields(Request $r, string $book)
    {
        return $this->data($this->service->fields($r->user('admin'), $book));
    }

    public function saveField(Request $r, string $book)
    {
        return $this->data($this->service->saveField($r->user('admin'), $book, $r->all(), $this->key($r)));
    }

    public function grants(Request $r, string $book)
    {
        return $this->data($this->service->grants($r->user('admin'), $book));
    }

    public function saveGrant(Request $r, string $book)
    {
        return $this->data($this->service->saveGrant($r->user('admin'), $book, $r->all(), $this->key($r)));
    }

    public function bindings(Request $r, string $book)
    {
        return $this->data($this->integrations->bindings($r->user('admin'), $book));
    }

    public function bind(Request $r, string $book)
    {
        return $this->data($this->integrations->bind($r->user('admin'), $book, $r->all(), $this->key($r)));
    }

    public function migrate(Request $r, string $binding)
    {
        return $this->data($this->integrations->migrate($r->user('admin'), $binding, $r->all(), $this->key($r)));
    }

    public function import(Request $r, string $book)
    {
        return $this->data(app(CustomerImportService::class)->run($r->user('admin'), $book, $r->all(), $this->key($r)));
    }

    public function export(Request $r, string $book)
    {
        return $this->data(app(CustomerExportService::class)->create($r->user('admin'), $book, $this->key($r)));
    }

    public function exportStatus(Request $r, string $export)
    {
        return $this->data(app(CustomerExportService::class)->status($r->user('admin'), $export));
    }

    public function download(Request $r, string $export)
    {
        return app(CustomerExportService::class)->download($r->user('admin'), $export);
    }

    public function command(Request $r, string $key)
    {
        return $this->data(app(CustomerCommandService::class)->status($r->user('admin'), $key));
    }

    public function accounting(Request $r, string $book, string $profile)
    {
        $actor = $r->user('admin');
        $this->service->profile($actor, $book, $profile);
        $v = $r->validate(['branch_id' => 'required|uuid', 'organization_id' => 'required|integer|min:1']);
        $status = app(ConsumerCustomerService::class)->status($actor, 'retail-pos', $v['branch_id']);
        abort_unless($status['book_id'] === $book, 404);

        return $this->data(app(RetailCustomerAccountingAdapter::class)->sync($actor, $v['branch_id'], $profile, $v['organization_id'], $this->key($r)));
    }

    public function invite(Request $r, string $book, string $profile)
    {
        return $this->data(app(CustomerAccountLinkService::class)->invite($r->user('admin'), $book, $profile, $r->all(), $this->key($r)));
    }

    public function options(Request $r, string $book)
    {
        $access = app(CustomerAccessService::class);
        $a = $access->actor($r->user('admin'));
        $access->book($a, $book, 'customers.settings', 'manager');
        $scopes = [];
        foreach (['fnb-pos', 'retail-pos'] as $module) {
            if (! app(ModuleCapabilityChecker::class)->enabled($module)) {
                continue;
            }
            $candidates = $module === 'fnb-pos' ? [['scope_type' => 'website', 'scope_key' => $access->website(), 'label' => 'Quán cafe · '.$access->website()]] : DB::table('retail_chains')->where('website_key', $access->website())->get()->map(fn ($c) => ['scope_type' => 'chain', 'scope_key' => $c->public_id, 'label' => $c->name])->all();
            foreach ($candidates as $c) {
                try {
                    $this->integrations->consumerScope($a, $module, $c['scope_type'], $c['scope_key'], 'configure');
                    $scopes[] = $c + ['module_key' => $module];
                } catch (AuthorizationException $e) {
                    continue;
                } catch (HttpException $e) {
                    if (! in_array($e->getStatusCode(), [403, 404], true)) {
                        throw $e;
                    }
                }
            }
        }
        $admins = Admin::query()->where('is_active', true)->where('status', 'active')->limit(500)->get()->filter(fn ($candidate) => $candidate->canAccessWebsite($access->website()))->map(fn ($candidate) => ['id' => $candidate->id, 'name' => $candidate->name])->values()->all();

        return $this->data(['admins' => $admins, 'scopes' => $scopes]);
    }

    public function savedFilters(Request $r, string $book)
    {
        return $this->data(app(CustomerSavedFilterService::class)->listing($r->user('admin'), $book));
    }

    public function saveFilter(Request $r, string $book)
    {
        return $this->data(app(CustomerSavedFilterService::class)->save($r->user('admin'), $book, $r->all(), $this->key($r)));
    }

    public function deleteFilter(Request $r, string $book, string $filter)
    {
        return $this->data(app(CustomerSavedFilterService::class)->delete($r->user('admin'), $book, $filter));
    }
}
