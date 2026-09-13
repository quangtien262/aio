<?php

namespace Modules\FnbPos\Domain;

use Closure;

final readonly class FnbContext
{
    public FnbAuthorizationEvidence $authorizationEvidence;

    /**
     * The callback is supplied by the trusted HTTP/security adapter. It is
     * deliberately executed by the command transaction only after replay
     * detection, so a retry never consumes a second approval/reauth proof.
     *
     * @param  null|Closure(string,array<string,mixed>,self):void  $authorizeMutation
     */
    public function __construct(
        public string $websiteKey,
        public int $outletId,
        public int $actorId,
        public ?int $terminalId = null,
        public ?Closure $authorizeMutation = null,
        ?FnbAuthorizationEvidence $authorizationEvidence = null,
    ) {
        // outletId=0 is the explicit pre-outlet scope used only by onboarding.
        // Every outlet-bound facade method rejects it before reading data.
        if (trim($this->websiteKey) === '' || $this->outletId < 0 || $this->actorId < 1) {
            throw new FnbValidationException('Invalid trusted F&B scope.');
        }

        if ($this->terminalId !== null && $this->terminalId < 1) {
            throw new FnbValidationException('Invalid terminal scope.');
        }

        $this->authorizationEvidence = $authorizationEvidence ?? new FnbAuthorizationEvidence;
    }

    /** @param array<string,mixed> $payload */
    public function authorize(string $operation, array $payload = []): void
    {
        if ($this->authorizeMutation !== null) {
            // Never let evidence from a prior command on a reused context leak
            // into a mutation that did not consume its own approval.
            $this->authorizationEvidence->clear();
            ($this->authorizeMutation)($operation, $payload, $this);
        }
    }
}
