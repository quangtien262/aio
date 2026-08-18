<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_id',
    'kind',
    'amount',
    'currency',
    'paid_at',
    'reference',
    'status',
    'created_by',
    'idempotency_key',
    'metadata',
])]
class AcctDocumentPayment extends Model
{
    protected $table = 'acct_document_payments';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AcctDocument::class, 'document_id');
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Accounting payments are append-only; record a refund row instead.');
        });
        static::deleting(function (): never {
            throw new \LogicException('Accounting payments are append-only; record a refund row instead.');
        });
    }
}
