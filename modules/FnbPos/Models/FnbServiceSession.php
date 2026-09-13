<?php

namespace Modules\FnbPos\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class FnbServiceSession extends FnbModel
{
    protected $table = 'fnb_service_sessions';

    protected $hidden = ['customer_snapshot'];

    protected function casts(): array
    {
        return ['customer_snapshot' => 'array', 'guest_count' => 'integer', 'opened_at' => 'datetime', 'settling_at' => 'datetime', 'closed_at' => 'datetime', 'cancelled_at' => 'datetime', 'version' => 'integer'];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(FnbOrder::class, 'session_id');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(FnbCheck::class, 'session_id');
    }
}
