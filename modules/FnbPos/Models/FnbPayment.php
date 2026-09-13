<?php

namespace Modules\FnbPos\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class FnbPayment extends FnbModel
{
    protected $table = 'fnb_payments';

    protected $hidden = ['request_fingerprint', 'provider_operation_key', 'provider_reference', 'metadata'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'tendered_minor' => 'integer', 'change_minor' => 'integer', 'metadata' => 'array', 'reserved_at' => 'datetime', 'provider_dispatched_at' => 'datetime', 'processed_at' => 'datetime', 'cancelled_at' => 'datetime', 'expired_at' => 'datetime'];
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(FnbRefund::class, 'payment_id');
    }
}
