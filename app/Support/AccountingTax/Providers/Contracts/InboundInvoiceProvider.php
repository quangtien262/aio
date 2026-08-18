<?php

namespace App\Support\AccountingTax\Providers\Contracts;

interface InboundInvoiceProvider
{
    public function healthCheck(): array;

    public function invoices(array $filters): array;

    public function downloadXml(string $providerInvoiceId): string;

    public function downloadHtml(string $providerInvoiceId): string;

    public function warning(string $providerInvoiceId): array;
}
