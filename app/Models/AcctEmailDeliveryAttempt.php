<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'delivery_id',
    'attempt_no',
    'status',
    'provider',
    'provider_message_id',
    'error_class',
    'error_message',
    'metadata',
    'started_at',
    'completed_at',
])]
class AcctEmailDeliveryAttempt extends Model
{
    use HasFactory;

    protected $table = 'acct_email_delivery_attempts';

    protected function casts(): array
    {
        return [
            'attempt_no' => 'integer',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(AcctEmailDelivery::class, 'delivery_id');
    }
}
