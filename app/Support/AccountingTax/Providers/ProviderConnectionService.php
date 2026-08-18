<?php

namespace App\Support\AccountingTax\Providers;

use App\Models\AcctProviderConnection;
use App\Models\Admin;
use App\Models\ModuleInstallation;
use App\Support\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProviderConnectionService
{
    private const SAFE_SETTING_KEYS = [
        'authorization_prefix',
        'login_path',
        'series_path',
        'invoice_path',
        'signing_mode',
        'invoice_type',
        'msmi_prefix',
        'default_series',
        'scheduled_sync_enabled',
        'sync_lookback_days',
    ];

    public function __construct(
        private readonly ProviderConnectionPolicy $policy,
        private readonly ProviderFactory $factory,
        private readonly AuditLogger $audit,
    ) {}

    public function save(array $payload, ?AcctProviderConnection $connection, ?int $adminId): AcctProviderConnection
    {
        $before = $connection?->exists ? $this->auditSnapshot($connection) : null;
        $saved = DB::transaction(function () use ($payload, $connection, $adminId): AcctProviderConnection {
            $connection ??= new AcctProviderConnection;
            $environment = (string) ($payload['environment'] ?? $connection->environment ?? 'sandbox');
            $baseUrl = $this->policy->normalizeAndValidateBaseUrl(
                (string) ($payload['base_url'] ?? $connection->base_url ?? ''),
                $environment,
            );
            $channel = (string) ($payload['channel'] ?? $connection->channel ?? 'outbound');
            $credentials = array_replace($connection->credentials ?? [], $payload['credentials'] ?? []);

            $this->validateCredentials($channel, $credentials);
            $settings = Arr::only(
                array_replace($connection->settings ?? [], $payload['settings'] ?? []),
                self::SAFE_SETTING_KEYS,
            );
            if (data_get($connection->settings, 'production_contract_version')) {
                $settings['production_contract_version'] = data_get(
                    $connection->settings,
                    'production_contract_version',
                );
            }
            $this->validateSettings($settings);
            $criticalSettingKeys = [
                'authorization_prefix',
                'login_path',
                'series_path',
                'invoice_path',
                'signing_mode',
                'msmi_prefix',
            ];
            $criticalSettingsChanged = Arr::only($connection->settings ?? [], $criticalSettingKeys)
                !== Arr::only($settings, $criticalSettingKeys);
            $enabledChanged = $connection->exists
                && array_key_exists('is_enabled', $payload)
                && (bool) $payload['is_enabled'] !== (bool) $connection->is_enabled;

            $securityMaterialChanged = $connection->exists && (
                $connection->base_url !== $baseUrl
                || $connection->environment !== $environment
                || $connection->channel !== $channel
                || ($payload['credentials'] ?? []) !== []
                || $criticalSettingsChanged
                || $enabledChanged
            );

            if ($securityMaterialChanged) {
                unset($settings['production_contract_version']);
            }

            $connection->fill([
                'organization_id' => $payload['organization_id'] ?? $connection->organization_id,
                'name' => $payload['name'] ?? $connection->name,
                'provider' => 'minvoice',
                'channel' => $channel,
                'environment' => $environment,
                'base_url' => $baseUrl,
                'credentials' => $credentials,
                'allowed_hosts' => [strtolower((string) parse_url($baseUrl, PHP_URL_HOST))],
                'settings' => $settings,
                'is_enabled' => $payload['is_enabled'] ?? $connection->is_enabled ?? true,
                'configured_at' => now(),
                'updated_by' => $adminId,
            ]);

            if (! $connection->exists) {
                $connection->created_by = $adminId;
            }

            if ($securityMaterialChanged) {
                $connection->forceFill([
                    'sandbox_verified_at' => null,
                    'healthy_at' => null,
                    'production_allowed_at' => null,
                    'last_health_checked_at' => null,
                    'health_status' => 'unknown',
                    'last_error' => null,
                ]);
            }

            $connection->readiness_state = $this->readinessState($connection);
            $connection->save();

            return $connection->fresh();
        });

        $this->audit->record(
            $before === null ? 'minvoice.connection.created' : 'minvoice.connection.updated',
            $saved,
            $before,
            $this->auditSnapshot($saved),
            $adminId ? Admin::query()->find($adminId) : null,
            'minvoice-connector',
        );

        return $saved;
    }

    public function test(AcctProviderConnection $connection): array
    {
        $this->policy->assertNetworkCallAllowed($connection);

        try {
            $result = $connection->channel === 'inbound'
                ? $this->factory->inbound($connection)->healthCheck()
                : $this->factory->outbound($connection)->healthCheck();

            $connection->forceFill([
                'sandbox_verified_at' => $connection->environment === 'sandbox'
                    ? ($connection->sandbox_verified_at ?? now())
                    : $connection->sandbox_verified_at,
                'healthy_at' => now(),
                'last_health_checked_at' => now(),
                'health_status' => 'healthy',
                'last_error' => null,
                'last_used_at' => now(),
            ]);
            $connection->readiness_state = $this->readinessState($connection);
            $connection->save();
            $this->audit->record(
                'minvoice.connection.health_checked',
                $connection,
                null,
                ['health_status' => 'healthy', 'environment' => $connection->environment],
                moduleKey: 'minvoice-connector',
            );

            return $result;
        } catch (\Throwable $exception) {
            $connection->forceFill([
                'healthy_at' => null,
                'last_health_checked_at' => now(),
                'health_status' => 'unhealthy',
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
            $connection->readiness_state = $this->readinessState($connection);
            $connection->save();
            $this->audit->record(
                'minvoice.connection.health_failed',
                $connection,
                null,
                ['health_status' => 'unhealthy', 'error' => $connection->last_error],
                moduleKey: 'minvoice-connector',
            );

            throw $exception;
        }
    }

    public function allowProduction(
        AcctProviderConnection $connection,
        string $confirmation,
        string $contractVersion,
    ): AcctProviderConnection {
        if ($connection->environment !== 'production') {
            throw ValidationException::withMessages([
                'connection' => ['Chỉ kết nối production mới cần mở cổng production.'],
            ]);
        }

        if ($confirmation !== 'ALLOW PRODUCTION') {
            throw ValidationException::withMessages([
                'confirmation' => ['Nhập chính xác ALLOW PRODUCTION để xác nhận.'],
            ]);
        }

        $requiredContractVersion = (string) config('accounting_einvoice.production.contract_version');

        if ($requiredContractVersion === ''
            || $contractVersion === ''
            || ! hash_equals($requiredContractVersion, $contractVersion)) {
            throw ValidationException::withMessages([
                'contract_version' => ['Chưa cấu hình hoặc chưa xác nhận đúng phiên bản API contract đã được review.'],
            ]);
        }

        if ($connection->configured_at === null || $connection->kill_switch || ! $connection->is_enabled) {
            throw ValidationException::withMessages([
                'connection' => ['Kết nối production phải được cấu hình, bật và không bị kill switch.'],
            ]);
        }

        $healthCutoff = now()->subHours(max(1, (int) config(
            'accounting_einvoice.production.sandbox_health_max_age_hours',
            24,
        )));
        $sandboxVerified = AcctProviderConnection::query()
            ->where('organization_id', $connection->organization_id)
            ->where('provider', $connection->provider)
            ->where('channel', $connection->channel)
            ->where('environment', 'sandbox')
            ->where('is_enabled', true)
            ->where('kill_switch', false)
            ->where('health_status', 'healthy')
            ->whereNotNull('sandbox_verified_at')
            ->where('last_health_checked_at', '>=', $healthCutoff)
            ->exists();

        if (! $sandboxVerified) {
            throw ValidationException::withMessages([
                'connection' => ['Cần một kết nối sandbox cùng pháp nhân/channel kiểm tra khỏe trong thời hạn cho phép.'],
            ]);
        }

        $settings = $connection->settings ?? [];
        $settings['production_contract_version'] = $contractVersion;
        $connection->forceFill([
            'production_allowed_at' => now(),
            'last_error' => null,
            'settings' => $settings,
        ]);
        $connection->readiness_state = $this->readinessState($connection);
        $connection->save();
        $this->audit->record(
            'minvoice.connection.production_allowed',
            $connection,
            null,
            ['contract_version' => $contractVersion, 'environment' => 'production'],
            moduleKey: 'minvoice-connector',
        );

        return $connection->fresh();
    }

    public function setKillSwitch(AcctProviderConnection $connection, bool $enabled): AcctProviderConnection
    {
        $connection->forceFill([
            'kill_switch' => $enabled,
            'health_status' => $enabled ? 'blocked' : 'unknown',
            'healthy_at' => $enabled ? $connection->healthy_at : null,
            'last_error' => null,
        ]);

        $connection->readiness_state = $this->readinessState($connection);
        $connection->save();
        $this->audit->record(
            'minvoice.connection.kill_switch_changed',
            $connection,
            null,
            ['enabled' => $enabled],
            moduleKey: 'minvoice-connector',
        );

        return $connection->fresh();
    }

    public function readiness(AcctProviderConnection $connection): array
    {
        $connectorEnabled = $this->connectorEnabled();
        $operationsEnabled = (bool) config('accounting_einvoice.network_enabled', false);
        $connectionEnabled = $connectorEnabled && $connection->is_enabled && $operationsEnabled;

        $healthCutoff = now()->subHours(max(1, (int) config('accounting_einvoice.health_max_age_hours', 24)));
        $healthy = $connection->healthy_at !== null
            && $connection->last_health_checked_at !== null
            && $connection->last_health_checked_at->isAfter($healthCutoff)
            && $connection->health_status === 'healthy';

        return [
            'state' => $this->readinessState($connection),
            'installed' => true,
            'connector_enabled' => $connectorEnabled,
            'operations_enabled' => $operationsEnabled,
            'enabled' => $connectionEnabled,
            'blocked' => ! $connectionEnabled || $connection->kill_switch,
            'configured' => $connection->configured_at !== null,
            'sandbox_verified' => $connection->sandbox_verified_at !== null,
            'healthy' => $healthy,
            'production_allowed' => $connection->production_allowed_at !== null,
        ];
    }

    public function readinessState(AcctProviderConnection $connection): string
    {
        if (! $this->connectorEnabled() || ! $connection->is_enabled) {
            return 'disabled';
        }

        if (! config('accounting_einvoice.network_enabled', false)) {
            return 'operations_disabled';
        }

        if ($connection->kill_switch) {
            return 'blocked';
        }

        if ($connection->environment === 'production'
            && ! config('accounting_einvoice.production_enabled', false)) {
            return 'production_blocked';
        }

        if ($connection->configured_at === null) {
            return 'installed';
        }

        $healthCutoff = now()->subHours(max(1, (int) config('accounting_einvoice.health_max_age_hours', 24)));
        $healthy = $connection->healthy_at !== null
            && $connection->last_health_checked_at !== null
            && $connection->last_health_checked_at->isAfter($healthCutoff)
            && $connection->health_status === 'healthy';

        if ($connection->environment === 'production'
            && $connection->production_allowed_at !== null
            && $healthy) {
            return 'production_allowed';
        }

        if ($healthy) {
            return 'healthy';
        }

        if ($connection->sandbox_verified_at !== null) {
            return 'sandbox_verified';
        }

        return 'configured';
    }

    private function validateCredentials(string $channel, array $credentials): void
    {
        $required = $channel === 'inbound'
            ? ['api_token', 'tax_code']
            : ['username', 'password', 'ma_dvcs', 'tax_code'];
        $missing = collect($required)->filter(fn (string $key): bool => trim((string) ($credentials[$key] ?? '')) === '');

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'credentials' => ['Thiếu thông tin bí mật bắt buộc: '.$missing->implode(', ').'.'],
            ]);
        }

        if (! in_array($channel, ['outbound', 'inbound'], true)) {
            throw ValidationException::withMessages(['channel' => ['Channel không hợp lệ.']]);
        }
    }

    private function validateSettings(array $settings): void
    {
        if (isset($settings['authorization_prefix'])
            && ! in_array($settings['authorization_prefix'], ['Bear', 'Bearer'], true)) {
            throw ValidationException::withMessages([
                'settings.authorization_prefix' => ['Authorization prefix chỉ được là Bear hoặc Bearer.'],
            ]);
        }

        foreach (['login_path', 'series_path', 'invoice_path'] as $key) {
            if (isset($settings[$key])) {
                $this->policy->assertRelativeApiPath((string) $settings[$key]);
            }
        }

        if (isset($settings['msmi_prefix'])) {
            $prefix = (string) $settings['msmi_prefix'];

            if (! in_array($prefix, ['/erp/qlhd-api', '/api/qlhd-api'], true)) {
                throw ValidationException::withMessages([
                    'settings.msmi_prefix' => ['Chỉ hỗ trợ prefix mSMI do tài liệu công bố.'],
                ]);
            }
        }

        if (isset($settings['sync_lookback_days'])
            && ((int) $settings['sync_lookback_days'] < 1 || (int) $settings['sync_lookback_days'] > 90)) {
            throw ValidationException::withMessages([
                'settings.sync_lookback_days' => ['Số ngày đồng bộ lùi phải từ 1 đến 90.'],
            ]);
        }
    }

    private function connectorEnabled(): bool
    {
        return ModuleInstallation::query()
            ->where('key', 'minvoice-connector')
            ->where('status', 'enabled')
            ->exists();
    }

    private function auditSnapshot(AcctProviderConnection $connection): array
    {
        return [
            'organization_id' => $connection->organization_id,
            'name' => $connection->name,
            'provider' => $connection->provider,
            'channel' => $connection->channel,
            'environment' => $connection->environment,
            'base_url' => $connection->base_url,
            'settings' => $connection->settings,
            'is_enabled' => $connection->is_enabled,
            'kill_switch' => $connection->kill_switch,
            'readiness_state' => $connection->readiness_state,
            'health_status' => $connection->health_status,
        ];
    }
}
