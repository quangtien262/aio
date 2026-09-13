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
            $data = validator($input, $rules)->validate();
            $query = DB::table($table)->where('website_key', $ctx->websiteKey);
            if ($scoped) {
                $query->where('outlet_id', $ctx->outletId);
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
            'category' => ['fnb_menu_categories', false, $base + ['menu_id' => ['required', 'integer', 'min:1'], 'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999']]],
            'modifier_group' => ['fnb_modifier_groups', false, $base + ['min_select' => ['required', 'integer', 'min:0', 'max:20'], 'max_select' => ['required', 'integer', 'min:1', 'max:20', 'gte:min_select'], 'free_quantity' => ['sometimes', 'integer', 'min:0', 'lte:max_select']]],
            'modifier_option' => ['fnb_modifier_options', false, $base + ['group_id' => ['required', 'integer', 'min:1'], 'base_price_delta_minor' => ['required', 'integer', 'min:0', 'max:100000000']]],
            'ingredient' => ['fnb_ingredients', false, $base + ['base_unit' => ['required', Rule::in(['g', 'kg', 'ml', 'l', 'piece'])]]],
            default => abort(404),
        };
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
