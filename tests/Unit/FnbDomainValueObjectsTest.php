<?php

namespace Tests\Unit;

use Modules\FnbPos\Domain\FnbValidationException;
use Modules\FnbPos\Domain\MinorMoney;
use Modules\FnbPos\Domain\RecipeConsumption;
use PHPUnit\Framework\TestCase;

final class FnbDomainValueObjectsTest extends TestCase
{
    public function test_money_allocation_is_exact_and_uses_stable_largest_remainder(): void
    {
        $this->assertSame(['a' => 34, 'b' => 33, 'c' => 33], MinorMoney::allocate(100, ['a' => 1, 'b' => 1, 'c' => 1]));
        $this->assertSame(58_000, MinorMoney::multiply(29_000, MinorMoney::quantityMicros('2.000000')));
        $this->assertSame(['rounding_minor' => -200, 'settlement_total_minor' => 28_500], MinorMoney::roundCash(28_700, 500));
    }

    public function test_money_multiplication_rejects_integer_overflow(): void
    {
        $this->expectException(FnbValidationException::class);
        MinorMoney::multiply(PHP_INT_MAX, 2_000_000);
    }

    public function test_recipe_expansion_applies_yield_binding_selection_and_loss(): void
    {
        $lines = RecipeConsumption::expand([
            'yield_quantity' => '2.000000',
            'binding_multiplier' => '1.500000',
            'lines' => [[
                'ingredient_id' => 7,
                'base_quantity' => '10.000000',
                'base_unit' => 'g',
                'loss_rate' => '0.1000',
            ]],
        ], '3.000000', '2.000000');

        // 10 * 1.5 * 3 * 2 / 2 / (1 - .1) = 50.
        $this->assertSame('50.000000', $lines[0]['consumed_quantity']);
    }
}
