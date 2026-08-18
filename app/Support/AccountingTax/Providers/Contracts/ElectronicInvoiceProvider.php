<?php

namespace App\Support\AccountingTax\Providers\Contracts;

interface ElectronicInvoiceProvider
{
    public function healthCheck(): array;

    public function series(int $invoiceType = 1): array;

    public function createDraft(array $payload): array;

    public function createAndSign(array $payload): array;

    public function signAndSend(string $providerDocumentId, array $options = []): array;

    public function status(?string $providerDocumentId, ?string $operationKey = null): array;

    public function downloadPdf(string $providerDocumentId): string;

    public function downloadXml(string $providerDocumentId): string;

    public function adjust(array $payload): array;

    public function replace(array $payload): array;

    public function cancel(array $payload): array;
}
