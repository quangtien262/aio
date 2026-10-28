<?php

namespace Modules\Pharmacy\Services\Integrations;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\Admin;
use App\Support\SiteContext;
use Illuminate\Support\Facades\DB;
use Modules\Customers\Services\CustomerAccessService;
use Modules\Customers\Services\CustomerService;
use Modules\Pharmacy\Services\Access\PharmacyAccessService;
use Modules\Pharmacy\Services\Commands\PharmacyCommandService;
use Modules\Pharmacy\Services\PharmacyData as D;

/** Explicit native Customers book authority until shared delegated scope supports Pharmacy. */
final class PharmacyCustomerAdapter
{
    public function dispatch(Admin $actor, string $action, array $input, string $key): array
    {
        abort_unless(in_array($action, ['customer.mapping.save', 'customer.save', 'customer.lookup', 'customer.link'], true), 422);

        // Lookup has no side effects and must not put commercial PII in the command journal.
        if ($action === 'customer.lookup') {
            return DB::transaction(function () use ($actor, $input): array {
                abort_unless(app(ModuleCapabilityChecker::class)->has('customers', 'customers.profiles.read.v1'), 503);
                $access = app(PharmacyAccessService::class);
                $live = $access->actor($actor, true);
                $p = $access->pharmacy($live, $input['pharmacy_id'] ?? '', 'pharmacy.patient.view', true);
                $mapping = $this->mapping($p);

                return $this->inWebsite($mapping->website_key, function () use ($live, $p, $mapping, $input): array {
                    $book = DB::table('cust_books')->find($mapping->book_id);
                    abort_unless($book, 409);
                    $this->book($live, $book->public_id, 'customers.view', 'reader');

                    return $this->lookup($live, $p, $book, $input);
                });
            }, 3);
        }

        return app(PharmacyCommandService::class)->run($actor, $action, $input, $key, function (Admin $live) use ($action, $input): array {
            abort_unless(app(ModuleCapabilityChecker::class)->has('customers', 'customers.profiles.manage.v1') && app(ModuleCapabilityChecker::class)->has('customers', 'customers.consents.v1'), 503, 'Ứng dụng “Quản lý khách hàng” chưa sẵn sàng. Vui lòng kiểm tra cài đặt, phiên bản và trạng thái bật.');
            $permission = match ($action) {
                'customer.mapping.save' => 'pharmacy.integrations.manage', 'customer.lookup' => 'pharmacy.patient.view', default => 'pharmacy.patient.manage'
            };
            $p = app(PharmacyAccessService::class)->pharmacy($live, $input['pharmacy_id'] ?? '', $permission, true);
            if ($action === 'customer.mapping.save') {
                app(PharmacyAccessService::class)->chain($live, (int) $p->chain_id, $permission, true);
                $v = validator($input, ['website_key' => 'required|string|max:100', 'book_id' => 'required|uuid'])->validate();
                $this->inWebsite($v['website_key'], fn () => $this->book($live, $v['book_id'], 'customers.settings', 'manager'));
            } else {
                $mapping = $this->mapping($p);
                $this->inWebsite($mapping->website_key, function () use ($live, $mapping, $action, $input): void {
                    $book = DB::table('cust_books')->where('id', $mapping->book_id)->first();
                    abort_unless($book, 409);
                    $permission = match ($action) {
                        'customer.lookup' => 'customers.view', 'customer.save' => empty($input['customer_id']) ? 'customers.create' : 'customers.update', default => 'customers.view'
                    };
                    $this->book($live, $book->public_id, $permission, $action === 'customer.save' ? 'editor' : 'reader');
                    if ($action === 'customer.link') {
                        $this->assertConsent($p = D::row('ph_pharmacies', $input['pharmacy_id']), $book, (string) ($input['customer_id'] ?? ''));
                    }
                    if ($action === 'customer.save') {
                        $this->book($live, $book->public_id, 'customers.consent.manage', 'editor');
                        $this->book($live, $book->public_id, 'customers.sensitive', 'editor');
                    }
                });
            }

            return ['pharmacy' => $p, 'permission' => $permission];
        }, function (Admin $live, array $scope) use ($action, $input, $key): array {
            $p = $scope['pharmacy'];
            if ($action === 'customer.mapping.save') {
                return $this->saveMapping($live, $p, $input);
            }
            $mapping = $this->mapping($p);

            return $this->inWebsite($mapping->website_key, function () use ($live, $p, $mapping, $action, $input, $key): array {
                $book = DB::table('cust_books')->find($mapping->book_id);
                abort_unless($book, 409);

                return match ($action) {
                    'customer.save' => $this->save($live, $p, $book, $input, $key),
                    'customer.lookup' => $this->lookup($live, $p, $book, $input),
                    'customer.link' => $this->link($live, $p, $mapping, $book, $input),
                };
            });
        });
    }

    private function book(Admin $actor, string $public, string $permission, string $role): object
    {
        $access = app(CustomerAccessService::class);
        $live = $access->actor($actor, true);

        return $access->book($live, $public, $permission, $role, true);
    }

    private function mapping(object $p): object
    {
        $mapping = DB::table('ph_customer_mappings')->where('chain_id', $p->chain_id)->where('status', 'active')->sharedLock()->first();
        abort_unless($mapping, 409, 'Chưa liên kết nhóm khách hàng của chuỗi.');
        $authorizer = Admin::query()->find($mapping->authorized_by);
        abort_unless($authorizer && $authorizer->isAvailable() && (int) $authorizer->auth_version === (int) $mapping->authorized_auth_version, 403, 'Quyền liên kết ứng dụng “Quản lý khách hàng” đã thay đổi.');
        app(PharmacyAccessService::class)->chain($authorizer, (int) $p->chain_id, 'pharmacy.integrations.manage', true);
        $this->inWebsite($mapping->website_key, function () use ($authorizer, $mapping): void {
            $book = DB::table('cust_books')->find($mapping->book_id);
            abort_unless($book, 409);
            $this->book($authorizer, $book->public_id, 'customers.settings', 'manager');
        });

        return $mapping;
    }

    private function saveMapping(Admin $actor, object $p, array $input): array
    {
        $book = DB::table('cust_books')->where('public_id', $input['book_id'])->where('website_key', $input['website_key'])->first();
        abort_unless($book, 404);
        $old = DB::table('ph_customer_mappings')->where('chain_id', $p->chain_id)->lockForUpdate()->first();
        abort_if($old && ((int) $old->book_id !== (int) $book->id || $old->website_key !== $input['website_key']), 409, 'Chuyển nhóm cần quy trình cutover riêng.');
        $values = ['chain_id' => $p->chain_id, 'website_key' => $input['website_key'], 'book_id' => $book->id, 'authorized_by' => $actor->id, 'authorized_auth_version' => $actor->auth_version, 'status' => 'active'];

        return D::resource($old ? D::update('ph_customer_mappings', $old, $values, $input['expected_version'] ?? null) : D::insert('ph_customer_mappings', $values));
    }

    private function save(Admin $actor, object $p, object $book, array $input, string $key): array
    {
        $v = validator($input, ['customer_id' => 'nullable|uuid', 'profile' => 'required|array', 'processing_consent' => 'required|accepted', 'policy_version' => 'required|string|max:80', 'evidence' => 'required|string|max:2000'])->validate();
        $allowed = ['type', 'name', 'code', 'phone', 'email', 'country_code', 'expected_version'];
        abort_unless(array_diff(array_keys($v['profile']), $allowed) === [], 422, 'Chỉ chia sẻ hồ sơ thương mại; không gửi lâm sàng, ghi chú hoặc trường mở rộng.');
        $service = app(CustomerService::class);
        $customer = $service->save($actor, $book->public_id, $v['profile'], $v['customer_id'] ?? null, 'ph-customer:'.hash('sha256', $key.':profile'));
        $customer = $service->consent($actor, $book->public_id, $customer['public_id'], ['purpose' => 'pharmacy_commercial_profile', 'channel' => 'all', 'brand' => $this->brand($p), 'state' => 'granted', 'policy_version' => $v['policy_version'], 'evidence' => $v['evidence'], 'expected_version' => $customer['version']], 'ph-customer:'.hash('sha256', $key.':consent'));

        return array_intersect_key($customer, array_flip(['id', 'public_id', 'status', 'version']));
    }

    private function lookup(Admin $actor, object $p, object $book, array $input): array
    {
        $v = validator($input, ['q' => 'required|string|min:2|max:128', 'limit' => 'sometimes|integer|between:1,30'])->validate();
        $result = app(CustomerService::class)->listing($actor, $book->public_id, ['q' => $v['q'], 'per_page' => $v['limit'] ?? 20]);

        return ['items' => array_values(array_map(fn ($row) => array_intersect_key($row, array_flip(['id', 'public_id', 'name', 'code', 'type', 'phone', 'email', 'status', 'version'])), array_filter($result['items'], function ($row) use ($p, $book): bool {
            $profile = DB::table('cust_profiles')->where('book_id', $book->id)->where('public_id', $row['public_id'])->where('status', 'active')->first();

            return $profile && $this->hasConsent($p, $profile);
        })))];
    }

    private function link(Admin $actor, object $p, object $mapping, object $book, array $input): array
    {
        $v = validator($input, ['patient_id' => 'required|uuid', 'customer_id' => 'required|uuid', 'expected_version' => 'sometimes|integer|min:1'])->validate();
        $patient = D::row('ph_patients', $v['patient_id'], $p->chain_id, $p->id, true);
        abort_unless($patient->status === 'active', 409);
        $customer = $this->assertConsent($p, $book, $v['customer_id']);
        $old = DB::table('ph_patient_customer_links')->where('patient_id', $patient->id)->lockForUpdate()->first();
        $values = ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'patient_id' => $patient->id, 'mapping_id' => $mapping->id, 'customer_id' => $customer->id, 'actor_id' => $actor->id, 'status' => 'active'];
        $row = $old ? D::update('ph_patient_customer_links', $old, $values, $v['expected_version'] ?? null) : D::insert('ph_patient_customer_links', $values);

        return D::resource($row);
    }

    private function brand(object $p): string
    {
        return 'pharmacy:'.$p->chain_id.':'.hash('sha256', $p->legal_entity_ref);
    }

    private function hasConsent(object $p, object $customer): bool
    {
        $consent = DB::table('cust_consents')->where('profile_id', $customer->id)->where('purpose', 'pharmacy_commercial_profile')->where('brand', $this->brand($p))->orderByDesc('id')->first();

        return $consent && $consent->state === 'granted';
    }

    private function assertConsent(object $p, object $book, string $customerId): object
    {
        $customer = DB::table('cust_profiles')->where('book_id', $book->id)->where('public_id', $customerId)->where('status', 'active')->sharedLock()->first();
        abort_unless($customer, 404);
        abort_unless($this->hasConsent($p, $customer), 409, 'Cần consent thương mại hiện hành, độc lập hồ sơ lâm sàng.');

        return $customer;
    }

    private function inWebsite(string $website, callable $effect): mixed
    {
        $context = app(SiteContext::class);
        $site = $context->site();
        $old = $context->websiteKey();
        try {
            $context->set(null, $website);

            return $effect();
        } finally {
            $context->set($site, $old);
        }
    }
}
