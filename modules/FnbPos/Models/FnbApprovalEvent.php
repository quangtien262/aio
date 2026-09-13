<?php

namespace Modules\FnbPos\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FnbApprovalEvent extends FnbModel
{
    protected $table = 'fnb_approval_events';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(FnbApproval::class, 'approval_id');
    }
}
