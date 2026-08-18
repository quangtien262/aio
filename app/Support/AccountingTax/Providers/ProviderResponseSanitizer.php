<?php

namespace App\Support\AccountingTax\Providers;

class ProviderResponseSanitizer
{
    private const SENSITIVE_KEYS = [
        'password',
        'token',
        'access_token',
        'refresh_token',
        'api_token',
        'authorization',
        'credentials',
        'secret',
        'private_key',
        'certificate_password',
    ];

    public function sanitize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return is_string($value) && strlen($value) > 20000
                ? substr($value, 0, 20000).'…'
                : $value;
        }

        $sanitized = [];

        foreach ($value as $key => $item) {
            $normalizedKey = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', (string) $key));

            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            $sanitized[$key] = $this->sanitize($item);
        }

        return $sanitized;
    }
}
