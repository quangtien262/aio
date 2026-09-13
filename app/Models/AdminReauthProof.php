<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'admin_id', 'module_key', 'token_hash', 'nonce', 'session_id_hash',
    'auth_version', 'factor_set', 'scope_type', 'scope_value', 'action',
    'subject_type', 'subject_id', 'payload_hash', 'ip_hash', 'expires_at',
    'consumed_at', 'revoked_at',
])]
class AdminReauthProof extends Model
{
    use HasFactory;

    protected $hidden = [
        'token_hash',
        'nonce',
        'session_id_hash',
        'ip_hash',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    protected function casts(): array
    {
        return [
            'auth_version' => 'integer',
            'factor_set' => 'array',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
