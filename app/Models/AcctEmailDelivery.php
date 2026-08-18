<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'organization_id',
    'document_id',
    'recipient_email',
    'recipient_name',
    'template_key',
    'subject',
    'status',
    'idempotency_key',
    'request_fingerprint',
    'payload_snapshot',
    'attachments',
    'attempt_count',
    'provider',
    'provider_message_id',
    'last_error',
    'requested_by',
    'queued_at',
    'started_at',
    'sent_at',
    'completed_at',
])]
class AcctEmailDelivery extends Model
{
    use HasFactory;

    protected $table = 'acct_email_deliveries';

    protected function casts(): array
    {
        return [
            'payload_snapshot' => 'array',
            'attachments' => 'array',
            'attempt_count' => 'integer',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(AcctOrganization::class, 'organization_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AcctDocument::class, 'document_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'requested_by');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(AcctEmailDeliveryAttempt::class, 'delivery_id')->orderByDesc('attempt_no');
    }
}
