<?php

namespace Modules\FnbPos\Models;

final class FnbPrepStation extends FnbModel
{
    protected $table = 'fnb_prep_stations';

    protected function casts(): array
    {
        return ['settings' => 'array', 'sla_seconds' => 'integer', 'sort_order' => 'integer', 'version' => 'integer'];
    }
}
