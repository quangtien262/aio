<?php

namespace Modules\FnbPos\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FnbOutletStaffTerminal extends FnbModel
{
    protected $table = 'fnb_outlet_staff_terminals';

    public function membership(): BelongsTo
    {
        return $this->belongsTo(FnbOutletStaff::class, 'outlet_staff_id');
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(FnbTerminal::class, 'terminal_id');
    }
}
