<?php

namespace Modules\RetailPos\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

final class RetailNumbers
{
    public static function quantity(mixed $value, bool $positive = true): string
    {
        if (! is_string($value) && ! is_int($value)) {
            self::invalid('quantity');
        }
        if (! preg_match('/^\d{1,12}(?:\.\d{1,6})?$/D', (string) $value)) {
            self::invalid('quantity');
        }
        $n = BigDecimal::of((string) $value);
        if ($positive && $n->isLessThanOrEqualTo(0)) {
            self::invalid('quantity');
        }

        return (string) $n->toScale(6);
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
        $value = BigDecimal::of($quantity)->multipliedBy($price)->toScale(0, RoundingMode::HalfUp);
        if ($value->isGreaterThan('99999999999999')) {
            self::invalid('amount_minor');
        }

        return $value->toInt();
    }

    public static function invalid(string $field): never
    {
        throw ValidationException::withMessages([$field => ['Giá trị không hợp lệ hoặc vượt độ chính xác cho phép.']]);
    }
}
