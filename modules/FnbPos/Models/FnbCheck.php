<?php

namespace Modules\FnbPos\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class FnbCheck extends FnbModel
{
    protected $table = 'fnb_checks';

    protected $hidden = ['buyer_snapshot'];

    protected function casts(): array
    {
        return ['subtotal_minor' => 'integer', 'discount_total_minor' => 'integer', 'tax_total_minor' => 'integer',
            'service_charge_total_minor' => 'integer', 'pricing_rounding_minor' => 'integer', 'grand_total_minor' => 'integer',
            'cash_rounding_minor' => 'integer', 'settlement_total_minor' => 'integer', 'buyer_snapshot' => 'array',
            'invoice_requested' => 'boolean', 'finalized_at' => 'datetime', 'settlement_planned_at' => 'datetime',
            'paid_at' => 'datetime', 'closed_at' => 'datetime', 'voided_at' => 'datetime', 'version' => 'integer'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FnbCheckLine::class, 'check_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FnbPayment::class, 'check_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(FnbRefund::class, 'check_id');
    }

    public function financialProjection(): HasOne
    {
        return $this->hasOne(FnbCheckFinancialProjection::class, 'check_id');
    }
}
