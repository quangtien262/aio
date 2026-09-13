<?php

namespace Modules\FnbPos\Domain;

final class FnbConflictException extends FnbDomainException
{
    /** @param array<string,mixed> $details */
    public function __construct(string $message, array $details = [])
    {
        parent::__construct($message, 409, $details);
    }
}
