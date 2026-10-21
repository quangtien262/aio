<?php

namespace Modules\Manufacturing\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Explicit scope and decimal helpers shared by Manufacturing domain services. */
final class ManufacturingData
{
    public static function json(mixed $value, array $default = []): array
    {
        if (is_array($value)) {
            return $value;
        }

        return is_string($value) && $value !== '' ? (json_decode($value, true, 512, JSON_THROW_ON_ERROR) ?? $default) : $default;
    }

    public static function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function decimal(mixed $value, bool $zero = false): BigDecimal
    {
        try {
            $number = BigDecimal::of((string) $value)->toScale(6, RoundingMode::Unnecessary);
            if ($number->isNegative() || (! $zero && $number->isZero())) {
                throw new \InvalidArgumentException;
            }

            return $number;
        } catch (\Throwable) {
            throw ValidationException::withMessages(['quantity' => 'Cần số thập phân dương, tối đa 6 chữ số thập phân.']);
        }
    }

    public static function pieces(mixed $value, bool $zero = false): string
    {
        try {
            return (string) self::decimal($value, $zero)->toScale(0, RoundingMode::Unnecessary)->toScale(6);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['quantity' => 'Sản lượng quần áo phải là số chiếc nguyên hợp lệ.']);
        }
    }

    public static function row(string $table, int|string $id, ?int $company = null, ?int $factory = null, bool $lock = false): object
    {
        $query = DB::table($table)->where(is_int($id) ? 'id' : 'public_id', $id);
        if ($company !== null) {
            $query->where('company_id', $company);
        }
        if ($factory !== null) {
            $query->where('factory_id', $factory);
        }
        $row = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($row, 404, 'Dữ liệu không tồn tại trong phạm vi sản xuất được chọn.');

        return $row;
    }

    public static function insert(string $table, array $values): object
    {
        if ($table === 'mfg_bundles') {
            $values = array_replace($values, self::bundleProjection($values));
        }
        $id = DB::table($table)->insertGetId(array_replace([
            'public_id' => (string) Str::uuid(), 'status' => 'draft', 'version' => 1,
            'metadata' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ], $values));

        return DB::table($table)->find($id);
    }

    public static function update(string $table, object $row, array $values, mixed $version): object
    {
        abort_unless($version !== null && (int) $version === (int) $row->version, 409, 'Phiên bản đã thay đổi; tải lại dữ liệu trước khi tiếp tục.');
        if ($table === 'mfg_bundles') {
            $values = array_replace($values, self::bundleProjection(array_replace((array) $row, $values)));
        }
        $count = DB::table($table)->where('id', $row->id)->where('version', $row->version)->update(array_replace($values, [
            'version' => $row->version + 1, 'updated_at' => now(),
        ]));
        abort_unless($count === 1, 409, 'Dữ liệu vừa được cập nhật bởi thao tác khác.');

        return DB::table($table)->find($row->id);
    }

    public static function bundleProjection(array $values): array
    {
        $meta = self::json($values['metadata'] ?? null);
        $finished = BigDecimal::of((string) ($meta['finished_quantity'] ?? '0'))->toScale(6, RoundingMode::HalfUp);
        $scrap = BigDecimal::of((string) ($meta['scrap_quantity'] ?? '0'))->toScale(6, RoundingMode::HalfUp);
        $wip = BigDecimal::max(BigDecimal::of((string) ($values['quantity'] ?? '0'))->minus($finished)->minus($scrap), BigDecimal::zero());
        if (in_array($values['status'] ?? '', ['merged', 'cancelled', 'split', 'shipped', 'completed', 'scrapped'], true)) {
            $wip = BigDecimal::zero();
        }

        return ['finished_quantity' => (string) $finished, 'scrap_quantity' => (string) $scrap, 'wip_quantity' => (string) $wip->toScale(6)];
    }

    public static function withoutCosts(array $data): array
    {
        foreach ($data as $key => $value) {
            $name = strtolower((string) $key);
            if (str_contains($name, 'cost') || str_contains($name, 'price') || str_contains($name, 'valuation')
                || str_ends_with($name, '_minor') || in_array($name, ['amount', 'fee_amount', 'financials', 'payments', 'payment', 'realized_value', 'remaining_value', 'allocated_value'], true)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = self::withoutCosts($value);
            }
        }

        return $data;
    }

    public static function resource(object|array $row): array
    {
        $data = (array) $row;
        $data['id'] = $data['public_id'] ?? $data['id'] ?? null;
        foreach (['metadata', 'settings', 'variant', 'bom', 'routing', 'measurements', 'attachments', 'released_snapshot', 'buyer_snapshot', 'snapshot', 'contents', 'lines', 'carton_ids', 'policy', 'allocations', 'defects', 'destination_snapshot'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = self::json($data[$field]);
            }
        }
        foreach ($data as $key => $value) {
            if ($value !== null && (is_numeric($value)) && (str_ends_with($key, '_quantity') || str_ends_with($key, '_cost')
                || in_array($key, ['quantity', 'amount', 'unit_price', 'fee_amount', 'capacity_minutes'], true))) {
                $data[$key] = (string) BigDecimal::of((string) $value)->toScale(6, RoundingMode::HalfUp);
            }
        }

        return $data;
    }

    public static function event(object $factory, string $type, ?int $order, ?int $bundle, array $snapshot, int $actor): void
    {
        self::insert('mfg_events', [
            'company_id' => $factory->company_id, 'factory_id' => $factory->id,
            'order_id' => $order, 'bundle_id' => $bundle, 'event_type' => $type,
            'quantity' => $snapshot['quantity'] ?? null, 'source_ref' => $snapshot['source_ref'] ?? null,
            'recorded_by' => $actor, 'occurred_at' => now(), 'snapshot' => self::encode($snapshot), 'status' => 'recorded',
        ]);
    }

    public static function metadata(object $row, array $changes = []): string
    {
        return self::encode(array_replace(self::json($row->metadata ?? null), $changes));
    }

    public static function syncBundleMaterials(object $bundle): void
    {
        foreach (self::json($bundle->metadata)['materials'] ?? [] as $material) {
            DB::table('mfg_bundle_materials')->updateOrInsert(['bundle_id' => $bundle->id, 'issue_id' => $material['issue_id']], [
                'company_id' => $bundle->company_id, 'factory_id' => $bundle->factory_id, 'order_id' => $bundle->order_id,
                'roll_id' => $material['roll_id'] ?? null, 'quantity' => $material['quantity'],
                'allocated_quantity' => $material['allocated_quantity'] ?? '0', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
