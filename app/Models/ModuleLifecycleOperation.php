<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operation_id', 'module_key', 'operation', 'status', 'active_slot',
    'from_version', 'target_version', 'owner_token', 'initiated_by',
    'context', 'error', 'started_at', 'heartbeat_at', 'completed_at',
])]
class ModuleLifecycleOperation extends Model
{
    use HasFactory;

    public const ACTIVE_SLOT = 'active';

    protected $hidden = ['owner_token'];

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'initiated_by');
    }

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'error' => 'array',
            'started_at' => 'datetime',
            'heartbeat_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
