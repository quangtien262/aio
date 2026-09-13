<?php

namespace Modules\FnbPos\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class FnbOrderLine extends FnbModel
{
    protected $table = 'fnb_order_lines';

    protected function casts(): array
    {
        return ['ordered_quantity' => 'decimal:6', 'voided_quantity' => 'decimal:6', 'fulfilled_quantity' => 'decimal:6',
            'compensated_quantity' => 'decimal:6', 'original_unit_price_minor' => 'integer', 'unit_price_minor' => 'integer',
            'subtotal_minor' => 'integer', 'discount_total_minor' => 'integer', 'tax_total_minor' => 'integer',
            'service_charge_total_minor' => 'integer', 'pricing_rounding_minor' => 'integer', 'total_minor' => 'integer',
            'recipe_snapshot' => 'array', 'submitted_at' => 'datetime'];
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(FnbOrderLineModifier::class, 'order_line_id');
    }
}
