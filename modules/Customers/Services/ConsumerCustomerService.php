<?php

namespace Modules\Customers\Services;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\Admin;
use App\Support\SiteContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Customers\Contracts\CustomerConsumerProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Delegated consumer access is bounded by the configured binding AND native consumer authorization. */
class ConsumerCustomerService
{
    public function __construct(private readonly CustomerProfiles $profiles, private readonly CustomerCommandService $commands, private readonly ModuleCapabilityChecker $capabilities) {}

    public function provider(string $module): CustomerConsumerProvider
    {
        $class = config('customers.consumers.'.$module);
        abort_unless($class && class_exists($class), 422, 'App chưa đăng ký bộ tích hợp khách hàng.');
        $provider = app($class);
        abort_unless($provider instanceof CustomerConsumerProvider, 503);

        return $provider;
    }

    public function status(Admin $actor, string $module, string $scope, bool $lock = false): array
    {
        $live = Admin::query()->find($actor->id);
        abort_unless($live && $live->isAvailable() && ! $live->must_change_password && (int) $live->auth_version === (int) $actor->auth_version, 403);
        $website = app(SiteContext::class)->websiteKey();
        abort_unless($live->canAccessWebsite($website), 403);
        $provider = $this->provider($module);
        $provider->authorize($live, $website, $scope, 'status');
        $bound = $provider->bindingScope($website, $scope);
        if (! Schema::hasTable('cust_bindings')) {
            return ['mode' => 'legacy', 'available' => false, 'ready' => false, 'book_id' => null, 'binding_id' => null, 'reason' => 'Chưa cài app Quản lý khách hàng.'];
        }
        $q = DB::table('cust_bindings')->where('website_key', $website)->where('module_key', $module)->where('scope_type', $bound['type'])->where('scope_key', $bound['key']);
        if ($lock) {
            abort_unless(DB::transactionLevel() > 0, 500);
            $q->sharedLock();
        }
        $binding = $q->first();
        $book = $binding ? DB::table('cust_books')->where('id', $binding->book_id)->where('website_key', $website)->first() : null;
        $available = $this->capabilities->has('customers', 'customers.consumers.v1');
        $ready = $available && $book && $book->status === 'active';
        if ($lock && $binding?->mode === 'shared' && $ready) {
            app(CustomerRuntimeState::class)->guard(true);
        }

        return ['mode' => $binding?->mode ?? 'legacy', 'available' => $available, 'ready' => (bool) $ready, 'book_id' => $book?->public_id, 'binding_id' => $binding?->public_id, 'reason' => $ready ? null : 'App khách hàng hoặc nhóm dữ liệu chưa sẵn sàng.'];
    }

    private function authorize(Admin $actor, string $module, string $scope, string $ability, bool $lock = false): object
    {
        $status = $this->status($actor, $module, $scope, $lock);
        abort_unless($status['mode'] === 'shared' && $status['ready'], 503, $status['reason'] ?? 'Chưa chuyển sang app khách hàng dùng chung.');
        app(CustomerRuntimeState::class)->guard($lock);
        $binding = DB::table('cust_bindings')->where('public_id', $status['binding_id'])->first();
        abort_unless(in_array($ability, json_decode($binding->abilities, true), true), 403, 'Nhóm khách hàng chưa cấp khả năng này cho app.');
        $this->provider($module)->authorize($actor, app(SiteContext::class)->websiteKey(), $scope, $ability);

        return $binding;
    }

    public function assertAccess(Admin $actor, string $module, string $scope, string $ability): void
    {
        $this->authorize($actor, $module, $scope, $ability, DB::transactionLevel() > 0);
    }

    public function lookup(Admin $actor, string $module, string $scope, string $query, int $limit = 20): array
    {
        $b = $this->authorize($actor, $module, $scope, 'lookup');
        $result = $this->profiles->query($b->book_id, ['q' => $query, 'per_page' => min(50, max(1, $limit))], true);

        return array_map(fn ($r) => array_diff_key($r, ['tags' => true]), $this->profiles->resources($result['records'], false));
    }

    public function listing(Admin $actor, string $module, string $scope, array $filters = []): array
    {
        $b = $this->authorize($actor, $module, $scope, 'view');
        $r = $this->profiles->query($b->book_id, $filters);

        return ['items' => $this->profiles->resources($r['records'], true), 'meta' => ['total' => $r['total'], 'page' => $r['page'], 'per_page' => $r['per_page']]];
    }

    public function profile(Admin $actor, string $module, string $scope, string $id): array
    {
        $b = $this->authorize($actor, $module, $scope, 'view');
        $r = $this->profiles->resource($this->profiles->find($b->book_id, $id), true);
        $r['sources'] = array_values(array_filter($r['sources'], fn ($s) => $s['module_key'] === $module && $s['scope_type'] === $b->scope_type && $s['scope_key'] === $b->scope_key));

        return $r;
    }

    public function snapshot(Admin $actor, string $module, string $scope, string $id): array
    {
        $b = $this->authorize($actor, $module, $scope, 'attach', DB::transactionLevel() > 0);
        $p = $this->profiles->find($b->book_id, $id, DB::transactionLevel() > 0);
        abort_unless($p->status === 'active', 409, 'Khách hàng đã ngừng sử dụng.');
        $r = $this->profiles->resource($p, true, false);

        return array_intersect_key($r, array_flip(['public_id', 'id', 'code', 'type', 'name', 'phone', 'phone_normalized', 'email', 'version'])) + ['source' => 'customers', 'snapshot_at' => now()->toISOString()];
    }

    public function create(Admin $actor, string $module, string $scope, array $data, string $key): array
    {
        $r = $this->commands->run($actor, 'consumer.create', ['module' => $module, 'scope' => $scope, 'data' => $data], $key, fn ($a) => $this->authorize($a, $module, $scope, 'create', true), function ($a, $b) use ($data, $module) {
            // POS quick-create deliberately excludes consent and arbitrary extensions.
            $safe = array_intersect_key($data, array_flip(['type', 'name', 'code', 'phone', 'email', 'country_code']));
            $p = $this->profiles->save($b->book_id, $safe, null, $a->id, $module);

            return ['public_id' => $p->public_id];
        });
        $b = $this->authorize($actor, $module, $scope, 'create');

        return $this->profiles->resource($this->profiles->find($b->book_id, $r['public_id']), false, false);
    }

    public function update(Admin $actor, string $module, string $scope, string $id, array $data, string $key): array
    {
        $r = $this->commands->run($actor, 'consumer.update', ['module' => $module, 'scope' => $scope, 'id' => $id, 'data' => $data], $key, fn ($a) => $this->authorize($a, $module, $scope, 'update', true), function ($a, $b) use ($data, $id, $module) {
            $safe = array_intersect_key($data, array_flip(['type', 'name', 'code', 'phone', 'email', 'country_code', 'expected_version']));
            $p = $this->profiles->save($b->book_id, $safe, $id, $a->id, $module);
            if (array_key_exists('status', $data)) {
                abort_unless(in_array($data['status'], ['active', 'archived'], true), 422);
                DB::table('cust_profiles')->where('id', $p->id)->update(['status' => $data['status']]);
                $p->status = $data['status'];
                $this->profiles->event($p, 'customer.status.changed.v1', $a->id, ['status'], ['source' => $module]);
            }

            return ['public_id' => $p->public_id];
        });
        $b = $this->authorize($actor, $module, $scope, 'update');

        return $this->profiles->resource($this->profiles->find($b->book_id, $r['public_id']), $this->canView($actor, $module, $scope, $b), false);
    }

    public function resolveLegacy(Admin $actor, string $module, string $scope, string $legacyId): ?array
    {
        $b = $this->authorize($actor, $module, $scope, 'lookup');
        $source = DB::table('cust_sources')->where('book_id', $b->book_id)->where('module_key', $module)->where('scope_type', $b->scope_type)->where('scope_key', $b->scope_key)->where('source_id', $legacyId)->first();
        if (! $source) {
            return null;
        }$p = DB::table('cust_profiles')->find($source->profile_id);

        return $this->profiles->resource($this->profiles->find($b->book_id, $p->public_id), false, false);
    }

    public function legacyIds(Admin $actor, string $module, string $scope, string $id): array
    {
        $b = $this->authorize($actor, $module, $scope, 'history');
        $p = $this->profiles->find($b->book_id, $id);

        return DB::table('cust_sources')->where('book_id', $b->book_id)->where('profile_id', $p->id)->where('module_key', $module)->where('scope_type', $b->scope_type)->where('scope_key', $b->scope_key)->pluck('source_id')->map(fn ($v) => (string) $v)->all();
    }

    public function profileIds(Admin $actor, string $module, string $scope, string $id): array
    {
        $b = $this->authorize($actor, $module, $scope, 'history');
        $p = $this->profiles->find($b->book_id, $id);
        $ids = [$p->id];
        $public = [$p->public_id];
        for ($depth = 0; $depth < 100; $depth++) {
            $aliases = DB::table('cust_profiles')->where('book_id', $b->book_id)->whereIn('merged_into_id', $ids)->whereNotIn('id', $ids)->get(['id', 'public_id']);
            if ($aliases->isEmpty()) {
                return $public;
            }$ids = array_merge($ids, $aliases->pluck('id')->all());
            $public = array_merge($public, $aliases->pluck('public_id')->all());
        }
        abort(409, 'Chuỗi hợp nhất vượt giới hạn kiểm tra.');
    }

    private function canView(Admin $actor, string $module, string $scope, object $binding): bool
    {
        try {
            $this->authorize($actor, $module, $scope, 'view');

            return true;
        } catch (AuthorizationException|HttpException $e) {
            if ($e instanceof HttpException && $e->getStatusCode() !== 403) {
                throw $e;
            }

            return false;
        }
    }
}
