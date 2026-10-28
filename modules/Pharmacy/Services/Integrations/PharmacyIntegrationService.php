<?php

namespace Modules\Pharmacy\Services\Integrations;

use App\Models\Admin;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Pharmacy\Jobs\SendPharmacyIntegration;
use Modules\Pharmacy\Services\Access\PharmacyAccessService;
use Modules\Pharmacy\Services\Commands\PharmacyCommandService;
use Modules\Pharmacy\Services\PharmacyData as D;
use Modules\Pharmacy\Services\Runtime\PharmacyRuntimeState;

/** Durable outbound journal; configuration never fabricates a successful provider response. */
final class PharmacyIntegrationService
{
    public function dispatch(Admin $actor, string $action, array $input, string $key): array
    {
        $action = match ($action) {
            'config.save' => 'connection.save', default => $action
        };
        abort_unless(in_array($action, ['connection.save', 'integration.queue', 'integration.reconcile'], true), 422);

        return app(PharmacyCommandService::class)->run($actor, $action, $input, $key, function (Admin $live) use ($input, $action): array {
            $access = app(PharmacyAccessService::class);
            $p = $access->pharmacy($live, $input['pharmacy_id'] ?? '', 'pharmacy.integrations.manage', true);
            if ($action === 'connection.save') {
                $access->chain($live, (int) $p->chain_id, 'pharmacy.integrations.manage', true);
            }
            if ($action === 'integration.queue') {
                $this->source($live, $p, (string) ($input['source_type'] ?? ''), (string) ($input['source_id'] ?? ''));
            }
            if ($action === 'integration.reconcile') {
                $attempt = D::row('ph_integration_attempts', $input['attempt_id'] ?? '', $p->chain_id, $p->id);
                $this->source($live, $p, $attempt->source_type, $attempt->source_id, $attempt->attempts === 0);
            }

            return ['pharmacy' => $p, 'permission' => 'pharmacy.integrations.manage'];
        }, function (Admin $live, array $scope) use ($action, $input): array {
            return match ($action) {
                'connection.save' => $this->save($live, $scope['pharmacy'], $input),
                'integration.queue' => $this->queue($live, $scope['pharmacy'], $input),
                'integration.reconcile' => $this->requestReconcile($live, $scope['pharmacy'], $input),
            };
        });
    }

    private function save(Admin $actor, object $p, array $input): array
    {
        $v = validator($input, ['kind' => 'required|in:drug_database,erx,payment,accounting', 'name' => 'required|string|max:255', 'contract_version' => 'required|string|max:60', 'environment' => 'required|in:sandbox,production', 'transport_key' => 'required|string|max:100', 'endpoint' => 'nullable|string|max:1000', 'credentials' => 'sometimes|array|max:30', 'credentials.*' => 'nullable|string|max:8000', 'enabled' => 'sometimes|boolean', 'expected_version' => 'sometimes|integer|min:1', 'confirmation' => 'nullable|string|max:255'])->validate();
        abort_if(isset($v['credentials']) && strlen(D::encode($v['credentials'])) > 24000, 422, 'Credentials vượt giới hạn lưu trữ mã hóa.');
        $old = DB::table('ph_connections')->where('chain_id', $p->chain_id)->where('kind', $v['kind'])->lockForUpdate()->first();
        if ($old) {
            abort_unless((int) ($v['expected_version'] ?? 0) === (int) $old->version, 409, 'Kết nối đã thay đổi.');
        }
        $settings = ['environment' => $v['environment'], 'transport_key' => $v['transport_key'], 'endpoint' => $v['endpoint'] ?? null];
        $values = ['chain_id' => $p->chain_id, 'kind' => $v['kind'], 'name' => $v['name'], 'contract_version' => $v['contract_version'], 'settings' => D::encode($settings), 'status' => ($v['enabled'] ?? false) ? 'active' : 'draft'];
        if (array_key_exists('credentials', $v)) {
            $values['encrypted_credentials'] = Crypt::encryptString(D::encode($v['credentials']));
        }
        if ($v['environment'] === 'production' && ($v['enabled'] ?? false)) {
            $chain = D::row('ph_chains', (int) $p->chain_id);
            abort_unless(($v['confirmation'] ?? '') === 'ENABLE PHARMACY PRODUCTION '.$chain->code, 422, 'Cần xác nhận production theo mã chuỗi.');
        }
        $candidate = (object) array_replace((array) ($old ?? (object) []), $values);
        $registered = $this->registration($candidate);
        $values['verified_at'] = $registered['ready'] ? now() : null;
        $values['verified_by'] = $registered['ready'] ? $actor->id : null;
        $row = $old ? D::update('ph_connections', $old, $values, $v['expected_version']) : D::insert('ph_connections', $values);

        return D::resource($row) + ['readiness' => $this->readiness($row)];
    }

    private function queue(Admin $actor, object $p, array $input): array
    {
        $v = validator($input, ['connection_id' => 'required|uuid', 'source_type' => 'required|in:dispensing,prescription,receipt,sale,refund', 'source_id' => 'required|uuid'])->validate();
        $connection = D::row('ph_connections', $v['connection_id'], $p->chain_id, lock: true);
        $allowed = match ($connection->kind) {
            'drug_database' => ['dispensing', 'receipt'], 'erx' => ['prescription', 'dispensing'], 'accounting' => ['sale', 'refund'], default => []
        };
        abort_unless(in_array($v['source_type'], $allowed, true), 422, 'Nguồn không phù hợp loại connector; payment intent chưa được triển khai.');
        $source = $this->source($actor, $p, $v['source_type'], $v['source_id']);
        $hash = hash('sha256', D::encode([$connection->public_id, $connection->version, $source]));
        $existing = DB::table('ph_integration_attempts')->where('connection_id', $connection->id)->where('source_type', $v['source_type'])->where('source_id', $v['source_id'])->where('request_hash', $hash)->lockForUpdate()->first();
        if ($existing) {
            return $this->present($existing);
        }
        $event = (string) Str::uuid();
        $ready = $this->readiness($connection);
        $attempt = D::insert('ph_integration_attempts', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'connection_id' => $connection->id, 'connection_version' => $connection->version, 'source_type' => $v['source_type'], 'source_id' => $v['source_id'], 'request_hash' => $hash, 'event_id' => $event, 'actor_id' => $actor->id, 'actor_auth_version' => $actor->auth_version, 'encrypted_request' => Crypt::encryptString(D::encode(['event_id' => $event, 'contract_version' => $connection->contract_version, 'destination' => $this->destination($connection), 'source' => $source])), 'available_at' => now(), 'attempts' => 0, 'status' => $ready['ready'] ? 'pending' : 'blocked', 'error' => $ready['ready'] ? null : $ready['reason']]);

        return $this->present($attempt);
    }

    private function requestReconcile(Admin $actor, object $p, array $input): array
    {
        $attempt = D::row('ph_integration_attempts', $input['attempt_id'] ?? '', $p->chain_id, $p->id, true);
        abort_unless((int) ($input['expected_version'] ?? 0) === (int) $attempt->version, 409, 'Lần gửi đã thay đổi.');
        if (in_array($attempt->status, ['accepted', 'rejected'], true)) {
            return $this->present($attempt);
        }
        abort_if($attempt->status === 'processing' && $attempt->lease_until && now()->lessThan($attempt->lease_until), 409, 'Worker đang xử lý; không tạo lần gửi mới.');
        $connection = D::row('ph_connections', (int) $attempt->connection_id, $p->chain_id);
        if ((int) $connection->version !== (int) $attempt->connection_version) {
            $envelope = D::json(Crypt::decryptString($attempt->encrypted_request));
            abort_unless($attempt->attempts > 0 && ($envelope['destination'] ?? null) === $this->destination($connection), 409, 'Chỉ đối soát cùng đích và contract; cấu hình đích đã đổi cần xử lý riêng.');
        }
        $ready = $this->readiness($connection);
        $row = D::update('ph_integration_attempts', $attempt, ['connection_version' => $connection->version, 'actor_id' => $actor->id, 'actor_auth_version' => $actor->auth_version, 'reconcile_requested' => $attempt->attempts > 0, 'status' => $ready['ready'] ? 'pending' : 'blocked', 'lease_token' => null, 'lease_until' => null, 'available_at' => now(), 'error' => $ready['ready'] ? null : $ready['reason']], $input['expected_version']);

        return $this->present($row);
    }

    public function dispatchDue(int $limit = 100, ?float $deadline = null): int
    {
        try {
            app(PharmacyRuntimeState::class)->guard(false);
        } catch (\Throwable) {
            return 0;
        }
        $limit = config('queue.default') === 'sync' ? 1 : $limit;
        $sent = 0;
        $rows = DB::table('ph_integration_attempts')->where('status', 'pending')->where('available_at', '<=', now())->limit(min(500, max(1, $limit)))->get();
        foreach ($rows as $row) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                break;
            }
            SendPharmacyIntegration::dispatch($row->public_id)->onQueue('pharmacy-integrations');
            $sent++;
        }
        // An expired processing lease implies uncertain delivery, never automatic resend.
        DB::transaction(function (): void {
            app(PharmacyRuntimeState::class)->guard(true);
            DB::table('ph_integration_attempts')->where('status', 'processing')->where('lease_until', '<', now())->update(['status' => 'uncertain', 'error' => 'WORKER_LEASE_EXPIRED', 'lease_token' => null, 'lease_until' => null, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
        }, 3);

        return $sent;
    }

    public function process(string $attemptId): array
    {
        try {
            app(PharmacyRuntimeState::class)->guard(false);
        } catch (\Throwable) {
            return ['status' => 'blocked', 'error' => 'MODULE_NOT_ACTIVE'];
        }
        $lease = (string) Str::uuid();
        try {
            $work = DB::transaction(function () use ($attemptId, $lease): ?array {
                app(PharmacyRuntimeState::class)->guard(true);
                $a = DB::table('ph_integration_attempts')->where('public_id', $attemptId)->lockForUpdate()->first();
                abort_unless($a, 404);
                if ($a->status !== 'pending' || ($a->available_at && now()->lessThan($a->available_at))) {
                    return null;
                }
                $actor = Admin::query()->find($a->actor_id);
                abort_unless($actor && $actor->isAvailable() && ! $actor->must_change_password && (int) $actor->auth_version === (int) $a->actor_auth_version, 403, 'Ủy nhiệm actor đã thay đổi.');
                $p = app(PharmacyAccessService::class)->pharmacy($actor, (int) $a->pharmacy_id, 'pharmacy.integrations.manage', true);
                $source = $this->source($actor, $p, $a->source_type, $a->source_id, ! $a->reconcile_requested);
                if (! $a->reconcile_requested) {
                    abort_unless(hash_equals($a->request_hash, hash('sha256', D::encode([D::row('ph_connections', (int) $a->connection_id, $p->chain_id)->public_id, $a->connection_version, $source]))), 409, 'Nguồn đã đổi; cần ý định mới.');
                }
                $connection = D::row('ph_connections', (int) $a->connection_id, $p->chain_id, lock: true);
                abort_unless((int) $a->connection_version === (int) $connection->version, 409, 'Cấu hình kết nối đã đổi.');
                $ready = $this->readiness($connection);
                if (! $ready['ready']) {
                    DB::table('ph_integration_attempts')->where('id', $a->id)->update(['status' => 'blocked', 'error' => $ready['reason'], 'version' => $a->version + 1, 'updated_at' => now()]);

                    return null;
                }
                DB::table('ph_integration_attempts')->where('id', $a->id)->update(['status' => 'processing', 'attempts' => $a->attempts + 1, 'lease_token' => $lease, 'lease_until' => now()->addSeconds(90), 'version' => $a->version + 1, 'updated_at' => now()]);

                return ['attempt' => $a, 'connection' => D::resource($connection) + ['delivery_timeout_seconds' => $ready['timeout_seconds']], 'credentials' => $connection->encrypted_credentials ? D::json(Crypt::decryptString($connection->encrypted_credentials)) : [], 'envelope' => D::json(Crypt::decryptString($a->encrypted_request)), 'transport' => app($ready['class'])];
            }, 3);
        } catch (\Throwable) {
            try {
                app(PharmacyRuntimeState::class)->guard(false);
            } catch (\Throwable) {
                return ['status' => 'blocked', 'error' => 'MODULE_NOT_ACTIVE'];
            }
            DB::table('ph_integration_attempts')->where('public_id', $attemptId)->where('status', 'pending')->update(['status' => 'blocked', 'error' => 'AUTHORITY_OR_CONTEXT_CHANGED', 'version' => DB::raw('version + 1'), 'updated_at' => now()]);

            return ['status' => 'blocked'];
        }
        if (! $work) {
            return ['status' => DB::table('ph_integration_attempts')->where('public_id', $attemptId)->value('status')];
        }
        abort_unless(DB::transactionLevel() === 0, 500, 'Không gọi provider trong transaction.');
        try {
            $result = $work['attempt']->reconcile_requested ? $work['transport']->reconcile($work['connection'], $work['envelope'], $work['credentials']) : $work['transport']->send($work['connection'], $work['envelope'], $work['credentials']);
            $v = validator($result, ['status' => 'required|in:accepted,rejected,uncertain', 'external_id' => 'nullable|string|max:150', 'response_code' => 'nullable|string|max:60'])->validate();
        } catch (\Throwable) {
            $v = ['status' => 'uncertain', 'response_code' => 'TRANSPORT_UNCERTAIN'];
        }
        DB::transaction(function () use ($attemptId, $lease, $v): void {
            $a = DB::table('ph_integration_attempts')->where('public_id', $attemptId)->lockForUpdate()->first();
            if (! $a || $a->lease_token !== $lease) {
                return;
            }
            DB::table('ph_integration_attempts')->where('id', $a->id)->update(['status' => $v['status'], 'external_id' => $v['external_id'] ?? null, 'response_code' => $v['response_code'] ?? null, 'result_reference' => D::encode($v), 'error' => $v['status'] === 'uncertain' ? 'LOOKUP_REQUIRED' : null, 'lease_token' => null, 'lease_until' => null, 'last_polled_at' => now(), 'version' => $a->version + 1, 'updated_at' => now()]);
        }, 3);

        return $v;
    }

    public function readiness(object $connection): array
    {
        $registered = $this->registration($connection);
        if (! $registered['ready']) {
            return $registered;
        }
        if ($connection->status !== 'active' || ! $connection->verified_at) {
            return ['ready' => false, 'reason' => 'CONNECTION_NOT_APPROVED'];
        }
        if (! config('pharmacy.network_enabled')) {
            return ['ready' => false, 'reason' => 'NETWORK_DISABLED'];
        }
        if ((D::json($connection->settings)['environment'] ?? '') === 'production' && ! config('pharmacy.production_enabled')) {
            return ['ready' => false, 'reason' => 'PRODUCTION_DISABLED'];
        }

        return $registered;
    }

    private function registration(object $connection): array
    {
        $settings = D::json($connection->settings);
        $definition = config('pharmacy.transports.'.($settings['transport_key'] ?? ''));
        if (! is_array($definition) || ! ($definition['verified'] ?? false) || ! is_string($definition['class'] ?? null) || ! is_subclass_of($definition['class'], PharmacyTransport::class)) {
            return ['ready' => false, 'reason' => 'TRANSPORT_NOT_REGISTERED'];
        }
        if (! is_int($definition['timeout_seconds'] ?? null) || $definition['timeout_seconds'] < 1 || $definition['timeout_seconds'] > 15) {
            return ['ready' => false, 'reason' => 'TRANSPORT_TIMEOUT_NOT_BOUNDED'];
        }
        if (! in_array($connection->contract_version, $definition['contract_versions'] ?? [], true) || ! in_array($settings['environment'] ?? '', $definition['environments'] ?? [], true) || ! in_array($connection->kind, $definition['kinds'] ?? [], true)) {
            return ['ready' => false, 'reason' => 'CONTRACT_NOT_VERIFIED'];
        }
        $endpoint = parse_url((string) ($settings['endpoint'] ?? ''));
        $host = strtolower($endpoint['host'] ?? '');
        if (($endpoint['scheme'] ?? '') !== 'https' || $host === '' || isset($endpoint['user']) || isset($endpoint['pass']) || isset($endpoint['fragment']) || isset($endpoint['query']) || (isset($endpoint['port']) && $endpoint['port'] !== 443) || filter_var($host, FILTER_VALIDATE_IP) || ! in_array($host, config('pharmacy.allowed_provider_hosts', []), true) || ! in_array($host, $definition['hosts'] ?? [], true)) {
            return ['ready' => false, 'reason' => 'ENDPOINT_NOT_ALLOWLISTED'];
        }

        return ['ready' => true, 'reason' => null, 'class' => $definition['class'], 'timeout_seconds' => $definition['timeout_seconds']];
    }

    private function source(Admin $actor, object $p, string $type, string $id, bool $requireReady = true): array
    {
        [$table, $permission, $statuses] = match ($type) {
            'dispensing' => ['ph_dispensings', 'pharmacy.dispensing.view', ['handed_over']],
            'prescription' => ['ph_prescriptions', 'pharmacy.prescription.view', ['verified']],
            'receipt' => ['ph_receipts', 'pharmacy.receipt.manage', ['quarantine', 'released', 'rejected']],
            'sale' => ['ph_sales', 'pharmacy.sale.view', ['paid', 'partially_refunded', 'refunded']],
            'refund' => ['ph_refunds', 'pharmacy.return.manage', ['paid']], default => abort(422, 'Loại nguồn chưa hỗ trợ.'),
        };
        app(PharmacyAccessService::class)->pharmacy($actor, (int) $p->id, $permission);
        $row = D::row($table, $id, $p->chain_id, $p->id);
        abort_unless(! $requireReady || in_array($row->status, $statuses, true), 409, 'Nguồn chưa đạt trạng thái được phép liên thông.');
        $snapshot = array_intersect_key((array) $row, array_flip(['public_id', 'version', 'status', 'quantity', 'batch_id', 'stock_operation_id', 'total_minor', 'amount_minor', 'handed_over_at', 'issued_at', 'expires_at', 'verification_method']));
        $snapshot['source_type'] = $type;
        $snapshot['pharmacy_id'] = $p->public_id;
        if ($type === 'dispensing') {
            $snapshot['lines'] = DB::table('ph_dispensing_lines as l')->join('ph_products as p', 'p.id', '=', 'l.product_id')->where('l.dispensing_id', $row->id)->where('l.status', 'active')->get(['l.public_id', 'l.quantity', 'l.unit', 'p.registration_number'])->map(fn ($l) => (array) $l)->all();
        }

        return $snapshot;
    }

    private function destination(object $connection): array
    {
        $settings = D::json($connection->settings);

        return ['kind' => $connection->kind, 'contract_version' => $connection->contract_version, 'environment' => $settings['environment'] ?? null, 'transport_key' => $settings['transport_key'] ?? null, 'endpoint' => $settings['endpoint'] ?? null];
    }

    private function present(object $row): array
    {
        return array_intersect_key((array) $row, array_flip(['public_id', 'status', 'version', 'source_type', 'source_id', 'event_id', 'external_id', 'response_code', 'error', 'attempts', 'created_at', 'updated_at'])) + ['id' => $row->public_id];
    }
}
