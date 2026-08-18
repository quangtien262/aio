<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'accounting_item_id',
    'source_module',
    'source_type',
    'source_id',
    'source_key',
    'source_updated_at',
    'source_hash',
    'synced_at',
    'sync_status',
    'snapshot',
])]
class AcctItemSource extends Model
{
    use HasFactory;

    protected $table = 'acct_item_sources';

    protected function casts(): array
    {
        return [
            'source_updated_at' => 'datetime',
            'synced_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AcctItem::class, 'accounting_item_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(AcctOrganization::class, 'organization_id');
    }
}
