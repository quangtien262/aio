<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'organization_id',
    'report_type',
    'definition_version',
    'format',
    'status',
    'idempotency_key',
    'request_fingerprint',
    'filters',
    'timezone',
    'artifact_path',
    'mime_type',
    'original_name',
    'checksum',
    'byte_size',
    'row_count',
    'requested_by',
    'snapshot_at',
    'started_at',
    'completed_at',
    'expires_at',
    'last_error',
])]
class AcctExport extends Model
{
    use HasFactory;

    protected $table = 'acct_exports';

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'byte_size' => 'integer',
            'row_count' => 'integer',
            'snapshot_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(AcctOrganization::class, 'organization_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'requested_by');
    }
}
