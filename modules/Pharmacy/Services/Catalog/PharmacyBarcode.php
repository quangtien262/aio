<?php

namespace Modules\Pharmacy\Services\Catalog;

/** Narrow GS1 parser: no guessed variable-field separators, lot assignment, or clinical decisions.
 * Reference: https://ref.gs1.org/guidelines/2d-in-retail/ (healthcare AI17 requires day 01–31).
 */
final class PharmacyBarcode
{
    public function parse(string $code): array
    {
        abort_if(strlen($code) > 300 || $code === '', 422, 'Mã quét rỗng hoặc quá dài.');
        if (preg_match('/^\d{8}$|^\d{12,14}$/D', $code)) {
            $this->gtin($code);

            return ['format' => 'gtin', 'gtin' => str_pad($code, 14, '0', STR_PAD_LEFT), 'batch_code' => null, 'expires_at' => null, 'serial' => null, 'requires_lot_confirmation' => true];
        }
        $fields = [];
        if (str_starts_with($code, '(')) {
            abort_unless(preg_match_all('/\((01|10|17|21)\)([^()]*)/', $code, $matches, PREG_SET_ORDER) && implode('', array_column($matches, 0)) === $code, 422, 'GS1 có AI không hỗ trợ hoặc dấu phân cách sai.');
            foreach ($matches as $match) {
                $this->field($fields, $match[1], $match[2]);
            }
        } else {
            $raw = preg_replace('/^\](d2|C1|Q3)/', '', $code);
            while ($raw !== '') {
                $raw = ltrim($raw, "\x1D");
                if ($raw === '') {
                    break;
                }
                $ai = substr($raw, 0, 2);
                $raw = substr($raw, 2);
                abort_unless(in_array($ai, ['01', '10', '17', '21'], true), 422, 'GS1 có AI không hỗ trợ; cần xác nhận mã thủ công.');
                if (in_array($ai, ['01', '17'], true)) {
                    $length = $ai === '01' ? 14 : 6;
                    $value = substr($raw, 0, $length);
                    $raw = substr($raw, $length);
                } else {
                    $end = strpos($raw, "\x1D");
                    $value = $end === false ? $raw : substr($raw, 0, $end);
                    $raw = $end === false ? '' : substr($raw, $end + 1);
                }
                $this->field($fields, $ai, $value);
            }
        }
        abort_unless(isset($fields['01']), 422, 'GS1 thiếu GTIN (01).');
        $this->gtin($fields['01']);
        $expiry = null;
        if (isset($fields['17'])) {
            abort_unless(preg_match('/^\d{6}$/D', $fields['17']), 422, 'Hạn dùng GS1 phải có YYMMDD.');
            [$year, $month, $day] = [2000 + (int) substr($fields['17'], 0, 2), (int) substr($fields['17'], 2, 2), (int) substr($fields['17'], 4, 2)];
            abort_unless(checkdate($month, $day, $year), 422, 'Hạn dùng GS1 không hợp lệ; không tự suy ngày 00.');
            $expiry = sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        return ['format' => 'gs1', 'gtin' => $fields['01'], 'batch_code' => $fields['10'] ?? null, 'expires_at' => $expiry, 'serial' => $fields['21'] ?? null, 'requires_lot_confirmation' => true];
    }

    private function field(array &$fields, string $ai, string $value): void
    {
        abort_if(isset($fields[$ai]), 422, 'Không chấp nhận AI trùng trong một mã quét.');
        $valid = match ($ai) {
            '01' => (bool) preg_match('/^\d{14}$/D', $value), '17' => (bool) preg_match('/^\d{6}$/D', $value), default => (bool) preg_match('/^[\x21-\x7E]{1,20}$/D', $value)
        };
        abort_unless($valid, 422, 'Độ dài hoặc ký tự trường GS1 không hợp lệ.');
        $fields[$ai] = $value;
    }

    private function gtin(string $value): void
    {
        $sum = 0;
        $digits = str_split(substr($value, 0, -1));
        foreach (array_reverse($digits) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 3 : 1);
        }
        abort_unless((10 - $sum % 10) % 10 === (int) substr($value, -1), 422, 'Checksum GTIN không hợp lệ.');
    }
}
