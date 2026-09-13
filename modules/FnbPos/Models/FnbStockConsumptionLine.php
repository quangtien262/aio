<?php

namespace Modules\FnbPos\Models;

final class FnbStockConsumptionLine extends FnbModel
{
    protected $table = 'fnb_stock_consumption_lines';

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'snapshot' => 'array'];
    }
}
