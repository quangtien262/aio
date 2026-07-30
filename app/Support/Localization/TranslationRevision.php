<?php

namespace App\Support\Localization;

final class TranslationRevision
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fingerprint(array $payload): string
    {
        $normalized = self::normalize($payload);

        return hash(
            'sha256',
            json_encode(
                $normalized,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ),
        );
    }

    private static function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return is_string($value) ? str_replace(["\r\n", "\r"], "\n", trim($value)) : $value;
        }

        if (array_is_list($value)) {
            return array_map(self::normalize(...), $value);
        }

        ksort($value);

        return array_map(self::normalize(...), $value);
    }
}
