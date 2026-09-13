<?php

namespace Modules\FnbPos\Models;

final class FnbShift extends FnbModel
{
    protected $table = 'fnb_shifts';

    protected function casts(): array
    {
        return ['opening_float_minor' => 'integer', 'expected_cash_minor' => 'integer', 'counted_cash_minor' => 'integer',
            'variance_minor' => 'integer', 'closing_snapshot' => 'array', 'opened_at' => 'datetime', 'closed_at' => 'datetime',
            'reconciled_at' => 'datetime', 'version' => 'integer'];
    }
}
