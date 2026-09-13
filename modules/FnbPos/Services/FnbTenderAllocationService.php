<?php

namespace Modules\FnbPos\Services;

use Modules\FnbPos\Domain\FnbConflictException;

/** Balances both component columns and tender rows without introducing a rounding charge. */
final class FnbTenderAllocationService
{
    public function allocate(array $components, int $quantityMicros, array $tenders): array
    {
        $coefficients = array_fill_keys(array_keys($components), 1);
        $coefficients['allocated_discount_minor'] = -1;
        $economicTotal = array_sum(array_map(fn ($key) => $components[$key] * $coefficients[$key], array_keys($components)));
        if ($economicTotal !== array_sum($tenders) || $economicTotal < 1) {
            throw new FnbConflictException('Tổng phương thức hoàn không khớp giá trị bồi hoàn.');
        }
        $rows = array_fill_keys(array_keys($tenders), []);
        foreach ($components as $component => $total) {
            foreach ($this->split(abs($total), $tenders) as $id => $amount) {
                $rows[$id][$component] = $amount * ($total < 0 ? -1 : 1);
            }
        }
        $deltas = [];
        foreach ($rows as $id => $row) {
            $deltas[$id] = $tenders[$id] - array_sum(array_map(fn ($key) => $row[$key] * $coefficients[$key], array_keys($components)));
        }
        foreach ($deltas as $recipient => $deficit) {
            if ($deficit <= 0) {
                continue;
            }
            foreach (array_keys($deltas) as $donor) {
                if ($deltas[$donor] >= 0 || $deltas[$recipient] === 0) {
                    continue;
                }
                foreach ($components as $component => $total) {
                    $sign = $total < 0 ? -1 : 1;
                    $positiveContribution = $sign * $coefficients[$component] > 0;
                    $from = $positiveContribution ? $donor : $recipient;
                    $to = $positiveContribution ? $recipient : $donor;
                    $move = min(abs($rows[$from][$component]), $deltas[$recipient], -$deltas[$donor]);
                    $rows[$from][$component] -= $move * $sign;
                    $rows[$to][$component] += $move * $sign;
                    $deltas[$recipient] -= $move;
                    $deltas[$donor] += $move;
                }
            }
        }
        if (array_filter($deltas, fn ($delta) => $delta !== 0) !== []) {
            throw new FnbConflictException('Không thể phân bổ vector hoàn tiền chính xác.');
        }
        $quantities = $this->split($quantityMicros, $tenders);
        foreach ($rows as $id => &$row) {
            if ($quantities[$id] < 1) {
                throw new FnbConflictException('Số lượng quá nhỏ để tách trên nhiều phương thức.');
            }
            $row['quantity_micros'] = $quantities[$id];
            $row['refund_total_minor'] = $tenders[$id];
        }

        return $rows;
    }

    private function split(int $total, array $weights): array
    {
        $weightTotal = array_sum($weights);
        $parts = $remainders = [];
        foreach ($weights as $id => $weight) {
            if ($weight < 1 || $weightTotal < 1) {
                throw new FnbConflictException('Hạn mức phương thức hoàn không hợp lệ.');
            }
            $numerator = bcmul((string) $total, (string) $weight, 0);
            $parts[$id] = (int) bcdiv($numerator, (string) $weightTotal, 0);
            $remainders[$id] = bcmod($numerator, (string) $weightTotal);
        }
        uksort($remainders, fn ($a, $b) => bccomp($remainders[$b], $remainders[$a], 0) ?: $a <=> $b);
        $left = $total - array_sum($parts);
        foreach (array_keys($remainders) as $id) {
            if ($left-- <= 0) {
                break;
            }
            $parts[$id]++;
        }

        return $parts;
    }
}
