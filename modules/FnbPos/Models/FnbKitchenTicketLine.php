<?php

namespace Modules\FnbPos\Models;

final class FnbKitchenTicketLine extends FnbModel
{
    protected $table = 'fnb_kitchen_ticket_lines';

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'served_quantity' => 'decimal:6', 'voided_quantity' => 'decimal:6', 'compensated_quantity' => 'decimal:6', 'waiting_at' => 'datetime', 'preparing_at' => 'datetime', 'ready_at' => 'datetime', 'served_at' => 'datetime', 'cancelled_at' => 'datetime', 'version' => 'integer'];
    }
}
