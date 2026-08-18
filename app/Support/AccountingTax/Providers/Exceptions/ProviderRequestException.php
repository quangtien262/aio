<?php

namespace App\Support\AccountingTax\Providers\Exceptions;

use RuntimeException;

class ProviderRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
