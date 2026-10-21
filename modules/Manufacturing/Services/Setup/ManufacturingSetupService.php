<?php

namespace Modules\Manufacturing\Services\Setup;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Inventory\Services\InventoryProductionService;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Manufacturing\Services\Access\ManufacturingAccessService;
use Modules\Manufacturing\Services\ManufacturingCode;
use Modules\Manufacturing\Services\ManufacturingData as D;
use Modules\Manufacturing\Services\Runtime\ManufacturingRuntimeState;
use Modules\Merchandise\Services\MerchandiseService;

class ManufacturingSetupService
{
    public function dispatch(Admin $actor, string $operation, array $scope, array $data, ?string $resource = null): array
    {
        $access = app(ManufacturingAccessService::class);
        $access->permission($actor, 'manufacturing.settings');

        return match ($operation) {
            'company.create' => $this->company($actor, $data),
            'company.update' => $this->updateCompany($actor, $scope, $data, $resource),
            'factory.create' => $this->factory($actor, $scope, $data),
            'workshop.create', 'line.create', 'shift.create' => $this->unit($actor, $operation, $scope, $data),
            'membership.save' => $this->membership($actor, $scope, $data, $resource),
            'sku.save' => $this->sku($actor, $scope, $data, $resource),
            'runtime.pause', 'runtime.resume' => app(ManufacturingRuntimeState::class)->setPaused($actor, $operation === 'runtime.pause', $data['reason'] ?? null),
            default => abort(422, 'Thao tác thiết lập sản xuất không hợp lệ.'),
        };
    }

    private function company(Admin $actor, array $data): array
    {
        $data = ManufacturingCode::fill($data, 'code', 'DN', 'mfg_companies');
        $v = validator($data, ['code' => 'required|string|max:40|regex:/^[A-Za-z0-9._-]+$/|unique:mfg_companies,code', 'name' => 'required|string|max:255', 'timezone' => 'sometimes|timezone'])->validate();
        abort_unless($actor->canAccess('merchandise.manage', 'global'), 403, 'Tạo danh mục doanh nghiệp cần quyền Merchandise toàn cục.');
        $public = (string) Str::uuid();
        $catalog = app(MerchandiseService::class)->createCatalog(['code' => 'MFG-'.$v['code'], 'name' => $v['name']], $actor->id);
        $id = DB::table('mfg_companies')->insertGetId($v + ['public_id' => $public, 'status' => 'active', 'version' => 1,
            'settings' => D::encode(['catalog_id' => $catalog['id'], 'legal_entity_ref' => 'mfg-company:'.$public]), 'created_at' => now(), 'updated_at' => now()]);
        D::insert('mfg_memberships', ['company_id' => $id, 'factory_id' => null, 'admin_id' => $actor->id, 'role' => 'manager', 'status' => 'active']);

        return D::resource(DB::table('mfg_companies')->find($id));
    }

    private function updateCompany(Admin $actor, array $scope, array $data, ?string $resource): array
    {
        abort_unless($resource, 422, 'Cần chọn doanh nghiệp cần sửa.');
        $company = app(ManufacturingAccessService::class)->company($actor, $resource, 'manufacturing.settings', true);
        abort_if(isset($scope['company']) && (int) $scope['company']->id !== (int) $company->id, 403, 'Doanh nghiệp không thuộc phạm vi giao dịch.');
        $row = D::row('mfg_companies', (int) $company->id, lock: true);
        $data = ManufacturingCode::fill($data, 'code', 'DN', 'mfg_companies', [], $row->code);
        $v = validator($data, [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('mfg_companies', 'code')->ignore($row->id)],
            'name' => 'required|string|max:255', 'timezone' => 'sometimes|timezone', 'expected_version' => 'required|integer|min:1',
        ], ['code.unique' => 'Mã công ty đã tồn tại.'])->validate();
        $version = $v['expected_version'];
        unset($v['expected_version']);

        return D::resource(D::update('mfg_companies', $row, $v, $version));
    }

    private function factory(Admin $actor, array $scope, array $data): array
    {
        if (is_string($data['code'] ?? null)) {
            $data['code'] = trim($data['code']);
            if ($data['code'] === '') {
                $data['code'] = null;
            }
        }
        $v = validator($data, ['company_id' => 'sometimes|uuid', 'code' => 'nullable|string|max:40|regex:/^[A-Za-z0-9._-]+$/', 'name' => 'required|string|max:255', 'timezone' => 'sometimes|timezone'])->validate();
        $company = app(ManufacturingAccessService::class)->company($actor, $data['company_id'] ?? (int) ($scope['company']->id ?? 0), 'manufacturing.settings', true);
        // Factory commands run in one transaction. Serialize allocations with
        // manual codes too, so concurrent creates cannot claim the same code.
        DB::table('mfg_companies')->where('id', $company->id)->lockForUpdate()->first(['id']);
        $automaticCode = ! isset($v['code']);
        if ($automaticCode) {
            $sequence = DB::table('mfg_factories')->where('company_id', $company->id)->count() + 1;
            do {
                $v['code'] = 'NM'.str_pad((string) $sequence++, 6, '0', STR_PAD_LEFT);
                $warehouseCode = 'MFG-'.$company->id.'-'.$v['code'];
            } while (DB::table('mfg_factories')->where('company_id', $company->id)->where('code', $v['code'])->exists()
                || DB::table('inv_warehouses')->where('code', $warehouseCode)->exists());
        } else {
            $warehouseCode = 'MFG-'.$company->code.'-'.$v['code'];
        }
        abort_if(DB::table('mfg_factories')->where('company_id', $company->id)->where('code', $v['code'])->exists(), 422, 'Mã nhà máy đã tồn tại.');
        abort_unless($actor->canAccess('inventory.warehouse.manage', 'global'), 403, 'Tạo kho nhà máy cần quyền quản lý kho toàn cục.');
        $settings = D::json($company->settings);
        $warehouse = app(InventoryV2Service::class)->createWarehouse(['code' => $warehouseCode, 'name' => $v['name'],
            'catalog_id' => $settings['catalog_id'], 'legal_entity_ref' => $settings['legal_entity_ref'], 'timezone' => $v['timezone'] ?? $company->timezone]);
        app(InventoryProductionService::class)->enableWarehouse(['warehouse_id' => $warehouse['id']], 'mfg-warehouse:'.$warehouse['public_id'], $actor->id);
        unset($v['company_id']);
        $row = D::insert('mfg_factories', $v + ['company_id' => $company->id, 'warehouse_id' => $warehouse['id'], 'timezone' => $company->timezone, 'settings' => '{}', 'status' => 'active']);

        return D::resource($row);
    }

    private function unit(Admin $actor, string $operation, array $scope, array $data): array
    {
        $factory = $this->scopedFactory($actor, $scope, $data);
        if (is_string($data['code'] ?? null)) {
            $data['code'] = trim($data['code']);
            if ($data['code'] === '') {
                $data['code'] = null;
            }
        }
        $rules = ['code' => 'nullable|string|max:50|regex:/^[A-Za-z0-9._-]+$/', 'name' => 'required|string|max:255'];
        if ($operation === 'line.create') {
            $rules += ['workshop_id' => 'nullable|uuid', 'capacity_minutes' => 'sometimes|numeric|min:0'];
        }
        if ($operation === 'shift.create') {
            $rules += ['starts_at' => 'required|date_format:H:i', 'ends_at' => 'required|date_format:H:i'];
        }
        $v = validator($data, $rules)->validate();
        if (isset($v['workshop_id'])) {
            $v['workshop_id'] = D::row('mfg_workshops', $v['workshop_id'], $factory->company_id, $factory->id)->id;
        }
        $table = match ($operation) {
            'workshop.create' => 'mfg_workshops', 'line.create' => 'mfg_lines', 'shift.create' => 'mfg_shifts'
        };
        // Serialize both manual and automatic unit codes in this factory
        // within the command transaction; the unique index remains the guard.
        DB::table('mfg_factories')->where('id', $factory->id)->lockForUpdate()->first(['id']);
        if (! isset($v['code'])) {
            $sequence = DB::table($table)->where('factory_id', $factory->id)->count() + 1;
            do {
                $v['code'] = (match ($operation) { 'workshop.create' => 'PX', 'line.create' => 'CM', 'shift.create' => 'CA' }).str_pad((string) $sequence++, 6, '0', STR_PAD_LEFT);
            } while (DB::table($table)->where('factory_id', $factory->id)->where('code', $v['code'])->exists());
        }
        abort_if(DB::table($table)->where('factory_id', $factory->id)->where('code', $v['code'])->exists(), 422, 'Mã đơn vị đã tồn tại tại nhà máy.');

        return D::resource(D::insert($table, $v + ['company_id' => $factory->company_id, 'factory_id' => $factory->id, 'status' => 'active']));
    }

    private function membership(Admin $actor, array $scope, array $data, ?string $resource): array
    {
        $access = app(ManufacturingAccessService::class);
        $v = validator($data, ['company_id' => 'sometimes|uuid', 'factory_id' => 'nullable|uuid', 'admin_id' => 'required|integer|exists:admins,id',
            'role' => 'required|in:reader,operator,manager', 'permissions' => 'nullable|array', 'permissions.*' => 'string|in:'.implode(',', ManufacturingAccessService::PERMISSIONS),
            'status' => 'sometimes|in:active,inactive', 'expires_at' => 'nullable|date|after:now'])->validate();
        $companyId = $data['company_id'] ?? $scope['company']->id ?? $scope['factory']->company_id ?? null;
        abort_unless($companyId !== null, 422, 'Cần chọn doanh nghiệp.');
        $company = D::row('mfg_companies', $companyId);
        $factory = ! empty($v['factory_id']) ? $access->factory($actor, $v['factory_id'], 'manufacturing.settings') : null;
        abort_if($factory && (int) $factory->company_id !== (int) $company->id, 422, 'Phân xưởng không thuộc doanh nghiệp.');
        $permissions = $v['permissions'] ?? $this->rolePermissions($v['role']);
        $access->assertCanDelegate($actor, $company, $factory, $permissions);
        $existing = $resource ? D::row('mfg_memberships', $resource, $company->id, lock: true) : null;
        if ($existing) {
            $oldFactory = $existing->factory_id ? D::row('mfg_factories', (int) $existing->factory_id, $company->id) : null;
            $access->assertCanDelegate($actor, $company, $oldFactory, D::json($existing->permissions, $this->rolePermissions($existing->role)));
            abort_unless((int) $existing->admin_id === (int) $v['admin_id'], 422, 'Không đổi danh tính của membership đã tạo.');
        } else {
            abort_if(DB::table('mfg_memberships')->where('company_id', $company->id)->where('admin_id', $v['admin_id'])->where('factory_id', $factory?->id)->exists(), 409, 'Membership đã tồn tại; cập nhật bằng ID và expected_version.');
        }
        unset($v['company_id'], $v['factory_id']);
        $values = $v + ['company_id' => $company->id, 'factory_id' => $factory?->id, 'status' => 'active'];
        $values['permissions'] = array_key_exists('permissions', $v) && $v['permissions'] !== null ? D::encode($v['permissions']) : null;
        $row = $existing ? D::update('mfg_memberships', $existing, $values, $data['expected_version'] ?? null) : D::insert('mfg_memberships', $values);

        return D::resource($row);
    }

    private function sku(Admin $actor, array $scope, array $data, ?string $resource): array
    {
        $company = $scope['company'] ?? ($scope['factory'] ? D::row('mfg_companies', (int) $scope['factory']->company_id) : null);
        abort_unless($company, 422, 'Cần chọn doanh nghiệp.');
        app(ManufacturingAccessService::class)->company($actor, (int) $company->id, 'manufacturing.settings');
        abort_unless($actor->canAccess('merchandise.manage', 'global'), 403, 'Cần quyền Merchandise toàn cục để quản lý SKU.');
        $catalogId = (int) D::json($company->settings)['catalog_id'];
        $sku = $resource ? DB::table('merch_skus')->where('public_id', $resource)->where('catalog_id', $catalogId)->first() : null;
        abort_if($resource && ! $sku, 404);

        $data = ManufacturingCode::fill($data, 'sku', 'VT', 'merch_skus', ['catalog_id' => $catalogId], $sku?->sku);

        return D::resource(app(MerchandiseService::class)->saveSku($catalogId, $data + ['tracking_mode' => $sku?->tracking_mode ?? 'batch'], $sku?->id));
    }

    private function scopedFactory(Admin $actor, array $scope, array $data): object
    {
        $factory = app(ManufacturingAccessService::class)->factory($actor, $data['factory_id'] ?? (int) ($scope['factory']->id ?? 0), 'manufacturing.settings', true);
        abort_if(isset($scope['factory']) && $scope['factory']->id !== $factory->id, 403, 'Không được thay phạm vi command bằng payload.');

        return $factory;
    }

    private function rolePermissions(string $role): array
    {
        return match ($role) {
            'manager' => ManufacturingAccessService::PERMISSIONS,
            'operator' => ['manufacturing.view', 'manufacturing.execute', 'manufacturing.quality', 'manufacturing.fulfill', 'manufacturing.documents'],
            default => ['manufacturing.view'],
        };
    }
}
