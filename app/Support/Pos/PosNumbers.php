<?php

namespace App\Support\Pos;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

/** Pure calculations shared by POS consumers. No persistence or ambient context. */
final class PosNumbers
{
    public static function quantity(mixed $value, bool $positive = true): string
    {
        if ((! is_string($value) && ! is_int($value)) || ! preg_match('/^\d{1,12}(?:\.\d{1,6})?$/D', (string) $value)) {
            self::invalid('quantity');
        }
        $number = BigDecimal::of((string) $value);
        if ($positive && $number->isLessThanOrEqualTo(0)) {
            self::invalid('quantity');
        }

        return (string) $number->toScale(6);
    }

    public static function money(mixed $value): int
    {
        if ((! is_string($value) && ! is_int($value)) || ! preg_match('/^\d{1,14}$/D', (string) $value)) {
            self::invalid('amount_minor');
        }

        return (int) $value;
    }

    public static function amount(string $quantity, int $price): int
    {
        $amount = BigDecimal::of(self::quantity($quantity, false))->multipliedBy(self::money($price))->toScale(0, RoundingMode::HalfUp);
        if ($amount->isGreaterThan('99999999999999')) {
            self::invalid('amount_minor');
        }

        return $amount->toInt();
    }

    public static function allocate(int $total, array $weights): array
    {
        self::money($total);
        $sum = BigDecimal::zero();
        foreach ($weights as $weight) {
            $sum = $sum->plus(self::money($weight));
        }
        if ($sum->isZero()) {
            if ($total !== 0) {
                self::invalid('amount_minor');
            }

            return array_fill_keys(array_keys($weights), 0);
        }
        $parts = [];
        $allocated = 0;
        foreach ($weights as $key => $weight) {
            self::money($weight);
            $parts[$key] = BigDecimal::of($total)->multipliedBy($weight)->dividedBy($sum, 0, RoundingMode::Down)->toInt();
            $allocated += $parts[$key];
        }
        foreach (array_keys($weights) as $key) {
            if ($allocated >= $total) {
                break;
            } $parts[$key]++;
            $allocated++;
        }

        return $parts;
    }

    public static function invalid(string $field): never
    {
        throw ValidationException::withMessages([$field => 'Giá trị không hợp lệ hoặc vượt độ chính xác cho phép.']);
    }
}
