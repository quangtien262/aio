<?php

namespace Modules\RetailPos\Domain;

use App\Support\Pos\PosNumbers;

/** Compatibility facade: Retail keeps its own ledger and transaction authority. */
final class RetailNumbers
{
    public static function quantity(mixed $value, bool $positive = true): string
    {
        return PosNumbers::quantity($value, $positive);
    }

    public static function money(mixed $value): int
    {
        return PosNumbers::money($value);
    }

    public static function amount(string $quantity, int $price): int
    {
        return PosNumbers::amount($quantity, $price);
    }

    public static function invalid(string $field): never
    {
        PosNumbers::invalid($field);
    }
}
