<?php

namespace App\Http\Controllers\Admin\Api\AccountingTax\Minvoice;

use App\Models\AcctEinvoiceInbound;
use App\Models\AcctEinvoiceInboundLine;
use App\Models\AcctEinvoiceTransmission;
use App\Models\AcctProviderConnection;
use App\Models\AcctProviderSeries;
use App\Support\AccountingTax\Providers\ProviderConnectionService;

class ProviderApiSerializer
{
    public function __construct(private readonly ProviderConnectionService $connections) {}

    public function connection(AcctProviderConnection $connection): array
    {
        return [
            'id' => $connection->id,
            'organization_id' => $connection->organization_id,
            'name' => $connection->name,
            'provider' => $connection->provider,
            'channel' => $connection->channel,
            'environment' => $connection->environment,
            'base_url' => $connection->base_url,
            'allowed_hosts' => $connection->allowed_hosts ?? [],
            'settings' => $connection->settings ?? [],
            'readiness' => $this->connections->readiness($connection),
            'health_status' => $connection->health_status,
            'is_enabled' => (bool) $connection->is_enabled,
            'kill_switch' => (bool) $connection->kill_switch,
            'credentials_configured' => collect(array_keys($connection->credentials ?? []))
                ->filter(fn (string $key): bool => in_array($key, ['username', 'ma_dvcs', 'tax_code', 'api_token', 'password'], true))
                ->map(fn (string $key): string => $key === 'password' || $key === 'api_token' ? $key.'_set' : $key)
                ->values()
                ->all(),
            'configured_at' => $connection->configured_at?->toIso8601String(),
            'sandbox_verified_at' => $connection->sandbox_verified_at?->toIso8601String(),
            'healthy_at' => $connection->healthy_at?->toIso8601String(),
            'production_allowed_at' => $connection->production_allowed_at?->toIso8601String(),
            'last_health_checked_at' => $connection->last_health_checked_at?->toIso8601String(),
            'last_used_at' => $connection->last_used_at?->toIso8601String(),
            'last_error' => $connection->last_error,
        ];
    }

    public function series(AcctProviderSeries $series): array
    {
        return [
            'id' => $series->id,
            'connection_id' => $series->connection_id,
            'provider_series_id' => $series->provider_series_id,
            'series' => $series->series,
            'invoice_form' => $series->invoice_form,
            'invoice_year' => $series->invoice_year,
            'invoice_type_name' => $series->invoice_type_name,
            'is_default' => (bool) $series->is_default,
            'is_active' => (bool) $series->is_active,
            'synced_at' => $series->synced_at?->toIso8601String(),
        ];
    }

    public function transmission(AcctEinvoiceTransmission $transmission): array
    {
        return [
            'id' => $transmission->id,
            'document_id' => $transmission->document_id,
            'connection_id' => $transmission->connection_id,
            'provider' => $transmission->provider,
            'operation' => $transmission->operation,
            'status' => $transmission->status,
            'provider_document_id' => $transmission->provider_document_id,
            'provider_status' => $transmission->provider_status,
            'legal_status' => $transmission->legal_status,
            'attempt_count' => $transmission->attempt_count,
            'has_pdf' => $transmission->pdf_path !== null,
            'has_xml' => $transmission->xml_path !== null,
            'checksum' => $transmission->checksum,
            'pdf_checksum' => $transmission->pdf_checksum,
            'xml_checksum' => $transmission->xml_checksum,
            'last_error' => $transmission->last_error,
            'sent_at' => $transmission->sent_at?->toIso8601String(),
            'completed_at' => $transmission->completed_at?->toIso8601String(),
            'created_at' => $transmission->created_at?->toIso8601String(),
        ];
    }

    public function inbound(AcctEinvoiceInbound $invoice): array
    {
        return [
            'id' => $invoice->id,
            'organization_id' => $invoice->organization_id,
            'connection_id' => $invoice->connection_id,
            'document_id' => $invoice->document_id,
            'provider_invoice_id' => $invoice->provider_invoice_id,
            'provider_tax_id' => $invoice->provider_tax_id,
            'seller' => [
                'tax_code' => $invoice->seller_tax_code,
                'name' => $invoice->seller_name,
                'address' => $invoice->seller_address,
            ],
            'buyer' => [
                'tax_code' => $invoice->buyer_tax_code,
                'name' => $invoice->buyer_name,
            ],
            'template_code' => $invoice->template_code,
            'invoice_series' => $invoice->invoice_series,
            'invoice_number' => $invoice->invoice_number,
            'invoice_code' => $invoice->invoice_code,
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'currency' => $invoice->currency,
            'exchange_rate' => (float) $invoice->exchange_rate,
            'subtotal_ex_vat' => (float) $invoice->subtotal_ex_vat,
            'tax_amount' => (float) $invoice->tax_amount,
            'total_amount' => (float) $invoice->total_amount,
            'invoice_status_code' => $invoice->invoice_status_code,
            'processing_status_code' => $invoice->processing_status_code,
            'illegal_status' => $invoice->illegal_status,
            'illegal_reason' => $invoice->illegal_reason,
            'duplicate_status' => $invoice->duplicate_status,
            'reconciliation_status' => $invoice->reconciliation_status,
            'sync_status' => $invoice->sync_status,
            'warnings' => $invoice->warnings ?? [],
            'vat_breakdown' => $invoice->vat_breakdown ?? [],
            'has_xml' => $invoice->xml_path !== null,
            'has_html' => $invoice->html_path !== null,
            'xml_checksum' => $invoice->xml_checksum,
            'html_checksum' => $invoice->html_checksum,
            'synced_at' => $invoice->synced_at?->toIso8601String(),
            'lines' => $invoice->relationLoaded('lines')
                ? $invoice->lines->map(fn (AcctEinvoiceInboundLine $line): array => $this->line($line))->values()->all()
                : [],
        ];
    }

    private function line(AcctEinvoiceInboundLine $line): array
    {
        return [
            'id' => $line->id,
            'provider_line_id' => $line->provider_line_id,
            'line_no' => $line->line_no,
            'item_name' => $line->item_name,
            'unit' => $line->unit,
            'quantity' => (float) $line->quantity,
            'unit_price' => (float) $line->unit_price,
            'subtotal_ex_vat' => (float) $line->subtotal_ex_vat,
            'vat_rate' => $line->vat_rate,
            'vat_amount' => (float) $line->vat_amount,
            'discount_amount' => (float) $line->discount_amount,
            'line_type' => $line->line_type,
        ];
    }
}
