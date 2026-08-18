<?php

namespace App\Support\AccountingTax\Providers\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ProviderSafetyException extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct(409, $message);
    }
}
