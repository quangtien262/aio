<?php

namespace App\Support;

use Illuminate\Support\Str;

class PermissionLabel
{
    public static function make(string $permissionKey): string
    {
        return collect(explode('.', $permissionKey))
            ->filter()
            ->map(fn (string $segment): string => self::formatSegment($segment))
            ->implode(' ');
    }

    private static function formatSegment(string $segment): string
    {
        $normalizedSegment = strtolower($segment);

        return match ($normalizedSegment) {
            'api' => 'API',
            'cms' => 'CMS',
            'rbac' => 'RBAC',
            'seo' => 'SEO',
            'sms' => 'SMS',
            'ui' => 'UI',
            default => Str::of(str_replace(['-', '_'], ' ', $segment))->title()->toString(),
        };
    }
}