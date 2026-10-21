<?php

namespace Modules\Manufacturing\Services\Commands;

use App\Models\Admin;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Manufacturing\Services\Access\ManufacturingAccessService;
use Modules\Manufacturing\Services\Runtime\ManufacturingRuntimeState;

class ManufacturingCommandService
{
    public function run(Admin $actor, string $operation, array $payload, string $key, callable $authorize, callable $mutate): array
    {
        abort_unless(preg_match('/^[A-Za-z0-9:_.-]{8,120}$/D', $key), 422, 'Cần Idempotency-Key hợp lệ (8–120 ký tự).');

        return DB::transaction(function () use ($actor, $operation, $payload, $key, $authorize, $mutate): array {
            $live = app(ManufacturingAccessService::class)->actor($actor, true);
            $runtime = app(ManufacturingRuntimeState::class);
            in_array($operation, ['runtime.pause', 'runtime.resume'], true) ? $runtime->guardMaintenanceCommand() : $runtime->guard(true);
            $scope = $authorize($live);
            $authorization = $this->authorization($scope);
            $fingerprint = hash('sha256', $operation.'|'.$this->canonical($payload).'|'.$this->canonical($authorization));
            DB::table('mfg_commands')->insertOrIgnore([
                'public_id' => (string) Str::uuid(), 'actor_id' => $live->id, 'company_id' => $authorization['company_id'], 'factory_id' => $authorization['factory_id'],
                'command_key' => $key, 'operation' => $operation, 'fingerprint' => $fingerprint, 'authorization' => json_encode($authorization, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $command = DB::table('mfg_commands')->where('actor_id', $live->id)->where('command_key', $key)->lockForUpdate()->first();
            abort_unless(hash_equals($command->fingerprint, $fingerprint), 409, 'Idempotency-Key đã được dùng cho nội dung hoặc phạm vi khác.');
            if ($command->status === 'committed') {
                return json_decode($command->result_ref, true, flags: JSON_THROW_ON_ERROR) + ['replayed' => true, 'command_id' => $command->public_id];
            }
            $result = $mutate($live, $scope);
            $ref = $this->reference($result);
            app(AuditLogger::class)->record('manufacturing.'.$operation, 'manufacturing-command', null, $ref, $live, 'manufacturing', '', 'manufacturing');
            DB::table('mfg_commands')->where('id', $command->id)->update(['status' => 'committed', 'result_ref' => json_encode($ref, JSON_THROW_ON_ERROR), 'updated_at' => now()]);

            return $result + ['replayed' => false, 'command_id' => $command->public_id];
        }, 3);
    }

    public function status(Admin $actor, string $key): array
    {
        $access = app(ManufacturingAccessService::class);
        $live = $access->actor($actor);
        app(ManufacturingRuntimeState::class)->guard(false);
        $row = DB::table('mfg_commands')->where('actor_id', $live->id)->where('command_key', $key)->first();
        if (! $row) {
            $access->permission($live, 'manufacturing.view');

            return ['status' => 'unknown', 'resource' => null];
        }
        $scope = json_decode($row->authorization, true, flags: JSON_THROW_ON_ERROR);
        if ($scope['factory_id'] !== null) {
            $access->factory($live, (int) $scope['factory_id'], $scope['permission']);
        } elseif ($scope['company_id'] !== null) {
            $access->company($live, (int) $scope['company_id'], $scope['permission']);
        } else {
            $access->permission($live, $scope['permission']);
        }

        return ['status' => $row->status, 'command_id' => $row->public_id, 'resource' => $row->result_ref === null ? null : json_decode($row->result_ref, true, flags: JSON_THROW_ON_ERROR)];
    }

    public function emit(array $scope, string $eventType, string $aggregateType, string $aggregateId, int $version, array $payload = [], string $destination = 'audit'): string
    {
        abort_unless(DB::transactionLevel() > 0, 500, 'Outbox phải ghi trong cùng transaction nghiệp vụ.');
        $event = (string) Str::uuid();
        $auth = $this->authorization($scope);
        DB::table('mfg_outbox')->insert(['event_id' => $event, 'company_id' => $auth['company_id'], 'factory_id' => $auth['factory_id'], 'event_type' => $eventType,
            'aggregate_type' => $aggregateType, 'aggregate_id' => $aggregateId, 'aggregate_version' => $version, 'schema_version' => 1, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'destination' => $destination, 'available_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return $event;
    }

    public function consume(string $consumer, string $eventId, callable $apply): bool
    {
        return DB::transaction(function () use ($consumer, $eventId, $apply): bool {
            app(ManufacturingRuntimeState::class)->guard(true);
            if (! DB::table('mfg_inbox')->insertOrIgnore(['consumer' => $consumer, 'event_id' => $eventId, 'processed_at' => now()])) {
                return false;
            }
            $apply();

            return true;
        }, 3);
    }

    private function authorization(mixed $scope): array
    {
        $factory = is_array($scope) ? ($scope['factory'] ?? null) : $scope;
        $company = is_array($scope) ? ($scope['company'] ?? null) : null;

        return ['company_id' => $company?->id ?? $factory?->company_id, 'factory_id' => $factory?->id, 'permission' => is_array($scope) ? ($scope['permission'] ?? 'manufacturing.view') : 'manufacturing.view'];
    }

    private function reference(array $result): array
    {
        return array_intersect_key($result, array_flip(['id', 'public_id', 'company_id', 'factory_id', 'resource', 'status', 'version', 'code', 'created_count', 'accepted_count', 'quantity', 'event_id']));
    }

    private function canonical(mixed $value): string
    {
        $sort = function ($item) use (&$sort) {
            if (! is_array($item)) {
                return $item;
            }
            if (! array_is_list($item)) {
                ksort($item);
            }

            return array_map($sort, $item);
        };

        return json_encode($sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }
}
