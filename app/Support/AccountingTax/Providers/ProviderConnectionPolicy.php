<?php

namespace App\Support\AccountingTax\Providers;

use App\Models\AcctProviderConnection;
use App\Support\AccountingTax\Providers\Exceptions\ProviderSafetyException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProviderConnectionPolicy
{
    public function normalizeAndValidateBaseUrl(string $baseUrl, string $environment): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        $parts = parse_url($baseUrl);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (($parts['scheme'] ?? null) !== 'https'
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ! in_array((int) ($parts['port'] ?? 443), [443], true)
            || ! in_array((string) ($parts['path'] ?? ''), ['', '/'], true)) {
            throw ValidationException::withMessages([
                'base_url' => ['Base URL phải là HTTPS root URL, không chứa tài khoản, query, fragment hoặc port tùy ý.'],
            ]);
        }

        if (! $this->isMinvoiceHost($host)) {
            throw ValidationException::withMessages([
                'base_url' => ['Chỉ cho phép host chính thức thuộc minvoice.com.vn.'],
            ]);
        }

        if ($environment === 'production' && Str::contains($host, ['test', 'sandbox', 'staging', 'dev'])) {
            throw ValidationException::withMessages([
                'base_url' => ['Kết nối production không được trỏ tới host test/sandbox.'],
            ]);
        }

        return $baseUrl;
    }

    public function assertNetworkCallAllowed(AcctProviderConnection $connection, bool $mutation = false): void
    {
        if (! config('accounting_einvoice.network_enabled', false)) {
            throw new ProviderSafetyException('E-invoice network bị khóa ở cấp vận hành hệ thống.');
        }

        if (! $connection->is_enabled) {
            throw new ProviderSafetyException('Kết nối nhà cung cấp đang bị vô hiệu hóa.');
        }

        if ($connection->kill_switch) {
            throw new ProviderSafetyException('Kill switch đang bật; mọi request tới nhà cung cấp đã bị chặn.');
        }

        if ($connection->configured_at === null) {
            throw new ProviderSafetyException('Kết nối chưa được cấu hình đầy đủ.');
        }

        $baseUrl = $this->normalizeAndValidateBaseUrl($connection->base_url, $connection->environment);
        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        $allowedHosts = collect($connection->allowed_hosts ?? [])->map(fn ($value) => strtolower((string) $value));

        if ($allowedHosts->isNotEmpty() && ! $allowedHosts->contains($host)) {
            throw new ProviderSafetyException('Host hiện tại không nằm trong allowlist của kết nối.');
        }

        if ($connection->environment === 'production' && $connection->production_allowed_at === null) {
            throw new ProviderSafetyException('Production bị khóa cho đến khi được cho phép rõ ràng.');
        }

        if ($connection->environment === 'production'
            && ! config('accounting_einvoice.production_enabled', false)) {
            throw new ProviderSafetyException('E-invoice production bị khóa ở cấp triển khai hệ thống.');
        }

        if ($mutation && $connection->environment === 'sandbox' && $connection->sandbox_verified_at === null) {
            throw new ProviderSafetyException('Phải xác minh sandbox thành công trước khi tạo hoặc ký hóa đơn.');
        }

        if ($mutation) {
            $healthCutoff = now()->subHours(max(1, (int) config(
                'accounting_einvoice.health_max_age_hours',
                24,
            )));

            if ($connection->health_status !== 'healthy'
                || $connection->last_health_checked_at === null
                || $connection->last_health_checked_at->isBefore($healthCutoff)) {
                throw new ProviderSafetyException('Cần kiểm tra sức khỏe kết nối gần đây trước khi phát hành hóa đơn.');
            }
        }
    }

    public function assertRelativeApiPath(string $path): string
    {
        if (! str_starts_with($path, '/api/') || str_contains($path, '..') || str_contains($path, '://')) {
            throw new ProviderSafetyException('Provider API path không hợp lệ.');
        }

        return $path;
    }

    public function isMinvoiceHost(string $host): bool
    {
        $host = strtolower($host);

        return $host === 'minvoice.com.vn' || str_ends_with($host, '.minvoice.com.vn');
    }
}
