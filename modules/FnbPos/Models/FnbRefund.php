<?php

namespace Modules\FnbPos\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class FnbRefund extends FnbModel
{
    protected $table = 'fnb_refunds';

    protected $hidden = ['request_fingerprint', 'provider_operation_key', 'provider_reference', 'metadata'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'metadata' => 'array', 'requested_at' => 'datetime', 'approved_at' => 'datetime', 'reserved_at' => 'datetime', 'provider_dispatched_at' => 'datetime', 'processed_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime', 'expired_at' => 'datetime'];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FnbRefundAllocation::class, 'refund_id');
    }
}
