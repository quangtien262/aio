<?php

namespace Modules\Merchandise\Services;

use App\Core\Modules\ModuleCapabilityChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Generic axes only; garment specifications and style releases belong to Manufacturing. */
class MerchandiseVariantService
{
    public function extendSet(int $setId, array $input, ?int $expectedVersion = null): array
    {
        return DB::transaction(function () use ($setId, $input, $expectedVersion): array {
            app(MerchandiseRuntimeState::class)->guard();
            $this->ready();
            $set = DB::table('merch_variant_sets')->where('id', $setId)->lockForUpdate()->first();
            abort_unless($set, 404);
            abort_unless((int) ($expectedVersion ?? $input['expected_version'] ?? 0) === (int) $set->version, 409, 'Nhóm biến thể đã thay đổi.');
            $axes = validator($input, ['axes' => 'required|array|min:1|max:8', 'axes.*' => 'required|array|min:1|max:100', 'axes.*.*' => 'required|string|max:80'])->validate()['axes'];
            $old = json_decode($set->axes, true);
            abort_unless(count($axes) === count($old), 422, 'Không đổi trục của nhóm đã có identity.');
            foreach ($old as $axis => $values) {
                abort_unless(isset($axes[$axis]) && array_diff($values, $axes[$axis]) === [] && count(array_unique($axes[$axis])) === count($axes[$axis]), 422, 'Chỉ thêm giá trị mới, không xóa giá trị đã dùng.');
            }
            DB::table('merch_variant_sets')->where('id', $setId)->update(['axes' => json_encode($axes, JSON_THROW_ON_ERROR), 'version' => $set->version + 1, 'updated_at' => now()]);

            return $this->set($setId);
        }, 3);
    }

    public function createSet(int $catalogId, array $input): array
    {
        $v = validator($input, ['code' => 'required|string|max:80|regex:/^[A-Za-z0-9._-]+$/D', 'name' => 'required|string|max:255', 'axes' => 'required|array|min:1|max:8', 'axes.*' => 'required|array|min:1|max:100', 'axes.*.*' => 'required|string|max:80'])->validate();

        return DB::transaction(function () use ($catalogId, $v): array {
            app(MerchandiseRuntimeState::class)->guard();
            $this->ready();
            abort_unless(DB::table('merch_catalogs')->where('id', $catalogId)->where('is_active', true)->lockForUpdate()->first(), 404);
            foreach ($v['axes'] as $axis => $values) {
                abort_unless(is_string($axis) && preg_match('/^[a-z][a-z0-9_]{0,39}$/D', $axis) && count(array_unique($values)) === count($values), 422, 'Trục biến thể không hợp lệ hoặc giá trị trùng.');
            }
            abort_if(DB::table('merch_variant_sets')->where('catalog_id', $catalogId)->where('code', $v['code'])->exists(), 409, 'Nhóm biến thể đã tồn tại.');
            $id = DB::table('merch_variant_sets')->insertGetId(['public_id' => (string) Str::uuid(), 'catalog_id' => $catalogId, 'code' => $v['code'], 'name' => $v['name'], 'axes' => json_encode($v['axes'], JSON_THROW_ON_ERROR), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

            return $this->set($id);
        }, 3);
    }

    public function saveVariant(int $setId, array $input): array
    {
        return DB::transaction(function () use ($setId, $input): array {
            app(MerchandiseRuntimeState::class)->guard();
            $this->ready();
            $set = DB::table('merch_variant_sets')->where('id', $setId)->lockForUpdate()->first();
            abort_unless($set, 404);
            $attributes = validator($input, ['attributes' => 'required|array|min:1|max:8', 'attributes.*' => 'required|string|max:80'])->validate()['attributes'];
            $axes = json_decode($set->axes, true, flags: JSON_THROW_ON_ERROR);
            abort_unless(count($attributes) === count($axes), 422, 'Phải chọn đủ trục biến thể.');
            foreach ($axes as $axis => $values) {
                abort_unless(isset($attributes[$axis]) && in_array($attributes[$axis], $values, true), 422, 'Giá trị biến thể ngoài nhóm.');
            }
            ksort($attributes);
            $hash = hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));
            abort_if(DB::table('merch_sku_variants')->where('variant_set_id', $setId)->where('combination_hash', $hash)->exists(), 409, 'Tổ hợp biến thể đã có SKU.');
            $sku = app(MerchandiseService::class)->saveSku((int) $set->catalog_id, $input);
            DB::table('merch_sku_variants')->insert(['variant_set_id' => $setId, 'sku_id' => $sku['id'], 'attributes' => json_encode($attributes, JSON_THROW_ON_ERROR), 'combination_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);

            return $sku + ['variant_set_id' => $set->public_id, 'attributes' => $attributes];
        }, 3);
    }

    public function set(int $id): array
    {
        abort_unless(app(ModuleCapabilityChecker::class)->has('merchandise', 'merchandise.variants.read.v1'), 503, 'Merchandise cần nâng cấp variant contract.');
        $set = DB::table('merch_variant_sets')->find($id);
        abort_unless($set, 404);

        return array_replace((array) $set, ['axes' => json_decode($set->axes, true), 'variants' => DB::table('merch_sku_variants as v')->join('merch_skus as s', 's.id', '=', 'v.sku_id')->where('variant_set_id', $id)->get(['s.*', 'v.attributes'])->map(fn ($r) => array_replace((array) $r, ['attributes' => json_decode($r->attributes, true)]))->all()]);
    }

    private function ready(): void
    {
        abort_unless(app(ModuleCapabilityChecker::class)->has('merchandise', 'merchandise.variants.manage.v1'), 503, 'Merchandise cần nâng cấp variant contract.');
    }
}
