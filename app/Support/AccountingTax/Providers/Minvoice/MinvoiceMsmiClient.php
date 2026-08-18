<?php

namespace App\Support\AccountingTax\Providers\Minvoice;

use App\Models\AcctProviderConnection;
use App\Support\AccountingTax\Providers\Contracts\InboundInvoiceProvider;
use App\Support\AccountingTax\Providers\Exceptions\ProviderRequestException;
use App\Support\AccountingTax\Providers\ProviderConnectionPolicy;
use App\Support\AccountingTax\Providers\ProviderResponseSanitizer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MinvoiceMsmiClient implements InboundInvoiceProvider
{
    public function __construct(
        private readonly AcctProviderConnection $connection,
        private readonly ProviderConnectionPolicy $policy,
        private readonly ProviderResponseSanitizer $sanitizer,
    ) {}

    public function healthCheck(): array
    {
        $today = now()
            ->timezone((string) config('accounting_einvoice.legal_timezone', 'Asia/Ho_Chi_Minh'))
            ->format('d/m/Y');
        $this->invoices([
            'page' => 1,
            'size' => 1,
            'invoiceType' => 'INPUT_ELECTRONIC_INVOICE',
            'invoiceReleaseDateFrom' => $today,
            'invoiceReleaseDateTo' => $today,
        ]);

        return [
            'ok' => true,
            'provider' => 'minvoice',
            'channel' => 'inbound',
        ];
    }

    public function invoices(array $filters): array
    {
        $this->policy->assertNetworkCallAllowed($this->connection);
        $size = (int) ($filters['size'] ?? 100);

        if ($size < 1 || $size > 200) {
            throw new ProviderRequestException('Kích thước trang mSMI phải từ 1 đến 200.');
        }

        $filters['size'] = $size;
        $filters['invoiceType'] = 'INPUT_ELECTRONIC_INVOICE';
        $response = $this->request()->get($this->prefix().'/invoices', $filters);

        return $this->json($response, 'Không thể lấy danh sách hóa đơn đầu vào từ mSMI.');
    }

    public function downloadXml(string $providerInvoiceId): string
    {
        return $this->download($providerInvoiceId, 'invoice.xml', 'Không thể tải XML hóa đơn mSMI.');
    }

    public function downloadHtml(string $providerInvoiceId): string
    {
        return $this->download($providerInvoiceId, 'invoice.html', 'Không thể tải HTML hóa đơn mSMI.');
    }

    public function warning(string $providerInvoiceId): array
    {
        $this->policy->assertNetworkCallAllowed($this->connection);
        $id = $this->safeId($providerInvoiceId);
        $response = $this->request()->get($this->prefix().'/invoices/'.$id.'/warning');

        return $this->json($response, 'Không thể kiểm tra cảnh báo hóa đơn mSMI.');
    }

    private function download(string $providerInvoiceId, string $filename, string $failureMessage): string
    {
        $this->policy->assertNetworkCallAllowed($this->connection);
        $id = $this->safeId($providerInvoiceId);
        $response = $this->request()->get($this->prefix().'/invoices/'.$id.'/download/'.$filename);

        if (! $response->successful()) {
            throw new ProviderRequestException($failureMessage, $response->status());
        }

        return $response->body();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->connection->base_url)
            ->acceptJson()
            ->withHeader('apiToken', (string) data_get($this->connection->credentials, 'api_token'))
            ->connectTimeout(5)
            ->timeout(30)
            ->withoutRedirecting();
    }

    private function prefix(): string
    {
        $prefix = (string) data_get($this->connection->settings, 'msmi_prefix', '/erp/qlhd-api');

        if (! in_array($prefix, ['/erp/qlhd-api', '/api/qlhd-api'], true)) {
            throw new ProviderRequestException('mSMI API prefix không hợp lệ.');
        }

        return $prefix;
    }

    private function safeId(string $providerInvoiceId): string
    {
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $providerInvoiceId)) {
            throw new ProviderRequestException('Provider invoice ID không hợp lệ.');
        }

        return $providerInvoiceId;
    }

    private function json(Response $response, string $failureMessage): array
    {
        $payload = $response->json();

        $providerRejected = is_array($payload) && (
            ($payload['status'] ?? true) === false
            || ($payload['success'] ?? true) === false
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
}
