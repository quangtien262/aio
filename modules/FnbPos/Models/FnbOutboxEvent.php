<?php

namespace Modules\FnbPos\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class FnbOutboxEvent extends FnbModel
{
    public $timestamps = false;

    protected $table = 'fnb_outbox_events';

    protected function casts(): array
    {
        return ['aggregate_version' => 'integer', 'schema_version' => 'integer', 'payload' => 'array', 'occurred_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(FnbOutboxDelivery::class, 'outbox_event_id');
    }
}
