<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'organization_id',
    'document_id',
    'provider',
    'direction',
    'provider_invoice_id',
    'seller_tax_code',
    'buyer_tax_code',
    'invoice_series',
    'invoice_number',
    'invoice_date',
    'total_amount',
    'tax_amount',
    'provider_status',
    'reconciliation_status',
    'xml_path',
    'html_path',
    'pdf_path',
    'content_checksum',
    'warnings',
    'payload_snapshot',
    'synced_at',
])]
class AcctExternalInvoice extends Model
{
    use HasFactory;

    protected $table = 'acct_external_invoices';

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'total_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'warnings' => 'array',
            'payload_snapshot' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}
