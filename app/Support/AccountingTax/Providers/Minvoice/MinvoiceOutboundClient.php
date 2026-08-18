<?php

namespace App\Support\AccountingTax\Providers\Minvoice;

use App\Models\AcctProviderConnection;
use App\Support\AccountingTax\Providers\Contracts\ElectronicInvoiceProvider;
use App\Support\AccountingTax\Providers\Exceptions\ProviderRequestException;
use App\Support\AccountingTax\Providers\Exceptions\ProviderSafetyException;
use App\Support\AccountingTax\Providers\ProviderConnectionPolicy;
use App\Support\AccountingTax\Providers\ProviderResponseSanitizer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MinvoiceOutboundClient implements ElectronicInvoiceProvider
{
    public function __construct(
        private readonly AcctProviderConnection $connection,
        private readonly ProviderConnectionPolicy $policy,
        private readonly ProviderResponseSanitizer $sanitizer,
    ) {}

    public function healthCheck(): array
    {
        $token = $this->authenticate(refresh: true);

        return [
            'ok' => true,
            'provider' => 'minvoice',
            'channel' => 'outbound',
            'token_received' => $token !== '',
        ];
    }

    public function series(int $invoiceType = 1): array
    {
        $this->policy->assertNetworkCallAllowed($this->connection);
        $path = $this->path('series_path', '/api/Invoice68/GetTypeInvoiceSeries');
        $response = $this->authorized()->get($path, ['Type' => $invoiceType]);

        return $this->json($response, 'Không thể lấy danh sách ký hiệu hóa đơn.');
    }

    public function createDraft(array $payload): array
    {
        $this->policy->assertNetworkCallAllowed($this->connection, mutation: true);
        $payload['editmode'] = 1;
        $response = $this->authorized()->post($this->path('invoice_path', '/api/InvoiceApi78'), $payload);

        return $this->json($response, 'Không thể tạo hóa đơn nháp trên Minvoice.');
    }

    public function createAndSign(array $payload): array
    {
        $this->policy->assertNetworkCallAllowed($this->connection, mutation: true);
        $payload['editmode'] = 1;
        $response = $this->authorized(taxCode: true)->post('/api/InvoiceApi78/SaveSign', $payload);

        return $this->json($response, 'Không thể tạo, ký và gửi hóa đơn trên Minvoice.');
    }

    public function signAndSend(string $providerDocumentId, array $options = []): array
    {
        $this->policy->assertNetworkCallAllowed($this->connection, mutation: true);
        $response = $this->authorized(taxCode: true)->post('/api/InvoiceApi78/Sign', [
            'hoadon68_id' => $providerDocumentId,
            ...$options,
        ]);

        return $this->json($response, 'Không thể ký và gửi hóa đơn trên Minvoice.');
    }

    public function status(?string $providerDocumentId, ?string $operationKey = null): array
    {
        $this->policy->assertNetworkCallAllowed($this->connection);

        if ($providerDocumentId === null && $operationKey === null) {
            throw new ProviderRequestException('Cần provider document ID hoặc operation key để đối soát.');
        }

        $query = $providerDocumentId !== null
            ? ['id' => $providerDocumentId]
            : ['keyApi' => $operationKey];
        $response = $this->authorized()->get('/api/InvoiceApi78/GetInfoInvoice', $query);

        return $this->json($response, 'Không thể đồng bộ trạng thái hóa đơn Minvoice.');
    }

    public function downloadPdf(string $providerDocumentId): string
    {
        $this->policy->assertNetworkCallAllowed($this->connection);
        $response = $this->authorized()->get('/api/InvoiceApi78/PrintInvoice', ['id' => $providerDocumentId]);

        return $this->binary($response, ['byteArray', 'data', 'pdf'], 'Không thể tải PDF hóa đơn Minvoice.');
    }

    public function downloadXml(string $providerDocumentId): string
    {
        $this->policy->assertNetworkCallAllowed($this->connection);
        $response = $this->authorized()->get('/api/InvoiceApi78/ExportXml', ['id' => $providerDocumentId]);

        return $this->binary($response, ['xml', 'data', 'byteArray'], 'Không thể tải XML hóa đơn Minvoice.');
    }

    public function adjust(array $payload): array
    {
        throw new ProviderSafetyException(
            'Luồng điều chỉnh bị khóa: API contract/chữ ký Minvoice chưa được xác nhận cho môi trường này.',
        );
    }

    public function replace(array $payload): array
    {
        throw new ProviderSafetyException(
            'Luồng thay thế bị khóa: API contract/chữ ký Minvoice chưa được xác nhận cho môi trường này.',
        );
    }

    public function cancel(array $payload): array
    {
        throw new ProviderSafetyException(
            'Luồng hủy/sai sót bị khóa: chưa xác nhận mapping pháp lý và endpoint provider hiện hành.',
        );
    }

    private function authenticate(bool $refresh = false): string
    {
        $this->policy->assertNetworkCallAllowed($this->connection);
        $cacheKey = 'accounting-provider-token:'.$this->connection->id.':'.hash('sha256', json_encode($this->connection->credentials));

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addMinutes(15), function (): string {
            $credentials = $this->connection->credentials ?? [];
            $response = $this->request()->post($this->path('login_path', '/api/Account/Login'), [
                'username' => $credentials['username'] ?? null,
                'password' => $credentials['password'] ?? null,
                'ma_dvcs' => $credentials['ma_dvcs'] ?? null,
            ]);
            $payload = $this->json($response, 'Đăng nhập Minvoice thất bại.');
            $token = (string) (data_get($payload, 'token')
                ?? data_get($payload, 'data.token')
                ?? data_get($payload, 'result.token')
                ?? '');

            if ($token === '' || (($payload['ok'] ?? true) === false) || (($payload['code'] ?? '00') !== '00')) {
                throw new ProviderRequestException('Minvoice không trả về access token hợp lệ.', $response->status(), $this->sanitizer->sanitize($payload));
            }

            return $token;
        });
    }

    private function authorized(bool $taxCode = false): PendingRequest
    {
        $prefix = trim((string) data_get($this->connection->settings, 'authorization_prefix', 'Bear'));
        $request = $this->request()->withHeader('Authorization', $prefix.' '.$this->authenticate());

        if ($taxCode) {
            $request = $request->withHeader('TaxCode', (string) data_get($this->connection->credentials, 'tax_code'));
        }

        return $request;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->connection->base_url)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(30)
            ->withoutRedirecting();
    }

    private function path(string $setting, string $default): string
    {
        return $this->policy->assertRelativeApiPath(
            (string) data_get($this->connection->settings, $setting, $default),
        );
    }

    private function json(Response $response, string $failureMessage): array
    {
        $payload = $response->json();

        $providerCode = is_array($payload) ? ($payload['code'] ?? null) : null;
        $providerRejected = is_array($payload) && (
            ($payload['ok'] ?? true) === false
            || ($payload['success'] ?? true) === false
            || ($providerCode !== null && ! in_array((string) $providerCode, ['0', '00', '200'], true))
        );

        if (! $response->successful() || ! is_array($payload) || $providerRejected) {
            throw new ProviderRequestException(
                $failureMessage,
                $providerRejected && $response->successful() ? 422 : $response->status(),
                $this->sanitizer->sanitize(is_array($payload) ? $payload : ['body' => $response->body()]),
            );
        }

        return $payload;
    }

    private function binary(Response $response, array $candidateKeys, string $failureMessage): string
    {
        if (! $response->successful()) {
            throw new ProviderRequestException($failureMessage, $response->status());
        }

        $contentType = strtolower((string) $response->header('Content-Type'));

        if (! str_contains($contentType, 'json')) {
            return $response->body();
        }

        $payload = $response->json();

        foreach ($candidateKeys as $key) {
            $candidate = data_get($payload, $key) ?? data_get($payload, 'data.'.$key);

            if (is_array($candidate)
                && $candidate !== []
                && collect($candidate)->every(fn ($byte): bool => is_int($byte) && $byte >= 0 && $byte <= 255)) {
                return pack('C*', ...$candidate);
            }

            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            $decoded = base64_decode($candidate, true);

            return $decoded !== false ? $decoded : $candidate;
        }

        $body = ltrim($response->body());

        if (str_starts_with($body, '%PDF-') || str_starts_with($body, '<?xml') || str_starts_with($body, '<')) {
            return $response->body();
        }

        throw new ProviderRequestException($failureMessage, $response->status(), $this->sanitizer->sanitize((array) $payload));
    }
}
