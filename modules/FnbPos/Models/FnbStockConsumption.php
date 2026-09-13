<?php

namespace Modules\FnbPos\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class FnbStockConsumption extends FnbModel
{
    protected $table = 'fnb_stock_consumptions';

    protected $hidden = ['payload_hash'];

    protected function casts(): array
    {
        return ['payload_snapshot' => 'array', 'attempts' => 'integer', 'posted_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FnbStockConsumptionLine::class, 'consumption_id');
    }
}
