<?php

namespace Modules\FnbPos\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\FnbPos\Domain\FnbConflictException;
use Modules\FnbPos\Domain\FnbContext;
use Modules\FnbPos\Services\Security\FnbAuditLogger;
use Modules\FnbPos\Services\Security\FnbOutletAccessService;

final class FnbChainService
{
    public function __construct(private readonly FnbCommandRunner $commands, private readonly FnbOutletAccessService $access, private readonly FnbAuditLogger $audit) {}

    public function bootstrap(Admin $actor, string $website): array
    {
        $this->access->authorizeHq($actor, $website, 'fnb.chain.view');

        $hqPermissions = $this->access->hqPermissions($actor, $website);
        $hqStaff = in_array('fnb.chain.staff.manage', $hqPermissions, true)
            ? DB::table('fnb_staff_role_scopes as scopes')->join('admins', 'admins.id', '=', 'scopes.admin_id')->join('roles', 'roles.id', '=', 'scopes.preset_role_id')
                ->where('scopes.website_key', $website)->where('scopes.scope_type', 'hq')
                ->get(['scopes.id', 'admins.name as display_name', 'admins.username', 'admins.email', 'roles.key as role_key', 'scopes.version', 'scopes.status'])
                ->map(fn ($row) => ['id' => $row->id, 'display_name' => $row->display_name, 'identifier' => $row->username ?: $row->email, 'role_key' => $row->role_key, 'version' => $row->version, 'status' => $row->status])->all()
            : [];

        return ['website_key' => $website, 'actor' => ['id' => $actor->id], 'hq_permissions' => $this->access->hqPermissions($actor, $website),
            'brands' => DB::table('fnb_brands')->where('website_key', $website)->orderBy('name')->get()->all(),
            'regions' => DB::table('fnb_regions')->where('website_key', $website)->orderBy('name')->get()->all(),
            'outlets' => DB::table('fnb_outlets')->where('website_key', $website)->orderBy('name')->get(['id', 'code', 'name', 'brand_id', 'region_id', 'status', 'version', 'timezone', 'currency', 'phone', 'address'])->all(),
            'hq_staff' => $hqStaff];
    }

    public function save(FnbContext $ctx, string $kind, ?int $id, array $input, string $key): array
    {
        return $this->commands->run($ctx, 'chain.'.$kind.'.save', ['id' => $id, 'input' => $input], $key, function () use ($ctx, $kind, $id, $input): array {
            $actor = Admin::findOrFail($ctx->actorId);
            $this->access->authorizeHq($actor, $ctx->websiteKey, 'fnb.chain.manage');

            return ['resource' => $kind === 'outlet' ? $this->outlet($ctx, $id, $input) : $this->dimension($ctx, $kind, $id, $input)];
        });
    }

    private function dimension(FnbContext $ctx, string $kind, ?int $id, array $input): array
    {
        $data = validator($input, ['code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'], 'name' => ['required', 'string', 'max:255'], 'status' => ['sometimes', Rule::in(['active', 'inactive'])]])->validate();
        $table = $kind === 'brand' ? 'fnb_brands' : 'fnb_regions';
        $query = DB::table($table)->where('website_key', $ctx->websiteKey);
        $existing = $id ? (clone $query)->where('id', $id)->lockForUpdate()->first() : null;
        abort_if($id && ! $existing, 404);
        $this->expected($existing, $input);
        validator($data, ['code' => [Rule::unique($table, 'code')->where('website_key', $ctx->websiteKey)->ignore($id)]])->validate();
        if (($data['status'] ?? 'active') === 'inactive' && $id && DB::table('fnb_outlets')->where('website_key', $ctx->websiteKey)->where($kind.'_id', $id)->where('status', 'active')->exists()) {
            throw new FnbConflictException('Cần chuyển hoặc ngừng hoạt động các cửa hàng trước khi ẩn nhóm này.');
        }
        $data += ['status' => 'active'];
        $data['version'] = (int) ($existing->version ?? 0) + 1;
        $data['updated_at'] = now();
        if ($existing) {
            (clone $query)->where('id', $id)->update($data);
        } else {
            $id = DB::table($table)->insertGetId($data + ['website_key' => $ctx->websiteKey, 'created_at' => now()]);
        }

        return (array) (clone $query)->where('id', $id)->first();
    }

    private function outlet(FnbContext $ctx, ?int $id, array $input): array
    {
        $data = validator($input, ['code' => [$id ? 'sometimes' : 'required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:255'], 'timezone' => ['sometimes', 'timezone'], 'currency' => ['sometimes', Rule::in(['VND'])],
            'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:1000'],
            'brand_id' => ['nullable', 'integer', 'min:1'], 'region_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])], 'table_count' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'terminal' => ['sometimes', 'array'], 'terminal.code' => ['required_with:terminal', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'], 'terminal.name' => ['required_with:terminal', 'string', 'max:255'],
            'terminal.type' => ['sometimes', Rule::in(['pos', 'kds'])], 'station' => ['sometimes', 'array'],
            'station.code' => ['required_with:station', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'], 'station.name' => ['required_with:station', 'string', 'max:255'],
            'station.sla_seconds' => ['sometimes', 'integer', 'min:10', 'max:86400']])->validate();
        $existing = $id ? DB::table('fnb_outlets')->where('website_key', $ctx->websiteKey)->where('id', $id)->lockForUpdate()->first() : null;
        abort_if($id && ! $existing, 404);
        $this->expected($existing, $input);
        foreach (['brand', 'region'] as $kind) {
            if (! empty($data[$kind.'_id'])) {
                abort_unless(DB::table('fnb_'.$kind.'s')->where('website_key', $ctx->websiteKey)->where('id', $data[$kind.'_id'])->where('status', 'active')->exists(), 422, 'Thương hiệu/vùng không thuộc website hoặc đã ngừng hoạt động.');
            }
        }
        if ($existing && isset($data['code']) && $data['code'] !== $existing->code) {
            throw new FnbConflictException('Mã cửa hàng bất biến sau khi tạo.');
        }
        if ($existing && (isset($data['currency']) && $data['currency'] !== $existing->currency || isset($data['timezone']) && $data['timezone'] !== $existing->timezone)) {
            throw new FnbConflictException('Tiền tệ và múi giờ không đổi sau khi tạo.');
        }
        if ($existing && ($data['status'] ?? 'active') === 'inactive') {
            if (DB::table('fnb_shifts')->where('website_key', $ctx->websiteKey)->where('outlet_id', $id)->where('status', 'open')->exists()
                || DB::table('fnb_service_sessions')->where('website_key', $ctx->websiteKey)->where('outlet_id', $id)->whereNotIn('status', ['closed', 'cancelled', 'merged'])->exists()) {
                throw new FnbConflictException('Cần đóng ca và kết thúc phiên phục vụ trước khi ngừng cửa hàng.');
            }
        }
        $values = array_intersect_key($data, array_flip(['code', 'name', 'phone', 'address', 'brand_id', 'region_id', 'status']));
        $values['version'] = (int) ($existing->version ?? 0) + 1;
        $values['updated_by'] = $ctx->actorId;
        $values['updated_at'] = now();
        if ($existing) {
            DB::table('fnb_outlets')->where('id', $id)->update($values);
        } else {
            validator($data, ['code' => [Rule::unique('fnb_outlets', 'code')->where('website_key', $ctx->websiteKey)]])->validate();
            $id = DB::table('fnb_outlets')->insertGetId($values + ['website_key' => $ctx->websiteKey, 'public_id' => (string) Str::uuid(), 'currency' => $data['currency'] ?? 'VND',
                'timezone' => $data['timezone'] ?? 'Asia/Ho_Chi_Minh', 'status' => 'active', 'settings' => json_encode(['cash_rounding_step_minor' => 500]), 'created_by' => $ctx->actorId, 'created_at' => now()]);
            $this->provision($ctx, $id, $data);
        }
        $this->audit->record('fnb.chain.outlet.saved', $ctx->websiteKey, Admin::findOrFail($ctx->actorId), 'fnb_outlets:'.$id, after: ['outlet_id' => $id, 'version' => $values['version']], outletIds: [$id]);

        return ['outlet' => (array) DB::table('fnb_outlets')->where('id', $id)->first(),
            'terminals' => DB::table('fnb_terminals')->where('website_key', $ctx->websiteKey)->where('outlet_id', $id)->get()->all(),
            'stations' => DB::table('fnb_prep_stations')->where('website_key', $ctx->websiteKey)->where('outlet_id', $id)->get()->all()];
    }

    private function provision(FnbContext $ctx, int $id, array $data): void
    {
        $base = ['website_key' => $ctx->websiteKey, 'outlet_id' => $id, 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()];
        DB::table('fnb_terminals')->insert($base + ['public_id' => (string) Str::uuid(), 'code' => $data['terminal']['code'] ?? 'POS-01', 'name' => $data['terminal']['name'] ?? 'Thu ngân', 'type' => $data['terminal']['type'] ?? 'pos']);
        $stationId = DB::table('fnb_prep_stations')->insertGetId($base + ['code' => $data['station']['code'] ?? 'BAR', 'name' => $data['station']['name'] ?? 'Bar', 'sla_seconds' => $data['station']['sla_seconds'] ?? 600]);
        foreach (DB::table('fnb_menu_items')->where('website_key', $ctx->websiteKey)->where('status', 'active')->where('item_type', 'prepared')->pluck('id') as $itemId) {
            DB::table('fnb_item_station_routes')->insert(['website_key' => $ctx->websiteKey, 'outlet_id' => $id, 'item_id' => $itemId, 'variant_id' => null, 'variant_key' => 0, 'prep_station_id' => $stationId, 'priority' => 100, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([['CASH', 'Tiền mặt', 'cash'], ['TRANSFER', 'Chuyển khoản', 'transfer']] as [$code, $name, $kind]) {
            DB::table('fnb_payment_methods')->insert($base + compact('code', 'name', 'kind'));
        }
        $area = DB::table('fnb_service_areas')->insertGetId($base + ['code' => 'MAIN', 'name' => 'Khu vực chính']);
        for ($n = 1; $n <= ($data['table_count'] ?? 5); $n++) {
            DB::table('fnb_dining_tables')->insert(array_replace($base, ['status' => 'available']) + ['service_area_id' => $area, 'code' => 'B'.$n, 'name' => 'Bàn '.$n, 'capacity' => 4, 'sort_order' => $n]);
        }
        $menu = DB::table('fnb_menus')->where('website_key', $ctx->websiteKey)->where('status', 'published')->whereNull('archived_at')->orderBy('id')->first();
        if ($menu) {
            DB::table('fnb_menu_outlets')->insert(['website_key' => $ctx->websiteKey, 'menu_id' => $menu->id, 'outlet_id' => $id, 'channel' => 'pos', 'default_slot' => 'default', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function expected(?object $existing, array $input): void
    {
        if ($existing && (! isset($input['expected_version']) || (int) $input['expected_version'] !== (int) $existing->version)) {
            throw new FnbConflictException('Dữ liệu đã thay đổi.', ['current_versions' => ['resource' => (int) $existing->version]]);
        }
    }
}
