<?php

namespace Modules\FnbPos\Domain;

/**
 * Mutable, server-only evidence populated by the authorization adapter inside
 * the command transaction. It is deliberately not constructed from request
 * payload data.
 */
final class FnbAuthorizationEvidence
{
    private ?int $approvalId = null;

    private ?int $approverId = null;

    public function recordApproval(int $approvalId, int $approverId): void
    {
        if ($approvalId < 1 || $approverId < 1) {
            throw new FnbValidationException('Invalid approval evidence.');
        }

        $this->approvalId = $approvalId;
        $this->approverId = $approverId;
    }

    public function clear(): void
    {
        $this->approvalId = null;
        $this->approverId = null;
    }

    public function approvalId(): ?int
    {
        return $this->approvalId;
    }

    public function approverId(): ?int
    {
        return $this->approverId;
    }
}
