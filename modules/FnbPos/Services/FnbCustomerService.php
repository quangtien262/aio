<?php

namespace Modules\FnbPos\Services;

use App\Models\Admin;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Domain\FnbNotFoundException;
use Modules\FnbPos\Domain\FnbValidationException;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;

/**
 * Customer profile commands and full-PII reads.
 *
 * Lookup and quick-create remain in FnbSetupService because those endpoints
 * intentionally return a smaller projection. Every read here requires the
 * dedicated full-view permission, including when invoked outside HTTP.
 */
final class FnbCustomerService
{
    private const PROFILE_FIELDS = [
        'code',
        'name',
        'phone_normalized',
        'email',
        'birthday',
        'notes',
        'status',
        'privacy_consented_at',
        'marketing_consented_at',
    ];

    public function __construct(
        private readonly FnbCommandRunner $commands,
        private readonly FnbOutletAccessService $access,
    ) {}

    /**
     * Attach a website-owned customer to an open session and every mutable
     * check already created from it. Once any check is finalized (or has any
     * payment history), the buyer identity is frozen and this command fails.
     *
     * @return array{resource:array<string,mixed>,replayed:bool,events:array<int,mixed>,meta:array<string,mixed>}
     */
    public function attachToSession(
        FnbContext $context,
        int $sessionId,
        int $customerProfileId,
        string $idempotencyKey,
        int $expectedSessionVersion,
    ): array {
        if ($sessionId < 1 || $customerProfileId < 1 || $expectedSessionVersion < 1) {
            throw new FnbValidationException('A valid session, customer and expected session version are required.');
        }

        $payload = [
            'session_id' => $sessionId,
            'customer_profile_id' => $customerProfileId,
            'expected_version' => $expectedSessionVersion,
        ];

        return $this->commands->run($context, 'customer.attach', $payload, $idempotencyKey, function () use ($context, $sessionId, $customerProfileId, $expectedSessionVersion): array {
            $this->authorize($context, 'fnb.customer.attach', true);

            $session = $this->outletScope('fnb_service_sessions', $context)
                ->where('id', $sessionId)
                ->lockForUpdate()
                ->first();
            if ($session === null) {
                throw new FnbNotFoundException('Service session was not found in the current outlet.');
            }
            if ((int) $session->version !== $expectedSessionVersion) {
                throw $this->versionConflict('service_session', $session);
            }
            if ($session->status !== 'open' || $session->merged_into_session_id !== null) {
                throw new FnbConflictException('Only an open, unmerged service session can attach a customer.', [
                    'current_versions' => ["service_session:{$sessionId}" => (int) $session->version],
                    'current_snapshot' => $this->safeSessionSnapshot($session),
                ]);
            }

            $customer = DB::table('fnb_customer_profiles')
                ->where('website_key', $context->websiteKey)
                ->where('id', $customerProfileId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();
            if ($customer === null) {
                throw new FnbNotFoundException('Active customer profile was not found in the current website.');
            }

            /** @var Collection<int,object> $checks */
            $checks = $this->outletScope('fnb_checks', $context)
                ->where('session_id', $sessionId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $finalized = $checks->first(fn (object $check): bool => $check->finalized_at !== null);
            if ($finalized !== null) {
                throw new FnbConflictException('Customer identity is frozen after a session check is finalized.', [
                    'check_id' => (int) $finalized->id,
                    'current_versions' => ["check:{$finalized->id}" => (int) $finalized->version],
                ]);
            }

            $checkIds = $checks->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            if ($checkIds !== [] && $this->outletScope('fnb_payments', $context)->whereIn('check_id', $checkIds)->exists()) {
                throw new FnbConflictException('Customer identity is frozen after payment processing starts.');
            }

            $snapshot = $this->customerSnapshot($customer);
            $encodedSnapshot = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $now = now();
            $sessionVersion = (int) $session->version + 1;
            $this->outletScope('fnb_service_sessions', $context)->where('id', $sessionId)->update([
                'customer_profile_id' => $customerProfileId,
                'customer_snapshot' => $encodedSnapshot,
                'version' => $sessionVersion,
                'updated_at' => $now,
            ]);

            $checkVersions = [];
            foreach ($checks as $check) {
                // Voided, never-finalized checks are evidence and remain untouched.
                if ($check->status !== 'open') {
                    continue;
                }

                $version = (int) $check->version + 1;
                $updated = $this->outletScope('fnb_checks', $context)
                    ->where('id', $check->id)
                    ->where('status', 'open')
                    ->whereNull('finalized_at')
                    ->where('version', $check->version)
                    ->update([
                        'customer_profile_id' => $customerProfileId,
                        'buyer_snapshot' => $encodedSnapshot,
                        'version' => $version,
                        'updated_at' => $now,
                    ]);
                if ($updated !== 1) {
                    throw new FnbConflictException('A session check changed while the customer was being attached.', [
                        'check_id' => (int) $check->id,
                    ]);
                }
                $checkVersions[(string) $check->id] = $version;
            }

            $evidence = [
                'outlet_id' => $context->outletId,
                'session_id' => $sessionId,
                'customer_profile_id' => $customerProfileId,
                'session_version' => $sessionVersion,
                'check_versions' => $checkVersions,
            ];
            $this->customerEvent($context, $customerProfileId, 'attached_to_session', [
                'session.customer_profile_id',
                'session.customer_snapshot',
            ], $evidence, $now);
            $previousCustomerId = $session->customer_profile_id === null ? null : (int) $session->customer_profile_id;
            if ($previousCustomerId !== null && $previousCustomerId !== $customerProfileId) {
                $this->customerEvent($context, $previousCustomerId, 'detached_from_session', [
                    'session.customer_profile_id',
                    'session.customer_snapshot',
                ], $evidence, $now);
            }

            return [
                'resource' => [
                    ...$this->safeSessionSnapshot((object) array_replace((array) $session, [
                        'customer_profile_id' => $customerProfileId,
                        'version' => $sessionVersion,
                    ])),
                    'customer' => $this->attachCustomerProjection($customer),
                    'updated_check_ids' => array_map('intval', array_keys($checkVersions)),
                ],
                'meta' => [
                    'resource_type' => 'service_session',
                    'resource_id' => $sessionId,
                    'version' => $sessionVersion,
                    'check_versions' => $checkVersions,
                ],
            ];
        });
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array{resource:array<string,mixed>,replayed:bool,events:array<int,mixed>,meta:array<string,mixed>}
     */
    public function updateProfile(
        FnbContext $context,
        int $customerProfileId,
        array $input,
        string $idempotencyKey,
        int $expectedVersion,
    ): array {
        if ($customerProfileId < 1 || $expectedVersion < 1) {
            throw new FnbValidationException('A valid customer and expected version are required.');
        }

        $data = validator($input, [
            'code' => ['sometimes', 'nullable', 'string', 'max:40'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'birthday' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before:today'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(['active', 'archived'])],
            'privacy_consent' => ['sometimes', 'boolean'],
            'marketing_consent' => ['sometimes', 'boolean'],
        ])->validate();
        if ($data === []) {
            throw new FnbValidationException('At least one customer profile field is required.');
        }
        $data = $this->normalizeProfileInput($data);
        $payload = $data + ['customer_profile_id' => $customerProfileId, 'expected_version' => $expectedVersion];

        return $this->commands->run($context, 'customer.update', $payload, $idempotencyKey, function () use ($context, $customerProfileId, $data, $expectedVersion): array {
            $this->authorize($context, 'fnb.customer.update', true);

            $profile = DB::table('fnb_customer_profiles')
                ->where('website_key', $context->websiteKey)
                ->where('id', $customerProfileId)
                ->lockForUpdate()
                ->first();
            if ($profile === null) {
                throw new FnbNotFoundException('Customer profile was not found in the current website.');
            }
            if ((int) $profile->version !== $expectedVersion) {
                throw $this->versionConflict('customer_profile', $profile, $this->profileResource($profile));
            }

            $now = now();
            $candidate = $this->profileChanges($profile, $data, $now);
            $changedFields = array_keys(array_filter(
                $candidate,
                fn (mixed $value, string $field): bool => $this->different($profile->{$field}, $value),
                ARRAY_FILTER_USE_BOTH,
            ));

            if ($changedFields === []) {
                return [
                    'resource' => $this->profileResource($profile),
                    'meta' => [
                        'resource_type' => 'customer_profile',
                        'resource_id' => $customerProfileId,
                        'version' => (int) $profile->version,
                        'changed_fields' => [],
                    ],
                ];
            }

            if (in_array('phone_normalized', $changedFields, true)
                && $candidate['phone_normalized'] !== null
                && DB::table('fnb_customer_profiles')
                    ->where('website_key', $context->websiteKey)
                    ->where('phone_normalized', $candidate['phone_normalized'])
                    ->where('id', '<>', $customerProfileId)
                    ->exists()) {
                throw new FnbConflictException('A customer with this phone already exists in the website.');
            }

            $changes = array_intersect_key($candidate, array_flip($changedFields));
            $version = (int) $profile->version + 1;
            try {
                $updated = DB::table('fnb_customer_profiles')
                    ->where('website_key', $context->websiteKey)
                    ->where('id', $customerProfileId)
                    ->where('version', $expectedVersion)
                    ->update($changes + ['version' => $version, 'updated_at' => $now]);
            } catch (QueryException $exception) {
                if (! $this->isUniqueViolation($exception)) {
                    throw $exception;
                }

                throw new FnbConflictException('A customer with the same unique identity already exists in the website.');
            }
            if ($updated !== 1) {
                $current = DB::table('fnb_customer_profiles')
                    ->where('website_key', $context->websiteKey)
                    ->where('id', $customerProfileId)
                    ->first();
                throw $this->versionConflict('customer_profile', $current ?? $profile, $current ? $this->profileResource($current) : null);
            }

            sort($changedFields, SORT_STRING);
            $beforeEvidence = array_intersect_key((array) $profile, array_flip($changedFields));
            $afterEvidence = array_intersect_key($changes, array_flip($changedFields));
            $evidence = ['before' => $beforeEvidence, 'after' => $afterEvidence, 'version' => $version];
            $ordinary = array_values(array_diff($changedFields, ['privacy_consented_at', 'marketing_consented_at', 'status']));
            if ($ordinary !== [] || (in_array('status', $changedFields, true) && $changes['status'] !== 'archived')) {
                $this->customerEvent($context, $customerProfileId, 'updated', $ordinary === [] ? ['status'] : $ordinary, $evidence, $now);
            }
            $consentFields = array_values(array_intersect($changedFields, ['privacy_consented_at', 'marketing_consented_at']));
            if ($consentFields !== []) {
                $this->customerEvent($context, $customerProfileId, 'consent_changed', $consentFields, $evidence, $now);
            }
            if (in_array('status', $changedFields, true) && $changes['status'] === 'archived') {
                $this->customerEvent($context, $customerProfileId, 'archived', ['status'], $evidence, $now);
            }

            $fresh = DB::table('fnb_customer_profiles')
                ->where('website_key', $context->websiteKey)
                ->where('id', $customerProfileId)
                ->firstOrFail();

            return [
                'resource' => $this->profileResource($fresh),
                'meta' => [
                    'resource_type' => 'customer_profile',
                    'resource_id' => $customerProfileId,
                    'version' => $version,
                    'changed_fields' => $changedFields,
                ],
            ];
        });
    }

    /** @return array{resource:array<string,mixed>} */
    public function profile(FnbContext $context, int $customerProfileId): array
    {
        if ($customerProfileId < 1) {
            throw new FnbValidationException('A valid customer is required.');
        }
        $this->authorize($context, 'fnb.customer.view');

        $profile = DB::table('fnb_customer_profiles')
            ->where('website_key', $context->websiteKey)
            ->where('id', $customerProfileId)
            ->first();
        if ($profile === null) {
            throw new FnbNotFoundException('Customer profile was not found in the current website.');
        }

        return ['resource' => $this->profileResource($profile)];
    }

    /**
     * Return only closed checks from the requested outlet. Buyer, payment and
     * refund values come from transaction-time snapshots/append-only records;
     * the current mutable profile is never projected into historical checks.
     *
     * @return array{resource:array<string,mixed>,meta:array<string,mixed>}
     */
    public function purchaseHistory(
        FnbContext $context,
        int $customerProfileId,
        int $limit = 25,
        ?int $beforeCheckId = null,
    ): array {
        if ($customerProfileId < 1 || $limit < 1 || $limit > 100 || ($beforeCheckId !== null && $beforeCheckId < 1)) {
            throw new FnbValidationException('Invalid customer history pagination.');
        }
        $this->authorize($context, 'fnb.customer.view');

        if (! DB::table('fnb_customer_profiles')
            ->where('website_key', $context->websiteKey)
            ->where('id', $customerProfileId)
            ->exists()) {
            throw new FnbNotFoundException('Customer profile was not found in the current website.');
        }

        $query = $this->outletScope('fnb_checks', $context)
            ->where('customer_profile_id', $customerProfileId)
            ->where('status', 'closed')
            ->whereNotNull('closed_at');
        if ($beforeCheckId !== null) {
            $query->where('id', '<', $beforeCheckId);
        }

        /** @var Collection<int,object> $rows */
        $rows = $query->orderByDesc('id')->limit($limit + 1)->get([
            'id',
            'public_id',
            'business_day_id',
            'session_id',
            'check_no',
            'status',
            'currency',
            'timezone_snapshot',
            'subtotal_minor',
            'discount_total_minor',
            'tax_total_minor',
            'service_charge_total_minor',
            'pricing_rounding_minor',
            'grand_total_minor',
            'cash_rounding_minor',
            'settlement_total_minor',
            'buyer_snapshot',
            'invoice_requested',
            'finalized_at',
            'paid_at',
            'closed_at',
        ]);
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit)->values();
        $checkIds = $rows->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        $payments = $checkIds === [] ? collect() : $this->outletScope('fnb_payments', $context)
            ->whereIn('check_id', $checkIds)
            ->where('status', 'succeeded')
            ->orderBy('id')
            ->get([
                'id', 'public_id', 'check_id', 'method_code_snapshot', 'method_name_snapshot',
                'method_kind_snapshot', 'status', 'amount_minor', 'tendered_minor', 'change_minor', 'processed_at',
            ])
            ->groupBy('check_id');
        $refunds = $checkIds === [] ? collect() : $this->outletScope('fnb_refunds', $context)
            ->whereIn('check_id', $checkIds)
            ->where('status', 'succeeded')
            ->orderBy('id')
            ->get([
                'id', 'public_id', 'check_id', 'payment_id', 'refund_no', 'method_code_snapshot',
                'method_name_snapshot', 'method_kind_snapshot', 'status', 'amount_minor', 'processed_at',
            ])
            ->groupBy('check_id');

        $items = $rows->map(function (object $check) use ($payments, $refunds): array {
            /** @var Collection<int,object> $checkPayments */
            $checkPayments = $payments->get($check->id, collect());
            /** @var Collection<int,object> $checkRefunds */
            $checkRefunds = $refunds->get($check->id, collect());
            $grossPaid = (int) $checkPayments->sum(fn (object $payment): int => (int) $payment->amount_minor);
            $refunded = (int) $checkRefunds->sum(fn (object $refund): int => (int) $refund->amount_minor);

            return [
                'id' => (int) $check->id,
                'public_id' => $check->public_id,
                'business_day_id' => (int) $check->business_day_id,
                'session_id' => (int) $check->session_id,
                'check_no' => $check->check_no,
                'status' => $check->status,
                'currency' => $check->currency,
                'timezone_snapshot' => $check->timezone_snapshot,
                'subtotal_minor' => (int) $check->subtotal_minor,
                'discount_total_minor' => (int) $check->discount_total_minor,
                'tax_total_minor' => (int) $check->tax_total_minor,
                'service_charge_total_minor' => (int) $check->service_charge_total_minor,
                'pricing_rounding_minor' => (int) $check->pricing_rounding_minor,
                'grand_total_minor' => (int) $check->grand_total_minor,
                'cash_rounding_minor' => $check->cash_rounding_minor === null ? null : (int) $check->cash_rounding_minor,
                'settlement_total_minor' => $check->settlement_total_minor === null ? null : (int) $check->settlement_total_minor,
                'buyer_snapshot' => $this->decodeJson($check->buyer_snapshot),
                'invoice_requested' => (bool) $check->invoice_requested,
                'finalized_at' => $check->finalized_at,
                'paid_at' => $check->paid_at,
                'closed_at' => $check->closed_at,
                'gross_paid_total_minor' => $grossPaid,
                'refunded_total_minor' => $refunded,
                'net_collected_total_minor' => $grossPaid - $refunded,
                'payments' => $checkPayments->map(fn (object $payment): array => $this->paymentProjection($payment))->values()->all(),
                'refunds' => $checkRefunds->map(fn (object $refund): array => $this->refundProjection($refund))->values()->all(),
            ];
        })->all();

        return [
            'resource' => [
                'customer_profile_id' => $customerProfileId,
                'items' => $items,
            ],
            'meta' => [
                'has_more' => $hasMore,
                'next_before_check_id' => $hasMore && $items !== [] ? $items[array_key_last($items)]['id'] : null,
            ],
        ];
    }

    private function authorize(FnbContext $context, string $permission, bool $lockActor = false): void
    {
        $actorQuery = Admin::query()->whereKey($context->actorId);
        if ($lockActor) {
            $actorQuery->lockForUpdate();
        }
        $actor = $actorQuery->firstOrFail();
        $this->access->authorizeTerminal(
            $actor,
            $context->websiteKey,
            $context->outletId,
            $context->terminalId,
            $permission,
        );
    }

    private function outletScope(string $table, FnbContext $context): Builder
    {
        return DB::table($table)
            ->where('website_key', $context->websiteKey)
            ->where('outlet_id', $context->outletId);
    }

    /** @return array<string,mixed> */
    private function customerSnapshot(object $customer): array
    {
        return [
            'customer_profile_id' => (int) $customer->id,
            'code' => $customer->code,
            'name' => $customer->name,
            'phone_normalized' => $customer->phone_normalized,
        ];
    }

    /** @return array<string,mixed> */
    private function attachCustomerProjection(object $customer): array
    {
        $phone = (string) ($customer->phone_normalized ?? '');

        return [
            'id' => (int) $customer->id,
            'public_id' => $customer->public_id,
            'code' => $customer->code,
            'name' => $customer->name,
            'phone_masked' => $phone === '' ? null : '***'.substr($phone, -4),
            'status' => $customer->status,
            'version' => (int) $customer->version,
        ];
    }

    /** @return array<string,mixed> */
    private function safeSessionSnapshot(object $session): array
    {
        return [
            'id' => (int) $session->id,
            'public_id' => $session->public_id,
            'status' => $session->status,
            'customer_profile_id' => $session->customer_profile_id === null ? null : (int) $session->customer_profile_id,
            'version' => (int) $session->version,
        ];
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function normalizeProfileInput(array $data): array
    {
        if (array_key_exists('code', $data)) {
            $data['code'] = $data['code'] === null ? null : trim((string) $data['code']);
            $data['code'] = $data['code'] === '' ? null : $data['code'];
        }
        if (array_key_exists('name', $data)) {
            $data['name'] = trim((string) $data['name']);
            if ($data['name'] === '') {
                throw new FnbValidationException('Customer name cannot be blank.');
            }
        }
        if (array_key_exists('phone', $data)) {
            $phone = $data['phone'] === null ? '' : (string) preg_replace('/\D+/', '', (string) $data['phone']);
            if ($phone !== '' && (strlen($phone) < 7 || strlen($phone) > 15)) {
                throw new FnbValidationException('Customer phone must contain between 7 and 15 digits.');
            }
            $data['phone_normalized'] = $phone === '' ? null : $phone;
            unset($data['phone']);
        }
        if (array_key_exists('email', $data)) {
            $email = $data['email'] === null ? '' : mb_strtolower(trim((string) $data['email']));
            $data['email'] = $email === '' ? null : $email;
        }
        if (array_key_exists('notes', $data) && $data['notes'] !== null) {
            $data['notes'] = trim((string) $data['notes']);
            $data['notes'] = $data['notes'] === '' ? null : $data['notes'];
        }

        return $data;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function profileChanges(object $profile, array $data, mixed $now): array
    {
        $changes = array_intersect_key($data, array_flip(self::PROFILE_FIELDS));
        if (array_key_exists('privacy_consent', $data)) {
            $changes['privacy_consented_at'] = $data['privacy_consent']
                ? ($profile->privacy_consented_at ?? $now)
                : null;
        }
        if (array_key_exists('marketing_consent', $data)) {
            $changes['marketing_consented_at'] = $data['marketing_consent']
                ? ($profile->marketing_consented_at ?? $now)
                : null;
        }

        return $changes;
    }

    private function different(mixed $before, mixed $after): bool
    {
        if ($before === null || $after === null) {
            return $before !== $after;
        }

        return (string) $before !== (string) $after;
    }

    /** @return array<string,mixed> */
    private function profileResource(object $profile): array
    {
        return [
            'id' => (int) $profile->id,
            'public_id' => $profile->public_id,
            'core_customer_id' => $profile->core_customer_id === null ? null : (int) $profile->core_customer_id,
            'code' => $profile->code,
            'name' => $profile->name,
            'phone_normalized' => $profile->phone_normalized,
            'email' => $profile->email,
            'birthday' => $profile->birthday,
            'notes' => $profile->notes,
            'status' => $profile->status,
            'privacy_consented_at' => $profile->privacy_consented_at,
            'marketing_consented_at' => $profile->marketing_consented_at,
            'version' => (int) $profile->version,
            'created_at' => $profile->created_at,
            'updated_at' => $profile->updated_at,
        ];
    }

    /**
     * @param  list<string>  $changedFields
     * @param  array<string,mixed>  $evidence
     */
    private function customerEvent(
        FnbContext $context,
        int $customerProfileId,
        string $eventType,
        array $changedFields,
        array $evidence,
        mixed $occurredAt,
    ): void {
        sort($changedFields, SORT_STRING);
        DB::table('fnb_customer_events')->insert([
            'website_key' => $context->websiteKey,
            'customer_profile_id' => $customerProfileId,
            'event_type' => $eventType,
            'actor_id' => $context->actorId,
            'changed_fields' => json_encode($changedFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'evidence_hash' => $this->commands->fingerprint($evidence),
            'request_id' => request()?->header('X-Request-ID'),
            'occurred_at' => $occurredAt,
        ]);
    }

    /** @return array<string,mixed> */
    private function paymentProjection(object $payment): array
    {
        return [
            'id' => (int) $payment->id,
            'public_id' => $payment->public_id,
            'check_id' => (int) $payment->check_id,
            'method_code_snapshot' => $payment->method_code_snapshot,
            'method_name_snapshot' => $payment->method_name_snapshot,
            'method_kind_snapshot' => $payment->method_kind_snapshot,
            'status' => $payment->status,
            'amount_minor' => (int) $payment->amount_minor,
            'tendered_minor' => $payment->tendered_minor === null ? null : (int) $payment->tendered_minor,
            'change_minor' => (int) $payment->change_minor,
            'processed_at' => $payment->processed_at,
        ];
    }

    /** @return array<string,mixed> */
    private function refundProjection(object $refund): array
    {
        return [
            'id' => (int) $refund->id,
            'public_id' => $refund->public_id,
            'check_id' => (int) $refund->check_id,
            'payment_id' => (int) $refund->payment_id,
            'refund_no' => $refund->refund_no,
            'method_code_snapshot' => $refund->method_code_snapshot,
            'method_name_snapshot' => $refund->method_name_snapshot,
            'method_kind_snapshot' => $refund->method_kind_snapshot,
            'status' => $refund->status,
            'amount_minor' => (int) $refund->amount_minor,
            'processed_at' => $refund->processed_at,
        ];
    }

    private function decodeJson(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string,mixed>|null $safeSnapshot */
    private function versionConflict(string $type, object $record, ?array $safeSnapshot = null): FnbConflictException
    {
        return new FnbConflictException('Aggregate version is stale.', [
            'current_versions' => ["{$type}:{$record->id}" => (int) $record->version],
            'current_snapshot' => $safeSnapshot ?? $this->safeSessionSnapshot($record),
        ]);
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower($exception->getMessage());

        return $sqlState === '23505'
            || $driverCode === 1062
            || ($sqlState === '23000' && str_contains($message, 'unique'))
            || ($driverCode === 19 && str_contains($message, 'unique constraint'));
    }
}
