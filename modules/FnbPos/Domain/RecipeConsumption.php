<?php

namespace Modules\FnbPos\Domain;

/** Deterministic six-decimal recipe expansion used by sale, waste and reversal ledgers. */
final class RecipeConsumption
{
    /**
     * @param  array<string,mixed>  $recipe  Immutable recipe snapshot.
     * @return list<array<string,mixed>>
     */
    public static function expand(array $recipe, string $saleQuantity, string $selectionQuantity = '1.000000'): array
    {
        $yield = (string) ($recipe['yield_quantity'] ?? '0');
        $binding = (string) ($recipe['binding_multiplier'] ?? '1');
        if (bccomp($yield, '0', 12) <= 0 || bccomp($binding, '0', 12) <= 0
            || bccomp($saleQuantity, '0', 12) <= 0 || bccomp($selectionQuantity, '0', 12) <= 0) {
            throw new FnbValidationException('Recipe consumption factors must be positive.');
        }

        $result = [];
        foreach ($recipe['lines'] ?? [] as $ingredient) {
            $base = (string) ($ingredient['base_quantity'] ?? '0');
            $loss = (string) ($ingredient['loss_rate'] ?? '0');
            $retained = bcsub('1', $loss, 12);
            if (bccomp($base, '0', 12) <= 0 || bccomp($retained, '0', 12) <= 0) {
                throw new FnbValidationException('Recipe snapshot contains an invalid quantity or loss rate.');
            }

            // base_quantity is the net recipe requirement. Gross stock usage
            // includes selection/binding multipliers, yield and preparation loss.
            $numerator = bcmul(bcmul(bcmul($base, $binding, 12), $saleQuantity, 12), $selectionQuantity, 12);
            $raw = bcdiv(bcdiv($numerator, $yield, 12), $retained, 12);
            $quantity = bcdiv(bcadd($raw, '0.0000005', 12), '1', 6);
            if (bccomp($quantity, '0', 6) <= 0) {
                continue;
            }
            $result[] = $ingredient + ['consumed_quantity' => $quantity];
        }

        return $result;
    }
}
