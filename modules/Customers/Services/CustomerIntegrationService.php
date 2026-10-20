<?php

namespace Modules\Customers\Services;

use App\Models\Admin;
use App\Support\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CustomerIntegrationService
{
    public function __construct(private readonly CustomerAccessService $access, private readonly CustomerProfiles $profiles, private readonly CustomerCommandService $commands, private readonly ConsumerCustomerService $consumers) {}

    public function bindings(Admin $actor, string $book): array
    {
        $a = $this->access->actor($actor);
        $b = $this->access->book($a, $book, 'customers.settings', 'manager');

        return DB::table('cust_bindings')->where('book_id', $b->id)->get()->map(function ($r) {
            $r->abilities = json_decode($r->abilities, true);
            unset($r->book_id);

            return (array) $r;
        })->all();
    }

    public function bind(Admin $actor, string $book, array $data, string $key): array
    {
        $v = validator($data, ['module_key' => 'required|string|max:60', 'scope_type' => 'required|in:website,chain', 'scope_key' => 'required|string|max:100', 'abilities' => 'required|array|max:6', 'abilities.*' => 'required|in:lookup,create,view,update,attach,history', 'expected_version' => 'sometimes|integer|min:1'])->validate();
        $r = $this->commands->run($actor, 'binding.save', ['book' => $book] + $v, $key, function ($a) use ($book, $v) {
            $b = $this->access->book($a, $book, 'customers.settings', 'manager', true);
            $this->consumerScope($a, $v['module_key'], $v['scope_type'], $v['scope_key'], 'configure');

            return $b;
        }, function ($a, $b) use ($v) {
            $scope = $this->consumerScope($a, $v['module_key'], $v['scope_type'], $v['scope_key'], 'configure');
            $bound = $this->consumers->provider($v['module_key'])->bindingScope($this->access->website(), $scope);
            abort_unless($bound['type'] === $v['scope_type'] && $bound['key'] === $v['scope_key'], 422, 'Phạm vi liên kết không khớp website hoặc chuỗi.');
            $query = DB::table('cust_bindings')->where('website_key', $this->access->website())->where('module_key', $v['module_key'])->where('scope_type', $v['scope_type'])->where('scope_key', $v['scope_key']);
            $old = $query->lockForUpdate()->first();
            if ($old) {
                abort_unless((int) $old->book_id === (int) $b->id, 409, 'Phạm vi đã liên kết nhóm khác; cần quy trình chuyển dữ liệu riêng.');
                abort_unless((int) ($v['expected_version'] ?? 0) === (int) $old->version, 409);
            }
            $row = ['abilities' => json_encode(array_values(array_unique($v['abilities'])), JSON_THROW_ON_ERROR), 'version' => $old ? $old->version + 1 : 1, 'updated_at' => now()];
            if ($old) {
                $query->update($row);
                $public = $old->public_id;
            } else {
                $public = (string) Str::uuid();
                DB::table('cust_bindings')->insert($row + ['public_id' => $public, 'website_key' => $this->access->website(), 'book_id' => $b->id, 'module_key' => $v['module_key'], 'scope_type' => $v['scope_type'], 'scope_key' => $v['scope_key'], 'mode' => 'legacy', 'created_at' => now()]);
            }
            app(AuditLogger::class)->record('customers.binding.saved', 'customer-binding', after: ['binding_id' => $public, 'abilities' => $v['abilities']], actor: $a, moduleKey: 'customers');

            return ['public_id' => $public];
        });

        return ['public_id' => $r['public_id'], 'items' => $this->bindings($actor, $book)];
    }

    public function migrate(Admin $actor, string $bindingId, array $data, string $key): array
    {
        $v = validator($data, ['commit' => 'sometimes|boolean', 'cutover' => 'sometimes|boolean'])->validate() + ['commit' => false, 'cutover' => false];
        abort_if($v['cutover'] && ! $v['commit'], 422, 'Chỉ chuyển nguồn khi đã xác nhận ghi dữ liệu.');
        if (! $v['commit']) {
            $a = $this->access->actor($actor);
            $binding = $this->binding($a, $bindingId);

            return $this->migrationReport($a, $binding, false, false);
        }

        return $this->commands->run($actor, 'binding.migrate', ['binding' => $bindingId] + $v, $key, fn ($a) => $this->binding($a, $bindingId, true), fn ($a, $b) => $this->migrationReport($a, $b, true, $v['cutover']));
    }

    private function binding(Admin $actor, string $id, bool $lock = false): object
    {
        $q = DB::table('cust_bindings')->where('public_id', $id)->where('website_key', $this->access->website());
        $r = ($lock ? $q->lockForUpdate() : $q)->first();
        abort_unless($r, 404);
        $book = DB::table('cust_books')->find($r->book_id);
        $this->access->book($actor, $book->public_id, 'customers.settings', 'manager', $lock);
        $this->consumerScope($actor, $r->module_key, $r->scope_type, $r->scope_key, 'configure');

        return $r;
    }

    private function migrationReport(Admin $actor, object $binding, bool $commit, bool $cutover): array
    {
        $scope = $this->consumerScope($actor, $binding->module_key, $binding->scope_type, $binding->scope_key, 'configure');
        $items = [];
        $errors = [];
        $warnings = [];
        $seen = [];
        $codes = [];
        $existing = 0;
        $count = 0;
        $hasMore = false;
        foreach ($this->consumers->provider($binding->module_key)->legacyProfiles($actor, $this->access->website(), $scope) as $row) {
            $count++;
            $source = (string) $row['source_id'];
            $mapped = DB::table('cust_sources')->where('website_key', $this->access->website())->where('module_key', $binding->module_key)->where('scope_type', $binding->scope_type)->where('scope_key', $binding->scope_key)->where('source_id', $source)->first();
            if ($mapped) {
                abort_unless((int) $mapped->book_id === (int) $binding->book_id, 409, 'Mapping thuộc nhóm khác.');
                $current = $this->profiles->validate($row['profile']);
                if ($binding->mode === 'legacy' && $mapped->source_hash && ! hash_equals($mapped->source_hash, hash('sha256', json_encode($current, JSON_THROW_ON_ERROR)))) {
                    $errors[] = ['source_id' => $source, 'errors' => ['source' => ['Dữ liệu nguồn đã đổi sau lần chuyển trước; cần đối chiếu lại.']]];
                }
                $existing++;

                continue;
            }
            if (count($items) >= 500) {
                $hasMore = true;
                break;
            }
            try {
                $profile = $this->profiles->validate($row['profile']);
                if (! empty($profile['code'])) {
                    if (isset($codes[$profile['code']]) || DB::table('cust_profiles')->where('book_id', $binding->book_id)->where('code', $profile['code'])->exists()) {
                        throw ValidationException::withMessages(['code' => 'Mã nguồn trùng; cần xử lý trước khi chuyển nguồn.']);
                    }$codes[$profile['code']] = true;
                }
                // A matching contact is reported for review, never used as an automatic identity link.
                foreach (['phone', 'email'] as $kind) {
                    if (! empty($profile[$kind])) {
                        $normalized = $this->profiles->normalize($kind, $profile[$kind]);
                        if (isset($seen[$kind.'|'.$normalized]) || DB::table('cust_contacts')->where('book_id', $binding->book_id)->where('kind', $kind)->where('normalized', $normalized)->exists()) {
                            $warnings[] = ['source_id' => $source, 'field' => $kind, 'action' => 'create_separate_profile'];
                        }$seen[$kind.'|'.$normalized] = true;
                    }
                }
                $items[] = ['source_id' => $source, 'profile' => $profile, 'status' => ($row['profile']['status'] ?? 'active') === 'archived' ? 'archived' : 'active'];
            } catch (ValidationException $e) {
                $errors[] = ['source_id' => $source, 'errors' => $e->errors()];
            } catch (HttpException $e) {
                if ($e->getStatusCode() !== 422) {
                    throw $e;
                }$errors[] = ['source_id' => $source, 'errors' => ['profile' => [$e->getMessage()]]];
            }
        }
        abort_if($commit && $errors !== [], 422, 'Nguồn dữ liệu có lỗi; chạy kiểm tra và xử lý xung đột trước.');
        $created = 0;
        if ($commit) {
            foreach ($items as $item) {
                $p = $this->profiles->save($binding->book_id, $item['profile'], null, $actor->id, $binding->module_key);
                if ($item['status'] === 'archived') {
                    DB::table('cust_profiles')->where('id', $p->id)->update(['status' => 'archived']);
                }
                DB::table('cust_sources')->insert(['website_key' => $this->access->website(), 'book_id' => $binding->book_id, 'profile_id' => $p->id, 'module_key' => $binding->module_key, 'scope_type' => $binding->scope_type, 'scope_key' => $binding->scope_key, 'source_id' => $item['source_id'], 'source_hash' => hash('sha256', json_encode($item['profile'], JSON_THROW_ON_ERROR)), 'created_at' => now(), 'updated_at' => now()]);
                $created++;
            }
            if ($cutover && ! $hasMore) {
                DB::table('cust_bindings')->where('id', $binding->id)->update(['mode' => 'shared', 'version' => $binding->version + 1, 'updated_at' => now()]);
            }
            app(AuditLogger::class)->record('customers.migration.completed', 'customer-binding', after: ['binding_id' => $binding->public_id, 'created_count' => $created, 'existing_count' => $existing, 'cutover' => $cutover], actor: $actor, moduleKey: 'customers');
        }

        return ['binding_id' => $binding->public_id, 'valid' => $errors === [], 'row_count' => $count, 'accepted_count' => count($items), 'existing_count' => $existing, 'created_count' => $created, 'errors' => $errors, 'warnings' => $warnings, 'has_more' => $hasMore, 'committed' => $commit, 'cutover' => $commit && $cutover && ! $hasMore];
    }

    public function consumerScope(Admin $actor, string $module, string $type, string $key, string $ability = 'view'): string
    {
        if ($module === 'fnb-pos' && $type === 'website') {
            abort_unless($key === $this->access->website(), 404);
            $candidates = DB::table('fnb_outlets')->where('website_key', $key)->where('status', 'active')->pluck('id')->map(fn ($v) => (string) $v);
        } elseif ($module === 'retail-pos' && $type === 'chain') {
            $chain = DB::table('retail_chains')->where('website_key', $this->access->website())->where('public_id', $key)->first();
            abort_unless($chain, 404);
            $candidates = DB::table('retail_branches')->where('chain_id', $chain->id)->where('status', 'active')->pluck('public_id');
        } else {
            abort(422, 'Loại phạm vi không được app hỗ trợ.');
        }
        foreach ($candidates as $scope) {
            try {
                $this->consumers->provider($module)->authorize($actor, $this->access->website(), $scope, $ability);

                return $scope;
            } catch (AuthorizationException $e) {
                continue;
            } catch (HttpException $e) {
                if (! in_array($e->getStatusCode(), [403, 404], true)) {
                    throw $e;
                }
            }
        }
        abort(403, 'Cần quyền khách hàng native tại ít nhất một chi nhánh trong phạm vi.');
    }
}
