<?php

namespace Modules\FnbPos\Models;

final class FnbOrderLineModifier extends FnbModel
{
    protected $table = 'fnb_order_line_modifiers';

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'unit_price_delta_minor' => 'integer', 'total_minor' => 'integer', 'recipe_snapshot' => 'array'];
    }
}
