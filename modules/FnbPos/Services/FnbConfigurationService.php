<?php

namespace Modules\FnbPos\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\Security\FnbAuditLogger;

class FnbConfigurationService
{
    public function __construct(private readonly FnbCommandRunner $commands, private readonly FnbAuditLogger $audit) {}

    public function save(FnbContext $ctx, string $kind, ?int $id, array $input, string $key): array
    {
        return $this->commands->run($ctx, $kind.'.save', ['id' => $id, 'input' => $input], $key, function () use ($ctx, $kind, $id, $input): array {
            [$table, $scoped, $rules] = $this->definition($kind);
            if (! $scoped && $kind !== 'outlet') {
                app(FnbMasterDataLock::class)->acquire($ctx->websiteKey);
            }
            $validationInput = $input;
            if (in_array($kind, ['category', 'modifier_group', 'ingredient'], true) && is_string($validationInput['code'] ?? null) && trim($validationInput['code']) === '') {
                $validationInput['code'] = null;
            }
            $data = validator($validationInput, $rules)->validate();
            $query = DB::table($table)->where('website_key', $ctx->websiteKey);
            if ($scoped) {
                $query->where('outlet_id', $ctx->outletId);
            }
            if ($kind === 'unit') {
                $query->whereNull('archived_at');
            }
            $existing = $id ? (clone $query)->where('id', $id)->lockForUpdate()->first() : null;
            abort_if($id && ! $existing, 404);
            if ($existing) {
                validator($input, ['expected_version' => ['required', 'integer', 'min:1']])->validate();
                if ((int) $existing->version !== (int) $input['expected_version']) {
                    throw new FnbConflictException('Dữ liệu cấu hình đã thay đổi; vui lòng tải lại.', ['current_versions' => [$kind.':'.$id => (int) $existing->version]]);
                }
            }
            $this->validateLinks($ctx, $kind, $data);
            if (in_array($kind, ['category', 'modifier_group', 'ingredient'], true) && ($data['code'] ?? null) === null) {
                $data['code'] = $existing?->code ?? $this->generateGroupCode($ctx, $table, $data['name'], $kind === 'category' ? (int) $data['menu_id'] : null);
            }
            if (in_array($kind, ['modifier_group', 'ingredient', 'unit'], true)) {
                validator($data, ['code' => [Rule::unique($table, 'code')->where('website_key', $ctx->websiteKey)->ignore($existing?->id)]])->validate();
            }
            if ($kind === 'category') {
                validator($data, ['code' => [Rule::unique('fnb_menu_categories', 'code')->where('menu_id', $data['menu_id'])->ignore($existing?->id)]])->validate();
            }
            if ($kind === 'category' && (! $existing || (int) $existing->menu_id !== (int) $data['menu_id']) && ! array_key_exists('sort_order', $data)) {
                $maximum = DB::table('fnb_menu_categories')->where('website_key', $ctx->websiteKey)->where('menu_id', $data['menu_id'])->max('sort_order');
                $data['sort_order'] = $maximum === null ? 0 : (int) $maximum + 1;
            }
            if ($kind === 'unit') {
                app(FnbUnitService::class)->guardEdit($ctx, $existing, $data);
            }
            if ($kind === 'ingredient' && (! $existing || $existing->base_unit !== $data['base_unit'])) {
                validator($data, ['base_unit' => [Rule::exists('fnb_units', 'code')->where('website_key', $ctx->websiteKey)->where('status', 'active')->whereNull('archived_at')]])->validate();
            }
            $this->guardHistory($ctx, $kind, $existing, $data);
            $data['version'] = $existing ? (int) $existing->version + 1 : 1;
            $data['updated_at'] = now();
            if ($existing) {
                (clone $query)->where('id', $id)->update($data);
            } else {
                $data += ['website_key' => $ctx->websiteKey, 'created_at' => now()];
                if ($scoped) {
                    $data['outlet_id'] = $ctx->outletId;
                }
                if ($kind === 'terminal') {
                    $data['public_id'] = (string) Str::uuid();
                }
                $id = DB::table($table)->insertGetId($data);
            }
            $this->audit->record('fnb.'.$kind.'.saved', $ctx->websiteKey, Admin::findOrFail($ctx->actorId), $table,
                after: ['resource_id' => $id, 'version' => $data['version'], 'fields' => array_keys($data)], outletIds: [$ctx->outletId]);

            return ['resource' => (array) (clone $query)->where('id', $id)->first()];
        });
    }

    public function reorderCategories(FnbContext $ctx, array $input, string $key): array
    {
        return $this->commands->run($ctx, 'category.reorder', $input, $key, function () use ($ctx, $input): array {
            app(FnbMasterDataLock::class)->acquire($ctx->websiteKey);
            $data = validator($input, [
                'menu_id' => ['required', 'integer', 'min:1'],
                'items' => ['required', 'array', 'list', 'min:1', 'max:10000'],
                'items.*.id' => ['required', 'integer', 'min:1', 'distinct'],
                'items.*.expected_version' => ['required', 'integer', 'min:1'],
            ])->validate();
            abort_unless(DB::table('fnb_menus')->where('website_key', $ctx->websiteKey)->where('id', $data['menu_id'])->exists(), 404);
            $query = DB::table('fnb_menu_categories')->where('website_key', $ctx->websiteKey)->where('menu_id', $data['menu_id']);
            $categories = (clone $query)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $ids = collect($data['items'])->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
            if ($categories->keys()->sort()->values()->all() !== $ids) {
                throw new FnbConflictException('Danh sách nhóm món đã thay đổi; vui lòng tải lại.');
            }
            foreach ($data['items'] as $item) {
                if ((int) $categories[$item['id']]->version !== (int) $item['expected_version']) {
                    throw new FnbConflictException('Nhóm món đã được chỉnh sửa; vui lòng tải lại.');
                }
            }
            foreach ($data['items'] as $position => $item) {
                $category = $categories[$item['id']];
                if ((int) $category->sort_order !== $position) {
                    (clone $query)->where('id', $category->id)->update([
                        'sort_order' => $position, 'version' => (int) $category->version + 1, 'updated_at' => now(),
                    ]);
                }
            }

            return ['resource' => ['menu_id' => (int) $data['menu_id'], 'items' => (clone $query)->orderBy('sort_order')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all()]];
        });
    }

    private function definition(string $kind): array
    {
        $name = ['required', 'string', 'max:255'];
        $code = ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'];
        $status = ['sometimes', Rule::in(['active', 'inactive'])];
        $base = ['code' => $code, 'name' => $name, 'status' => $status];

        return match ($kind) {
            'outlet' => ['fnb_outlets', false, ['name' => $name, 'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:1000']]],
            'terminal' => ['fnb_terminals', true, $base + ['type' => ['required', Rule::in(['pos', 'kds'])]]],
            'area' => ['fnb_service_areas', true, $base + ['sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999']]],
            'table' => ['fnb_dining_tables', true, ['code' => $code, 'name' => $name, 'status' => ['sometimes', Rule::in(['available', 'inactive'])], 'capacity' => ['required', 'integer', 'min:1', 'max:100'], 'service_area_id' => ['required', 'integer', 'min:1'], 'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999']]],
            'station' => ['fnb_prep_stations', true, $base + ['sla_seconds' => ['sometimes', 'integer', 'min:10', 'max:86400']]],
            'payment_method' => ['fnb_payment_methods', true, $base + ['kind' => ['required', Rule::in(['cash', 'transfer', 'card'])], 'requires_reference' => ['sometimes', 'boolean']]],
            'category' => ['fnb_menu_categories', false, array_replace($base, ['code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/']]) + ['menu_id' => ['required', 'integer', 'min:1'], 'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999']]],
            'modifier_group' => ['fnb_modifier_groups', false, array_replace($base, ['code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/']]) + ['min_select' => ['required', 'integer', 'min:0', 'max:20'], 'max_select' => ['required', 'integer', 'min:1', 'max:20', 'gte:min_select'], 'free_quantity' => ['sometimes', 'integer', 'min:0', 'lte:max_select']]],
            'modifier_option' => ['fnb_modifier_options', false, $base + ['group_id' => ['required', 'integer', 'min:1'], 'base_price_delta_minor' => ['required', 'integer', 'min:0', 'max:100000000']]],
            'ingredient' => ['fnb_ingredients', false, array_replace($base, ['code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/']]) + ['base_unit' => ['required', 'string', 'max:30']]],
            'unit' => ['fnb_units', false, array_replace($base, ['code' => ['required', 'string', 'max:30', 'regex:/^[a-z][a-z0-9_-]*$/'], 'name' => ['required', 'string', 'max:100']]) + [
                'dimension' => ['required', Rule::in(['mass', 'volume', 'count'])],
                'factor' => ['required', 'numeric', 'min:0.000001', 'max:1000000', 'regex:/^\d+(\.\d{1,6})?$/'],
            ]],
            default => abort(404),
        };
    }

    private function generateGroupCode(FnbContext $ctx, string $table, string $name, ?int $menuId): string
    {
        $base = substr(Str::slug($name), 0, 40) ?: match ($table) {
            'fnb_menu_categories' => 'nhom-mon', 'fnb_ingredients' => 'nguyen-lieu', default => 'nhom-topping'
        };
        $usedCodes = DB::table($table)
            ->where('website_key', $ctx->websiteKey)
            ->when($menuId !== null, fn ($query) => $query->where('menu_id', $menuId))
            ->pluck('code')
            ->mapWithKeys(fn (string $code): array => [strtolower($code) => true])
            ->all();
        $code = $base;
        for ($number = 2; isset($usedCodes[$code]); $number++) {
            $suffix = '-'.$number;
            $code = substr($base, 0, 40 - strlen($suffix)).$suffix;
        }

        return $code;
    }

    private function validateLinks(FnbContext $ctx, string $kind, array $data): void
    {
        [$table, $column] = match ($kind) {
            'table' => ['fnb_service_areas', 'service_area_id'],
            'category' => ['fnb_menus', 'menu_id'],
            'modifier_option' => ['fnb_modifier_groups', 'group_id'],
            default => [null, null],
        };
        if ($table) {
            $query = DB::table($table)->where('website_key', $ctx->websiteKey)->where('id', $data[$column]);
            if ($kind === 'table') {
                $query->where('outlet_id', $ctx->outletId);
            }
            abort_unless($query->exists(), 404);
        }
    }

    private function guardHistory(FnbContext $ctx, string $kind, ?object $existing, array $data): void
    {
        if (! $existing) {
            return;
        }
        if ($kind === 'payment_method' && $existing->kind !== $data['kind']) {
            throw new FnbConflictException('Loại phương thức thanh toán bất biến; hãy tạo phương thức mới.');
        }
        if ($kind === 'ingredient' && $existing->base_unit !== $data['base_unit']) {
            throw new FnbConflictException('Đơn vị gốc bất biến; hãy tạo nguyên liệu mới.');
        }
        if ($kind === 'table' && DB::table('fnb_service_session_tables')->where('website_key', $ctx->websiteKey)->where('outlet_id', $ctx->outletId)->where('table_id', $existing->id)->whereNotNull('active_slot')->exists()) {
            throw new FnbConflictException('Không thể thay đổi bàn đang có khách.');
        }
        if ($kind === 'terminal' && ($data['status'] ?? 'active') !== 'active' && DB::table('fnb_shifts')->where('website_key', $ctx->websiteKey)->where('outlet_id', $ctx->outletId)->where('terminal_id', $existing->id)->where('status', 'open')->exists()) {
            throw new FnbConflictException('Cần đóng ca trước khi ngừng sử dụng thiết bị.');
        }
    }
}
