<?php

namespace App\Core\Modules\Support;

use App\Core\Modules\Contracts\OperationalModuleStateProvider;
use App\Models\ModuleLifecycleOperation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Module-owned drain state; shared command locks never serialize separate branches. */
abstract class ModuleRuntimeBarrier implements OperationalModuleStateProvider
{
    protected const MODULE_KEY = '';

    protected const TABLE = '';

    public function install(): void
    {
        if (! Schema::hasTable(static::TABLE)) {
            Schema::create(static::TABLE, function (Blueprint $table): void {
                $table->string('module_key', 80)->primary();
                $table->string('operational_state', 20)->default('disabled');
                $table->uuid('operation_id')->nullable();
                $table->string('resume_state', 20)->default('disabled');
                $table->unsignedBigInteger('version')->default(1);
                $table->timestamps();
            });
        }
        $resume = DB::table('module_installations')->where('key', static::MODULE_KEY)->value('status') === 'enabled' ? 'active' : 'disabled';
        $operation = DB::table('module_lifecycle_operations')->where('module_key', static::MODULE_KEY)->where('active_slot', 'active')->whereIn('operation', ['upgrade', 'disable', 'uninstall'])->first();
        DB::table(static::TABLE)->insertOrIgnore(['module_key' => static::MODULE_KEY, 'operational_state' => $operation ? 'draining' : $resume, 'operation_id' => $operation?->operation_id, 'resume_state' => $resume, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function guard(bool $lock = true): void
    {
        if ($lock && DB::transactionLevel() < 1) {
            throw new RuntimeException('A mutation runtime barrier requires the caller transaction.');
        }
        abort_unless(Schema::hasTable(static::TABLE), 503, static::MODULE_KEY.' runtime chưa được nâng cấp.');
        $query = DB::table(static::TABLE)->where('module_key', static::MODULE_KEY);
        $runtime = ($lock ? $query->sharedLock() : $query)->first();
        abort_unless($runtime && $runtime->operational_state === 'active', 503, static::MODULE_KEY.' đang dừng hoặc thay đổi vòng đời.');
        abort_unless(DB::table('module_installations')->where('key', static::MODULE_KEY)->where('status', 'enabled')->exists(), 503, static::MODULE_KEY.' chưa được bật.');
        abort_if(DB::table('module_lifecycle_operations')->where('module_key', static::MODULE_KEY)->where('active_slot', 'active')->exists(), 503, static::MODULE_KEY.' đang thay đổi vòng đời.');
    }

    /** Exclude native command writers while exact-owned fixtures are removed. */
    public function lockForTestDataCleanup(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('Test-data cleanup must run inside a transaction.');
        }
        if (Schema::hasTable(static::TABLE)) {
            DB::table(static::TABLE)->where('module_key', static::MODULE_KEY)->lockForUpdate()->first();
        }
    }

    public function prepare(ModuleLifecycleLease $lease): void
    {
        if (! in_array($lease->operation, ['upgrade', 'disable', 'uninstall'], true) || ! Schema::hasTable(static::TABLE)) {
            return;
        }
        DB::transaction(function () use ($lease): void {
            $this->lockLease($lease);
            // Wait for every command's shared lock before checking drain blockers.
            $runtime = DB::table(static::TABLE)->where('module_key', static::MODULE_KEY)->lockForUpdate()->first();
            if (! $runtime) {
                throw new RuntimeException('Missing module runtime state.');
            }
            if ($runtime->operational_state === 'draining') {
                if ($runtime->operation_id !== $lease->operationId) {
                    throw new RuntimeException('Module drain belongs to another lifecycle operation.');
                }

                return;
            }
            DB::table(static::TABLE)->where('module_key', static::MODULE_KEY)->update(['operational_state' => 'draining', 'resume_state' => $runtime->operational_state, 'operation_id' => $lease->operationId, 'version' => $runtime->version + 1, 'updated_at' => now()]);
        }, 3);
    }

    public function commit(ModuleLifecycleLease $lease): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('Runtime transition must share the lifecycle commit transaction.');
        }
        if (! Schema::hasTable(static::TABLE)) {
            if ($lease->operation === 'uninstall') {
                return;
            }
            throw new RuntimeException('Missing module runtime schema at lifecycle commit.');
        }
        $this->lockLease($lease);
        $runtime = DB::table(static::TABLE)->where('module_key', static::MODULE_KEY)->lockForUpdate()->first();
        if (! $runtime) {
            throw new RuntimeException('Missing module runtime state at lifecycle commit.');
        }
        if (in_array($lease->operation, ['upgrade', 'disable', 'uninstall'], true)
            && ($runtime->operational_state !== 'draining' || $runtime->operation_id !== $lease->operationId)) {
            throw new RuntimeException('Module runtime changed before lifecycle commit.');
        }
        $target = match ($lease->operation) {
            'enable' => 'active',
            'upgrade' => $runtime->resume_state,
            default => 'disabled',
        };
        DB::table(static::TABLE)->where('module_key', static::MODULE_KEY)->update(['operational_state' => $target, 'resume_state' => $target, 'operation_id' => null, 'version' => $runtime->version + 1, 'updated_at' => now()]);
    }

    public function compensate(ModuleLifecycleLease $lease): void
    {
        if (! Schema::hasTable(static::TABLE)) {
            return;
        }
        DB::transaction(function () use ($lease): void {
            $this->lockLease($lease);
            $runtime = DB::table(static::TABLE)->where('module_key', static::MODULE_KEY)->lockForUpdate()->first();
            if ($runtime && $runtime->operational_state === 'draining' && $runtime->operation_id === $lease->operationId) {
                DB::table(static::TABLE)->where('module_key', static::MODULE_KEY)->update(['operational_state' => $runtime->resume_state, 'operation_id' => null, 'version' => $runtime->version + 1, 'updated_at' => now()]);
            }
        }, 3);
    }

    private function lockLease(ModuleLifecycleLease $lease): void
    {
        $operation = ModuleLifecycleOperation::query()->where('operation_id', $lease->operationId)->lockForUpdate()->firstOrFail();
        if ($lease->moduleKey !== static::MODULE_KEY || $operation->module_key !== static::MODULE_KEY
            || $operation->active_slot !== 'active' || ! hash_equals((string) $operation->owner_token, $lease->ownerToken)) {
            throw new RuntimeException('Module runtime lifecycle lease is no longer valid.');
        }
    }
}
