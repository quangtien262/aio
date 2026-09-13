<?php

namespace Modules\FnbPos\Services\Security;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FnbAuditLogger
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<int>  $outletIds  An empty list records website scope.
     * @param  array<string,mixed>|null  $before
     * @param  array<string,mixed>|null  $after
     */
    public function record(
        string $action,
        string $websiteKey,
        ?Admin $actor,
        Model|string|null $target = null,
        ?array $before = null,
        ?array $after = null,
        array $outletIds = [],
    ): AuditLog {
        $scopes = $outletIds === []
            ? [['type' => 'website', 'value' => $websiteKey]]
            : collect($outletIds)
                ->map(fn (int $id): array => ['type' => 'outlet', 'value' => (string) $id])
                ->unique(fn (array $scope): string => $scope['type'].':'.$scope['value'])
                ->values()
                ->all();

        return DB::transaction(function () use ($action, $websiteKey, $actor, $target, $before, $after, $scopes): AuditLog {
            // The exact scope tuple is part of the core hash-chain payload.
            $after = array_replace($after ?? [], ['audit_scope' => $scopes]);
            $log = $this->audit->record(
                $action,
                $target,
                $before,
                $after,
                $actor,
                'fnb-pos',
                $websiteKey,
            );

            if (Schema::hasTable('fnb_audit_scopes')) {
                foreach ($scopes as $scope) {
                    DB::table('fnb_audit_scopes')->insertOrIgnore([
                        'audit_log_id' => $log->id,
                        'website_key' => $websiteKey,
                        'scope_type' => $scope['type'],
                        'scope_value' => $scope['value'],
                        'created_at' => now(),
                    ]);
                }
            }

            return $log;
        }, 3);
    }
}
