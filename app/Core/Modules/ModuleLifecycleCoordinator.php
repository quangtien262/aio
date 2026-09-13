<?php

namespace App\Core\Modules;

use App\Core\Modules\Support\ModuleLifecycleLease;
use App\Models\ModuleLifecycleOperation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class ModuleLifecycleCoordinator
{
    public function begin(
        string $moduleKey,
        string $operation,
        ?string $fromVersion,
        ?string $targetVersion,
        ?int $initiatedBy = null,
        array $context = [],
    ): ModuleLifecycleLease {
        $this->assertOperation($operation);

        return $this->withAdvisoryLock($moduleKey, function () use (
            $moduleKey,
            $operation,
            $fromVersion,
            $targetVersion,
            $initiatedBy,
            $context,
        ): ModuleLifecycleLease {
            $operationId = (string) Str::uuid();
            $ownerToken = (string) Str::uuid();

            try {
                DB::transaction(function () use (
                    $moduleKey,
                    $operation,
                    $fromVersion,
                    $targetVersion,
                    $initiatedBy,
                    $context,
                    $operationId,
                    $ownerToken,
                ): void {
                    $active = ModuleLifecycleOperation::query()
                        ->where('module_key', $moduleKey)
                        ->where('active_slot', ModuleLifecycleOperation::ACTIVE_SLOT)
                        ->lockForUpdate()
                        ->first();

                    if ($active !== null) {
                        throw new ConflictHttpException(sprintf(
                            'Module [%s] already has lifecycle operation [%s] in progress.',
                            $moduleKey,
                            $active->operation_id,
                        ));
                    }

                    ModuleLifecycleOperation::query()->create([
                        'operation_id' => $operationId,
                        'module_key' => $moduleKey,
                        'operation' => $operation,
                        'status' => 'started',
                        'active_slot' => ModuleLifecycleOperation::ACTIVE_SLOT,
                        'from_version' => $fromVersion,
                        'target_version' => $targetVersion,
                        'owner_token' => $ownerToken,
                        'initiated_by' => $initiatedBy,
                        'context' => $context,
                        'started_at' => now(),
                        'heartbeat_at' => now(),
                    ]);
                }, 3);
            } catch (QueryException $exception) {
                if ($this->isUniqueConstraintViolation($exception)) {
                    throw new ConflictHttpException("Module [{$moduleKey}] already has a lifecycle operation in progress.", $exception);
                }

                throw $exception;
            }

            return new ModuleLifecycleLease(
                $operationId,
                $ownerToken,
                $moduleKey,
                $operation,
                $fromVersion,
                $targetVersion,
            );
        }, true);
    }

    public function resume(string $operationId, ?int $initiatedBy = null, int $staleAfterSeconds = 300): ModuleLifecycleLease
    {
        $operation = ModuleLifecycleOperation::query()->where('operation_id', $operationId)->firstOrFail();

        return $this->withAdvisoryLock($operation->module_key, function () use (
            $operationId,
            $initiatedBy,
            $staleAfterSeconds,
        ): ModuleLifecycleLease {
            return DB::transaction(function () use ($operationId, $initiatedBy, $staleAfterSeconds): ModuleLifecycleLease {
                $operation = ModuleLifecycleOperation::query()
                    ->where('operation_id', $operationId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($operation->active_slot !== ModuleLifecycleOperation::ACTIVE_SLOT) {
                    throw new ConflictHttpException('Only a non-terminal lifecycle operation can be resumed.');
                }

                $isRecoverable = $operation->status === 'recovery_required'
                    || $operation->heartbeat_at === null
                    || $operation->heartbeat_at->lte(now()->subSeconds($staleAfterSeconds));

                if (! $isRecoverable) {
                    throw new ConflictHttpException('Lifecycle operation is still owned by an active worker.');
                }

                $ownerToken = (string) Str::uuid();
                $operation->forceFill([
                    'owner_token' => $ownerToken,
                    'initiated_by' => $initiatedBy ?? $operation->initiated_by,
                    'status' => 'resuming',
                    'heartbeat_at' => now(),
                    'error' => null,
                ])->save();

                return new ModuleLifecycleLease(
                    $operation->operation_id,
                    $ownerToken,
                    $operation->module_key,
                    $operation->operation,
                    $operation->from_version,
                    $operation->target_version,
                    true,
                );
            }, 3);
        }, true);
    }

    public function heartbeat(ModuleLifecycleLease $lease, string $status, array $context = []): void
    {
        DB::transaction(function () use ($lease, $status, $context): void {
            $operation = $this->lockOwnedOperation($lease);
            $operation->forceFill([
                'status' => $status,
                'heartbeat_at' => now(),
                'context' => $context === [] ? $operation->context : array_replace_recursive($operation->context ?? [], $context),
            ])->save();
        }, 3);
    }

    /**
     * Persisted in the same database transaction as the installation/security
     * state. A recovery worker can therefore distinguish "commit succeeded,
     * completion marker lost" from a genuinely incomplete lifecycle run.
     *
     * @param  array<string,mixed>  $state
     */
    public function markStateCommitted(ModuleLifecycleLease $lease, array $state): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('Lifecycle commit marker must share the module state transaction.');
        }

        $operation = $this->lockOwnedOperation($lease);
        $context = $operation->context ?? [];
        $context['lifecycle_commit'] = [
            'state' => $state,
            'committed_at' => now()->toIso8601String(),
        ];
        $operation->forceFill([
            'status' => 'state_committed',
            'heartbeat_at' => now(),
            'context' => $context,
        ])->save();
    }

    public function complete(ModuleLifecycleLease $lease, array $context = []): void
    {
        try {
            DB::transaction(function () use ($lease, $context): void {
                $operation = $this->lockOwnedOperation($lease);
                $operation->forceFill([
                    'status' => 'completed',
                    'active_slot' => null,
                    'heartbeat_at' => now(),
                    'completed_at' => now(),
                    'context' => $context === [] ? $operation->context : array_replace_recursive($operation->context ?? [], $context),
                    'error' => null,
                ])->save();
            }, 3);
        } finally {
            $this->releaseAdvisoryLock($lease->moduleKey);
        }
    }

    public function fail(ModuleLifecycleLease $lease, Throwable $exception, bool $recoveryRequired): void
    {
        try {
            DB::transaction(function () use ($lease, $exception, $recoveryRequired): void {
                $operation = $this->lockOwnedOperation($lease);
                $operation->forceFill([
                    'status' => $recoveryRequired ? 'recovery_required' : 'failed',
                    'active_slot' => $recoveryRequired ? ModuleLifecycleOperation::ACTIVE_SLOT : null,
                    'heartbeat_at' => now(),
                    'completed_at' => $recoveryRequired ? null : now(),
                    'error' => [
                        'type' => $exception::class,
                        'message_hash' => hash('sha256', $exception->getMessage()),
                        'recorded_at' => now()->toIso8601String(),
                    ],
                ])->save();
            }, 3);
        } finally {
            // A failed worker must relinquish the process lock so an explicitly
            // audited resume can acquire it while the durable sentinel remains.
            $this->releaseAdvisoryLock($lease->moduleKey);
        }
    }

    public function active(string $moduleKey): ?ModuleLifecycleOperation
    {
        return ModuleLifecycleOperation::query()
            ->where('module_key', $moduleKey)
            ->where('active_slot', ModuleLifecycleOperation::ACTIVE_SLOT)
            ->first();
    }

    public function recordPostHookFailure(string $operationId, Throwable $exception): void
    {
        DB::transaction(function () use ($operationId, $exception): void {
            $operation = ModuleLifecycleOperation::query()
                ->where('operation_id', $operationId)
                ->lockForUpdate()
                ->firstOrFail();
            $context = $operation->context ?? [];
            $context['post_hook'] = [
                'status' => 'degraded',
                'type' => $exception::class,
                'message_hash' => hash('sha256', $exception->getMessage()),
                'recorded_at' => now()->toIso8601String(),
            ];
            $operation->forceFill(['context' => $context])->save();
        }, 3);
    }

    private function lockOwnedOperation(ModuleLifecycleLease $lease): ModuleLifecycleOperation
    {
        $operation = ModuleLifecycleOperation::query()
            ->where('operation_id', $lease->operationId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($operation->active_slot !== ModuleLifecycleOperation::ACTIVE_SLOT
            || ! hash_equals($operation->owner_token, $lease->ownerToken)) {
            throw new RuntimeException('Lifecycle operation lease is no longer valid.');
        }

        return $operation;
    }

    private function assertOperation(string $operation): void
    {
        if (! in_array($operation, ['install', 'upgrade', 'enable', 'disable', 'uninstall'], true)) {
            throw new RuntimeException("Unsupported module lifecycle operation [{$operation}].");
        }
    }

    private function withAdvisoryLock(string $moduleKey, callable $callback, bool $retain = false): mixed
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $lockName = $this->advisoryLockName($moduleKey);
        $acquired = false;
        $succeeded = false;

        try {
            if ($driver === 'mysql') {
                $row = $connection->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName]);
                $acquired = (int) ($row->acquired ?? 0) === 1;
            } elseif ($driver === 'pgsql') {
                $row = $connection->selectOne('SELECT pg_try_advisory_lock(hashtext(?)) AS acquired', [$lockName]);
                $acquired = (bool) ($row->acquired ?? false);
            } else {
                // SQLite and other local/test drivers rely on the durable unique sentinel.
                $acquired = true;
            }

            if (! $acquired) {
                throw new ConflictHttpException("Module [{$moduleKey}] lifecycle is locked by another process.");
            }

            $result = $callback();
            $succeeded = true;

            return $result;
        } finally {
            if ($acquired && (! $retain || ! $succeeded) && $driver === 'mysql') {
                $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            } elseif ($acquired && (! $retain || ! $succeeded) && $driver === 'pgsql') {
                $connection->selectOne('SELECT pg_advisory_unlock(hashtext(?)) AS released', [$lockName]);
            }
        }
    }

    private function releaseAdvisoryLock(string $moduleKey): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $lockName = $this->advisoryLockName($moduleKey);

        if ($driver === 'mysql') {
            $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        } elseif ($driver === 'pgsql') {
            $connection->selectOne('SELECT pg_advisory_unlock(hashtext(?)) AS released', [$lockName]);
        }
    }

    private function advisoryLockName(string $moduleKey): string
    {
        // MySQL caps user lock names at 64 bytes.
        return 'aio:mod:'.substr(hash('sha256', $moduleKey), 0, 56);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            || str_contains(strtolower($exception->getMessage()), 'unique constraint');
    }
}
