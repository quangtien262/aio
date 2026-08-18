<?php

namespace App\Support\AccountingTax\Providers;

use App\Models\AcctProviderConnection;
use App\Support\AccountingTax\Providers\Contracts\ElectronicInvoiceProvider;
use App\Support\AccountingTax\Providers\Contracts\InboundInvoiceProvider;
use App\Support\AccountingTax\Providers\Exceptions\ProviderSafetyException;
use App\Support\AccountingTax\Providers\Minvoice\MinvoiceMsmiClient;
use App\Support\AccountingTax\Providers\Minvoice\MinvoiceOutboundClient;

class ProviderFactory
{
    public function __construct(
        private readonly ProviderConnectionPolicy $policy,
        private readonly ProviderResponseSanitizer $sanitizer,
    ) {}

    public function outbound(AcctProviderConnection $connection): ElectronicInvoiceProvider
    {
        if ($connection->provider !== 'minvoice' || $connection->channel !== 'outbound') {
            throw new ProviderSafetyException('Kết nối không hỗ trợ nghiệp vụ hóa đơn đầu ra.');
        }

        return new MinvoiceOutboundClient($connection, $this->policy, $this->sanitizer);
    }

    public function inbound(AcctProviderConnection $connection): InboundInvoiceProvider
    {
        if ($connection->provider !== 'minvoice' || $connection->channel !== 'inbound') {
            throw new ProviderSafetyException('Kết nối không hỗ trợ đồng bộ hóa đơn đầu vào.');
        }

        return new MinvoiceMsmiClient($connection, $this->policy, $this->sanitizer);
    }
}
