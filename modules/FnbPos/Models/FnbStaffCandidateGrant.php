<?php

namespace Modules\FnbPos\Models;

final class FnbStaffCandidateGrant extends FnbModel
{
    protected $table = 'fnb_staff_candidate_grants';

    protected $hidden = ['request_session_hash', 'identifier_hmac', 'token_hash', 'nonce'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
