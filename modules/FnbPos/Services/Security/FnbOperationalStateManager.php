<?php

namespace Modules\FnbPos\Services\Security;

use App\Core\Modules\Contracts\OperationalModuleStateProvider;
use App\Core\Modules\Support\ModuleLifecycleLease;
use App\Models\Admin;
use App\Models\ModuleLifecycleOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class FnbOperationalStateManager implements OperationalModuleStateProvider
{
    private const CONTEXT_KEY = 'operational_state';

    public function __construct(private readonly FnbAuditLogger $audit) {}

    public function prepare(ModuleLifecycleLease $lease): void
    {
        if (! in_array($lease->operation, ['upgrade', 'disable'], true)
            || ! Schema::hasTable('fnb_site_settings')) {
            return;
        }

        DB::transaction(function () use ($lease): void {
            $operation = $this->lockOperation($lease);
            $context = $operation->context ?? [];
            $stateContext = $context[self::CONTEXT_KEY] ?? null;

            if (is_array($stateContext) && array_key_exists('drained_websites', $stateContext)) {
                $websiteKeys = array_values(array_filter(
                    $stateContext['drained_websites'],
                    fn (mixed $value): bool => is_string($value) && $value !== '',
                ));

                if ($websiteKeys !== []) {
                    $invalid = DB::table('fnb_site_settings')
                        ->whereIn('website_key', $websiteKeys)
                        ->whereNotIn('operational_state', ['draining'])
                        ->lockForUpdate()
                        ->exists();

                    if ($invalid) {
                        throw new RuntimeException('Prepared F&B website state changed before lifecycle recovery.');
                    }
                }

                return;
            }

            $websiteKeys = DB::table('fnb_site_settings')
                ->where('is_active', true)
                ->where('operational_state', 'active')
                ->orderBy('website_key')
                ->lockForUpdate()
                ->pluck('website_key')
                ->map(fn (mixed $key): string => (string) $key)
                ->all();

            if ($websiteKeys !== []) {
                DB::table('fnb_site_settings')
                    ->whereIn('website_key', $websiteKeys)
                    ->where('operational_state', 'active')
                    ->update([
                        'operational_state' => 'draining',
                        'version' => DB::raw('version + 1'),
                        'updated_at' => now(),
                    ]);
            }

            $context[self::CONTEXT_KEY] = [
                'drained_websites' => $websiteKeys,
                'prepared_at' => now()->toIso8601String(),
            ];
            $operation->forceFill(['context' => $context, 'heartbeat_at' => now()])->save();
        }, 3);
    }

    public function commit(ModuleLifecycleLease $lease): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('F&B operational state commit must share the lifecycle state transaction.');
        }

        if (! Schema::hasTable('fnb_site_settings')) {
            return;
        }

        $operation = $this->lockOperation($lease);
        $query = DB::table('fnb_site_settings');

        if ($lease->operation === 'enable') {
            $query->where('is_active', true)
                ->where('operational_state', 'disabled')
                ->update([
                    'operational_state' => 'active',
                    'version' => DB::raw('version + 1'),
                    'updated_at' => now(),
                ]);

            return;
        }

        if (! in_array($lease->operation, ['upgrade', 'disable'], true)) {
            return;
        }

        $websiteKeys = $this->drainedWebsiteKeys($operation);
        if ($websiteKeys === []) {
            return;
        }

        $targetState = $lease->operation === 'disable' ? 'disabled' : 'active';
        $updated = $query->whereIn('website_key', $websiteKeys)
            ->where('operational_state', 'draining')
            ->update([
                'operational_state' => $targetState,
                'version' => DB::raw('version + 1'),
                'updated_at' => now(),
            ]);

        if ($updated !== count($websiteKeys)) {
            throw new RuntimeException('F&B operational state changed during lifecycle commit.');
        }
    }

    public function compensate(ModuleLifecycleLease $lease): void
    {
        if (! in_array($lease->operation, ['upgrade', 'disable'], true)
            || ! Schema::hasTable('fnb_site_settings')) {
            return;
        }

        DB::transaction(function () use ($lease): void {
            $operation = $this->lockOperation($lease);
            $websiteKeys = $this->drainedWebsiteKeys($operation);

            if ($websiteKeys !== []) {
                DB::table('fnb_site_settings')
                    ->whereIn('website_key', $websiteKeys)
                    ->where('operational_state', 'draining')
                    ->update([
                        'operational_state' => 'active',
                        'version' => DB::raw('version + 1'),
                        'updated_at' => now(),
                    ]);
            }

            $context = $operation->context ?? [];
            $context[self::CONTEXT_KEY]['compensated_at'] = now()->toIso8601String();
            $operation->forceFill(['context' => $context, 'heartbeat_at' => now()])->save();

            $actor = $operation->initiated_by === null ? null : Admin::query()->find($operation->initiated_by);
            foreach ($websiteKeys as $websiteKey) {
                $this->audit->record(
                    'fnb.lifecycle.'.$lease->operation.'_failed_restored',
                    $websiteKey,
                    $actor,
                    $operation,
                    before: ['operational_state' => 'draining'],
                    after: ['operational_state' => 'active', 'operation_id' => $lease->operationId],
                );
            }
        }, 3);
    }

    private function lockOperation(ModuleLifecycleLease $lease): ModuleLifecycleOperation
    {
        $operation = ModuleLifecycleOperation::query()
            ->where('operation_id', $lease->operationId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($operation->active_slot !== ModuleLifecycleOperation::ACTIVE_SLOT
            || ! hash_equals((string) $operation->owner_token, $lease->ownerToken)) {
            throw new RuntimeException('F&B lifecycle operation lease is no longer valid.');
        }

        return $operation;
    }

    /** @return list<string> */
    private function drainedWebsiteKeys(ModuleLifecycleOperation $operation): array
    {
        $keys = $operation->context[self::CONTEXT_KEY]['drained_websites'] ?? [];

        return array_values(array_unique(array_filter(
            is_array($keys) ? $keys : [],
            fn (mixed $value): bool => is_string($value) && $value !== '',
        )));
    }
}
