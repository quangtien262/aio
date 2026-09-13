<?php

namespace Modules\FnbPos\Models;

final class FnbIdempotencyRequest extends FnbModel
{
    protected $table = 'fnb_idempotency_requests';

    protected $hidden = ['request_hash', 'response_body', 'owner_token'];

    protected function casts(): array
    {
        return ['response_body' => 'array', 'response_code' => 'integer', 'heartbeat_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
