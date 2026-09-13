<?php

namespace Modules\FnbPos\Domain;

final class FnbValidationException extends FnbDomainException
{
    /** @param array<string,mixed> $details */
    public function __construct(string $message, array $details = [])
    {
        parent::__construct($message, 422, $details);
    }
}
