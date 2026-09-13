<?php

namespace Modules\FnbPos\Models;

final class FnbCheckLine extends FnbModel
{
    protected $table = 'fnb_check_lines';

    protected function casts(): array
    {
        return ['allocated_quantity' => 'decimal:6', 'allocated_subtotal_minor' => 'integer', 'allocated_discount_minor' => 'integer', 'allocated_service_charge_minor' => 'integer', 'allocated_tax_minor' => 'integer', 'allocated_pricing_rounding_minor' => 'integer', 'allocated_total_minor' => 'integer'];
    }
}
