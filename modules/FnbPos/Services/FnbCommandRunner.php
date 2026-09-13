<?php

namespace Modules\FnbPos\Services;

use App\Models\Admin;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Domain\FnbNotFoundException;
use Modules\FnbPos\Domain\FnbValidationException;
use Modules\FnbPos\Services\Security\FnbAuditLogger;

final class FnbCommandRunner
{
    public function __construct(private readonly FnbAuditLogger $audit) {}

    /**
     * Run one command in the required order: scope/lifecycle lock, replay
     * detection, live authorization, mutation and durable response commit.
     *
     * The callback receives no arguments and must return either a resource
     * array or an envelope containing `resource`, optional `events`/`meta`.
     *
     * @param  array<string,mixed>  $payload
     * @param  Closure():array<string,mixed>  $mutation
     * @return array{resource:mixed,replayed:bool,events:array<int,mixed>,meta:array<string,mixed>}
     */
    public function run(
        FnbContext $context,
        string $operation,
        array $payload,
        string $key,
        Closure $mutation,
    ): array {
        $operation = trim($operation);
        $key = trim($key);
        if ($operation === '' || strlen($operation) > 100) {
            throw new FnbValidationException('A valid command operation is required.');
        }
        if ($key === '' || strlen($key) > 120) {
            throw new FnbValidationException('A valid Idempotency-Key is required.');
        }

        $requestHash = $this->fingerprint($payload);

        return DB::transaction(function () use ($context, $operation, $payload, $key, $mutation, $requestHash): array {
            $this->lockAndValidateScope($context, $operation);

            $ownerToken = (string) Str::uuid();
            $now = now();
            try {
                DB::table('fnb_idempotency_requests')->insert([
                    'website_key' => $context->websiteKey,
                    'outlet_id' => $context->outletId,
                    'terminal_id' => $context->terminalId,
                    'actor_id' => $context->actorId,
                    'operation' => $operation,
                    'idempotency_key' => $key,
                    'request_hash' => $requestHash,
                    'state' => 'processing',
                    'owner_token' => $ownerToken,
                    'heartbeat_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } catch (QueryException $exception) {
                if (! $this->isUniqueViolation($exception)) {
                    throw $exception;
                }
            }

            $request = DB::table('fnb_idempotency_requests')
                ->where('website_key', $context->websiteKey)
                ->where('outlet_id', $context->outletId)
                ->where('operation', $operation)
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();

            if ($request === null) {
                throw new FnbConflictException('Could not establish command idempotency ownership.');
            }
            if ((int) $request->actor_id !== $context->actorId
                || ($request->terminal_id === null ? null : (int) $request->terminal_id) !== $context->terminalId) {
                throw new FnbConflictException('Idempotency-Key belongs to a different actor or terminal.', [
                    'operation' => $operation,
                ]);
            }
            if (! hash_equals((string) $request->request_hash, $requestHash)) {
                throw new FnbConflictException('Idempotency-Key was already used with a different payload.', [
                    'operation' => $operation,
                ]);
            }
            if ($request->state === 'completed') {
                $response = json_decode((string) $request->response_body, true, 512, JSON_THROW_ON_ERROR);
                $response['replayed'] = true;

                return $response;
            }
            if (! hash_equals((string) $request->owner_token, $ownerToken)) {
                throw new FnbConflictException('The same command is already processing.', [
                    'operation' => $operation,
                    'retryable' => true,
                ]);
            }

            // Security proof consumption belongs to this outer transaction.
            $context->authorize($operation, $payload);
            $result = $mutation();
            $response = $this->normalizeResponse($result);

            $resourceType = $response['meta']['resource_type'] ?? 'command';
            $resourceId = isset($response['meta']['resource_id']) ? (string) $response['meta']['resource_id'] : null;
            $auditOutletId = $context->outletId > 0
                ? $context->outletId
                : (($resourceType === 'outlet' && $resourceId !== null) ? (int) $resourceId : 0);
            $this->audit->record(
                'fnb.command.'.$operation,
                $context->websiteKey,
                Admin::query()->findOrFail($context->actorId),
                $resourceType.($resourceId === null ? '' : ':'.$resourceId),
                null,
                [
                    'operation' => $operation,
                    'idempotency_key_hash' => hash('sha256', $key),
                    'resource_type' => $resourceType,
                    'resource_id' => $resourceId,
                    'version' => $response['meta']['version'] ?? null,
                ],
                $auditOutletId > 0 ? [$auditOutletId] : [],
            );

            DB::table('fnb_idempotency_requests')->where('id', $request->id)->update([
                'state' => 'completed',
                'response_code' => 200,
                'response_body' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'resource_type' => $response['meta']['resource_type'] ?? null,
                'resource_id' => isset($response['meta']['resource_id']) ? (string) $response['meta']['resource_id'] : null,
                'completed_at' => now(),
                'heartbeat_at' => now(),
                'updated_at' => now(),
            ]);

            return $response;
        }, 3);
    }

    /** @param array<string,mixed> $payload */
    public function fingerprint(array $payload): string
    {
        return hash('sha256', json_encode($this->canonicalize($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function lockAndValidateScope(FnbContext $context, string $operation): void
    {
        $actorActive = DB::table('admins')
            ->where('id', $context->actorId)
            ->where('is_active', true)
            ->where('status', 'active')
            ->whereNull('locked_at')
            ->exists();
        if (! $actorActive) {
            throw new FnbConflictException('The command actor is no longer active.');
        }

        $installation = DB::table('module_installations')
            ->where('key', 'fnb-pos')
            ->lockForUpdate()
            ->first();
        if ($installation === null || $installation->status !== 'enabled') {
            throw new FnbConflictException('F&B module is not enabled.');
        }
        if (DB::table('module_lifecycle_operations')
            ->where('module_key', 'fnb-pos')
            ->where('active_slot', 'active')
            ->lockForUpdate()
            ->exists()) {
            throw new FnbConflictException('F&B module lifecycle operation is in progress.');
        }

        if ($operation === 'onboard' && $context->outletId === 0) {
            $now = now();
            DB::table('fnb_site_settings')->insertOrIgnore([
                'website_key' => $context->websiteKey,
                'is_active' => true,
                'operational_state' => 'active',
                'default_currency' => 'VND',
                'default_timezone' => 'Asia/Ho_Chi_Minh',
                'order_prefix' => 'ORD',
                'tax_mode' => 'inclusive',
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $settings = DB::table('fnb_site_settings')
            ->where('website_key', $context->websiteKey)
            ->lockForUpdate()
            ->first();

        if ($operation === 'onboard' && $context->outletId === 0) {
            if ($settings !== null && $settings->operational_state === 'draining') {
                throw new FnbConflictException('F&B module is draining for an upgrade.');
            }

            return;
        }

        if ($context->outletId < 1 || $settings === null) {
            throw new FnbNotFoundException('F&B site or outlet is not onboarded.');
        }
        if (! (bool) $settings->is_active || $settings->operational_state !== 'active') {
            throw new FnbConflictException('F&B mutations are unavailable while the module is disabled or draining.', [
                'operational_state' => $settings->operational_state,
            ]);
        }

        $outlet = DB::table('fnb_outlets')
            ->where('website_key', $context->websiteKey)
            ->where('id', $context->outletId)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();
        if ($outlet === null) {
            throw new FnbNotFoundException('Active outlet was not found in the current website scope.');
        }

        if ($context->terminalId !== null) {
            $terminalExists = DB::table('fnb_terminals')
                ->where('website_key', $context->websiteKey)
                ->where('outlet_id', $context->outletId)
                ->where('id', $context->terminalId)
                ->where('status', 'active')
                ->exists();
            if (! $terminalExists) {
                throw new FnbNotFoundException('Active terminal was not found in the current outlet scope.');
            }
        }
    }

    /** @param array<string,mixed> $result */
    private function normalizeResponse(array $result): array
    {
        if (! array_key_exists('resource', $result)) {
            $result = ['resource' => $result];
        }

        return [
            'resource' => $result['resource'],
            'replayed' => false,
            'events' => array_values($result['events'] ?? []),
            'meta' => $result['meta'] ?? [],
        ];
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
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
