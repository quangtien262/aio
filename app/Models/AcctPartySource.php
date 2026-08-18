<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id', 'party_id', 'source_module', 'source_type', 'source_id',
    'source_key', 'snapshot', 'synced_at',
])]
class AcctPartySource extends Model
{
    protected $table = 'acct_party_sources';

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'synced_at' => 'datetime'];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(AcctParty::class, 'party_id');
    }
}
