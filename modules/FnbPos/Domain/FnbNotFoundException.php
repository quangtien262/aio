<?php

namespace Modules\FnbPos\Domain;

final class FnbNotFoundException extends FnbDomainException
{
    /** @param array<string,mixed> $details */
    public function __construct(string $message = 'F&B resource was not found in the current scope.', array $details = [])
    {
        parent::__construct($message, 404, $details);
    }
}
