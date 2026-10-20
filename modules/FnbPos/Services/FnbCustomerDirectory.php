<?php

namespace Modules\FnbPos\Services;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\Admin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Customers\Services\ConsumerCustomerService;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;

/** Optional directory boundary; shared mode never falls back to local profiles. */
final class FnbCustomerDirectory
{
    public function actor(FnbContext $context): Admin
    {
        return Admin::query()->findOrFail($context->actorId);
    }

    public function authorize(FnbContext $context, string $permission): Admin
    {
        $actor = $this->actor($context);
        app(FnbOutletAccessService::class)->authorizeTerminal($actor, $context->websiteKey, $context->outletId, $context->terminalId, $permission);

        return $actor;
    }

    public function status(FnbContext $context, bool $lock = false): array
    {
        if (! class_exists(ConsumerCustomerService::class) || $context->outletId < 1) {
            return ['mode' => 'legacy', 'available' => false, 'ready' => false, 'book_id' => null, 'reason' => 'Customers chưa được cài đặt.', 'binding_id' => null];
        }
        $status = app(ConsumerCustomerService::class)->status($this->actor($context), 'fnb-pos', (string) $context->outletId, $lock);
        if ($status['mode'] === 'shared' && ! app(ModuleCapabilityChecker::class)->has('fnb-pos', 'fnb.customers.shared.v1')) {
            $status['ready'] = false;
            $status['reason'] = 'Cần nâng cấp F&B trước khi dùng hồ sơ Customers chung.';
        }

        return $status;
    }

    public function shared(FnbContext $context, bool $lock = false): bool
    {
        $status = $this->status($context, $lock);
        if ($status['mode'] !== 'shared') {
            return false;
        }
        abort_unless($status['ready'], 503, $status['reason'] ?? 'Customers chưa sẵn sàng.');

        return true;
    }

    public function resolve(FnbContext $context, int|string $id): string
    {
        if (Str::isUuid((string) $id)) {
            return (string) $id;
        }
        $profile = app(ConsumerCustomerService::class)->resolveLegacy($this->actor($context), 'fnb-pos', (string) $context->outletId, (string) $id);
        abort_unless($profile, 404, 'Khách hàng cũ chưa được liên kết với Customers.');

        return $profile['public_id'];
    }

    public function assertAccess(FnbContext $context, string $ability): void
    {
        app(ConsumerCustomerService::class)->assertAccess($this->actor($context), 'fnb-pos', (string) $context->outletId, $ability);
    }

    public function lookup(FnbContext $context, string $query, int $limit): array
    {
        $actor = $this->authorize($context, 'fnb.customer.lookup');

        return array_map(fn (array $profile): array => $this->projection($profile), app(ConsumerCustomerService::class)->lookup($actor, 'fnb-pos', (string) $context->outletId, $query, $limit));
    }

    public function profile(FnbContext $context, int|string $id): array
    {
        $actor = $this->authorize($context, 'fnb.customer.view');
        $profile = app(ConsumerCustomerService::class)->profile($actor, 'fnb-pos', (string) $context->outletId, $this->resolve($context, $id));

        return $this->extend($context, $profile);
    }

    public function listing(FnbContext $context, array $filters): array
    {
        $actor = $this->authorize($context, 'fnb.customer.view');
        $result = app(ConsumerCustomerService::class)->listing($actor, 'fnb-pos', (string) $context->outletId, $filters);
        $result['items'] = array_map(fn (array $profile): array => $this->extend($context, $profile), $result['items']);

        return ['resource' => ['items' => $result['items']], 'meta' => $result['meta']];
    }

    public function create(FnbContext $context, array $input, string $key): array
    {
        $actor = $this->authorize($context, 'fnb.customer.create');
        $profile = app(ConsumerCustomerService::class)->create($actor, 'fnb-pos', (string) $context->outletId, array_intersect_key($input, array_flip(['name', 'type', 'code', 'phone', 'email'])), $key);
        $this->saveExtension($context, $profile['public_id'], $input);

        return $this->extend($context, $profile);
    }

    public function update(FnbContext $context, int|string $id, array $input, string $key): array
    {
        $actor = $this->authorize($context, 'fnb.customer.update');
        $profile = app(ConsumerCustomerService::class)->update($actor, 'fnb-pos', (string) $context->outletId, $this->resolve($context, $id), array_intersect_key($input, array_flip(['name', 'type', 'code', 'phone', 'email', 'status', 'expected_version'])), $key);
        $this->saveExtension($context, $profile['public_id'], $input);

        return $this->extend($context, $profile);
    }

    public function snapshot(FnbContext $context, int|string $id): array
    {
        $actor = $this->authorize($context, 'fnb.customer.attach');
        $id = $this->resolve($context, $id);

        $snapshot = app(ConsumerCustomerService::class)->snapshot($actor, 'fnb-pos', (string) $context->outletId, $id);
        $canonical = $snapshot['public_id'] ?? $snapshot['id'];

        return array_replace($snapshot, ['id' => $canonical, 'public_id' => $canonical, 'shared_customer_id' => $canonical]);
    }

    public function legacyIds(FnbContext $context, string $id): array
    {
        return app(ConsumerCustomerService::class)->legacyIds($this->actor($context), 'fnb-pos', (string) $context->outletId, $id);
    }

    public function profileIds(FnbContext $context, string $id): array
    {
        return app(ConsumerCustomerService::class)->profileIds($this->actor($context), 'fnb-pos', (string) $context->outletId, $id);
    }

    private function extend(FnbContext $context, array $profile): array
    {
        $profile = $this->projection($profile);
        try {
            $this->authorize($context, 'fnb.customer.view');
        } catch (AuthorizationException) {
            return $profile;
        }
        $extension = DB::table('fnb_shared_customer_extensions')->where('website_key', $context->websiteKey)->where('shared_customer_id', $profile['public_id'])->first();
        if (! $extension) {
            $extension = $this->legacyExtension($context, $profile['public_id']);
        }

        return array_replace($profile, ['id' => $profile['public_id'], 'shared_customer_id' => $profile['public_id'], 'notes' => $extension?->notes, 'birthday' => $extension?->birthday, 'privacy_consented_at' => $extension?->privacy_consented_at, 'marketing_consented_at' => $extension?->marketing_consented_at]);
    }

    private function projection(array $profile): array
    {
        return array_replace($profile, ['id' => $profile['public_id'], 'shared_customer_id' => $profile['public_id'], 'phone_normalized' => $profile['phone_normalized'] ?? $profile['phone_masked'] ?? $profile['phone'] ?? null]);
    }

    private function saveExtension(FnbContext $context, string $id, array $input): void
    {
        $data = validator($input, ['notes' => 'sometimes|nullable|string|max:2000', 'birthday' => 'sometimes|nullable|date_format:Y-m-d|before:today', 'privacy_consent' => 'sometimes|boolean', 'marketing_consent' => 'sometimes|boolean'])->validate();
        if ($data === []) {
            return;
        }
        $this->authorize($context, 'fnb.customer.update');
        $old = DB::table('fnb_shared_customer_extensions')->where('website_key', $context->websiteKey)->where('shared_customer_id', $id)->lockForUpdate()->first();
        $previous = $old ?? $this->legacyExtension($context, $id);
        $changes = array_intersect_key($data, array_flip(['notes', 'birthday']));
        foreach (['privacy', 'marketing'] as $purpose) {
            if (array_key_exists($purpose.'_consent', $data)) {
                $changes[$purpose.'_consented_at'] = $data[$purpose.'_consent'] ? ($previous?->{$purpose.'_consented_at'} ?? now()) : null;
            }
        }
        if ($old === null) {
            foreach (['notes', 'birthday', 'privacy_consented_at', 'marketing_consented_at'] as $field) {
                $changes += [$field => $previous?->{$field}];
            }
        }
        DB::table('fnb_shared_customer_extensions')->updateOrInsert(['website_key' => $context->websiteKey, 'shared_customer_id' => $id], $changes + ['updated_at' => now(), 'created_at' => $old?->created_at ?? now()]);
    }

    private function legacyExtension(FnbContext $context, string $id): ?object
    {
        // Local notes stay in the original application scope, without requiring financial-history access.
        $ids = DB::table('cust_sources as s')->join('cust_profiles as p', 'p.id', '=', 's.profile_id')->where('p.public_id', $id)->where('s.website_key', $context->websiteKey)->where('s.module_key', 'fnb-pos')->where('s.scope_type', 'website')->where('s.scope_key', $context->websiteKey)->pluck('s.source_id');

        return DB::table('fnb_customer_profiles')->where('website_key', $context->websiteKey)->whereIn('id', $ids)->orderBy('id')->first();
    }
}
