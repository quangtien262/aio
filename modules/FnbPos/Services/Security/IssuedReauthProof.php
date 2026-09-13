<?php

namespace Modules\FnbPos\Services\Security;

use Carbon\CarbonInterface;

final readonly class IssuedReauthProof
{
    public string $reauthProof;

    public function __construct(
        public string $token,
        public CarbonInterface $expiresAt,
    ) {
        $this->reauthProof = $token;
    }

    /** @return array{reauth_proof:string,expires_at:string} */
    public function toArray(): array
    {
        return [
            'reauth_proof' => $this->token,
            'expires_at' => $this->expiresAt->toISOString(),
        ];
    }
}
