<?php

namespace Modules\AccountingTax\Services;

use App\Core\Modules\TestData\ModuleTestDataContext;
use App\Core\Modules\TestData\ModuleTestDataProvider;

final class AccountingTaxTestDataProvider implements ModuleTestDataProvider
{
    public function preview(): array
    {
        return ['Một pháp nhân TEST riêng không mặc định, một đối tác và mặt hàng mẫu.', 'Một chứng từ nội bộ nháp chưa đánh giá thuế; không map website, duyệt, ghi sổ, thu tiền hoặc phát hành hóa đơn.'];
    }

    public function references(): array
    {
        $references = [
            ['table' => 'ph_accounting_mappings', 'column' => 'organization_id', 'foreign_table' => 'acct_organizations', 'foreign_column' => 'id'],
            ['table' => 'ph_accounting_source_links', 'column' => 'organization_id', 'foreign_table' => 'acct_organizations', 'foreign_column' => 'id'],
            ['table' => 'ph_accounting_mappings', 'column' => 'item_mappings->*', 'foreign_table' => 'acct_items', 'foreign_column' => 'id'],
            ['table' => 'ph_accounting_source_links', 'column' => 'document_id', 'foreign_table' => 'acct_documents', 'foreign_column' => 'id'],
        ];
        foreach (['retail_legal_accounting_links', 'retail_accounting_mappings', 'retail_integration_links'] as $table) {
            $references[] = ['table' => $table, 'column' => 'organization_id', 'foreign_table' => 'acct_organizations', 'foreign_column' => 'id'];
        }
        $references[] = ['table' => 'retail_accounting_item_links', 'column' => 'accounting_item_id', 'foreign_table' => 'acct_items', 'foreign_column' => 'id'];
        $references[] = ['table' => 'retail_integration_links', 'column' => 'destination_id', 'foreign_table' => 'acct_documents', 'foreign_column' => 'id', 'where' => ['destination_module' => 'accounting-tax']];
        $references[] = ['table' => 'fnb_integration_mappings', 'column' => 'config->organization_id', 'foreign_table' => 'acct_organizations', 'foreign_column' => 'id', 'where' => ['destination' => 'accounting-tax']];
        $references[] = ['table' => 'fnb_integration_item_mappings', 'column' => 'destination_id', 'foreign_table' => 'acct_items', 'foreign_column' => 'id', 'where' => ['destination' => 'accounting-tax']];
        $references[] = ['table' => 'fnb_outbox_deliveries', 'column' => 'external_reference', 'foreign_table' => 'acct_documents', 'foreign_column' => 'id', 'where' => ['destination' => 'accounting-tax']];

        return $references;
    }

    public function create(ModuleTestDataContext $context): void
    {
        $organization = self::organization($context);
        $party = $context->insert('acct_parties', ['organization_id' => $organization, 'type' => 'customer', 'name' => '[TEST] Đối tác giả lập', 'email' => strtolower($context->code('PARTY')).'@example.invalid']);
        $sku = $context->code('ITEM');
        $item = $context->insert('acct_items', ['organization_id' => $organization, 'kind' => 'service', 'name' => '[TEST] Dịch vụ mẫu', 'sku' => $sku, 'unit' => 'lần', 'default_price' => '100000.00', 'tax_category' => 'not_declared', 'is_stock_tracked' => false, 'status' => 'active']);
        $document = $context->insert('acct_documents', ['organization_id' => $organization, 'party_id' => $party, 'direction' => 'outbound', 'document_type' => 'internal_invoice', 'document_date' => now()->toDateString(), 'currency' => 'VND', 'workflow_status' => 'draft', 'payment_status' => 'unpaid', 'legal_status' => 'not_due', 'inventory_status' => 'not_applicable', 'mail_status' => 'not_sent', 'subtotal' => '100000.00', 'grand_total' => '100000.00', 'base_currency' => 'VND', 'base_subtotal' => '100000.00', 'base_grand_total' => '100000.00', 'tax_eligibility' => 'not_assessed', 'idempotency_key' => $context->code('DOCUMENT'), 'created_by' => $context->actor->id, 'version' => 1, 'notes' => '[TEST] Chứng từ giả lập chưa duyệt, không dùng kê khai hoặc thanh toán.', 'seller_snapshot' => json_encode(['name' => '[TEST] Pháp nhân mẫu'], JSON_THROW_ON_ERROR), 'buyer_snapshot' => json_encode(['name' => '[TEST] Đối tác giả lập'], JSON_THROW_ON_ERROR), 'metadata' => json_encode(['requires_tax_classification' => true], JSON_THROW_ON_ERROR)]);
        $context->insert('acct_document_lines', ['document_id' => $document, 'accounting_item_id' => $item, 'line_type' => 'item', 'item_kind' => 'service', 'name' => '[TEST] Dịch vụ mẫu', 'sku' => $sku, 'unit' => 'lần', 'quantity' => '1.0000', 'unit_price' => '100000.00', 'line_subtotal' => '100000.00', 'tax_base' => '100000.00', 'tax_category' => 'not_declared', 'tax_rate' => '0.00', 'tax_amount' => '0.00', 'line_total' => '100000.00']);
    }

    public static function organization(ModuleTestDataContext $context): int
    {
        return (int) $context->insert('acct_organizations', ['name' => '[TEST] Pháp nhân '.$context->code('ORG'), 'legal_name' => '[TEST] Pháp nhân giả lập', 'default_currency' => 'VND', 'is_default' => false, 'default_slot' => null, 'status' => 'active']);
    }
}
