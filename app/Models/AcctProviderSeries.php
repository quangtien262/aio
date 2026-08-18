<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'connection_id',
    'provider_series_id',
    'series',
    'invoice_form',
    'invoice_year',
    'invoice_type_name',
    'is_default',
    'is_active',
    'payload_snapshot',
    'synced_at',
])]
class AcctProviderSeries extends Model
{
    use HasFactory;

    protected $table = 'acct_provider_series';

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'payload_snapshot' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AcctProviderConnection::class, 'connection_id');
    }
}
