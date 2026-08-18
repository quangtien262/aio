<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'name',
    'provider',
    'channel',
    'environment',
    'base_url',
    'credentials',
    'allowed_hosts',
    'settings',
    'readiness_state',
    'health_status',
    'is_enabled',
    'kill_switch',
    'configured_at',
    'sandbox_verified_at',
    'healthy_at',
    'production_allowed_at',
    'last_health_checked_at',
    'last_used_at',
    'last_error',
    'created_by',
    'updated_by',
])]
class AcctProviderConnection extends Model
{
    use HasFactory;

    protected $table = 'acct_provider_connections';

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'allowed_hosts' => 'array',
            'settings' => 'array',
            'is_enabled' => 'boolean',
            'kill_switch' => 'boolean',
            'configured_at' => 'datetime',
            'sandbox_verified_at' => 'datetime',
            'healthy_at' => 'datetime',
            'production_allowed_at' => 'datetime',
            'last_health_checked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(AcctOrganization::class, 'organization_id');
    }

    public function series(): HasMany
    {
        return $this->hasMany(AcctProviderSeries::class, 'connection_id');
    }

    public function transmissions(): HasMany
    {
        return $this->hasMany(AcctEinvoiceTransmission::class, 'connection_id');
    }

    public function inboundInvoices(): HasMany
    {
        return $this->hasMany(AcctEinvoiceInbound::class, 'connection_id');
    }
}
