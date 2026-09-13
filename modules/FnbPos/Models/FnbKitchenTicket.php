<?php

namespace Modules\FnbPos\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class FnbKitchenTicket extends FnbModel
{
    protected $table = 'fnb_kitchen_tickets';

    protected function casts(): array
    {
        return ['priority' => 'integer', 'fired_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime', 'version' => 'integer'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FnbKitchenTicketLine::class, 'ticket_id');
    }
}
