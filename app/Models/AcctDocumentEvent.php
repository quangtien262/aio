<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_id',
    'event_type',
    'from_status',
    'to_status',
    'actor_admin_id',
    'document_version',
    'idempotency_key',
    'payload',
])]
class AcctDocumentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'acct_document_events';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AcctDocument::class, 'document_id');
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Accounting document events are append-only.');
        });
        static::deleting(function (): never {
            throw new \LogicException('Accounting document events are append-only.');
        });
    }
}
