<?php

namespace Modules\FnbPos\Models;

final class FnbRefundAllocation extends FnbModel
{
    protected $table = 'fnb_refund_allocations';

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'allocated_subtotal_minor' => 'integer', 'allocated_discount_minor' => 'integer', 'allocated_service_charge_minor' => 'integer', 'allocated_tax_minor' => 'integer', 'allocated_pricing_rounding_minor' => 'integer', 'allocated_cash_rounding_minor' => 'integer', 'allocated_total_minor' => 'integer'];
    }
}
