<?php

namespace Modules\MinvoiceConnector\Services;

use App\Core\Modules\TestData\ModuleTestDataContext;
use App\Core\Modules\TestData\ModuleTestDataProvider;
use Modules\AccountingTax\Services\AccountingTaxTestDataProvider;

final class MinvoiceConnectorTestDataProvider implements ModuleTestDataProvider
{
    public function preview(): array
    {
        return ['Một pháp nhân TEST riêng và cấu hình kết nối sandbox đã tắt, không có thông tin xác thực.', 'Kill switch bật, host example.invalid; không gọi mạng, đồng bộ hoặc tạo lệnh phát hành.'];
    }

    public function references(): array
    {
        return (new AccountingTaxTestDataProvider)->references();
    }

    public function create(ModuleTestDataContext $context): void
    {
        $organization = AccountingTaxTestDataProvider::organization($context);
        $connection = $context->insert('acct_provider_connections', ['organization_id' => $organization, 'name' => '[TEST] Sandbox chưa kết nối', 'provider' => 'minvoice', 'channel' => 'outbound', 'environment' => 'sandbox', 'base_url' => 'https://sandbox.example.invalid', 'credentials' => null, 'allowed_hosts' => json_encode(['sandbox.example.invalid'], JSON_THROW_ON_ERROR), 'readiness_state' => 'installed', 'health_status' => 'unknown', 'is_enabled' => false, 'kill_switch' => true, 'created_by' => $context->actor->id, 'updated_by' => $context->actor->id]);
        $context->insert('acct_provider_series', ['connection_id' => $connection, 'series' => $context->code('SERIES'), 'invoice_type_name' => '[TEST] Ký hiệu giả lập', 'is_default' => false, 'is_active' => false]);
    }
}
