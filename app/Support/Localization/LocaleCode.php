<?php

namespace App\Support\Localization;

use InvalidArgumentException;

final class LocaleCode
{
    public const MAX_LENGTH = 35;

    public static function normalize(string $locale): string
    {
        $locale = str_replace('_', '-', trim($locale));

        if (! self::isValid($locale)) {
            throw new InvalidArgumentException(sprintf('Invalid BCP 47 locale code [%s].', $locale));
        }

        $parts = explode('-', $locale);

        if (strtolower($parts[0]) === 'x') {
            return strtolower($locale);
        }

        $normalized = [strtolower(array_shift($parts))];
        $index = 0;

        while (
            $index < count($parts)
            && count($normalized) <= 3
            && strlen($parts[$index]) === 3
            && ctype_alpha($parts[$index])
        ) {
            $normalized[] = strtolower($parts[$index]);
            $index++;
        }

        if (
            isset($parts[$index])
            && strlen($parts[$index]) === 4
            && ctype_alpha($parts[$index])
        ) {
            $normalized[] = ucfirst(strtolower($parts[$index]));
            $index++;
        }

        if (
            isset($parts[$index])
            && (
                (strlen($parts[$index]) === 2 && ctype_alpha($parts[$index]))
                || (strlen($parts[$index]) === 3 && ctype_digit($parts[$index]))
            )
        ) {
            $normalized[] = ctype_alpha($parts[$index])
                ? strtoupper($parts[$index])
                : $parts[$index];
            $index++;
        }

        while (isset($parts[$index])) {
            $normalized[] = strtolower($parts[$index]);
            $index++;
        }

        return implode('-', $normalized);
    }

    public static function tryNormalize(?string $locale): ?string
    {
        if ($locale === null || trim($locale) === '') {
            return null;
        }

        try {
            return self::normalize($locale);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public static function isValid(?string $locale): bool
    {
        if ($locale === null) {
            return false;
        }

        $locale = str_replace('_', '-', trim($locale));

        if ($locale === '' || strlen($locale) > self::MAX_LENGTH) {
            return false;
        }

        $language = '(?:[A-Za-z]{2,3}(?:-[A-Za-z]{3}){0,3}|[A-Za-z]{4}|[A-Za-z]{5,8})';
        $script = '(?:-[A-Za-z]{4})?';
        $region = '(?:-(?:[A-Za-z]{2}|[0-9]{3}))?';
        $variant = '(?:-(?:[A-Za-z0-9]{5,8}|[0-9][A-Za-z0-9]{3}))*';
        $extension = '(?:-[0-9A-WY-Za-wy-z](?:-[A-Za-z0-9]{2,8})+)*';
        $privateUse = '(?:-x(?:-[A-Za-z0-9]{1,8})+)?';
        $privateOnly = 'x(?:-[A-Za-z0-9]{1,8})+';

        return preg_match(
            '/^(?:'.$language.$script.$region.$variant.$extension.$privateUse.'|'.$privateOnly.')$/D',
            $locale,
        ) === 1;
    }

    public static function routePattern(): string
    {
        return '(?:[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*|x(?:-[A-Za-z0-9]{1,8})+)';
    }
}
