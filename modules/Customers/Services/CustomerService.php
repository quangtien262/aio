<?php

namespace Modules\Customers\Services;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\Admin;
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CustomerService
{
    public function __construct(private readonly CustomerAccessService $access, private readonly CustomerProfiles $profiles, private readonly CustomerCommandService $commands) {}

    public function books(Admin $actor): array
    {
        $live = $this->access->actor($actor);
        $this->access->permission($live, 'customers.view');

        return DB::table('cust_books')->where('website_key', $this->access->website())->where('status', 'active')->orderBy('name')->get()
            ->filter(fn ($b) => $this->access->allowed($live, $b, 'customers.view'))->map(fn ($b) => $this->bookResource($live, $b))->values()->all();
    }

    public function createBook(Admin $actor, array $data, string $key): array
    {
        $v = validator($data, ['name' => 'required|string|max:255', 'code' => 'required|string|max:40|regex:/^[A-Za-z0-9._-]+$/D'])->validate();
        $result = $this->commands->run($actor, 'book.create', $v, $key, fn ($a) => $this->access->permission($a, 'customers.settings'), function ($a) use ($v) {
            abort_if(DB::table('cust_books')->where('website_key', $this->access->website())->where('code', $v['code'])->exists(), 422, 'Mã nhóm đã tồn tại.');
            $id = DB::table('cust_books')->insertGetId($v + ['public_id' => (string) Str::uuid(), 'website_key' => $this->access->website(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('cust_book_grants')->insert(['book_id' => $id, 'admin_id' => $a->id, 'role' => 'manager', 'created_at' => now(), 'updated_at' => now()]);

            return ['public_id' => DB::table('cust_books')->where('id', $id)->value('public_id')];
        });

        return $this->bookResource($actor, $this->access->book($this->access->actor($actor), $result['public_id']));
    }

    public function listing(Admin $actor, string $book, array $filters = []): array
    {
        $live = $this->access->actor($actor);
        $b = $this->access->book($live, $book);
        $result = $this->profiles->query($b->id, $filters);
        $sensitive = $this->access->allowed($live, $b, 'customers.sensitive');
        $items = $this->profiles->resources($result['records'], $sensitive);
        unset($result['records']);
        $stats = DB::table('cust_profiles')->where('book_id', $b->id)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($v) => (int) $v)->all();

        return $result + ['items' => $items, 'stats' => $stats, 'permissions' => $this->permissions($live, $b)];
    }

    public function profile(Admin $actor, string $book, string $id): array
    {
        $live = $this->access->actor($actor);
        $b = $this->access->book($live, $book);
        $sensitive = $this->access->allowed($live, $b, 'customers.sensitive');
        $p = $this->profiles->find($b->id, $id);
        if ($sensitive) {
            app(AuditLogger::class)->record('customers.profile.revealed', 'customer-profile', after: ['profile_id' => $p->public_id], actor: $live, moduleKey: 'customers');
        }
        $result = $this->profiles->resource($p, $sensitive);
        $result['sources'] = array_values(array_filter($result['sources'], function ($source) use ($live) {
            try {
                if (! app(ModuleCapabilityChecker::class)->enabled($source['module_key'])) {
                    return false;
                }app(CustomerIntegrationService::class)->consumerScope($live, $source['module_key'], $source['scope_type'], $source['scope_key']);

                return true;
            } catch (AuthorizationException $e) {
                return false;
            } catch (HttpException $e) {
                if (in_array($e->getStatusCode(), [403, 404, 503], true)) {
                    return false;
                }throw $e;
            }
        }));

        return $result + ['permissions' => $this->permissions($live, $b), 'book_id' => $b->public_id];
    }

    public function save(Admin $actor, string $book, array $data, ?string $id, string $key): array
    {
        $permission = $id ? 'customers.update' : 'customers.create';
        $result = $this->commands->run($actor, 'profile.'.($id ? 'update' : 'create'), ['book' => $book, 'profile' => $id, 'data' => $data], $key, function ($a) use ($book, $permission, $data) {
            $b = $this->access->book($a, $book, $permission, 'editor', true);
            $this->sensitiveWrite($a, $b, $data);

            return $b;
        }, function ($a, $b) use ($data, $id) {
            $p = $this->profiles->save($b->id, $data, $id, $a->id);

            return ['public_id' => $p->public_id, 'version' => $p->version];
        });

        return $this->profile($actor, $book, $result['public_id']);
    }

    public function archive(Admin $actor, string $book, string $id, array $data, string $key): array
    {
        $v = validator($data, ['expected_version' => 'required|integer|min:1', 'archived' => 'required|boolean'])->validate();
        $r = $this->commands->run($actor, 'profile.archive', ['book' => $book, 'id' => $id] + $v, $key, fn ($a) => $this->access->book($a, $book, 'customers.archive', 'editor', true), function ($a, $b) use ($id, $v) {
            $p = $this->profiles->find($b->id, $id, true, false);
            $this->version($p, $v['expected_version']);
            abort_if($p->status === 'merged', 409);
            DB::table('cust_profiles')->where('id', $p->id)->update(['status' => $v['archived'] ? 'archived' : 'active', 'version' => $p->version + 1, 'updated_at' => now()]);
            $p = $this->profiles->find($b->id, $id);
            $this->profiles->event($p, $v['archived'] ? 'customer.archived.v1' : 'customer.restored.v1', $a->id, ['status']);

            return ['public_id' => $id];
        });

        return $this->profile($actor, $book, $r['public_id']);
    }

    public function timeline(Admin $actor, string $book, string $id, array $query = []): array
    {
        $live = $this->access->actor($actor);
        $b = $this->access->book($live, $book);
        $p = $this->profiles->find($b->id, $id);
        $v = validator($query, ['before' => 'nullable|integer|min:1', 'limit' => 'nullable|integer|between:1,100'])->validate();
        $ids = [$p->id];
        for ($depth = 0; $depth < 100; $depth++) {
            $aliases = DB::table('cust_profiles')->where('book_id', $b->id)->whereIn('merged_into_id', $ids)->whereNotIn('id', $ids)->pluck('id')->all();
            if (! $aliases) {
                break;
            }$ids = array_merge($ids, $aliases);
        }
        $items = DB::table('cust_events')->where('book_id', $b->id)->whereIn('profile_id', $ids)->when($v['before'] ?? null, fn ($q, $before) => $q->where('id', '<', $before))->orderByDesc('id')->limit($v['limit'] ?? 50)->get()->map(function ($r) {
            $r->changed_fields = json_decode($r->changed_fields, true);
            $r->metadata = json_decode($r->metadata, true);
            unset($r->profile_id,$r->book_id);

            return (array) $r;
        })->all();
        $next = count($items) === ($v['limit'] ?? 50) ? end($items)['id'] : null;
        $unavailable = [];
        if (empty($v['before'])) {
            foreach (DB::table('cust_bindings')->where('book_id', $b->id)->where('mode', 'shared')->get() as $binding) {
                if (! in_array('history', json_decode($binding->abilities, true), true)) {
                    continue;
                }
                try {
                    if (! app(ModuleCapabilityChecker::class)->enabled($binding->module_key)) {
                        $unavailable[] = $binding->module_key;

                        continue;
                    }
                    $provider = app(ConsumerCustomerService::class)->provider($binding->module_key);
                    if (! method_exists($provider, 'activities')) {
                        continue;
                    }
                    $scopes = $binding->module_key === 'fnb-pos' ? DB::table('fnb_outlets')->where('website_key', $b->website_key)->where('status', 'active')->limit(100)->pluck('id')->map(fn ($s) => (string) $s)->all() : [$binding->scope_key];
                    foreach ($scopes as $scope) {
                        try {
                            $items = array_merge($items, $provider->activities($live, $b->website_key, $scope, $p->public_id, 20));
                        } catch (AuthorizationException $e) {
                            continue;
                        } catch (HttpException $e) {
                            if (! in_array($e->getStatusCode(), [403, 404], true)) {
                                throw $e;
                            }
                        }
                    }
                } catch (AuthorizationException $e) {
                    continue;
                } catch (HttpException $e) {
                    if (in_array($e->getStatusCode(), [403, 404], true)) {
                        continue;
                    }if ($e->getStatusCode() === 503) {
                        $unavailable[] = $binding->module_key;
                    } else {
                        throw $e;
                    }
                }
            }
        }
        usort($items, fn ($a, $z) => CarbonImmutable::parse($z['occurred_at'])->getTimestamp() <=> CarbonImmutable::parse($a['occurred_at'])->getTimestamp());

        return ['items' => $items, 'scope' => 'authorized-sources', 'as_of' => now()->toISOString(), 'unavailable_sources' => array_values(array_unique($unavailable)), 'next_cursor' => $next];
    }

    public function consent(Admin $actor, string $book, string $id, array $data, string $key): array
    {
        $v = validator($data, ['purpose' => 'required|string|max:80', 'channel' => 'required|in:email,sms,phone,all', 'brand' => 'required|string|max:100', 'state' => 'required|in:unknown,granted,denied,withdrawn', 'policy_version' => 'nullable|required_if:state,granted|string|max:80', 'evidence' => 'nullable|string|max:2000', 'expected_version' => 'required|integer|min:1'])->validate();
        $this->commands->run($actor, 'profile.consent', ['book' => $book, 'id' => $id] + $v, $key, fn ($a) => $this->access->book($a, $book, 'customers.consent.manage', 'editor', true), function ($a, $b) use ($id, $v) {
            $p = $this->profiles->find($b->id, $id, true, false);
            $this->version($p, $v['expected_version']);
            abort_unless($p->status === 'active', 409);
            $this->appendConsent($p, $v, $a->id, 'admin');

            return ['public_id' => $id];
        });

        return $this->profile($actor, $book, $id);
    }

    public function appendConsent(object $p, array $v, int $actorId, string $source): void
    {
        DB::table('cust_consents')->insert(['public_id' => (string) Str::uuid(), 'profile_id' => $p->id, 'purpose' => $v['purpose'], 'channel' => $v['channel'], 'brand' => $v['brand'], 'state' => $v['state'], 'policy_version' => $v['policy_version'] ?? null, 'evidence' => isset($v['evidence']) ? Crypt::encryptString($v['evidence']) : null, 'source' => $source, 'actor_id' => $actorId ?: null, 'occurred_at' => now(), 'created_at' => now()]);
        DB::table('cust_profiles')->where('id', $p->id)->update(['version' => $p->version + 1, 'updated_at' => now()]);
        $p->version++;
        $this->profiles->event($p, 'customer.consent.changed.v1', $actorId, ['consent'], ['purpose' => $v['purpose'], 'channel' => $v['channel'], 'state' => $v['state']]);
    }

    public function fields(Admin $actor, string $book): array
    {
        $live = $this->access->actor($actor);
        $b = $this->access->book($live, $book);

        return DB::table('cust_field_definitions')->where('book_id', $b->id)->get()->filter(fn ($d) => ! $d->sensitive || $this->access->allowed($live, $b, 'customers.sensitive'))->map(function ($d) {
            $d->options = json_decode($d->options ?? '[]', true);
            unset($d->book_id);

            return (array) $d;
        })->values()->all();
    }

    public function saveField(Admin $actor, string $book, array $data, string $key): array
    {
        $v = validator($data, ['key' => 'required|string|max:60|regex:/^[a-z][a-z0-9_]*$/D', 'namespace' => 'sometimes|string|max:60|regex:/^[a-z][a-z0-9_-]*$/D', 'label' => 'required|string|max:120', 'type' => 'required|in:text,number,date,boolean,select', 'options' => 'sometimes|array|max:100', 'options.*' => 'string|max:120', 'required' => 'sometimes|boolean', 'sensitive' => 'sometimes|boolean', 'searchable' => 'sometimes|boolean', 'status' => 'sometimes|in:active,archived', 'expected_version' => 'sometimes|integer|min:1'])->validate();
        $v += ['namespace' => 'customers', 'options' => [], 'required' => false, 'sensitive' => false, 'searchable' => false, 'status' => 'active'];
        abort_if($v['sensitive'] && $v['searchable'], 422, 'Trường nhạy cảm không được tạo chỉ mục tìm kiếm công khai.');
        $r = $this->commands->run($actor, 'field.save', ['book' => $book] + $v, $key, fn ($a) => $this->access->book($a, $book, 'customers.settings', 'manager', true), function ($a, $b) use ($v) {
            $old = DB::table('cust_field_definitions')->where('book_id', $b->id)->where('namespace', $v['namespace'])->where('key', $v['key'])->lockForUpdate()->first();
            if ($old) {
                $this->version($old, $v['expected_version'] ?? 0);
                abort_if($old->type !== $v['type'] && DB::table('cust_field_values')->where('field_id', $old->id)->exists(), 422, 'Trường đã có dữ liệu cần migration rõ ràng để đổi kiểu.');
                if ((bool) $old->searchable !== (bool) $v['searchable'] || (bool) $old->sensitive !== (bool) $v['sensitive']) {
                    DB::table('cust_field_values')->where('field_id', $old->id)->orderBy('id')->chunkById(200, function ($values) use ($v) {
                        foreach ($values as $value) {
                            $decoded = json_decode(Crypt::decryptString($value->value), true, flags: JSON_THROW_ON_ERROR);
                            DB::table('cust_field_values')->where('id', $value->id)->update(['search_value' => $v['searchable'] && ! $v['sensitive'] && $decoded !== null ? mb_substr((string) $decoded, 0, 255) : null]);
                        }
                    });
                }
            }
            $row = $v;
            unset($row['expected_version']);
            $row['options'] = json_encode($row['options'], JSON_THROW_ON_ERROR);
            $row['version'] = $old ? $old->version + 1 : 1;
            $row['updated_at'] = now();
            if ($old) {
                DB::table('cust_field_definitions')->where('id', $old->id)->update($row);
            } else {
                DB::table('cust_field_definitions')->insert($row + ['book_id' => $b->id, 'created_at' => now()]);
            }

            return ['status' => 'saved'];
        });

        return ['items' => $this->fields($actor, $book)] + $r;
    }

    public function grants(Admin $actor, string $book): array
    {
        $a = $this->access->actor($actor);
        $b = $this->access->book($a, $book, 'customers.settings', 'manager');

        return DB::table('cust_book_grants as g')->join('admins as a', 'a.id', '=', 'g.admin_id')->where('g.book_id', $b->id)->get(['g.admin_id', 'g.role', 'a.name'])->map(fn ($r) => (array) $r)->all();
    }

    public function saveGrant(Admin $actor, string $book, array $data, string $key): array
    {
        $v = validator($data, ['admin_id' => 'required|integer|min:1', 'role' => 'required_unless:revoke,true|in:reader,editor,manager', 'revoke' => 'sometimes|boolean'])->validate();
        $this->commands->run($actor, 'book.grant', ['book' => $book] + $v, $key, fn ($a) => $this->access->book($a, $book, 'customers.settings', 'manager', true), function ($a, $b) use ($v) {
            $target = Admin::query()->findOrFail($v['admin_id']);
            abort_unless($target->isAvailable() && $target->canAccessWebsite($b->website_key), 422, 'Tài khoản chưa có quyền website.');
            if (($v['revoke'] ?? false)) {
                abort_if($target->id === $a->id, 422, 'Không tự thu hồi quyền quản lý nhóm.');
                DB::table('cust_book_grants')->where('book_id', $b->id)->where('admin_id', $target->id)->delete();
            } else {
                DB::table('cust_book_grants')->updateOrInsert(['book_id' => $b->id, 'admin_id' => $target->id], ['role' => $v['role'], 'created_at' => now(), 'updated_at' => now()]);
            }
            app(AuditLogger::class)->record('customers.grant.changed', 'customer-book', after: ['book_id' => $b->public_id, 'admin_id' => $target->id, 'role' => $v['role'] ?? 'revoked'], actor: $a, moduleKey: 'customers');

            return ['status' => 'saved'];
        });

        return $this->grants($actor, $book);
    }

    public function relationship(Admin $actor, string $book, string $id, array $data, string $key): array
    {
        $v = validator($data, ['related_profile_id' => 'required|uuid', 'role' => 'required|in:contact,employee,representative,household,other', 'starts_at' => 'nullable|date_format:Y-m-d', 'ends_at' => 'nullable|date_format:Y-m-d|after_or_equal:starts_at', 'expected_version' => 'required|integer|min:1'])->validate();
        $this->commands->run($actor, 'profile.relationship', ['book' => $book, 'id' => $id] + $v, $key, fn ($a) => $this->access->book($a, $book, 'customers.update', 'editor', true), function ($a, $b) use ($id, $v) {
            $p = $this->profiles->find($b->id, $id, true);
            $other = $this->profiles->find($b->id, $v['related_profile_id'], true);
            abort_if($p->id === $other->id, 422, 'Không thể liên kết hồ sơ với chính nó.');
            $this->version($p, $v['expected_version']);
            DB::table('cust_relationships')->updateOrInsert(['profile_id' => $p->id, 'related_profile_id' => $other->id, 'role' => $v['role']], ['public_id' => (string) Str::uuid(), 'book_id' => $b->id, 'starts_at' => $v['starts_at'] ?? null, 'ends_at' => $v['ends_at'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('cust_profiles')->where('id', $p->id)->update(['version' => $p->version + 1, 'updated_at' => now()]);
            $p->version++;
            $this->profiles->event($p, 'customer.relationship.changed.v1', $a->id, ['relationships']);

            return ['public_id' => $id];
        });

        return $this->profile($actor, $book, $id);
    }

    public function duplicates(Admin $actor, string $book): array
    {
        $a = $this->access->actor($actor);
        $b = $this->access->book($a, $book, 'customers.merge', 'manager');
        $candidates = DB::table('cust_contacts as a')->join('cust_contacts as b', fn ($j) => $j->on('a.book_id', '=', 'b.book_id')->on('a.kind', '=', 'b.kind')->on('a.normalized', '=', 'b.normalized')->on('a.profile_id', '<', 'b.profile_id'))
            ->join('cust_profiles as p', 'p.id', '=', 'a.profile_id')->join('cust_profiles as q', 'q.id', '=', 'b.profile_id')->where('a.book_id', $b->id)->where('p.status', 'active')->where('q.status', 'active')->limit(100)->get(['p.public_id as left_id', 'q.public_id as right_id', 'a.kind']);

        return $candidates->map(fn ($c) => ['left' => $this->profiles->resource($this->profiles->find($b->id, $c->left_id), false, false), 'right' => $this->profiles->resource($this->profiles->find($b->id, $c->right_id), false, false), 'matches' => [$c->kind]])->all();
    }

    public function merge(Admin $actor, string $book, string $id, array $data, string $key): array
    {
        $v = validator($data, ['target_id' => 'required|uuid', 'expected_version' => 'required|integer|min:1', 'target_version' => 'required|integer|min:1', 'reason' => 'required|string|min:5|max:500', 'field_choices' => 'sometimes|array:name,legal_name,tax_code,birthday,notes,contacts,addresses,tags', 'field_choices.*' => 'required|in:source,target,combine'])->validate();
        $r = $this->commands->run($actor, 'profile.merge', ['book' => $book, 'id' => $id] + $v, $key, fn ($a) => $this->access->book($a, $book, 'customers.merge', 'manager', true), function ($a, $b) use ($id, $v) {
            // The book row serializes cross-profile operations and prevents lock inversions.
            $source = $this->profiles->find($b->id, $id, true, false);
            $target = $this->profiles->find($b->id, $v['target_id'], true, false);
            abort_unless($source->id !== $target->id && $source->status === 'active' && $target->status === 'active' && $source->type === $target->type, 422, 'Chỉ hợp nhất hai hồ sơ đang hoạt động cùng loại.');
            $this->version($source, $v['expected_version']);
            $this->version($target, $v['target_version']);
            abort_if(DB::table('cust_account_links')->where('profile_id', $source->id)->exists(), 422, 'Cần xử lý liên kết tài khoản riêng trước khi hợp nhất.');
            $choices = $v['field_choices'] ?? [];
            $patch = [];
            if ($choices) {
                $sourceValues = $this->profiles->resource($source, true);
                $targetValues = $this->profiles->resource($target, true);
                foreach ($choices as $field => $choice) {
                    if ($choice === 'target') {
                        continue;
                    }
                    if ($choice === 'combine') {
                        abort_unless(in_array($field, ['contacts', 'addresses', 'tags'], true), 422, 'Chỉ kết hợp danh sách liên hệ, địa chỉ và nhãn.');
                        $patch[$field] = array_merge($targetValues[$field], $sourceValues[$field]);
                    } else {
                        $patch[$field] = $sourceValues[$field] ?? null;
                    }
                }
                if ($patch) {
                    $this->sensitiveWrite($a, $b, $patch);
                    $target = $this->profiles->save($b->id, $patch + ['expected_version' => $target->version], $target->public_id, $a->id);
                }
            }
            // Explicit source mappings move to the survivor; contacts/consents remain at their original provenance.
            DB::table('cust_sources')->where('profile_id', $source->id)->update(['profile_id' => $target->id, 'updated_at' => now()]);
            DB::table('cust_profiles')->where('id', $source->id)->update(['status' => 'merged', 'merged_into_id' => $target->id, 'version' => $source->version + 1, 'updated_at' => now()]);
            DB::table('cust_profiles')->where('id', $target->id)->update(['version' => $target->version + 1, 'updated_at' => now()]);
            // Merging identity never widens consent. Reconfirm grants; preserve any withdrawal/denial.
            $consents = DB::table('cust_consents')->whereIn('profile_id', [$source->id, $target->id])->orderByDesc('id')->get()->groupBy(fn ($c) => $c->purpose.'|'.$c->channel.'|'.$c->brand);
            foreach ($consents as $entries) {
                $dimension = $entries->first();
                $restricted = $entries->contains(fn ($c) => in_array($c->state, ['denied', 'withdrawn'], true));
                DB::table('cust_consents')->insert(['public_id' => (string) Str::uuid(), 'profile_id' => $target->id, 'purpose' => $dimension->purpose, 'channel' => $dimension->channel, 'brand' => $dimension->brand, 'state' => $restricted ? 'withdrawn' : 'unknown', 'policy_version' => null, 'evidence' => null, 'source' => 'identity_merge', 'actor_id' => $a->id, 'occurred_at' => now(), 'created_at' => now()]);
            }
            $target->version++;
            $this->profiles->event($target, 'customer.merged.v1', $a->id, ['aliases'], ['source_id' => $source->public_id, 'reason_hash' => hash('sha256', $v['reason'])]);

            return ['public_id' => $target->public_id, 'target_id' => $target->public_id];
        });

        return $this->profile($actor, $book, $r['public_id']);
    }

    public function sensitiveWrite(Admin $actor, object $book, array $data): void
    {
        $sensitive = array_intersect(array_keys($data), ['contacts', 'phone', 'email', 'addresses', 'birthday', 'notes', 'tax_code']);
        if (! empty($data['custom_fields'])) {
            foreach (DB::table('cust_field_definitions')->where('book_id', $book->id)->where('sensitive', true)->get() as $d) {
                if (array_key_exists($d->namespace.'.'.$d->key, $data['custom_fields'])) {
                    $sensitive[] = 'custom_fields';
                }
            }
        }
        if ($sensitive !== []) {
            abort_unless($this->access->allowed($actor, $book, 'customers.sensitive', 'editor'), 403, 'Cần quyền thông tin liên hệ đầy đủ để cập nhật dữ liệu này.');
        }
    }

    public function permissions(Admin $a, object $b): array
    {
        $permissions = [];
        foreach (json_decode(file_get_contents(base_path('modules/Customers/module.json')), true)['permissions'] as $p) {
            $role = in_array($p, ['customers.settings', 'customers.merge', 'customers.account.link'], true) ? 'manager' : (in_array($p, ['customers.view', 'customers.lookup', 'customers.sensitive'], true) ? 'reader' : 'editor');
            if ($this->access->allowed($a, $b, $p, $role)) {
                $permissions[] = $p;
            }
        }

        return $permissions;
    }

    public function version(object $p, int $version): void
    {
        abort_unless((int) $p->version === $version, 409, 'Phiên bản đã thay đổi; hãy tải lại.');
    }

    private function bookResource(Admin $a, object $b): array
    {
        return ['id' => $b->public_id, 'public_id' => $b->public_id, 'name' => $b->name, 'code' => $b->code, 'website_key' => $b->website_key, 'version' => $b->version, 'can_manage' => $this->access->allowed($a, $b, 'customers.settings', 'manager'), 'permissions' => $this->permissions($a,$b)];
    }
}
