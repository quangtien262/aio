<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'connection_id',
    'document_id',
    'provider',
    'direction',
    'provider_invoice_id',
    'provider_tax_id',
    'provider_type',
    'seller_tax_code',
    'seller_name',
    'seller_address',
    'buyer_tax_code',
    'buyer_name',
    'template_code',
    'invoice_series',
    'invoice_number',
    'invoice_code',
    'invoice_date',
    'issued_at',
    'tax_authority_code_issued_at',
    'tax_authority_received_at',
    'provider_updated_at',
    'currency',
    'exchange_rate',
    'subtotal_ex_vat',
    'total_amount',
    'tax_amount',
    'non_taxable_amount',
    'discount_amount',
    'fee_amount',
    'other_amount',
    'provider_status',
    'invoice_status_code',
    'processing_status_code',
    'illegal_status',
    'illegal_reason',
    'duplicate_status',
    'reconciliation_status',
    'xml_path',
    'html_path',
    'pdf_path',
    'content_checksum',
    'xml_checksum',
    'html_checksum',
    'warnings',
    'vat_breakdown',
    'warning_payload',
    'payload_snapshot',
    'sync_status',
    'synced_at',
])]
class AcctEinvoiceInbound extends Model
{
    use HasFactory;

    protected $table = 'acct_external_invoices';

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'issued_at' => 'datetime',
            'tax_authority_code_issued_at' => 'datetime',
            'tax_authority_received_at' => 'datetime',
            'provider_updated_at' => 'datetime',
            'exchange_rate' => 'decimal:6',
            'subtotal_ex_vat' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'non_taxable_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'other_amount' => 'decimal:2',
            'warnings' => 'array',
            'vat_breakdown' => 'array',
            'warning_payload' => 'array',
            'payload_snapshot' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(AcctOrganization::class, 'organization_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AcctProviderConnection::class, 'connection_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AcctDocument::class, 'document_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AcctEinvoiceInboundLine::class, 'external_invoice_id')->orderBy('line_no');
    }

    public function vatBreakdowns(): HasMany
    {
        return $this->hasMany(AcctEinvoiceVatBreakdown::class, 'external_invoice_id');
    }
}
