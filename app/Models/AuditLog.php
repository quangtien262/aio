<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sequence', 'actor_admin_id', 'action', 'module_key', 'website_key', 'target_type',
    'target_id', 'before', 'after', 'ip_address', 'user_agent', 'request_id',
    'previous_hash', 'entry_hash',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'actor_admin_id');
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Audit logs are append-only.');
        });
        static::deleting(function (): never {
            throw new \LogicException('Audit logs are append-only.');
        });
    }
}
