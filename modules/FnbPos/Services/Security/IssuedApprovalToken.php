<?php

namespace Modules\FnbPos\Services\Security;

use Carbon\CarbonInterface;

final readonly class IssuedApprovalToken
{
    public string $approvalToken;

    public function __construct(
        public string $token,
        public CarbonInterface $expiresAt,
    ) {
        $this->approvalToken = $token;
    }

    /** @return array{approval_token:string,expires_at:string} */
    public function toArray(): array
    {
        return [
            'approval_token' => $this->token,
            'expires_at' => $this->expiresAt->toISOString(),
        ];
    }
}
