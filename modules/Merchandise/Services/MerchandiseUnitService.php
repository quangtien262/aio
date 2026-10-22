<?php

namespace Modules\Merchandise\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MerchandiseUnitService
{
    public static function seedCatalog(int $catalogId): void
    {
        foreach ([['pcs', 'Cái / đơn vị'], ['kg', 'Kilôgam (kg)'], ['l', 'Lít (l)'], ['pack', 'Gói']] as [$code, $name]) {
            self::register($catalogId, $code, $name);
        }
    }

    public static function register(int $catalogId, string $code, string $name): void
    {
        DB::table('merch_units')->insertOrIgnore(['public_id' => (string) Str::uuid(), 'catalog_id' => $catalogId, 'code' => $code, 'name' => $name, 'is_active' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function listing(int $catalogId): array
    {
        $used = DB::table('merch_skus')->where('catalog_id', $catalogId)->distinct()->pluck('base_unit')->flip();

        return DB::table('merch_units')->where('catalog_id', $catalogId)->orderBy('id')->get()->map(fn ($unit) => (array) $unit + ['in_use' => $used->has($unit->code)])->all();
    }

    public function save(int $catalogId, array $data, ?string $publicId = null, bool $archive = false): array
    {
        return DB::transaction(function () use ($catalogId, $data, $publicId, $archive): array {
            app(MerchandiseRuntimeState::class)->guard();
            abort_unless(DB::table('merch_catalogs')->where('id', $catalogId)->where('is_active', true)->lockForUpdate()->first(), 404);
            $unit = $publicId ? DB::table('merch_units')->where('catalog_id', $catalogId)->where('public_id', $publicId)->lockForUpdate()->first() : null;
            abort_if($publicId && ! $unit, 404, 'Không tìm thấy đơn vị.');
            if ($unit) {
                validator($data, ['expected_version' => 'required|integer|min:1'])->validate();
                abort_unless((int) $data['expected_version'] === (int) $unit->version, 409, 'Đơn vị đã thay đổi. Tải lại danh sách.');
            }
            $values = $archive ? ['is_active' => false] : validator($data, [
                'code' => ['required', 'string', 'max:24', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('merch_units')->where('catalog_id', $catalogId)->ignore($unit?->id)],
                'name' => 'required|string|max:100', 'is_active' => 'sometimes|boolean',
            ])->validate();
            if ($unit) {
                abort_if(isset($values['code']) && $values['code'] !== $unit->code, 422, 'Không đổi mã đơn vị đã tạo.');
                $used = DB::table('merch_skus')->where('catalog_id', $catalogId)->where('base_unit', $unit->code)->exists();
                abort_if($used && isset($values['is_active']) && ! $values['is_active'], 422, 'Đơn vị đã dùng cho hàng hóa; chỉ được sửa tên hiển thị.');
                DB::table('merch_units')->where('id', $unit->id)->update($values + ['version' => $unit->version + 1, 'updated_at' => now()]);
                $id = $unit->id;
            } else {
                $id = DB::table('merch_units')->insertGetId($values + ['catalog_id' => $catalogId, 'public_id' => (string) Str::uuid(), 'is_active' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }

            return (array) DB::table('merch_units')->find($id);
        }, 3);
    }
}
