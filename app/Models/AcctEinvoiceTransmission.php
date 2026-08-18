<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_id',
    'connection_id',
    'provider',
    'operation',
    'operation_key',
    'status',
    'provider_document_id',
    'provider_status',
    'legal_status',
    'attempt_count',
    'next_attempt_at',
    'request_snapshot',
    'response_snapshot',
    'pdf_path',
    'xml_path',
    'checksum',
    'pdf_checksum',
    'xml_checksum',
    'last_error',
    'sent_at',
    'completed_at',
])]
class AcctEinvoiceTransmission extends Model
{
    use HasFactory;

    protected $table = 'acct_einvoice_transmissions';

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'next_attempt_at' => 'datetime',
            'request_snapshot' => 'array',
            'response_snapshot' => 'array',
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AcctDocument::class, 'document_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AcctProviderConnection::class, 'connection_id');
    }
}
