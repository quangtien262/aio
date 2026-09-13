<?php

namespace Modules\FnbPos\Domain;

final class MinorMoney
{
    public const QUANTITY_SCALE = 1_000_000;

    public static function assertMinor(int $amount, string $field = 'amount_minor', bool $allowNegative = false): int
    {
        if (! $allowNegative && $amount < 0) {
            throw new FnbValidationException("{$field} must be zero or greater.", [$field => $amount]);
        }

        return $amount;
    }

    public static function quantityMicros(string|int|float $quantity, string $field = 'quantity'): int
    {
        $raw = trim((string) $quantity);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.(\d{1,6}))?$/', $raw, $matches)) {
            throw new FnbValidationException("{$field} must be a positive decimal with at most six places.");
        }

        $fraction = str_pad($matches[1] ?? '', 6, '0');
        $whole = (int) strstr($raw.'.', '.', true);
        if ($whole > intdiv(PHP_INT_MAX - (int) $fraction, self::QUANTITY_SCALE)) {
            throw new FnbValidationException("{$field} is too large.");
        }

        $micros = $whole * self::QUANTITY_SCALE + (int) $fraction;
        if ($micros <= 0) {
            throw new FnbValidationException("{$field} must be greater than zero.");
        }

        return $micros;
    }

    public static function formatQuantity(int $micros): string
    {
        if ($micros < 0) {
            throw new FnbValidationException('Quantity cannot be negative.');
        }

        return sprintf('%d.%06d', intdiv($micros, self::QUANTITY_SCALE), $micros % self::QUANTITY_SCALE);
    }

    /** Multiply a minor-unit price by a six-place quantity, half-up. */
    public static function multiply(int $unitMinor, int $quantityMicros): int
    {
        self::assertMinor($unitMinor, 'unit_minor');
        if ($quantityMicros < 0) {
            throw new FnbValidationException('Quantity cannot be negative.');
        }
        if ($quantityMicros !== 0 && $unitMinor > intdiv(PHP_INT_MAX - 500_000, $quantityMicros)) {
            throw new FnbValidationException('Monetary multiplication overflow.');
        }

        return intdiv($unitMinor * $quantityMicros + 500_000, self::QUANTITY_SCALE);
    }

    /** @return array{rounding_minor:int,settlement_total_minor:int} */
    public static function roundCash(int $grandTotalMinor, int $stepMinor): array
    {
        self::assertMinor($grandTotalMinor, 'grand_total_minor');
        if ($stepMinor < 1) {
            throw new FnbValidationException('cash_rounding_step_minor must be positive.');
        }

        $lower = intdiv($grandTotalMinor, $stepMinor) * $stepMinor;
        $remainder = $grandTotalMinor - $lower;
        $rounded = $remainder * 2 >= $stepMinor ? $lower + $stepMinor : $lower;

        return [
            'rounding_minor' => $rounded - $grandTotalMinor,
            'settlement_total_minor' => $rounded,
        ];
    }

    /**
     * Deterministic largest-remainder allocation. Ties are resolved by the
     * original stable key order.
     *
     * @param  array<int|string,int>  $weights
     * @return array<int|string,int>
     */
    public static function allocate(int $totalMinor, array $weights): array
    {
        self::assertMinor($totalMinor, 'total_minor');
        if ($weights === [] || array_filter($weights, fn (int $weight): bool => $weight < 0) !== []) {
            throw new FnbValidationException('Allocation weights must be non-negative and non-empty.');
        }

        $weightTotal = array_sum($weights);
        if ($weightTotal <= 0) {
            throw new FnbValidationException('Allocation weights must have a positive sum.');
        }

        $allocated = [];
        $remainders = [];
        $used = 0;
        foreach ($weights as $key => $weight) {
            if ($weight !== 0 && $totalMinor > intdiv(PHP_INT_MAX, $weight)) {
                throw new FnbValidationException('Monetary allocation overflow.');
            }
            $numerator = $totalMinor * $weight;
            $floor = intdiv($numerator, $weightTotal);
            $allocated[$key] = $floor;
            $remainders[] = ['key' => $key, 'remainder' => $numerator % $weightTotal];
            $used += $floor;
        }

        usort($remainders, fn (array $a, array $b): int => $b['remainder'] <=> $a['remainder']);
        for ($index = 0, $left = $totalMinor - $used; $index < $left; $index++) {
            $allocated[$remainders[$index % count($remainders)]['key']]++;
        }

        return $allocated;
    }
}
