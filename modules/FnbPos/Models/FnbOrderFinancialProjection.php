<?php

namespace Modules\FnbPos\Models;

final class FnbOrderFinancialProjection extends FnbModel
{
    public $timestamps = false;

    protected $table = 'fnb_order_financial_projections';

    protected function casts(): array
    {
        return ['gross_paid_total_minor' => 'integer', 'refunded_total_minor' => 'integer', 'net_collected_total_minor' => 'integer', 'source_watermark' => 'integer', 'version' => 'integer', 'updated_at' => 'datetime'];
    }
}
