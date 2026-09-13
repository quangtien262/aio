<?php

namespace Tests\Unit;

use Modules\FnbPos\Services\FnbTenderAllocationService;
use PHPUnit\Framework\TestCase;

class FnbTenderAllocationTest extends TestCase
{
    public function test_tender_rows_and_component_columns_are_preserved_for_rounding_vectors(): void
    {
        $allocator = new FnbTenderAllocationService;
        for ($i = 1; $i <= 250; $i++) {
            $source = ['allocated_subtotal_minor' => 27999 + $i, 'allocated_discount_minor' => 901 + ($i % 101),
                'allocated_service_charge_minor' => $i % 97, 'allocated_tax_minor' => 2157 + $i,
                'allocated_pricing_rounding_minor' => ($i % 3) - 1, 'allocated_cash_rounding_minor' => 0];
            $total = array_sum($source) - 2 * $source['allocated_discount_minor'];
            $weights = [1 => 100 + $i, 2 => 9000 + $i, 3 => $total - 9100 - $i * 2];
            $rows = $allocator->allocate($source, 1000000, $weights);
            foreach ($source as $column => $amount) {
                $this->assertSame($amount, array_sum(array_column($rows, $column)));
            }
            $this->assertSame(1000000, array_sum(array_column($rows, 'quantity_micros')));
            foreach ($rows as $id => $row) {
                $economic = $row['allocated_subtotal_minor'] - $row['allocated_discount_minor'] + $row['allocated_service_charge_minor']
                    + $row['allocated_tax_minor'] + $row['allocated_pricing_rounding_minor'] + $row['allocated_cash_rounding_minor'];
                $this->assertSame($weights[$id], $economic);
            }
        }
    }
}
