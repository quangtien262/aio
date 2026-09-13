<?php

namespace Modules\FnbPos\Models;

final class FnbOutboxDelivery extends FnbModel
{
    protected $table = 'fnb_outbox_deliveries';

    protected $hidden = ['last_error'];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'max_attempts' => 'integer', 'available_at' => 'datetime', 'lease_expires_at' => 'datetime', 'published_at' => 'datetime'];
    }
}
