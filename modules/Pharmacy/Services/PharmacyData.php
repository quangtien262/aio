<?php

namespace Modules\Pharmacy\Services;

use App\Support\Pos\PosNumbers;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class PharmacyData
{
    public static function json(mixed $value, array $default = []): array
    {
        return is_array($value) ? $value : (is_string($value) && $value !== '' ? (json_decode($value, true, flags: JSON_THROW_ON_ERROR) ?? $default) : $default);
    }

    public static function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function decimal(mixed $value, bool $zero = false): BigDecimal
    {
        return BigDecimal::of(PosNumbers::quantity($value, ! $zero));
    }

    public static function number(mixed $value, bool $zero = false): BigDecimal
    {
        return self::decimal($value, $zero);
    }

    public static function money(mixed $value): int
    {
        return PosNumbers::money($value);
    }

    public static function row(string $table, int|string $id, ?int $chain = null, ?int $pharmacy = null, bool $lock = false): object
    {
        $query = DB::table($table)->where(is_int($id) ? 'id' : 'public_id', $id);
        if ($chain !== null) {
            $query->where('chain_id', $chain);
        }
        if ($pharmacy !== null) {
            $query->where('pharmacy_id', $pharmacy);
        }
        $row = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($row, 404, 'Không tìm thấy dữ liệu trong phạm vi nhà thuốc.');

        return $row;
    }

    public static function find(string $table, int|string $id, ?int $chain = null, ?int $pharmacy = null, bool $lock = false): object
    {
        return self::row($table, $id, $chain, $pharmacy, $lock);
    }

    public static function insert(string $table, array $values): object
    {
        $defaults = array_intersect_key(['public_id' => (string) Str::uuid(), 'status' => 'active', 'version' => 1, 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now()], array_flip(Schema::getColumnListing($table)));
        $id = DB::table($table)->insertGetId(array_replace($defaults, $values));

        return DB::table($table)->find($id);
    }

    public static function update(string $table, object $row, array $values, mixed $version): object
    {
        abort_unless($version !== null && (int) $version === (int) $row->version, 409, 'Phiên bản đã thay đổi; tải lại dữ liệu.');
        $count = DB::table($table)->where('id', $row->id)->where('version', $row->version)->update(array_replace($values, ['version' => $row->version + 1, 'updated_at' => now()]));
        abort_unless($count === 1, 409, 'Dữ liệu vừa thay đổi trên thiết bị khác.');

        return DB::table($table)->find($row->id);
    }

    public static function resource(object|array $row): array
    {
        $result = (array) $row;
        $result['id'] = $result['public_id'] ?? $result['id'] ?? null;
        foreach ($result as $key => $value) {
            if (str_starts_with($key, 'encrypted_') || in_array($key, ['fingerprint', 'authorization', 'lease_token'], true)) {
                unset($result[$key]);

                continue;
            }
            if (in_array($key, ['metadata', 'settings', 'ingredients', 'permissions', 'abilities', 'evidence', 'targets', 'acknowledgements', 'payload', 'allocations', 'snapshot', 'lines', 'errors', 'filters', 'item_mappings'], true) && $value !== null) {
                $result[$key] = self::json($value);
            }
        }

        return $result;
    }
}
