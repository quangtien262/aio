<?php

namespace Modules\Pharmacy\Services\Setup;

use App\Models\Admin;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Inventory\Services\InventoryManagedStockService;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Merchandise\Services\MerchandiseService;
use Modules\Merchandise\Services\MerchandiseUnitService;
use Modules\Pharmacy\Services\Access\PharmacyAccessService;
use Modules\Pharmacy\Services\Commands\PharmacyCommandService;
use Modules\Pharmacy\Services\PharmacyData as D;

final class PharmacySetupService
{
    public function dispatch(Admin $actor, string $action, array $data, string $key): array
    {
        $access = app(PharmacyAccessService::class);
        $permission = match ($action) {
            'product.save', 'unit.save' => 'pharmacy.product.manage', 'price.save' => 'pharmacy.price.manage', 'credential.save' => 'pharmacy.professional.manage', default => 'pharmacy.settings'
        };

        return app(PharmacyCommandService::class)->run($actor, $action, $data, $key, function ($live) use ($action, $data, $permission, $access) {
            if ($action === 'setup') {
                $access->permission($live, 'pharmacy.settings');

                return ['permission' => 'pharmacy.settings'];
            }
            if ($action === 'pharmacy.create') {
                return ['chain' => $access->chain($live, $data['chain_id'] ?? '', $permission, true), 'permission' => $permission];
            }
            $pharmacy = $access->pharmacy($live, $data['pharmacy_id'] ?? '', $permission, true);
            if (in_array($action, ['membership.save', 'credential.save'], true)) {
                $access->chain($live, (int) $pharmacy->chain_id, $permission, true);
            }

            return ['pharmacy' => $pharmacy, 'permission' => $permission];
        }, function ($live, $scope) use ($action, $data) {
            $p = $scope['pharmacy'] ?? null;

            return match ($action) {
                'setup' => $this->setup($live, $data),
                'pharmacy.create' => $this->createPharmacy($live, $scope['chain'], $data),
                'product.save' => $this->product($live, $p, $data),
                'unit.save' => $this->unit($live, $p, $data),
                'price.save' => $this->price($live, $p, $data),
                'membership.save' => $this->membership($live, $p, $data),
                'credential.save' => $this->credential($live, $p, $data),
                'counter.save' => $this->counter($p, $data),
                'settings.save' => $this->settings($p, $data),
                default => abort(422, 'Thao tác thiết lập chưa được hỗ trợ.'),
            };
        });
    }

    private function setup(Admin $actor, array $data): array
    {
        $v = validator($data, ['chain_name' => 'required|string|max:255', 'pharmacy_name' => 'required|string|max:255', 'code' => 'nullable|string|max:35|regex:/^[A-Za-z0-9._-]+$/', 'timezone' => 'sometimes|timezone', 'license_number' => 'nullable|string|max:255'])->validate();
        abort_unless($actor->canAccess('merchandise.manage', 'global') && $actor->canAccess('inventory.warehouse.manage', 'global'), 403, 'Khởi tạo chuỗi cần quyền tạo danh mục và kho.');
        $code = strtoupper($v['code'] ?? 'NT'.substr(str_replace('-', '', (string) Str::uuid()), 0, 10));
        $catalog = app(MerchandiseService::class)->createCatalog(['code' => 'PH-'.$code, 'name' => $v['chain_name']], $actor->id);
        foreach ([['vien', 'Viên'], ['vi', 'Vỉ'], ['hop', 'Hộp'], ['chai', 'Chai'], ['ong', 'Ống'], ['goi', 'Gói'], ['tuyp', 'Tuýp'], ['lo', 'Lọ'], ['ml', 'Mililít']] as [$unit, $name]) {
            MerchandiseUnitService::register($catalog['id'], $unit, $name);
        }
        $uuid = (string) Str::uuid();
        $chain = D::insert('ph_chains', ['public_id' => $uuid, 'code' => $code, 'name' => $v['chain_name'], 'catalog_id' => $catalog['id'], 'legal_entity_ref' => 'pharmacy-chain:'.$uuid, 'timezone' => $v['timezone'] ?? 'Asia/Ho_Chi_Minh', 'settings' => '{}']);
        D::insert('ph_memberships', ['chain_id' => $chain->id, 'pharmacy_id' => null, 'admin_id' => $actor->id, 'permissions' => D::encode(app(PharmacyAccessService::class)->permissions($actor))]);
        $created = $this->createPharmacy($actor, $chain, ['name' => $v['pharmacy_name'], 'code' => $code.'-01', 'license_number' => $v['license_number'] ?? null]);

        return ['id' => $created['id'], 'public_id' => $created['public_id'], 'status' => 'active', 'chain' => D::resource($chain), 'pharmacy' => $created];
    }

    private function createPharmacy(Admin $actor, object $chain, array $data): array
    {
        $chain = D::row('ph_chains', (int) $chain->id, lock: true);
        $v = validator($data, ['name' => 'required|string|max:255', 'code' => ['required', 'string', 'max:50', Rule::unique('ph_pharmacies')->where('chain_id', $chain->id)], 'timezone' => 'sometimes|timezone', 'facility_type' => 'sometimes|in:pharmacy,drug_counter', 'license_number' => 'nullable|string|max:255'])->validate();
        $warehouse = app(InventoryV2Service::class)->createWarehouse(['code' => 'PH-'.$chain->id.'-'.$v['code'], 'name' => $v['name'], 'catalog_id' => $chain->catalog_id, 'legal_entity_ref' => $chain->legal_entity_ref, 'timezone' => $v['timezone'] ?? $chain->timezone]);
        $pharmacy = D::insert('ph_pharmacies', $v + ['chain_id' => $chain->id, 'warehouse_id' => $warehouse['id'], 'legal_entity_ref' => $chain->legal_entity_ref, 'timezone' => $chain->timezone, 'settings' => '{}']);
        app(InventoryManagedStockService::class)->withinOwner('pharmacy', $pharmacy->public_id, $actor->id, 'setup', fn () => app(InventoryManagedStockService::class)->tagWarehouse($warehouse['id'], 'pharmacy', $pharmacy->public_id, 'pharmacy-setup:'.$pharmacy->public_id, $actor->id));
        foreach (DB::table('ph_recalls')->where('chain_id', $chain->id)->orderBy('id')->get() as $recall) {
            $product = D::row('ph_products', (int) $recall->product_id, $chain->id);
            $hold = app(InventoryManagedStockService::class)->withinOwner('pharmacy', $pharmacy->public_id, $actor->id, 'setup', fn () => app(InventoryManagedStockService::class)->placeHold(['warehouse_id' => $warehouse['id'], 'item_id' => $product->inventory_item_id, 'batch_id' => $recall->batch_id, 'reason_code' => 'recall', 'reason' => $recall->reason, 'source_ref' => $recall->public_id], 'recall:new-site:'.$pharmacy->public_id.':'.$recall->public_id, $actor->id));
            D::insert('ph_recall_tasks', ['chain_id' => $chain->id, 'pharmacy_id' => $pharmacy->id, 'recall_id' => $recall->id, 'hold_id' => $hold['public_id'], 'status' => $recall->status === 'closed' ? 'permanent_hold' : 'pending']);
        }
        $counter = D::insert('ph_counters', ['chain_id' => $chain->id, 'pharmacy_id' => $pharmacy->id, 'code' => 'POS-01', 'name' => 'Quầy 01']);

        return D::resource($pharmacy) + ['counter' => D::resource($counter)];
    }

    private function product(Admin $actor, object $p, array $data): array
    {
        app(PharmacyAccessService::class)->chain($actor, (int) $p->chain_id, 'pharmacy.product.manage', true);
        app(PharmacyAccessService::class)->pharmacy($actor, (int) $p->id, 'pharmacy.price.manage', true);
        $v = validator($data, ['product_id' => 'nullable|uuid', 'name' => 'required|string|max:255', 'code' => 'nullable|string|max:60', 'base_unit' => 'required|string|max:30', 'registration_number' => 'nullable|string|max:255', 'strength' => 'nullable|string|max:255', 'dosage_form' => 'nullable|string|max:255', 'route' => 'nullable|string|max:255', 'requires_prescription' => 'sometimes|boolean', 'controlled' => 'sometimes|boolean', 'cold_chain' => 'sometimes|boolean', 'restricted_retail' => 'sometimes|boolean', 'minimum_increment' => 'sometimes', 'price_minor' => 'required', 'barcode' => 'nullable|string|max:100', 'packagings' => 'sometimes|array|max:30', 'barcodes' => 'sometimes|array|max:50', 'expected_version' => 'sometimes|integer|min:1'])->validate();
        $chain = D::row('ph_chains', (int) $p->chain_id, lock: true);
        $existing = ! empty($v['product_id']) ? D::row('ph_products', $v['product_id'], $p->chain_id, lock: true) : null;
        if ($existing) {
            abort_unless((int) ($v['expected_version'] ?? 0) === (int) $existing->version, 409, 'Thuốc đã thay đổi.');
        }
        $ingredients = $data['ingredients'] ?? ($existing ? D::json($existing->ingredients) : []);
        if (is_string($ingredients)) {
            $ingredients = [['name' => trim($ingredients)]];
        }
        validator(['ingredients' => $ingredients], ['ingredients' => 'array|max:30', 'ingredients.*.name' => 'required|string|max:255', 'ingredients.*.strength' => 'nullable|string|max:100', 'ingredients.*.unit' => 'nullable|string|max:50'])->validate();
        $skuData = ['sku' => $v['code'] ?? $existing?->code, 'name' => $v['name'], 'base_unit' => $v['base_unit'], 'tracking_mode' => 'batch', 'requires_expiry' => true, 'is_active' => true];
        if (isset($v['packagings'])) {
            $skuData['packagings'] = $v['packagings'];
        }
        if (isset($v['barcodes']) || ! empty($v['barcode'])) {
            $skuData['barcodes'] = array_merge($v['barcodes'] ?? [], ! empty($v['barcode']) ? [$v['barcode']] : []);
        }
        if ($existing) {
            $skuData['expected_version'] = DB::table('merch_skus')->where('id', $existing->sku_id)->value('version');
        }
        $sku = app(MerchandiseService::class)->saveSku($chain->catalog_id, $skuData, $existing?->sku_id);
        $item = app(InventoryV2Service::class)->ensureItem($sku['id']);
        $values = array_intersect_key($v, array_flip(['name', 'base_unit', 'registration_number', 'strength', 'dosage_form', 'route', 'requires_prescription', 'controlled', 'cold_chain', 'restricted_retail']));
        $values += ['chain_id' => $chain->id, 'sku_id' => $sku['id'], 'inventory_item_id' => $item, 'code' => $sku['sku'], 'ingredients' => D::encode($ingredients), 'minimum_increment' => (string) D::decimal($v['minimum_increment'] ?? $existing?->minimum_increment ?? '1'), 'metadata' => D::encode(['source' => 'manual', 'classification_review_required' => true])];
        $row = $existing ? D::update('ph_products', $existing, $values, $v['expected_version']) : D::insert('ph_products', $values);
        $price = DB::table('ph_prices')->where('pharmacy_id', $p->id)->where('product_id', $row->id)->where('packaging_key', 0)->lockForUpdate()->first();
        $this->savePriceRow($p, $row, D::money($v['price_minor']), 0, $price);

        return D::resource($row) + ['price_minor' => (string) $v['price_minor']];
    }

    private function price(Admin $actor, object $p, array $data): array
    {
        $product = D::row('ph_products', $data['product_id'] ?? '', $p->chain_id);
        $packaging = (int) ($data['packaging_id'] ?? 0);
        if ($packaging) {
            abort_unless(DB::table('merch_packagings')->where('id', $packaging)->where('sku_id', $product->sku_id)->where('is_active', true)->exists(), 422, 'Quy cách không thuộc thuốc.');
        }
        $row = DB::table('ph_prices')->where('pharmacy_id', $p->id)->where('product_id', $product->id)->where('packaging_key', $packaging)->lockForUpdate()->first();
        if ($row) {
            abort_unless((int) ($data['expected_version'] ?? 0) === (int) $row->version, 409, 'Giá đã thay đổi.');
        }

        return D::resource($this->savePriceRow($p, $product, D::money($data['price_minor'] ?? null), $packaging, $row));
    }

    private function unit(Admin $actor, object $p, array $data): array
    {
        app(PharmacyAccessService::class)->chain($actor, (int) $p->chain_id, 'pharmacy.product.manage', true);
        $chain = D::row('ph_chains', (int) $p->chain_id);

        return D::resource(app(MerchandiseUnitService::class)->save($chain->catalog_id, $data, $data['unit_id'] ?? null));
    }

    private function savePriceRow(object $p, object $product, int $amount, int $packaging, ?object $existing): object
    {
        $values = ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'product_id' => $product->id, 'packaging_id' => $packaging ?: null, 'packaging_key' => $packaging, 'price_minor' => $amount, 'effective_at' => now()];

        return $existing ? D::update('ph_prices', $existing, $values, $existing->version) : D::insert('ph_prices', $values);
    }

    private function membership(Admin $actor, object $p, array $data): array
    {
        $v = validator($data, ['membership_id' => 'nullable|uuid', 'admin_id' => 'required|integer|exists:admins,id', 'permissions' => 'required|array|min:1', 'permissions.*' => ['required', Rule::in(PharmacyAccessService::PERMISSIONS)], 'chain_wide' => 'sometimes|boolean', 'expires_at' => 'nullable|date|after:now', 'status' => 'sometimes|in:active,inactive', 'expected_version' => 'sometimes|integer'])->validate();
        $scopeId = ($v['chain_wide'] ?? false) ? null : $p->id;
        foreach ($v['permissions'] as $permission) {
            app(PharmacyAccessService::class)->chain($actor, (int) $p->chain_id, $permission, true);
            $target = Admin::query()->findOrFail($v['admin_id']);
            abort_unless($target->canAccess($permission, 'global'), 422, 'Tài khoản đích chưa có role Core cho '.$permission);
        }
        $values = ['chain_id' => $p->chain_id, 'pharmacy_id' => $scopeId, 'admin_id' => $v['admin_id'], 'permissions' => D::encode(array_values(array_unique($v['permissions']))), 'expires_at' => $v['expires_at'] ?? null, 'status' => $v['status'] ?? 'active'];
        $old = ! empty($v['membership_id']) ? D::row('ph_memberships', $v['membership_id'], $p->chain_id, lock: true) : null;
        $row = $old ? D::update('ph_memberships', $old, $values, $v['expected_version'] ?? null) : D::insert('ph_memberships', $values);

        return D::resource($row);
    }

    private function credential(Admin $actor, object $p, array $data): array
    {
        $v = validator($data, ['credential_id' => 'nullable|uuid', 'admin_id' => 'required|integer|exists:admins,id', 'license_number' => 'required|string|max:255', 'abilities' => 'required|array|min:1', 'abilities.*' => 'in:review,dispense,handover,controlled', 'valid_from' => 'required|date', 'valid_until' => 'required|date|after:valid_from', 'status' => 'sometimes|in:active,inactive', 'reason' => 'required|string|max:1000', 'expected_version' => 'sometimes|integer'])->validate();
        abort_unless(Admin::query()->findOrFail($v['admin_id'])->isAvailable(), 422);
        $values = array_intersect_key($v, array_flip(['admin_id', 'license_number', 'valid_from', 'valid_until', 'status']));
        $values += ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'abilities' => D::encode($v['abilities']), 'verified_by' => $actor->id, 'verified_at' => now(), 'encrypted_verification_evidence' => Crypt::encryptString(D::encode(['reason' => $v['reason'], 'method' => 'manual_attestation', 'actor_id' => $actor->id, 'recorded_at' => now()->toIso8601String()]))];
        $old = ! empty($v['credential_id']) ? D::row('ph_credentials', $v['credential_id'], $p->chain_id, $p->id, true) : null;

        return D::resource($old ? D::update('ph_credentials', $old, $values, $v['expected_version'] ?? null) : D::insert('ph_credentials', $values));
    }

    private function counter(object $p, array $data): array
    {
        $v = validator($data, ['code' => 'required|string|max:50', 'name' => 'required|string|max:255'])->validate();

        return D::resource(D::insert('ph_counters', $v + ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id]));
    }

    private function settings(object $p, array $data): array
    {
        $v = validator($data, ['expected_version' => 'required|integer', 'license_number' => 'nullable|string|max:255', 'workflow_mode' => 'required|in:single_counter,separated_queue', 'address' => 'nullable|string|max:1000', 'phone' => 'nullable|string|max:40'])->validate();
        $settings = D::json($p->settings);
        $settings['workflow_mode'] = $v['workflow_mode'];
        $settings['address'] = $v['address'] ?? null;
        $settings['phone'] = $v['phone'] ?? null;

        return D::resource(D::update('ph_pharmacies', $p, ['settings' => D::encode($settings), 'license_number' => $v['license_number'] ?? $p->license_number], $v['expected_version']));
    }
}
