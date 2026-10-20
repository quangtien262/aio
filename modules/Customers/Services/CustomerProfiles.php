<?php

namespace Modules\Customers\Services;

use App\Support\AuditLogger;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Internal persistence/projections. Public entry points authorize through CustomerService or the consumer gateway. */
class CustomerProfiles
{
    public function find(int $bookId, string $public, bool $lock = false, bool $follow = true): object
    {
        $q = DB::table('cust_profiles')->where('book_id', $bookId)->where('public_id', $public);
        $profile = ($lock ? $q->lockForUpdate() : $q)->first();
        abort_unless($profile, 404, 'Không tìm thấy hồ sơ khách hàng.');
        if ($follow && $profile->merged_into_id) {
            $target = DB::table('cust_profiles')->where('book_id', $bookId)->where('id', $profile->merged_into_id)->first();
            abort_unless($target, 409);

            return $this->find($bookId, $target->public_id, $lock);
        }

        return $profile;
    }

    public function query(int $bookId, array $filters = [], bool $lookup = false): array
    {
        $v = validator($filters, ['q' => 'nullable|string|max:128', 'query' => 'nullable|string|max:128', 'status' => 'nullable|in:active,archived,merged,all', 'type' => 'nullable|in:person,company', 'tag' => 'nullable|string|max:80', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|between:1,100'])->validate();
        $status = $lookup ? 'active' : ($v['status'] ?? 'active');
        $q = DB::table('cust_profiles')->where('book_id', $bookId);
        if ($status !== 'all') {
            $q->where('status', $status);
        }
        if (! empty($v['type'])) {
            $q->where('type', $v['type']);
        }
        if (! empty($v['tag'])) {
            $q->whereIn('id', DB::table('cust_profile_tags')->where('book_id', $bookId)->where('tag', $v['tag'])->select('profile_id'));
        }
        $search = trim((string) ($v['q'] ?? $v['query'] ?? ''));
        if ($search !== '') {
            $literal = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $phone = preg_replace('/\D/', '', $search);
            $q->where(function ($q) use ($literal, $search, $phone, $bookId): void {
                $q->where('name', 'like', $literal.'%')->orWhere('code', 'like', $literal.'%')
                    ->orWhereIn('id', DB::table('cust_contacts')->where('book_id', $bookId)->where(function ($c) use ($search, $phone): void {
                        $c->where('normalized', mb_strtolower($search));
                        if (strlen($phone) >= 4) {
                            $c->orWhere('normalized', 'like', '%'.$phone);
                            if (str_starts_with($phone, '0')) {
                                $c->orWhere('normalized', 'like', '+84'.substr($phone, 1).'%');
                            }
                        }
                    })->select('profile_id'));
                $q->orWhereIn('id', DB::table('cust_field_values as fv')->join('cust_field_definitions as fd', 'fd.id', '=', 'fv.field_id')->where('fd.book_id', $bookId)->where('fd.status', 'active')->where('fd.sensitive', false)->where('fd.searchable', true)->where('fv.search_value', 'like', $literal.'%')->select('fv.profile_id'));
            });
        }
        $page = (int) ($v['page'] ?? 1);
        $size = (int) ($v['per_page'] ?? ($lookup ? 20 : 25));
        $total = (clone $q)->count();
        $items = $q->orderBy('name')->orderBy('id')->offset(($page - 1) * $size)->limit($size)->get()->all();

        return ['records' => $items, 'total' => $total, 'page' => $page, 'per_page' => $size];
    }

    public function validate(array $data, bool $creating = true): array
    {
        $rules = [
            'name' => ($creating ? 'required' : 'sometimes').'|string|min:1|max:255', 'code' => 'nullable|string|max:60|regex:/^[A-Za-z0-9._-]+$/D',
            'type' => 'sometimes|in:person,company', 'legal_name' => 'nullable|string|max:255', 'tax_code' => 'nullable|string|max:60', 'birthday' => 'nullable|date_format:Y-m-d|before_or_equal:today', 'notes' => 'nullable|string|max:5000',
            'contacts' => 'sometimes|array|max:20', 'contacts.*.kind' => 'required|in:phone,email', 'contacts.*.value' => 'required|string|max:255', 'contacts.*.label' => 'nullable|string|max:60', 'contacts.*.country_code' => 'sometimes|string|size:2', 'contacts.*.is_primary' => 'sometimes|boolean',
            'phone' => 'nullable|string|max:40', 'email' => 'nullable|email:rfc|max:255', 'country_code' => 'sometimes|string|size:2',
            'addresses' => 'sometimes|array|max:20', 'addresses.*.label' => 'nullable|string|max:60', 'addresses.*.kind' => 'sometimes|in:shipping,billing,both', 'addresses.*.line1' => 'required|string|max:255', 'addresses.*.line2' => 'nullable|string|max:255', 'addresses.*.city' => 'nullable|string|max:255', 'addresses.*.region' => 'nullable|string|max:255', 'addresses.*.postal_code' => 'nullable|string|max:30', 'addresses.*.country_code' => 'sometimes|string|size:2', 'addresses.*.is_default' => 'sometimes|boolean',
            'tags' => 'sometimes|array|max:30', 'tags.*' => 'required|string|max:80', 'custom_fields' => 'sometimes|array|max:100', 'expected_version' => 'sometimes|integer|min:1',
        ];
        $v = validator($data, $rules)->validate();
        if (isset($v['name'])) {
            $v['name'] = trim($v['name']);
            abort_if($v['name'] === '', 422, 'Tên khách không được để trống.');
        }
        foreach ($v['contacts'] ?? [] as $contact) {
            $this->normalize($contact['kind'], $contact['value'], $contact['country_code'] ?? 'VN');
        }
        if (! empty($v['phone'])) {
            $this->normalize('phone', $v['phone'], $v['country_code'] ?? 'VN');
        }

        return $v;
    }

    public function save(int $bookId, array $data, ?string $public, int $actorId, string $source = 'customers'): object
    {
        $v = $this->validate($data, $public === null);
        $old = $public ? $this->find($bookId, $public, true, false) : null;
        if ($old) {
            abort_unless($old->status !== 'merged', 409, 'Hồ sơ đã hợp nhất; tải lại hồ sơ chính.');
            abort_unless((int) ($v['expected_version'] ?? 0) === (int) $old->version, 409, 'Hồ sơ đã thay đổi. Hãy tải lại trước khi lưu.');
        }
        $row = array_intersect_key($v, array_flip(['name', 'type', 'code', 'legal_name', 'tax_code', 'birthday', 'notes']));
        if (! $old) {
            $row += ['type' => 'person', 'code' => 'KH-'.Str::upper(Str::random(10))];
        }
        if (empty($row['code'])) {
            unset($row['code']);
            if (! $old) {
                $row['code'] = 'KH-'.Str::upper(Str::random(10));
            }
        }
        if (isset($row['code'])) {
            abort_if(DB::table('cust_profiles')->where('book_id', $bookId)->where('code', $row['code'])->when($old, fn ($q) => $q->where('id', '!=', $old->id))->exists(), 422, 'Mã khách đã tồn tại trong nhóm dữ liệu.');
        }
        $row['updated_at'] = now();
        $row['version'] = $old ? $old->version + 1 : 1;
        if ($old) {
            DB::table('cust_profiles')->where('id', $old->id)->update($row);
            $id = $old->id;
        } else {
            $id = DB::table('cust_profiles')->insertGetId($row + ['public_id' => (string) Str::uuid(), 'book_id' => $bookId, 'created_by' => $actorId ?: null, 'created_at' => now()]);
        }
        $contacts = $v['contacts'] ?? null;
        if ($contacts === null && (array_key_exists('phone', $v) || array_key_exists('email', $v))) {
            $contacts = DB::table('cust_contacts')->where('profile_id', $id)->get()->map(fn ($r) => ['kind' => $r->kind, 'value' => $r->value, 'country_code' => $r->country_code, 'label' => $r->label, 'is_primary' => (bool) $r->is_primary])->all();
            foreach (['phone', 'email'] as $kind) {
                if (array_key_exists($kind, $v)) {
                    $contacts = array_values(array_filter($contacts, fn ($r) => $r['kind'] !== $kind));
                    if (! empty($v[$kind])) {
                        $contacts[] = ['kind' => $kind, 'value' => $v[$kind], 'country_code' => $v['country_code'] ?? 'VN', 'is_primary' => true];
                    }
                }
            }
        }
        if ($contacts !== null) {
            $this->contacts($bookId, $id, $contacts, $source);
        }
        if (array_key_exists('addresses', $v)) {
            DB::table('cust_addresses')->where('profile_id', $id)->delete();
            $default = false;
            foreach ($v['addresses'] as $a) {
                $a = array_intersect_key($a, array_flip(['label', 'kind', 'line1', 'line2', 'city', 'region', 'postal_code', 'country_code', 'is_default']));
                $a['is_default'] = (bool) ($a['is_default'] ?? false) && ! $default;
                $default = $default || $a['is_default'];
                DB::table('cust_addresses')->insert($a + ['public_id' => (string) Str::uuid(), 'profile_id' => $id, 'country_code' => 'VN', 'kind' => 'both', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        if (array_key_exists('tags', $v)) {
            DB::table('cust_profile_tags')->where('profile_id', $id)->delete();
            foreach (array_unique(array_map('trim', $v['tags'])) as $tag) {
                if ($tag !== '') {
                    DB::table('cust_profile_tags')->insert(['book_id' => $bookId, 'profile_id' => $id, 'tag' => $tag]);
                }
            }
        }
        $this->fields($bookId, $id, $v['custom_fields'] ?? []);
        $profile = DB::table('cust_profiles')->find($id);
        $this->event($profile, $old ? 'customer.updated.v1' : 'customer.created.v1', $actorId, array_keys($v), ['source' => $source]);

        return $profile;
    }

    private function contacts(int $bookId, int $id, array $contacts, string $source): void
    {
        $old = DB::table('cust_contacts')->where('profile_id', $id)->get()->keyBy(fn ($r) => $r->kind.'|'.$r->normalized);
        DB::table('cust_contacts')->where('profile_id', $id)->delete();
        $seen = [];
        $primary = [];
        foreach ($contacts as $c) {
            $normalized = $this->normalize($c['kind'], $c['value'], $c['country_code'] ?? 'VN');
            $key = $c['kind'].'|'.$normalized;
            if (isset($seen[$key])) {
                continue;
            } $seen[$key] = true;
            $prior = $old->get($key);
            $isPrimary = (bool) ($c['is_primary'] ?? false) && ! isset($primary[$c['kind']]);
            if ($isPrimary) {
                $primary[$c['kind']] = true;
            }
            DB::table('cust_contacts')->insert(['public_id' => $prior?->public_id ?? (string) Str::uuid(), 'book_id' => $bookId, 'profile_id' => $id, 'kind' => $c['kind'], 'value' => trim($c['value']), 'normalized' => $normalized, 'label' => $c['label'] ?? null, 'country_code' => strtoupper($c['country_code'] ?? 'VN'), 'is_primary' => $isPrimary, 'verified_at' => $prior?->verified_at, 'source' => $prior?->source ?? $source, 'created_at' => $prior?->created_at ?? now(), 'updated_at' => now()]);
        }
    }

    public function normalize(string $kind, string $value, string $country = 'VN'): string
    {
        if ($kind === 'email') {
            validator(['email' => $value], ['email' => 'required|email:rfc|max:255'])->validate();

            return mb_strtolower(trim($value));
        }
        $raw = trim($value);
        $digits = preg_replace('/\D/', '', $raw);
        if (str_starts_with($raw, '+')) {
            $normal = '+'.$digits;
        } elseif (strtoupper($country) === 'VN') {
            $normal = '+'.(str_starts_with($digits, '0') ? '84'.substr($digits, 1) : (str_starts_with($digits, '84') ? $digits : '84'.$digits));
        } else {
            throw ValidationException::withMessages(['phone' => 'Số ngoài Việt Nam cần mã quốc gia bắt đầu bằng +.']);
        }
        abort_unless(preg_match('/^\+[1-9][0-9]{6,14}$/D', $normal), 422, 'Số điện thoại không hợp lệ.');

        return $normal;
    }

    private function fields(int $bookId, int $profileId, array $values): void
    {
        $definitions = DB::table('cust_field_definitions')->where('book_id', $bookId)->get()->keyBy(fn ($d) => $d->namespace.'.'.$d->key);
        foreach ($values as $key => $value) {
            $d = $definitions->get($key);
            abort_unless($d && $d->status === 'active', 422, 'Trường mở rộng chưa đăng ký hoặc đã ngừng sử dụng: '.$key);
            $rules = match ($d->type) {
                'number' => ['numeric'],'date' => ['date_format:Y-m-d'],'boolean' => ['boolean'],'select' => [Rule::in(json_decode($d->options ?? '[]', true))],default => ['string', 'max:2000']
            };
            validator(['value' => $value], ['value' => array_merge([$d->required ? 'required' : 'nullable'], $rules)])->validate();
            DB::table('cust_field_values')->updateOrInsert(['profile_id' => $profileId, 'field_id' => $d->id], ['value' => Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR)), 'search_value' => $d->searchable && ! $d->sensitive && $value !== null ? mb_substr((string) $value, 0, 255) : null, 'schema_version' => $d->version, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ($definitions as $key => $d) {
            if ($d->required && $d->status === 'active') {
                abort_unless(DB::table('cust_field_values')->where('profile_id', $profileId)->where('field_id', $d->id)->exists(), 422, 'Thiếu trường bắt buộc: '.$d->label);
            }
        }
    }

    public function resources(array $profiles, bool $sensitive = false): array
    {
        $ids = array_map(fn ($p) => $p->id, $profiles);
        $contacts = DB::table('cust_contacts')->whereIn('profile_id', $ids)->orderByDesc('is_primary')->orderBy('id')->get()->groupBy('profile_id');
        $tags = DB::table('cust_profile_tags')->whereIn('profile_id', $ids)->get()->groupBy('profile_id');

        return array_map(fn ($p) => $this->resource($p, $sensitive, false, ['contacts' => $contacts->get($p->id, collect()), 'tags' => $tags->get($p->id, collect())->pluck('tag')->all()]), $profiles);
    }

    public function resource(object $profile, bool $sensitive = false, bool $detail = true, ?array $relations = null): array
    {
        $contacts = $relations['contacts'] ?? DB::table('cust_contacts')->where('profile_id', $profile->id)->orderByDesc('is_primary')->orderBy('id')->get();
        $phone = $contacts->firstWhere('kind', 'phone');
        $email = $contacts->firstWhere('kind', 'email');
        $maskPhone = fn ($v) => $v ? '***'.substr($v, -4) : null;
        $maskEmail = fn ($v) => $v ? mb_substr($v, 0, 1).'***@'.(explode('@', $v, 2)[1] ?? '') : null;
        $row = ['id' => $profile->public_id, 'public_id' => $profile->public_id, 'code' => $profile->code, 'type' => $profile->type, 'name' => $profile->name, 'status' => $profile->status, 'version' => (int) $profile->version,
            'phone' => $sensitive ? $phone?->value : $maskPhone($phone?->normalized), 'phone_normalized' => $sensitive ? $phone?->normalized : null, 'phone_masked' => $maskPhone($phone?->normalized), 'email' => $sensitive ? $email?->value : $maskEmail($email?->value), 'created_at' => $profile->created_at, 'updated_at' => $profile->updated_at,
            'tags' => $relations['tags'] ?? DB::table('cust_profile_tags')->where('profile_id', $profile->id)->pluck('tag')->all()];
        if (! $detail) {
            return $row;
        }
        $row += ['legal_name' => $profile->legal_name, 'tax_code' => $sensitive ? $profile->tax_code : null, 'birthday' => $sensitive ? $profile->birthday : null, 'notes' => $sensitive ? $profile->notes : null, 'sensitive_visible' => $sensitive,
            'contacts' => $contacts->map(fn ($c) => ['public_id' => $c->public_id, 'kind' => $c->kind, 'value' => $sensitive ? $c->value : ($c->kind === 'phone' ? $maskPhone($c->normalized) : $maskEmail($c->value)), 'normalized' => $sensitive ? $c->normalized : null, 'label' => $c->label, 'country_code' => $c->country_code, 'is_primary' => (bool) $c->is_primary, 'verified_at' => $c->verified_at, 'source' => $c->source])->all(),
            'addresses' => $sensitive ? DB::table('cust_addresses')->where('profile_id', $profile->id)->get(['public_id', 'label', 'kind', 'line1', 'line2', 'city', 'region', 'postal_code', 'country_code', 'is_default'])->map(fn ($r) => (array) $r)->all() : [],
            'tags' => DB::table('cust_profile_tags')->where('profile_id', $profile->id)->pluck('tag')->all(), 'custom_fields' => [],
            'sources' => DB::table('cust_sources')->where('profile_id', $profile->id)->get(['module_key', 'scope_type', 'scope_key', 'source_id', 'created_at'])->map(fn ($r) => (array) $r)->all(),
            'consents' => DB::table('cust_consents')->where('profile_id', $profile->id)->orderByDesc('id')->limit(100)->get(['public_id', 'purpose', 'channel', 'brand', 'state', 'policy_version', 'source', 'occurred_at'])->map(fn ($r) => (array) $r)->all(),
            'relationships' => DB::table('cust_relationships as r')->join('cust_profiles as p', 'p.id', '=', 'r.related_profile_id')->where('r.profile_id', $profile->id)->get(['r.public_id', 'r.role', 'r.starts_at', 'r.ends_at', 'p.public_id as related_profile_id', 'p.name'])->map(fn ($r) => (array) $r)->all()];
        foreach (DB::table('cust_field_values as v')->join('cust_field_definitions as d', 'd.id', '=', 'v.field_id')->where('v.profile_id', $profile->id)->get(['d.namespace', 'd.key', 'd.sensitive', 'v.value']) as $v) {
            if (! $v->sensitive || $sensitive) {
                $row['custom_fields'][$v->namespace.'.'.$v->key] = json_decode(Crypt::decryptString($v->value), true, flags: JSON_THROW_ON_ERROR);
            }
        }

        return $row;
    }

    public function event(object $profile, string $type, int $actorId, array $fields, array $metadata = []): void
    {
        $id = DB::table('cust_events')->insertGetId(['event_id' => (string) Str::uuid(), 'book_id' => $profile->book_id, 'profile_id' => $profile->id, 'event_type' => $type, 'aggregate_version' => $profile->version, 'actor_id' => $actorId ?: null, 'changed_fields' => json_encode(array_values($fields), JSON_THROW_ON_ERROR), 'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'occurred_at' => now()]);
        foreach (DB::table('cust_bindings')->where('book_id', $profile->book_id)->where('mode', 'shared')->distinct()->pluck('module_key') as $destination) {
            DB::table('cust_outbox_deliveries')->insertOrIgnore(['event_id' => $id, 'destination' => $destination, 'available_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        app(AuditLogger::class)->record($type, 'customer-profile', after: ['profile_id' => $profile->public_id, 'version' => $profile->version, 'changed_fields' => array_values($fields)], moduleKey: 'customers');
    }
}
