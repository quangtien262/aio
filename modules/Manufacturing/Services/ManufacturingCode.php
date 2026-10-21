<?php

namespace Modules\Manufacturing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Assign new identifiers inside the command transaction, before validation. */
final class ManufacturingCode
{
    public static function missing(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    public static function generate(string $prefix): string
    {
        return $prefix.(string) Str::ulid();
    }

    public static function fill(array $data, string $field, string $prefix, string $table, array $scope = [], mixed $existing = null, ?string $column = null): array
    {
        if (! self::missing($data[$field] ?? null)) {
            return $data;
        }
        if ($existing !== null) {
            $data[$field] = $existing;

            return $data;
        }
        // ULIDs avoid a shared counter/lock across companies and warehouses.
        // The scope's unique index remains the final concurrency guard.
        do {
            $code = self::generate($prefix);
        } while (DB::table($table)->where($scope)->where($column ?? $field, $code)->exists());
        $data[$field] = $code;

        return $data;
    }
}
