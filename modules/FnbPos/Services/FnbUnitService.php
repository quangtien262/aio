<?php

namespace Modules\FnbPos\Services;

use Illuminate\Support\Facades\DB;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;

final class FnbUnitService
{
    public static function seedDefaults(string $websiteKey): void
    {
        foreach ([['g', 'Gam', 'mass', '1'], ['kg', 'Kilôgam', 'mass', '1000'], ['ml', 'Mililít', 'volume', '1'], ['l', 'Lít', 'volume', '1000'], ['piece', 'Cái', 'count', '1']] as [$code, $name, $dimension, $factor]) {
            DB::table('fnb_units')->insertOrIgnore([
                'website_key' => $websiteKey, 'code' => $code, 'name' => $name, 'dimension' => $dimension,
                'factor' => $factor, 'is_system' => true, 'status' => 'active', 'version' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function catalog(string $websiteKey): array
    {
        $used = DB::table('fnb_ingredients')->where('website_key', $websiteKey)->distinct()->pluck('base_unit')
            ->merge(DB::table('fnb_recipe_lines')->where('website_key', $websiteKey)->distinct()->pluck('unit'));
        $conversions = DB::table('fnb_unit_conversions')->where('website_key', $websiteKey)->get(['from_unit', 'to_unit']);
        $used = $used->merge($conversions->pluck('from_unit'))->merge($conversions->pluck('to_unit'))->flip();

        return DB::table('fnb_units')->where('website_key', $websiteKey)->whereNull('archived_at')->orderBy('id')->get()
            ->map(fn ($unit): array => array_replace((array) $unit, ['factor' => $this->factorString($unit->factor), 'in_use' => $used->has($unit->code)]))->all();
    }

    public function used(string $websiteKey, string $code): bool
    {
        return DB::table('fnb_ingredients')->where('website_key', $websiteKey)->where('base_unit', $code)->exists()
            || DB::table('fnb_recipe_lines')->where('website_key', $websiteKey)->where('unit', $code)->exists()
            || DB::table('fnb_unit_conversions')->where('website_key', $websiteKey)->where(fn ($q) => $q->where('from_unit', $code)->orWhere('to_unit', $code))->exists();
    }

    public function guardEdit(FnbContext $ctx, ?object $existing, array $data): void
    {
        if (! $existing) {
            return;
        }
        if ($existing->code !== $data['code']) {
            throw new FnbConflictException('Mã đơn vị không thể đổi sau khi tạo.');
        }
        if (($existing->is_system || $this->used($ctx->websiteKey, $existing->code))
            && ($existing->dimension !== $data['dimension'] || bccomp($this->factorString($existing->factor), (string) $data['factor'], 6) !== 0)) {
            throw new FnbConflictException('Đơn vị chuẩn hoặc đã sử dụng không thể đổi nhóm quy đổi và hệ số.');
        }
    }

    public function convertQuantity(string $websiteKey, string $quantity, string $from, string $to): string
    {
        $units = DB::table('fnb_units')->where('website_key', $websiteKey)->whereNull('archived_at')->whereIn('code', [$from, $to])->get()->keyBy('code');
        $source = $units[$from] ?? null;
        $target = $units[$to] ?? null;
        if (! $source || ! $target || $source->status !== 'active') {
            throw new FnbConflictException('Đơn vị không tồn tại hoặc đã ngừng dùng.');
        }
        if ($source->dimension !== $target->dimension) {
            throw new FnbConflictException('Không thể đổi đơn vị giữa khối lượng, thể tích và số lượng.');
        }
        $result = bcdiv(bcmul($quantity, $this->factorString($source->factor), 12), $this->factorString($target->factor), 6);
        if (bccomp($result, '0', 6) <= 0 || bccomp($result, '999999999999.999999', 6) > 0) {
            throw new FnbConflictException('Định lượng sau quy đổi vượt giới hạn; hãy kiểm tra số lượng và đơn vị.');
        }

        return $result;
    }

    private function factorString(string|int|float $value): string
    {
        // SQLite returns small DECIMAL values as floats; BCMath needs plain decimal notation.
        return is_float($value) ? number_format($value, 6, '.', '') : (string) $value;
    }

    public function archive(FnbContext $ctx, int $id, array $input, string $key): array
    {
        return app(FnbCommandRunner::class)->run($ctx, 'unit.archive', ['id' => $id, 'input' => $input], $key, function () use ($ctx, $id, $input): array {
            app(FnbMasterDataLock::class)->acquire($ctx->websiteKey);
            validator($input, ['expected_version' => ['required', 'integer', 'min:1']])->validate();
            $query = DB::table('fnb_units')->where('website_key', $ctx->websiteKey)->where('id', $id)->whereNull('archived_at');
            $unit = (clone $query)->lockForUpdate()->first();
            abort_unless($unit, 404);
            if ((int) $unit->version !== (int) $input['expected_version']) {
                throw new FnbConflictException('Đơn vị đã được chỉnh sửa; vui lòng tải lại.');
            }
            if ($unit->is_system || $this->used($ctx->websiteKey, $unit->code)) {
                throw new FnbConflictException('Không thể xóa đơn vị chuẩn hoặc đã sử dụng; hãy chuyển sang Tạm ngưng.');
            }
            $query->update(['status' => 'inactive', 'archived_at' => now(), 'version' => (int) $unit->version + 1, 'updated_at' => now()]);

            return ['resource' => ['id' => $id, 'archived' => true]];
        });
    }
}
