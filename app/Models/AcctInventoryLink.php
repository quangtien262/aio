<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id', 'document_id', 'direction', 'inventory_document_id', 'status',
    'idempotency_key', 'payload_snapshot', 'last_error', 'posted_at', 'posted_by',
])]
class AcctInventoryLink extends Model
{
    protected $table = 'acct_inventory_links';

    protected function casts(): array
    {
        return ['payload_snapshot' => 'array', 'posted_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AcctDocument::class, 'document_id');
    }
}
