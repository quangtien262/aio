<?php

namespace Modules\Pharmacy\Services\Commands;

use App\Models\Admin;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Pharmacy\Services\Access\PharmacyAccessService;
use Modules\Pharmacy\Services\PharmacyData as D;
use Modules\Pharmacy\Services\Runtime\PharmacyRuntimeState;

final class PharmacyCommandService
{
    public function run(Admin $actor, string $operation, array $payload, string $key, callable $authorize, callable $effect): array
    {
        abort_unless(preg_match('/^[A-Za-z0-9:_.-]{8,120}$/D', $key), 422, 'Cần Idempotency-Key hợp lệ (8–120 ký tự).');

        return DB::transaction(function () use ($actor, $operation, $payload, $key, $authorize, $effect): array {
            $live = app(PharmacyAccessService::class)->actor($actor, true);
            app(PharmacyRuntimeState::class)->guard(true);
            $scope = $authorize($live);
            $auth = $this->authorization($scope);
            $fingerprint = hash('sha256', $operation.'|'.$this->canonical($payload).'|'.$this->canonical($auth));
            DB::table('ph_commands')->insertOrIgnore(['public_id' => (string) Str::uuid(), 'actor_id' => $live->id, 'chain_id' => $auth['chain_id'], 'pharmacy_id' => $auth['pharmacy_id'], 'command_key' => $key, 'operation' => $operation, 'fingerprint' => $fingerprint, 'authorization' => D::encode($auth), 'status' => 'pending', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $command = DB::table('ph_commands')->where('actor_id', $live->id)->where('command_key', $key)->lockForUpdate()->first();
            abort_unless($command && hash_equals($command->fingerprint, $fingerprint), 409, 'Mã yêu cầu đã dùng cho nội dung hoặc phạm vi khác.');
            if ($command->status === 'committed') {
                return D::json($command->result_ref) + ['replayed' => true, 'command_id' => $command->public_id];
            }
            $result = $effect($live, $scope);
            $ref = $this->reference($result);
            app(AuditLogger::class)->record('pharmacy.'.$operation, 'pharmacy-command', null, $ref, $live, 'pharmacy', '', 'pharmacy');
            DB::table('ph_commands')->where('id', $command->id)->update(['status' => 'committed', 'result_ref' => D::encode($ref), 'updated_at' => now()]);

            return $result + ['replayed' => false, 'command_id' => $command->public_id];
        }, 3);
    }

    public function status(Admin $actor, string $key): array
    {
        $access = app(PharmacyAccessService::class);
        $actor = $access->actor($actor);
        $access->permission($actor, 'pharmacy.view');
        $row = DB::table('ph_commands')->where('actor_id', $actor->id)->where('command_key', $key)->first();
        if (! $row) {
            return ['status' => 'unknown', 'resource' => null];
        }
        $auth = D::json($row->authorization);
        if ($auth['pharmacy_id']) {
            $access->pharmacy($actor, (int) $auth['pharmacy_id'], $auth['permission']);
        } elseif ($auth['chain_id']) {
            $access->chain($actor, (int) $auth['chain_id'], $auth['permission']);
        } else {
            $access->permission($actor, $auth['permission']);
        }

        return ['status' => $row->status, 'command_id' => $row->public_id, 'resource' => D::json($row->result_ref)];
    }

    public function emit(mixed $scope, string $type, string $aggregateType, string $aggregateId, int $version, array $payload = [], string $destination = 'audit'): string
    {
        abort_unless(DB::transactionLevel() > 0, 500, 'Outbox cần transaction nghiệp vụ.');
        $auth = $this->authorization($scope);
        $id = (string) Str::uuid();
        DB::table('ph_outbox')->insert(['public_id' => (string) Str::uuid(), 'event_id' => $id, 'chain_id' => $auth['chain_id'], 'pharmacy_id' => $auth['pharmacy_id'], 'event_type' => $type, 'aggregate_type' => $aggregateType, 'aggregate_id' => $aggregateId, 'aggregate_version' => $version, 'schema_version' => 1, 'payload' => D::encode($payload), 'destination' => $destination, 'status' => 'pending', 'version' => 1, 'available_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    public function consume(string $consumer, string $eventId, callable $apply): bool
    {
        return DB::transaction(function () use ($consumer, $eventId, $apply): bool {
            app(PharmacyRuntimeState::class)->guard(true);
            if (! DB::table('ph_inbox')->insertOrIgnore(['consumer' => $consumer, 'event_id' => $eventId, 'processed_at' => now()])) {
                return false;
            }
            $apply();

            return true;
        }, 3);
    }

    private function authorization(mixed $scope): array
    {
        $pharmacy = is_array($scope) ? ($scope['pharmacy'] ?? null) : $scope;
        $chain = is_array($scope) ? ($scope['chain'] ?? null) : null;

        return ['chain_id' => $chain?->id ?? $pharmacy?->chain_id, 'pharmacy_id' => $pharmacy?->id, 'permission' => is_array($scope) ? ($scope['permission'] ?? 'pharmacy.view') : 'pharmacy.view'];
    }

    private function reference(array $result): array
    {
        return array_intersect_key($result, array_flip(['id', 'public_id', 'chain_id', 'pharmacy_id', 'resource', 'status', 'version', 'total_minor', 'quantity', 'created_count', 'event_id', 'sale_id', 'dispensing_id', 'hold_id', 'transfer_id', 'document_id']));
    }

    private function canonical(mixed $value): string
    {
        $sort = function ($item) use (&$sort) {
            if (! is_array($item)) {
                return $item;
            } if (! array_is_list($item)) {
                ksort($item);
            }

            return array_map($sort, $item);
        };

        return json_encode($sort($value), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE);
    }
}
