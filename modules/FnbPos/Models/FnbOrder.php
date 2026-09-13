<?php

namespace Modules\FnbPos\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class FnbOrder extends FnbModel
{
    protected $table = 'fnb_orders';

    protected function casts(): array
    {
        return ['subtotal_minor' => 'integer', 'discount_total_minor' => 'integer', 'tax_total_minor' => 'integer',
            'service_charge_total_minor' => 'integer', 'pricing_rounding_minor' => 'integer', 'grand_total_minor' => 'integer',
            'pricing_snapshot' => 'array', 'submitted_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime', 'version' => 'integer'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FnbOrderLine::class, 'order_id');
    }

    public function financialProjection(): HasOne
    {
        return $this->hasOne(FnbOrderFinancialProjection::class, 'order_id');
    }
}
