<?php

namespace Modules\Manufacturing\Services\Engineering;

use App\Models\Admin;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Services\ManufacturingCode;
use Modules\Manufacturing\Services\ManufacturingData as D;
use Modules\Merchandise\Services\MerchandiseVariantService;

final class EngineeringService
{
    public function save(object $company, array $data, ?string $resource): array
    {
        $existing = $resource ? D::row('mfg_styles', $resource, $company->id, lock: true) : null;
        $data = ManufacturingCode::fill($data, 'code', 'MH', 'mfg_styles', ['company_id' => $company->id], $existing?->code);
        $v = validator($data, [
            'code' => 'required|string|max:60|regex:/^[A-Za-z0-9._-]+$/', 'name' => 'required|string|max:255',
            'sizes' => 'required|array|min:1|max:30', 'sizes.*' => 'required|string|max:30|distinct',
            'colors' => 'required|array|min:1|max:30', 'colors.*' => 'required|string|max:60|distinct',
            'bom' => 'required|array|min:1|max:200', 'routing' => 'required|array|min:1|max:100',
            'measurements' => 'sometimes|array|max:200', 'notes' => 'nullable|string|max:10000',
        ])->validate();
        abort_if(count($v['sizes']) * count($v['colors']) > 400, 422, 'Mỗi mã hàng tối đa 400 biến thể.');
        $catalog = (int) (D::json($company->settings)['catalog_id'] ?? 0);
        abort_unless($catalog, 409, 'Doanh nghiệp chưa có danh mục hàng hóa.');
        $bom = [];
        foreach ($v['bom'] as $line) {
            $sku = DB::table('merch_skus')->where('catalog_id', $catalog)->where('is_active', true)
                ->where(isset($line['sku_id']) ? 'id' : 'sku', $line['sku_id'] ?? $line['material_code'] ?? '')->first();
            abort_unless($sku, 422, 'Tạo mã nguyên liệu trong danh mục trước khi lập BOM.');
            abort_if(isset($line['uom']) && $line['uom'] !== $sku->base_unit, 422, 'Đơn vị BOM phải khớp đơn vị gốc của nguyên liệu.');
            if (! empty($line['size'])) {
                abort_unless(in_array($line['size'], $v['sizes'], true), 422, 'Size định mức không thuộc mã hàng.');
            }
            if (! empty($line['color'])) {
                abort_unless(in_array($line['color'], $v['colors'], true), 422, 'Màu định mức không thuộc mã hàng.');
            }
            $allowance = D::decimal($line['allowance_percent'] ?? '0', true);
            abort_if($allowance->isGreaterThan('100'), 422, 'Hao hụt định mức tối đa 100%.');
            $bom[] = ['sku_id' => (int) $sku->id, 'material_code' => $sku->sku, 'name' => $sku->name,
                'uom' => $sku->base_unit, 'quantity' => (string) D::decimal($line['quantity'] ?? ''),
                'size' => $line['size'] ?? null, 'color' => $line['color'] ?? null, 'allowance_percent' => (string) $allowance];
        }
        $routing = [];
        foreach ($v['routing'] as $line) {
            if (ManufacturingCode::missing($line['code'] ?? null)) {
                $line['code'] = ManufacturingCode::generate('CD');
            }
            $op = validator($line, ['code' => 'required|string|max:80|regex:/^[A-Za-z0-9._-]+$/', 'name' => 'required|string|max:255'])->validate();
            abort_if(in_array($op['code'], array_column($routing, 'code'), true), 422, 'Mã công đoạn bị trùng.');
            $routing[] = $op + ['smv' => (string) D::decimal($line['smv'] ?? '0', true)];
        }
        $duplicate = DB::table('mfg_styles')->where('company_id', $company->id)->where('code', $v['code'])
            ->when($resource, fn ($q) => $q->where('public_id', '!=', $resource))->exists();
        abort_if($duplicate, 422, 'Mã hàng đã tồn tại.');
        $style = $existing;
        $variants = $style ? D::json($style->metadata)['variants'] ?? [] : [];
        $variantService = app(MerchandiseVariantService::class);
        $setId = $style ? (D::json($style->metadata)['variant_set_id'] ?? null) : null;
        if ($setId) {
            $set = DB::table('merch_variant_sets')->where('id', $setId)->where('catalog_id', $catalog)->first();
            abort_unless($set, 409, 'Nhóm biến thể chưa sẵn sàng.');
            $variantService->extendSet((int) $setId, ['axes' => ['color' => $v['colors'], 'size' => $v['sizes']]], (int) $set->version);
        } else {
            $set = $variantService->createSet($catalog, ['code' => $v['code'], 'name' => $v['name'], 'axes' => ['color' => $v['colors'], 'size' => $v['sizes']]]);
            $setId = $set['id'];
        }
        foreach ($v['colors'] as $color) {
            foreach ($v['sizes'] as $size) {
                $identity = D::encode([$color, $size]);
                if (isset($variants[$identity])) {
                    continue;
                }
                $code = $v['code'].'-'.strtoupper(substr(hash('sha256', $identity), 0, 12));
                abort_if(DB::table('merch_skus')->where('catalog_id', $catalog)->where('sku', $code)->exists(), 409, 'SKU biến thể đã được sử dụng.');
                $sku = $variantService->saveVariant((int) $setId, ['sku' => $code, 'name' => $v['name'].' / '.$color.' / '.$size,
                    'base_unit' => 'pcs', 'tracking_mode' => 'batch', 'attributes' => ['color' => $color, 'size' => $size]]);
                $variants[$identity] = ['sku_id' => $sku['id'], 'sku' => $code, 'size' => $size, 'color' => $color];
            }
        }
        $metadata = ['sizes' => $v['sizes'], 'colors' => $v['colors'], 'variants' => $variants, 'variant_set_id' => $setId];
        $values = ['code' => $v['code'], 'name' => $v['name'], 'description' => $v['notes'] ?? null,
            'status' => 'draft', 'metadata' => D::encode($metadata)];
        $style = $style ? D::update('mfg_styles', $style, $values, $data['expected_version'] ?? null)
            : D::insert('mfg_styles', $values + ['company_id' => $company->id]);
        $revision = D::insert('mfg_style_revisions', ['company_id' => $company->id, 'style_id' => $style->id,
            'revision' => 1 + (int) DB::table('mfg_style_revisions')->where('style_id', $style->id)->max('revision'),
            'bom' => D::encode($bom), 'routing' => D::encode($routing), 'measurements' => D::encode($v['measurements'] ?? []),
            'attachments' => '[]', 'metadata' => D::encode($metadata), 'status' => 'draft']);
        DB::table('mfg_styles')->where('id', $style->id)->update(['metadata' => D::metadata($style, $metadata + ['revision_id' => $revision->id])]);

        return D::resource(DB::table('mfg_styles')->find($style->id)) + ['revision_id' => $revision->public_id];
    }

    public function sample(object $company, array $data, ?string $resource, Admin $actor, bool $approve = false): array
    {
        if ($approve) {
            $sample = D::row('mfg_samples', (string) $resource, $company->id, lock: true);
            abort_unless($sample->status === 'draft', 409, 'Mẫu không còn ở trạng thái chờ duyệt.');

            return D::resource(D::update('mfg_samples', $sample, ['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()], $data['expected_version'] ?? null));
        }
        $v = validator($data, ['style_id' => 'required|uuid', 'type' => 'required|in:proto,fit,size_set,pp', 'notes' => 'nullable|string|max:10000'])->validate();
        $style = D::row('mfg_styles', $v['style_id'], $company->id, lock: true);
        $revision = $this->currentRevision($style);

        return D::resource(D::insert('mfg_samples', ['company_id' => $company->id, 'style_id' => $style->id,
            'style_revision_id' => $revision->id, 'type' => $v['type'], 'notes' => $v['notes'] ?? null]));
    }

    public function approve(object $company, string $resource, array $data, Admin $actor): array
    {
        $style = D::row('mfg_styles', $resource, $company->id, lock: true);
        $revision = $this->currentRevision($style, true);
        abort_unless($revision->status === 'draft', 409, 'Phiên bản kỹ thuật đã được duyệt.');
        $sample = DB::table('mfg_samples')->where('style_revision_id', $revision->id)->where('type', 'pp')->where('status', 'approved')->exists();
        abort_unless($sample, 422, 'Cần mẫu PP được duyệt cho đúng phiên bản kỹ thuật.');
        D::update('mfg_style_revisions', $revision, ['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()], $revision->version);

        return D::resource(D::update('mfg_styles', $style, ['status' => 'approved'], $data['expected_version'] ?? null));
    }

    public function currentRevision(object $style, bool $lock = false): object
    {
        $q = DB::table('mfg_style_revisions')->where('style_id', $style->id)->orderByDesc('revision');
        $r = ($lock ? $q->lockForUpdate() : $q)->first();
        abort_unless($r, 422, 'Chưa có phiên bản kỹ thuật.');

        return $r;
    }

    public function variant(object $revision, string $color, string $size): array
    {
        $meta = D::json($revision->metadata);
        $v = $meta['variants'][D::encode([$color, $size])] ?? null;
        abort_unless($v && in_array($size, $meta['sizes'], true) && in_array($color, $meta['colors'], true), 422, 'Biến thể không thuộc phiên bản kỹ thuật.');

        return $v;
    }

    public function requirements(object $revision, array $lines): array
    {
        $result = [];
        foreach ($lines as $line) {
            $variant = D::json($line['variant'] ?? $line);
            foreach (D::json($revision->bom) as $material) {
                if ((! empty($material['size']) && $material['size'] !== $variant['size'])
                    || (! empty($material['color']) && $material['color'] !== $variant['color'])) {
                    continue;
                }
                $quantity = D::decimal($material['quantity'])->multipliedBy($line['quantity'])
                    ->multipliedBy(BigDecimal::one()->plus(D::decimal($material['allowance_percent'] ?? '0', true)->dividedBy('100', 8)))->toScale(6, RoundingMode::HalfUp);
                $old = $result[$material['sku_id']] ?? ['sku_id' => $material['sku_id'], 'base_unit' => $material['uom'], 'required_quantity' => '0'];
                $old['required_quantity'] = (string) BigDecimal::of($old['required_quantity'])->plus($quantity)->toScale(6);
                $result[$material['sku_id']] = $old;
            }
        }

        return array_values($result);
    }
}
