<?php

namespace Modules\Customers\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;

class CustomerCommandService
{
    /** The journal contains references only. Authorization is repeated before replay. */
    public function run(Admin $actor, string $operation, array $payload, string $key, callable $authorize, callable $mutate): array
    {
        abort_unless(preg_match('/^[A-Za-z0-9:_.-]{8,100}$/D', $key), 422, 'Cần Idempotency-Key hợp lệ (8–100 ký tự).');

        return DB::transaction(function () use ($actor, $operation, $payload, $key, $authorize, $mutate): array {
            $access = app(CustomerAccessService::class);
            $live = $access->actor($actor, true);
            $scope = $authorize($live);
            $fingerprint = hash('sha256', $operation.'|'.$this->canonical($payload));
            $old = DB::table('cust_commands')->where('website_key', $access->website())->where('actor_id', $actor->id)->where('command_key', $key)->first();
            if ($old) {
                abort_unless(hash_equals($old->fingerprint, $fingerprint), 409, 'Idempotency-Key đã dùng cho nội dung khác.');

                return json_decode($old->result_ref, true, flags: JSON_THROW_ON_ERROR) + ['replayed' => true];
            }
            $result = $mutate($live, $scope);
            $ref = array_intersect_key($result, array_flip(['public_id', 'id', 'book_id', 'profile_id', 'binding_id', 'status', 'version', 'created_count', 'row_count', 'committed', 'cutover', 'valid', 'accepted_count', 'linked', 'target_id']));
            $authorization = $this->authorization($operation, $payload, $scope, $result);
            DB::table('cust_commands')->insert(['website_key' => $access->website(), 'actor_id' => $actor->id, 'command_key' => $key, 'fingerprint' => $fingerprint, 'operation' => $operation, 'authorization' => json_encode($authorization, JSON_THROW_ON_ERROR), 'result_ref' => json_encode($ref, JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return $result + ['replayed' => false];
        }, 3);
    }

    public function status(Admin $actor, string $key): array
    {
        $access = app(CustomerAccessService::class);
        $a = $access->actor($actor);
        $access->permission($a, 'customers.view');
        $row = DB::table('cust_commands')->where('website_key', $access->website())->where('actor_id', $a->id)->where('command_key', $key)->first();
        if (! $row) {
            return ['status' => 'unknown', 'resource' => null];
        }
        $auth = json_decode($row->authorization ?? '{}', true);
        if (isset($auth['module'])) {
            app(ConsumerCustomerService::class)->assertAccess($a, $auth['module'], $auth['scope'], $auth['ability']);
        } elseif (isset($auth['book'])) {
            $access->book($a, $auth['book'], $auth['permission'], $auth['role']);
        } else {
            $access->permission($a, 'customers.settings');
        }
        if (isset($auth['configure'])) {
            $c = $auth['configure'];
            app(CustomerIntegrationService::class)->consumerScope($a, $c['module_key'], $c['scope_type'], $c['scope_key'], 'configure');
        }

        return ['status' => 'committed', 'resource' => json_decode($row->result_ref, true)];
    }

    private function authorization(string $operation, array $payload, mixed $scope, array $result): array
    {
        if (isset($payload['module'],$payload['scope'])) {
            return ['module' => $payload['module'], 'scope' => $payload['scope'], 'ability' => $operation === 'consumer.create' ? 'create' : 'update'];
        }
        $permission = match ($operation) {
            'filter.save' => 'customers.view','profile.create' => 'customers.create','profile.update','profile.relationship' => 'customers.update','profile.archive' => 'customers.archive','profile.consent' => 'customers.consent.manage','profile.merge' => 'customers.merge','profiles.import' => 'customers.import','export.create' => 'customers.export','account.invite' => 'customers.account.link',default => 'customers.settings'
        };
        $role = $permission === 'customers.view' ? 'reader' : (in_array($permission, ['customers.settings', 'customers.merge', 'customers.account.link'], true) ? 'manager' : 'editor');
        $book = $payload['book'] ?? null;
        if (! $book && is_object($scope) && isset($scope->book_id)) {
            $book = DB::table('cust_books')->where('id', $scope->book_id)->value('public_id');
        }
        if (! $book && $operation === 'book.create') {
            $book = $result['public_id'] ?? null;
        }
        $auth = $book ? ['book' => $book, 'permission' => $permission, 'role' => $role] : ['permission' => $permission];
        if ($operation === 'binding.save') {
            $auth['configure'] = array_intersect_key($payload, array_flip(['module_key', 'scope_type', 'scope_key']));
        } elseif ($operation === 'binding.migrate' && is_object($scope)) {
            $auth['configure'] = ['module_key' => $scope->module_key, 'scope_type' => $scope->scope_type, 'scope_key' => $scope->scope_key];
        }

        return $auth;
    }

    private function canonical(mixed $value): string
    {
        $sort = function ($value) use (&$sort) {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($sort, $value);
        };

        return json_encode($sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
