<?php

namespace Modules\FnbPos\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class FnbBusinessDay extends FnbModel
{
    protected $table = 'fnb_business_days';

    protected function casts(): array
    {
        return ['business_date' => 'date', 'opened_at' => 'datetime', 'closed_at' => 'datetime', 'reconciled_at' => 'datetime', 'version' => 'integer'];
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(FnbShift::class, 'business_day_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(FnbServiceSession::class, 'business_day_id');
    }
}
