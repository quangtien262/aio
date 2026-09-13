<?php

namespace Modules\FnbPos\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class FnbApproval extends FnbModel
{
    protected $table = 'fnb_approvals';

    protected $hidden = [
        'requester_session_hash', 'requester_authority_hash',
        'approver_authority_hash', 'payload_hash', 'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'required_permissions' => 'array',
            'policy_snapshot' => 'array',
            'requester_authority_revision' => 'integer',
            'approver_authority_revision' => 'integer',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'consumed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(FnbApprovalEvent::class, 'approval_id');
    }
}
