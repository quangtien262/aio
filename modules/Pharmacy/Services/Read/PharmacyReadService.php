<?php

namespace Modules\Pharmacy\Services\Read;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Core\Modules\ModuleRegistry;
use App\Models\Admin;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Inventory\Services\InventoryManagedStockService;
use Modules\Merchandise\Services\MerchandiseUnitService;
use Modules\Pharmacy\Services\Access\PharmacyAccessService;
use Modules\Pharmacy\Services\Catalog\PharmacyBarcode;
use Modules\Pharmacy\Services\Dispensing\DispensingService;
use Modules\Pharmacy\Services\Integrations\PharmacyIntegrationOptions;
use Modules\Pharmacy\Services\PharmacyData as D;
use Modules\Pharmacy\Services\Prescriptions\PatientService;
use Modules\Pharmacy\Services\Prescriptions\PrescriptionService;
use Modules\Pharmacy\Services\Quality\PharmacyRecallService;
use Modules\Pharmacy\Services\Sales\PharmacySaleService;
use Modules\Purchasing\Services\PurchasingService;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class PharmacyReadService
{
    public function bootstrap(Admin $actor, ?string $selected = null): array
    {
        $access = app(PharmacyAccessService::class);
        $actor = $access->actor($actor, allowPasswordIdentity: true);
        $base = ['id' => $actor->id, 'name' => $actor->name, 'username' => $actor->username, 'email' => $actor->email, 'is_system_owner' => $actor->isSystemOwner(), 'is_super_admin' => $actor->isSuperAdmin(), 'must_change_password' => (bool) $actor->must_change_password, 'two_factor_enabled' => $actor->two_factor_confirmed_at !== null, 'workspace_kind' => 'enterprise', 'current_website' => null, 'site_options' => [], 'organization_options' => [], 'site_profile' => ['site_name' => 'AIO Nhà thuốc', 'branding' => []], 'permissions' => [], 'navigation_permissions' => [], 'module_navigation' => [], 'pharmacies' => [], 'chains' => [], 'counters' => [], 'current_pharmacy_id' => null, 'capabilities' => []];
        if ($actor->must_change_password) {
            return $base;
        }
        $access->workspace($actor);
        $global = $access->permissions($actor);
        $pharmacies = DB::table('ph_pharmacies')->whereIn('id', $access->allowedPharmacyIds($actor))->orderBy('name')->get();
        $chains = DB::table('ph_chains')->whereIn('id', $access->allowedChainIds($actor))->orderBy('name')->get();
        $current = $selected ? $access->pharmacy($actor, $selected) : $pharmacies->first();
        $projection = $access->permissionProjection($actor, $pharmacies->all());
        $effective = $current ? ($projection['scopes'][$current->id] ?? []) : $global;
        $registry = app(ModuleRegistry::class);
        $module = $registry->find('pharmacy');
        $nativeNavigation = $projection['navigation'];
        $navigation = array_values(array_filter($registry->navigationForPermissions($nativeNavigation), fn ($item) => $item['module_key'] === 'pharmacy'));
        $capabilities = ['online' => true, 'offline' => false, 'otc' => true, 'prescriptions' => true, 'exact_lot' => true, 'controlled' => false, 'cold_chain' => false, 'ecommerce' => false, 'electronic_payment_provider' => false, 'clinical_knowledge' => false, 'customers' => false, 'accounting' => false, 'drug_database_sync' => false, 'eprescription_sync' => false];
        $runtimeState = DB::table('ph_runtime_states')->where('module_key', 'pharmacy')->value('operational_state');
        $checks = [];
        foreach (['merchandise' => 'merchandise.skus.read.v1', 'inventory' => 'inventory.managed_stock.v1'] as $key => $capability) {
            if (! app(ModuleCapabilityChecker::class)->has($key, $capability)) {
                $name = $registry->find($key)['name'] ?? $key;
                $checks[] = ['code' => $key, 'message' => 'Cần cài đặt hoặc cập nhật và bật ứng dụng “'.$name.'”.'];
            }
        }

        return array_replace($base, ['module' => $module, 'permissions' => $effective, 'navigation_permissions' => $nativeNavigation, 'module_navigation' => $navigation, 'navigation' => $navigation, 'pharmacies' => $pharmacies->map(fn ($p) => D::resource($p))->all(), 'chains' => $chains->map(fn ($p) => D::resource($p))->all(), 'current_pharmacy_id' => $current?->public_id, 'current_pharmacy' => $current ? D::resource($current) : null, 'counters' => $current ? DB::table('ph_counters')->where('pharmacy_id', $current->id)->where('status', 'active')->get()->map(fn ($r) => D::resource($r))->all() : [], 'capabilities' => $capabilities, 'runtime' => ['status' => $runtimeState, 'paused' => $runtimeState !== 'active'], 'readiness' => ['ready' => $checks === [], 'issues' => $checks, 'can_open_app_store' => $actor->canAccess('store.module.view', 'global')], 'can_setup' => in_array('pharmacy.settings', $global, true) && $actor->canAccess('merchandise.manage', 'global') && $actor->canAccess('inventory.warehouse.manage', 'global'), 'timezones' => timezone_identifiers_list()]);
    }

    public function read(Admin $actor, string $kind, array $filters = [], ?string $id = null): array
    {
        $permission = match ($kind) {
            'patients', 'customers' => 'pharmacy.patient.view', 'prescriptions' => 'pharmacy.prescription.view', 'dispensings', 'work' => 'pharmacy.dispensing.view',
            'sales' => 'pharmacy.sale.view', 'shifts' => 'pharmacy.shift.manage', 'inventory', 'lots', 'trace', 'receipts', 'transfers', 'stocktakes' => 'pharmacy.inventory.view',
            'purchasing', 'suppliers' => 'pharmacy.purchasing.manage', 'quality' => 'pharmacy.view', 'reports', 'operations' => 'pharmacy.report.view',
            'integrations', 'customer-books', 'accounting-organizations', 'accounting-items' => 'pharmacy.integrations.manage', 'settings' => 'pharmacy.settings', 'imports' => 'pharmacy.product.manage', 'prices', 'price-publications' => 'pharmacy.price.manage',
            'tasks' => 'pharmacy.dispensing.view', 'recovery' => 'pharmacy.dispensing.manage', 'replenishment' => 'pharmacy.purchasing.manage', 'cash-movements' => 'pharmacy.shift.manage',
            'recalls' => 'pharmacy.recall.manage',
            default => 'pharmacy.view',
        };
        $p = app(PharmacyAccessService::class)->pharmacy($actor, $filters['pharmacy_id'] ?? '', $permission);
        validator($filters, ['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from'])->validate();
        if ($kind === 'patients' || $kind === 'customers') {
            return $id ? app(PatientService::class)->show($actor, $id) : app(PatientService::class)->list($actor, $filters);
        }
        if ($kind === 'prescriptions') {
            return $id ? app(PrescriptionService::class)->show($actor, $id, $filters['pharmacy_id'] ?? null) : app(PrescriptionService::class)->list($actor, $filters);
        }
        if (in_array($kind, ['dispensings', 'work'], true)) {
            return $id ? app(DispensingService::class)->show($actor, $id) : app(DispensingService::class)->list($actor, $filters);
        }
        if ($kind === 'sales') {
            return $id ? app(PharmacySaleService::class)->showSale($actor, $id) : app(PharmacySaleService::class)->listSales($actor, $filters);
        }
        if ($kind === 'shifts') {
            return app(PharmacySaleService::class)->listShifts($actor, $filters);
        }
        // Transaction readers normalize their own boundaries; generic readers normalize once here.
        foreach (['from', 'to'] as $dateField) {
            if (! empty($filters[$dateField])) {
                $value = (string) $filters[$dateField];
                $date = CarbonImmutable::parse($value, $p->timezone);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
                    $date = $dateField === 'from' ? $date->startOfDay() : $date->endOfDay();
                }
                $filters[$dateField] = $date->utc()->toDateTimeString();
            }
        }
        if ($kind === 'recalls') {
            app(PharmacyAccessService::class)->chain($actor, (int) $p->chain_id, 'pharmacy.recall.manage');

            return $id ? app(PharmacyRecallService::class)->show($actor, $id) : $this->page(DB::table('ph_recalls')->where('chain_id', $p->chain_id)->select('public_id', 'reference', 'status', 'version', 'created_at'), $filters);
        }
        $result = match ($kind) {
            'products', 'prices' => $this->products($p, $filters), 'inventory', 'lots' => $this->inventory($p, $filters),
            'trace' => $this->trace($actor, $p, $filters), 'reports', 'operations' => $this->report($actor, $p, $filters),
            'quality' => $this->quality($actor, $p), 'settings' => $this->settings($actor, $p), 'integrations' => $this->integrations($actor, $p),
            'pharmacies' => $this->page(DB::table('ph_pharmacies')->where('chain_id', $p->chain_id)->whereIn('id', app(PharmacyAccessService::class)->allowedPharmacyIds($actor)), $filters),
            'chains' => $this->page(DB::table('ph_chains')->whereIn('id', app(PharmacyAccessService::class)->allowedChainIds($actor)), $filters),
            'units' => ['records' => app(MerchandiseUnitService::class)->listing(D::row('ph_chains', (int) $p->chain_id)->catalog_id)],
            'scan' => $this->scan($actor, $p, $filters),
            'imports' => $this->page(DB::table('ph_import_batches')->where('pharmacy_id', $p->id)->where('actor_id', $actor->id), $filters),
            'filters' => $this->page(DB::table('ph_saved_filters')->where('pharmacy_id', $p->id)->where('actor_id', $actor->id), $filters),
            'price-publications' => $this->page(DB::table('ph_price_publications')->where('chain_id', $p->chain_id), $filters),
            'tasks' => $this->page(DB::table('ph_work_tasks as t')->join('ph_dispensings as d', 'd.id', '=', 't.dispensing_id')->where('t.pharmacy_id', $p->id)->select('t.*', 'd.public_id as dispensing_public_id', 'd.status as dispensing_status'), $filters, 't.id'),
            'replenishment' => $this->page(DB::table('ph_replenishment_proposals')->where('pharmacy_id', $p->id), $filters),
            'cash-movements' => $this->page(DB::table('ph_cash_movements')->where('pharmacy_id', $p->id), $filters),
            'recovery' => $this->page(DB::table('ph_recovery_cases as c')->join('ph_dispensings as d', 'd.id', '=', 'c.dispensing_id')->where('c.pharmacy_id', $p->id)->select('c.*', 'd.public_id as dispensing_public_id', 'd.version as dispensing_version'), $filters, 'c.id'),
            'customer-books', 'accounting-organizations', 'accounting-items' => app(PharmacyIntegrationOptions::class)->read($actor, $kind, $filters),
            'counters' => $this->page(DB::table('ph_counters')->where('pharmacy_id', $p->id), $filters),
            'receipts' => $this->page(DB::table('ph_receipts as r')->join('ph_products as p', 'p.id', '=', 'r.product_id')->where('r.pharmacy_id', $p->id)->select('r.*', 'p.name as product_name'), $filters, 'r.id'),
            'transfers' => $this->page(DB::table('ph_transfers')->where('chain_id', $p->chain_id)->where(fn ($q) => $q->where('pharmacy_id', $p->id)->orWhere('destination_pharmacy_id', $p->id)), $filters),
            'stocktakes' => $this->stocktakes($p, $filters), 'purchasing' => $this->purchaseOrders($p, $filters), 'suppliers' => $this->suppliers($p, $filters),
            default => abort(404, 'Khu vực Nhà thuốc không tồn tại.'),
        };
        try {
            app(PharmacyAccessService::class)->pharmacy($actor, (int) $p->id, 'pharmacy.cost.view');

            return $result;
        } catch (HttpException $e) {
            if ($e->getStatusCode() !== 403) {
                throw $e;
            }

            return $this->withoutCosts($result);
        }
    }

    private function products(object $p, array $filters): array
    {
        $q = DB::table('ph_products as p')->leftJoin('ph_prices as r', fn ($join) => $join->on('r.product_id', '=', 'p.id')->where('r.pharmacy_id', $p->id)->where('r.packaging_key', 0))->where('p.chain_id', $p->chain_id)->where('p.status', 'active')->select('p.*', 'r.price_minor', 'r.version as price_version');
        $search = trim((string) ($filters['q'] ?? $filters['query'] ?? ''));
        if ($search !== '') {
            $q->where(fn ($q) => $q->where('p.name', 'like', '%'.$search.'%')->orWhere('p.code', 'like', '%'.$search.'%')->orWhere('p.strength', 'like', '%'.$search.'%')->orWhere('p.ingredients', 'like', '%'.$search.'%')->orWhereIn('p.sku_id', DB::table('merch_barcodes')->where('barcode', $search)->select('sku_id')));
        }

        $page = $this->page($q, $filters + ['_search_handled' => true], 'p.id');
        $packs = DB::table('merch_packagings as k')->leftJoin('ph_prices as r', fn ($join) => $join->on('r.packaging_id', '=', 'k.id')->where('r.pharmacy_id', $p->id))
            ->whereIn('k.sku_id', array_column($page['records'], 'sku_id'))->where('k.is_active', true)
            ->select('k.id', 'k.public_id', 'k.sku_id', 'k.code', 'k.name', 'k.quantity_multiplier', 'k.revision', 'r.price_minor', 'r.version as price_version')->get()->groupBy('sku_id');
        foreach ($page['records'] as &$record) {
            $record['packagings'] = ($packs[$record['sku_id']] ?? collect())->map(fn ($pack) => (array) $pack)->all();
        }
        unset($record);

        return $page;
    }

    private function inventory(object $p, array $filters): array
    {
        $query = DB::table('inv_stock_balances as b')->join('ph_products as p', 'p.inventory_item_id', '=', 'b.item_id')->leftJoin('inv_batches as l', 'l.id', '=', 'b.batch_id')->leftJoin('inv_locations as s', 's.id', '=', 'b.location_id')
            ->where('b.warehouse_id', $p->warehouse_id)->where('p.chain_id', $p->chain_id)->select('b.id as balance_id', 'p.public_id', 'p.public_id as product_id', 'p.name as product_name', 'p.name', 'p.code', 'p.base_unit', 'b.item_id', 'b.batch_id', 'l.batch_code', 'l.expires_at', 'l.is_active as batch_active', 's.type as disposition', 'b.quantity_on_hand', 'b.quantity_reserved');
        if (! empty($filters['product_id'])) {
            $query->where('p.public_id', $filters['product_id']);
        }
        if (! empty($filters['q'])) {
            $query->where(fn ($q) => $q->where('p.name', 'like', '%'.$filters['q'].'%')->orWhere('l.batch_code', 'like', '%'.$filters['q'].'%'));
        }
        if (! empty($filters['status'])) {
            abort_unless(in_array($filters['status'], ['saleable', 'quarantine', 'damaged', 'rejected'], true), 422, 'Lọc kho theo saleable/quarantine/damaged/rejected.');
            $query->whereRaw("COALESCE(s.type, 'saleable') = ?", [$filters['status']]);
            unset($filters['status']);
        }
        $result = $this->page($query, $filters + ['_search_handled' => true], 'b.id');
        $holds = DB::table('inv_stock_holds')->where('warehouse_id', $p->warehouse_id)->where('status', 'active')->get();
        $today = now($p->timezone)->toDateString();
        $result['records'] = array_map(function ($r) use ($holds, $today): array {
            $blocked = $holds->contains(fn ($h) => ($h->item_id === null || (int) $h->item_id === (int) $r['item_id']) && ($h->batch_id === null || (int) $h->batch_id === (int) $r['batch_id']));
            $eligible = ! $blocked && (! $r['batch_id'] || ($r['batch_active'] && (! $r['expires_at'] || substr($r['expires_at'], 0, 10) >= $today))) && ! in_array($r['disposition'], ['quarantine', 'damaged', 'rejected'], true);
            $r['available'] = $eligible ? (string) BigDecimal::max(BigDecimal::of((string) $r['quantity_on_hand'])->minus((string) $r['quantity_reserved']), 0) : '0.000000';
            $r['blocked'] = ! $eligible;

            return $r;
        }, $result['records']);

        return $result;
    }

    private function trace(Admin $actor, object $p, array $filters): array
    {
        $product = D::row('ph_products', (string) ($filters['product_id'] ?? ''), $p->chain_id);

        return app(InventoryManagedStockService::class)->withinOwner('pharmacy', $p->public_id, $actor->id, 'inventory.read', fn () => app(InventoryManagedStockService::class)->trace($p->warehouse_id, $product->inventory_item_id, isset($filters['batch_id']) ? (int) $filters['batch_id'] : null, $actor->id));
    }

    private function scan(Admin $actor, object $p, array $filters): array
    {
        validator($filters, ['code' => 'required|string|max:300'])->validate();
        $parsed = app(PharmacyBarcode::class)->parse($filters['code']);
        $gtin = $parsed['gtin'];
        // GTIN aliases may remove padding zeros only; truncating an indicator can select another product.
        $codes = array_values(array_filter(array_unique([$gtin, substr($gtin, -13), substr($gtin, -12), substr($gtin, -8)]), fn ($candidate) => str_pad($candidate, 14, '0', STR_PAD_LEFT) === $gtin));
        $records = DB::table('ph_products as p')->join('merch_barcodes as b', 'b.sku_id', '=', 'p.sku_id')
            ->leftJoin('merch_packagings as k', fn ($join) => $join->on('k.id', '=', 'b.packaging_id')->on('k.sku_id', '=', 'p.sku_id'))
            ->leftJoin('ph_prices as r', fn ($join) => $join->on('r.product_id', '=', 'p.id')->where('r.pharmacy_id', $p->id)->where('r.status', 'active')->whereRaw('r.packaging_key = COALESCE(b.packaging_id, 0)'))
            ->where('p.chain_id', $p->chain_id)->where('p.status', 'active')->whereIn('b.barcode', $codes)
            ->where(fn ($q) => $q->whereNull('b.packaging_id')->orWhere('k.is_active', true))
            ->select('p.*', 'b.packaging_id', 'k.name as packaging_name', 'k.name as packaging_unit', 'k.code as packaging_code', 'k.quantity_multiplier', 'k.revision as packaging_revision', 'r.price_minor', 'r.version as price_version')
            ->distinct()->limit(20)->get()->map(fn ($r) => D::resource($r))->all();
        $lots = [];
        if (count($records) === 1 && $parsed['batch_code'] !== null) {
            app(PharmacyAccessService::class)->pharmacy($actor, (int) $p->id, 'pharmacy.inventory.view');
            $lots = $this->inventory($p, ['product_id' => $records[0]['id'], 'per_page' => 100])['records'];
            $lots = array_values(array_filter($lots, fn ($lot) => $lot['batch_code'] === $parsed['batch_code'] && ($parsed['expires_at'] === null || substr((string) $lot['expires_at'], 0, 10) === $parsed['expires_at'])));
        }

        return $parsed + ['records' => $records, 'lots' => $lots, 'matched' => count($records) === 1, 'allocation_confirmed' => false];
    }

    private function quality(Admin $actor, object $p): array
    {
        $records = DB::table('ph_quality_cases')->where('pharmacy_id', $p->id)->orderByDesc('id')->limit(100)->get()->map(fn ($r) => D::resource($r))->all();

        return ['records' => $records, 'holds' => DB::table('inv_stock_holds')->where('warehouse_id', $p->warehouse_id)->orderByDesc('id')->limit(100)->get()->all(), 'recalls' => DB::table('ph_recall_tasks as t')->join('ph_recalls as r', 'r.id', '=', 't.recall_id')->where('t.pharmacy_id', $p->id)->select('t.*', 'r.reference', 'r.reason', 'r.batch_id', 'r.public_id as recall_public_id', 'r.version as recall_version')->orderByDesc('t.id')->limit(100)->get()->all(), 'temperatures' => DB::table('ph_temperature_readings')->where('pharmacy_id', $p->id)->orderByDesc('id')->limit(50)->get()->map(fn ($r) => D::resource($r))->all()];
    }

    private function settings(Admin $actor, object $p): array
    {
        app(PharmacyAccessService::class)->chain($actor, (int) $p->chain_id, 'pharmacy.settings');

        return ['pharmacy' => D::resource($p), 'memberships' => DB::table('ph_memberships')->where('chain_id', $p->chain_id)->get()->map(fn ($r) => D::resource($r))->all(), 'credentials' => DB::table('ph_credentials')->where('chain_id', $p->chain_id)->get()->map(fn ($r) => D::resource($r))->all(), 'capabilities' => $this->bootstrap($actor, $p->public_id)['capabilities']];
    }

    private function integrations(Admin $actor, object $p): array
    {
        $customerBooks = app(PharmacyIntegrationOptions::class)->read($actor, 'customer-books', []);
        $visibleBooks = collect($customerBooks['records'])->keyBy('id');
        $customerMappings = DB::table('ph_customer_mappings')->where('chain_id', $p->chain_id)->get();
        $bookIds = $customerMappings->pluck('book_id');
        $books = $customerBooks['available'] ? DB::table('cust_books')->whereIn('id', $bookIds)->get()->keyBy('id') : collect();

        return [
            'records' => DB::table('ph_connections')->where('chain_id', $p->chain_id)->get()->map(fn ($r) => D::resource($r))->all(),
            'attempts' => DB::table('ph_integration_attempts')->where('pharmacy_id', $p->id)->orderByDesc('id')->limit(100)->get()->map(fn ($r) => array_intersect_key(D::resource($r), array_flip(['id', 'version', 'status', 'source_type', 'source_id', 'error', 'external_id', 'response_code', 'created_at', 'updated_at'])))->all(),
            'internal_adapters' => [
                'customers' => ['available' => $customerBooks['available'], 'mappings' => $customerMappings->map(function ($r) use ($books, $visibleBooks) {
                    $book = $books[$r->book_id] ?? null;

                    return D::resource($r) + ['book_public_id' => $book && isset($visibleBooks[$book->public_id]) ? $book->public_id : null, 'book_name' => $book && isset($visibleBooks[$book->public_id]) ? $book->name : null];
                })->all(), 'clinical_sharing' => false],
                'accounting' => ['available' => app(ModuleCapabilityChecker::class)->has('accounting-tax', 'accounting.documents.manage.v1'), 'mappings' => DB::table('ph_accounting_mappings')->where('chain_id', $p->chain_id)->get()->map(fn ($r) => D::resource($r))->all(), 'draft_only' => true],
            ],
            'network_enabled' => (bool) config('pharmacy.network_enabled'), 'production_enabled' => (bool) config('pharmacy.production_enabled'),
            'unsupported' => [
                'drug_database' => 'Cần contract/tài khoản CSDL dược đã xác minh.', 'eprescription' => 'Adapter đơn thuốc độc lập; chưa có quyền truy cập đã nghiệm thu.',
                'controlled' => 'Chưa nghiệm thu gói thuốc kiểm soát đặc biệt.', 'online' => 'Chưa nghiệm thu kênh bán thuốc online.', 'cold_chain' => 'Chưa nghiệm thu điều kiện chuỗi lạnh.',
            ],
        ];
    }

    private function report(Admin $actor, object $p, array $filters): array
    {
        $v = validator($filters, ['from' => 'sometimes|date', 'to' => 'sometimes|date|after_or_equal:from'])->validate();
        $from = $v['from'] ?? now($p->timezone)->startOfDay()->utc();
        $to = $v['to'] ?? now($p->timezone)->endOfDay()->utc();
        $sales = DB::table('ph_sales')->where('pharmacy_id', $p->id)->whereBetween('created_at', [$from, $to]);

        $sites = DB::table('ph_pharmacies')->where('chain_id', $p->chain_id)->whereIn('id', app(PharmacyAccessService::class)->allowedPharmacyIds($actor, 'pharmacy.report.view'))->get();
        $totals = DB::table('ph_sales')->whereIn('pharmacy_id', $sites->pluck('id'))->whereBetween('created_at', [$from, $to])->groupBy('pharmacy_id')->selectRaw('pharmacy_id, COUNT(*) AS sale_count, SUM(total_minor) AS revenue_minor, SUM(refunded_minor) AS refunds_minor')->get()->keyBy('pharmacy_id');
        $pending = DB::table('ph_dispensings')->whereIn('pharmacy_id', $sites->pluck('id'))->where('status', 'finalized')->groupBy('pharmacy_id')->selectRaw('pharmacy_id, COUNT(*) AS pending_handovers')->get()->keyBy('pharmacy_id');
        $siteRows = $sites->map(fn ($site) => ['id' => $site->public_id, 'name' => $site->name, 'code' => $site->code, 'sale_count' => (int) ($totals[$site->id]->sale_count ?? 0), 'revenue_minor' => (string) ($totals[$site->id]->revenue_minor ?? '0'), 'refunds_minor' => (string) ($totals[$site->id]->refunds_minor ?? '0'), 'pending_handovers' => (int) ($pending[$site->id]->pending_handovers ?? 0)])->all();

        return ['sites' => $siteRows, 'period_timezone' => $p->timezone, 'from' => (string) $from, 'to' => (string) $to, 'as_of' => now()->toIso8601String(), 'pharmacy_id' => $p->public_id, 'sale_count' => (clone $sales)->count(), 'revenue_minor' => (string) (clone $sales)->sum('total_minor'), 'refunds_minor' => (string) (clone $sales)->sum('refunded_minor'), 'pending_handovers' => DB::table('ph_dispensings')->where('pharmacy_id', $p->id)->where('status', 'finalized')->count(), 'active_holds' => DB::table('inv_stock_holds')->where('warehouse_id', $p->warehouse_id)->where('status', 'active')->count(), 'pending_events' => DB::table('ph_outbox')->where('pharmacy_id', $p->id)->where('status', 'pending')->count(), 'open_shifts' => DB::table('ph_shifts')->where('pharmacy_id', $p->id)->where('status', 'open')->count()];
    }

    private function stocktakes(object $p, array $filters): array
    {
        $result = $this->page(DB::table('ph_stocktakes')->where('pharmacy_id', $p->id), $filters);
        foreach ($result['records'] as &$record) {
            $stocktake = DB::table('inv_stocktakes')->where('public_id', $record['inventory_stocktake_id'])->first();
            $record['counts'] = $stocktake ? DB::table('inv_stocktake_lines')->where('stocktake_id', $stocktake->id)->get()->map(fn ($r) => (array) $r)->all() : [];
        }

        return $result;
    }

    private function purchaseOrders(object $p, array $filters): array
    {
        if (! app(ModuleCapabilityChecker::class)->enabled('purchasing')) {
            return ['records' => [], 'available' => false, 'reason' => 'Cần cài và bật Mua hàng.'];
        }
        $result = $this->page(DB::table('ph_purchase_links as l')->join('purchase_orders as o', 'o.id', '=', 'l.order_id')->where('l.pharmacy_id', $p->id)->select('l.public_id as link_id', 'l.version as link_version', 'o.id as order_internal_id', 'o.*'), $filters, 'o.id');
        foreach ($result['records'] as &$record) {
            $record['lines'] = DB::table('purchase_order_lines')->where('order_id', $record['order_internal_id'])->get()->map(fn ($r) => D::resource($r))->all();
        }

        return $result;
    }

    private function suppliers(object $p, array $filters): array
    {
        if (! app(ModuleCapabilityChecker::class)->enabled('purchasing')) {
            return ['records' => [], 'available' => false];
        }

        $result = $this->page(DB::table('ph_supplier_links as l')->join('purchase_suppliers as s', 's.id', '=', 'l.supplier_id')->where('l.chain_id', $p->chain_id)->select('l.public_id as link_id', 'l.version as link_version', 'l.license_number', 'l.valid_until', 's.*'), $filters, 's.id');
        $suppliers = DB::table('purchase_suppliers')->whereIn('public_id', array_column($result['records'], 'public_id'))->get()->keyBy('public_id');
        foreach ($result['records'] as &$record) {
            $record['supplier_revision'] = app(PurchasingService::class)->supplierRecord($suppliers[$record['public_id']])['revision'];
        }

        return $result;
    }

    private function page(Builder $query, array $filters, string $order = 'id'): array
    {
        $from = preg_split('/\s+as\s+/i', (string) $query->from);
        $table = $from[0];
        $alias = $from[1] ?? $table;
        $columns = Schema::getColumnListing($table);
        $v = validator($filters, ['status' => 'nullable|string|max:40', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'q' => 'nullable|string|max:255'])->validate();
        if (! empty($v['status'])) {
            abort_unless(in_array('status', $columns, true), 422, 'Danh sách này không có bộ lọc trạng thái.');
            $query->where($alias.'.status', $v['status']);
        }
        foreach (['from' => '>=', 'to' => '<='] as $field => $operator) {
            if (! empty($v[$field]) && in_array('created_at', $columns, true)) {
                $query->where($alias.'.created_at', $operator, $v[$field]);
            }
        }
        $searchable = array_values(array_intersect(['name', 'code', 'reference', 'public_id', 'batch_code', 'zone'], $columns));
        if (! empty($v['q']) && empty($filters['_search_handled'])) {
            abort_unless($searchable !== [], 422, 'Danh sách này không hỗ trợ tìm kiếm văn bản.');
            $query->where(function ($q) use ($searchable, $v, $alias) {
                foreach ($searchable as $field) {
                    $q->orWhere($alias.'.'.$field, 'like', '%'.$v['q'].'%');
                }
            });
        }
        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, min(100, (int) ($filters['per_page'] ?? 30)));
        $total = (clone $query)->count();

        return ['records' => $query->orderByDesc($order)->offset(($page - 1) * $size)->limit($size)->get()->map(fn ($r) => D::resource($r))->all(), 'meta' => ['page' => $page, 'per_page' => $size, 'total' => $total, 'supports_search' => ! empty($filters['_search_handled']) || $searchable !== [], 'supports_status' => in_array('status', $columns, true)]];
    }

    private function withoutCosts(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, ['cost', 'unit_cost', 'total_cost', 'unit_cost_minor', 'cost_allocations'], true)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = $this->withoutCosts($value);
            } elseif (is_object($value)) {
                $data[$key] = $this->withoutCosts((array) $value);
            }
        }

        return $data;
    }
}
