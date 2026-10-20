<?php

namespace Modules\Merchandise\Services;

use App\Models\Admin;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MerchandiseService
{
    public function nativeMutation(Admin $actor, string $permission, callable $callback): array
    {
        return DB::transaction(function () use ($actor, $permission, $callback): array {
            $live = Admin::query()->whereKey($actor->id)->lockForUpdate()->first();
            abort_unless($live && $live->is_active && $live->status === 'active' && ! $live->locked_at
                && (int) $live->auth_version === (int) $actor->auth_version, 403, 'Tài khoản hoặc phiên xác thực đã thay đổi.');
            abort_unless($live->canAccess($permission, 'global'), 403, 'Cần quyền danh mục hàng hóa toàn cục.');
            app(MerchandiseRuntimeState::class)->guard();

            return $callback();
        }, 3);
    }

    public function createCatalog(array $data, int $actorId = 0): array
    {
        return DB::transaction(function () use ($data): array {
            app(MerchandiseRuntimeState::class)->guard();
            $v = Validator::make($data, ['code' => 'required|string|max:60|regex:/^[A-Za-z0-9._-]+$/', 'name' => 'required|string|max:255'])->validate();
            try {
                $id = DB::table('merch_catalogs')->insertGetId($v + ['public_id' => (string) Str::uuid(), 'is_active' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            } catch (QueryException $e) {
                if (DB::table('merch_catalogs')->where('code', $v['code'])->exists()) {
                    throw ValidationException::withMessages(['code' => 'Mã danh mục đã tồn tại.']);
                }
                throw $e;
            }

            return (array) DB::table('merch_catalogs')->find($id);
        }, 3);
    }

    public function saveSku(int $catalogId, array $data, ?int $skuId = null): array
    {
        $v = $this->validateSku($data);

        return DB::transaction(function () use ($catalogId, $data, $v, $skuId): array {
            app(MerchandiseRuntimeState::class)->guard();
            abort_unless(DB::table('merch_catalogs')->where('id', $catalogId)->where('is_active', true)->lockForUpdate()->first(), 404, 'Danh mục không hoạt động.');
            $existing = $skuId === null ? null : DB::table('merch_skus')->where('catalog_id', $catalogId)->where('id', $skuId)->lockForUpdate()->first();
            abort_if($skuId !== null && ! $existing, 404);
            if ($existing && (int) ($data['expected_version'] ?? 0) !== (int) $existing->version) {
                abort(409, 'Phiên bản hàng hóa đã thay đổi.');
            }
            $duplicate = DB::table('merch_skus')->where('catalog_id', $catalogId)->where('sku', $v['sku'])->when($skuId, fn ($q) => $q->where('id', '!=', $skuId))->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['sku' => 'SKU đã tồn tại trong danh mục.']);
            }
            if ($existing && ($existing->base_unit !== $v['base_unit'] || (int) $existing->quantity_scale !== $v['quantity_scale'] || $existing->tracking_mode !== $v['tracking_mode'])) {
                throw ValidationException::withMessages(['base_unit' => 'Đơn vị gốc, precision và tracking đã tạo là bất biến; tạo SKU mới để đổi.']);
            }
            $row = $v + ['catalog_id' => $catalogId, 'version' => $existing ? $existing->version + 1 : 1, 'updated_at' => now()];
            if ($existing) {
                DB::table('merch_skus')->where('id', $skuId)->update($row);
            } else {
                $skuId = DB::table('merch_skus')->insertGetId($row + ['public_id' => (string) Str::uuid(), 'created_at' => now()]);
            }
            foreach (($data['packagings'] ?? []) as $pack) {
                $this->savePackaging($catalogId, $skuId, $pack);
            }
            foreach (($data['barcodes'] ?? []) as $barcode) {
                $this->bindBarcode($catalogId, $skuId, is_array($barcode) ? $barcode : ['barcode' => $barcode]);
            }

            return $this->sku($skuId);
        }, 3);
    }

    public function sku(int $id): array
    {
        $row = DB::table('merch_skus')->find($id);
        abort_unless($row, 404);

        return (array) $row + [
            'barcodes' => DB::table('merch_barcodes')->where('sku_id', $id)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'packagings' => DB::table('merch_packagings')->where('sku_id', $id)->where('is_active', true)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        ];
    }

    public function lookup(int $catalogId, string $query): array
    {
        $query = trim($query);
        $barcode = DB::table('merch_barcodes')->where('catalog_id', $catalogId)->where('barcode', $query)->first();
        if ($barcode) {
            return [$this->resolveBarcode($catalogId, $query)];
        }

        return DB::table('merch_skus')->where('catalog_id', $catalogId)->where('is_active', true)
            ->when($query !== '', fn ($q) => $q->where(fn ($q) => $q->where('sku', 'like', '%'.$query.'%')->orWhere('name', 'like', '%'.$query.'%')))
            ->orderBy('name')->limit(100)->get()->map(fn ($r) => (array) $r + ['quantity_multiplier' => '1.000000', 'packaging_id' => null])->all();
    }

    public function resolveBarcode(int $catalogId, string $barcode): array
    {
        $mapping = DB::table('merch_barcodes')->where('catalog_id', $catalogId)->where('barcode', trim($barcode))->first();
        abort_unless($mapping, 404, 'Không tìm thấy mã vạch.');
        $sku = DB::table('merch_skus')->where('id', $mapping->sku_id)->where('is_active', true)->first();
        abort_unless($sku, 404, 'Hàng hóa đã ngừng hoạt động.');
        $pack = $mapping->packaging_id ? DB::table('merch_packagings')->where('id', $mapping->packaging_id)->where('is_active', true)->first() : null;
        abort_if($mapping->packaging_id && ! $pack, 422, 'Quy cách mã vạch không còn hiệu lực.');

        return (array) $sku + ['barcode' => $mapping->barcode, 'packaging_id' => $mapping->packaging_id, 'packaging_code' => $pack?->code, 'quantity_multiplier' => (string) BigDecimal::of($pack?->quantity_multiplier ?? '1')->toScale(6)];
    }

    public function convertQuantity(int $skuId, string $quantity, ?int $packagingId = null): string
    {
        $sku = DB::table('merch_skus')->where('id', $skuId)->where('is_active', true)->first();
        abort_unless($sku, 404);
        $qty = $this->decimal($quantity, 'quantity');
        $factor = '1';
        if ($packagingId !== null) {
            $pack = DB::table('merch_packagings')->where('id', $packagingId)->where('sku_id', $skuId)->where('is_active', true)->first();
            abort_unless($pack, 422, 'Quy cách không thuộc SKU.');
            $factor = (string) $pack->quantity_multiplier;
        }
        try {
            return (string) $qty->multipliedBy($factor)->toScale((int) $sku->quantity_scale, RoundingMode::Unnecessary)->toScale(6);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['quantity' => 'Số lượng vượt độ chính xác của đơn vị gốc.']);
        }
    }

    public function previewImport(int $catalogId, array $rows): array
    {
        abort_unless(DB::table('merch_catalogs')->where('id', $catalogId)->exists(), 404);
        if (count($rows) > 1000) {
            throw ValidationException::withMessages(['rows' => 'Mỗi lần tối đa 1.000 dòng.']);
        }
        $errors = [];
        $valid = [];
        $seen = [];
        $barcodes = [];
        foreach ($rows as $index => $row) {
            try {
                if (! is_array($row)) {
                    throw ValidationException::withMessages(['row' => 'Dòng dữ liệu không hợp lệ.']);
                }
                $v = $this->validateSku($row);
                if (isset($seen[$v['sku']]) || DB::table('merch_skus')->where('catalog_id', $catalogId)->where('sku', $v['sku'])->exists()) {
                    throw ValidationException::withMessages(['sku' => 'SKU trùng; import chỉ tạo mới.']);
                }
                $seen[$v['sku']] = true;
                foreach (array_merge($row['barcodes'] ?? [], array_column($row['packagings'] ?? [], 'barcode')) as $b) {
                    $value = is_array($b) ? (string) ($b['barcode'] ?? '') : (string) $b;
                    $this->validateBarcode($value, is_array($b) ? ($b['format'] ?? 'internal') : 'internal');
                    if (isset($barcodes[$value]) || DB::table('merch_barcodes')->where('catalog_id', $catalogId)->where('barcode', $value)->exists()) {
                        throw ValidationException::withMessages(['barcode' => 'Mã vạch trùng trong danh mục hoặc file.']);
                    }
                    $barcodes[$value] = true;
                }
                foreach ($row['packagings'] ?? [] as $pack) {
                    $this->validatePackaging($pack);
                }
                $valid[] = $row;
            } catch (ValidationException $e) {
                $errors[] = ['row' => $index + 1, 'errors' => $e->errors()];
            }
        }

        return ['valid' => $errors === [], 'row_count' => count($rows), 'accepted_count' => count($valid), 'errors' => $errors, 'rows' => $valid];
    }

    public function import(int $catalogId, array $rows): array
    {
        return DB::transaction(function () use ($catalogId, $rows): array {
            app(MerchandiseRuntimeState::class)->guard();
            $report = $this->previewImport($catalogId, $rows);
            if (! $report['valid']) {
                throw ValidationException::withMessages(['rows' => 'File có lỗi. Chạy kiểm tra trước khi nhập.']);
            }

            return array_map(fn ($row) => $this->saveSku($catalogId, $row), $rows);
        }, 3);
    }

    private function validateSku(array $data): array
    {
        $v = Validator::make($data, [
            'sku' => 'required|string|max:80|regex:/^[A-Za-z0-9._-]+$/', 'name' => 'required|string|max:255',
            'base_unit' => 'sometimes|string|max:24|regex:/^[A-Za-z0-9._-]+$/', 'quantity_scale' => 'sometimes|integer|between:0,6',
            'tracking_mode' => 'sometimes|in:none,batch', 'requires_expiry' => 'sometimes|boolean', 'is_active' => 'sometimes|boolean',
            'barcodes' => 'sometimes|array|max:50', 'packagings' => 'sometimes|array|max:30',
        ])->validate();
        unset($v['barcodes'], $v['packagings']);

        return $v + ['base_unit' => 'pcs', 'quantity_scale' => 0, 'tracking_mode' => 'none', 'requires_expiry' => false, 'is_active' => true];
    }

    private function savePackaging(int $catalogId, int $skuId, array $pack): void
    {
        $p = $this->validatePackaging($pack);
        $old = DB::table('merch_packagings')->where('sku_id', $skuId)->where('code', $p['code'])->where('is_active', true)->lockForUpdate()->first();
        if ($old && BigDecimal::of($old->quantity_multiplier)->isEqualTo($p['quantity_multiplier']) && $old->name === $p['name']) {
            $id = $old->id;
        } else {
            if ($old) {
                DB::table('merch_packagings')->where('id', $old->id)->update(['is_active' => false, 'updated_at' => now()]);
            }
            $revision = (int) DB::table('merch_packagings')->where('sku_id', $skuId)->where('code', $p['code'])->max('revision') + 1;
            $id = DB::table('merch_packagings')->insertGetId($p + ['public_id' => (string) Str::uuid(), 'sku_id' => $skuId, 'revision' => $revision, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            if ($old) {
                DB::table('merch_barcodes')->where('packaging_id', $old->id)->update(['packaging_id' => $id, 'updated_at' => now()]);
            }
        }
        if (isset($pack['barcode'])) {
            $this->bindBarcode($catalogId, $skuId, ['barcode' => $pack['barcode'], 'format' => $pack['format'] ?? 'internal', 'packaging_id' => $id]);
        }
    }

    private function validatePackaging(array $pack): array
    {
        $v = Validator::make($pack, ['code' => 'required|string|max:40', 'name' => 'required|string|max:100', 'quantity_multiplier' => 'required'])->validate();
        $v['quantity_multiplier'] = (string) $this->decimal((string) $v['quantity_multiplier'], 'quantity_multiplier');

        return $v;
    }

    private function bindBarcode(int $catalogId, int $skuId, array $barcode): void
    {
        $value = (string) ($barcode['barcode'] ?? '');
        $format = $barcode['format'] ?? 'internal';
        $this->validateBarcode($value, $format);
        $old = DB::table('merch_barcodes')->where('catalog_id', $catalogId)->where('barcode', $value)->lockForUpdate()->first();
        if ($old && (int) $old->sku_id !== $skuId) {
            throw ValidationException::withMessages(['barcode' => 'Mã vạch đã thuộc SKU khác.']);
        }
        $packagingId = $barcode['packaging_id'] ?? null;
        if ($packagingId !== null && ! DB::table('merch_packagings')->where('id', $packagingId)->where('sku_id', $skuId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['packaging_id' => 'Quy cách không thuộc SKU.']);
        }
        $data = ['sku_id' => $skuId, 'packaging_id' => $packagingId, 'format' => $format, 'updated_at' => now()];
        if ($old) {
            DB::table('merch_barcodes')->where('id', $old->id)->update($data);
        } else {
            DB::table('merch_barcodes')->insert($data + ['catalog_id' => $catalogId, 'barcode' => $value, 'created_at' => now()]);
        }
    }

    private function validateBarcode(string $value, string $format): void
    {
        if ($value === '' || trim($value) !== $value || strlen($value) > 128 || preg_match('/[\x00-\x20]/', $value) || ! in_array($format, ['internal', 'ean13', 'ean8', 'upca'], true)) {
            throw ValidationException::withMessages(['barcode' => 'Mã vạch hoặc định dạng không hợp lệ.']);
        }
        if ($format === 'internal') {
            return;
        }
        $length = ['ean13' => 13, 'ean8' => 8, 'upca' => 12][$format];
        if (! ctype_digit($value) || strlen($value) !== $length) {
            throw ValidationException::withMessages(['barcode' => 'Độ dài mã vạch không hợp lệ.']);
        }
        $sum = 0;
        for ($i = $length - 2, $position = 0; $i >= 0; $i--, $position++) {
            $sum += (int) $value[$i] * ($position % 2 === 0 ? 3 : 1);
        }
        if ((10 - $sum % 10) % 10 !== (int) $value[$length - 1]) {
            throw ValidationException::withMessages(['barcode' => 'Checksum mã vạch không hợp lệ.']);
        }
    }

    private function decimal(string $value, string $field): BigDecimal
    {
        try {
            if (! preg_match('/^\d{1,18}(?:\.\d{1,6})?$/', $value)) {
                throw new \InvalidArgumentException;
            }
            $decimal = BigDecimal::of($value)->toScale(6, RoundingMode::Unnecessary);
            if ($decimal->isLessThanOrEqualTo('0')) {
                throw new \InvalidArgumentException;
            }

            return $decimal;
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'Giá trị phải dương, tối đa 6 chữ số thập phân.']);
        }
    }
}
