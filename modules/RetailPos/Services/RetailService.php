<?php

namespace Modules\RetailPos\Services;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\Admin;
use App\Support\AuditLogger;
use App\Support\SiteContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Customers\Services\ConsumerCustomerService;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Merchandise\Services\MerchandiseService;
use Modules\RetailPos\Domain\RetailNumbers as N;

class RetailService
{
    private const QUOTE_TTL_SECONDS = 300;

    public function __construct(private readonly InventoryV2Service $inventory, private readonly MerchandiseService $merchandise, private readonly ModuleCapabilityChecker $capabilities) {}

    public function command(Admin $actor, string $key, string $operation, array $input, callable $mutation): array
    {
        validator(['key' => $key], ['key' => ['required', 'string', 'min:8', 'max:120', 'regex:/^[a-zA-Z0-9_.:\-]+$/']])->validate();
        $website = app(SiteContext::class)->websiteKey();
        $fingerprint = hash('sha256', json_encode([$website, $operation, $this->canonical($input)], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $key, $operation, $input, $mutation, $fingerprint): array {
            // Serialize commands for an actor, including first-insert races and revocation.
            $live = Admin::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($live->is_active && $live->status === 'active' && ! $live->locked_at && (int) $live->auth_version === (int) $actor->auth_version, 403);
            app(RetailRuntimeState::class)->guard();
            abort_unless($this->capabilities->enabled('retail-pos'), 503, 'Retail đang dừng hoặc chưa được bật.');
            $this->ready();
            $previous = DB::table('retail_commands')->where('actor_id', $actor->id)->where('command_key', $key)->lockForUpdate()->first();
            if ($previous) {
                abort_unless(hash_equals($previous->fingerprint, $fingerprint), 409, 'Idempotency-Key đã được dùng cho ý định khác.');
                $this->authorizeIntent($live, $operation, $input);

                return json_decode($previous->response, true, flags: JSON_THROW_ON_ERROR);
            }
            $result = $mutation($live);
            DB::table('retail_commands')->insert(['actor_id' => $actor->id, 'website_key' => app(SiteContext::class)->websiteKey(), 'command_key' => $key, 'operation' => $operation, 'fingerprint' => $fingerprint, 'intent' => json_encode($input, JSON_THROW_ON_ERROR), 'response' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
            app(AuditLogger::class)->record('retail.'.$operation, 'retail-command', after: ['command_key' => $key, 'resource_id' => $result['id'] ?? null], actor: $live, moduleKey: 'retail-pos');

            return $result;
        }, 3);
    }

    public function ready(): void
    {
        foreach (['inventory.availability.read.v2', 'inventory.reservations.manage.v2', 'inventory.movements.post.v2'] as $cap) {
            abort_unless($this->capabilities->has('inventory', $cap), 503, 'Inventory cần nâng cấp và bật đúng contract v2.');
        }
        abort_unless($this->capabilities->enabled('merchandise'), 503, 'Merchandise chưa sẵn sàng.');
    }

    public function bootstrap(Admin $actor): array
    {
        $this->permission($actor, 'retail.view');
        $chains = DB::table('retail_chains')->where('website_key', app(SiteContext::class)->websiteKey())
            ->when(! $actor->isSuperAdmin(), fn ($q) => $q->whereIn('id', DB::table('retail_memberships')->where('admin_id', $actor->id)->where('is_active', true)->select('chain_id')))->get();
        $branches = DB::table('retail_branches')->whereIn('chain_id', $chains->pluck('id'))->where('status', 'active')->get()->filter(fn ($b) => $this->member($actor, $b));
        $terminals = DB::table('retail_terminals')->whereIn('branch_id', $branches->pluck('id'))->where('status', 'active')->get();

        return ['chains' => $chains->map(fn ($r) => $this->present($r))->all(),
            'branches' => $branches->map(fn ($r) => $this->branchPayload($r, $terminals))->values()->all(),
            'legal_entities' => DB::table('retail_legal_entities')->whereIn('id', $branches->pluck('legal_entity_id'))->get()->map(fn ($r) => $this->present($r))->all(),
            'catalogs' => DB::table('merch_catalogs')->whereIn('id', $chains->pluck('catalog_id'))->get()->map(fn ($r) => $this->present($r))->all(),
            'permissions' => $actor->permissions(), 'capabilities' => ['online' => true, 'offline' => false, 'purchasing' => $this->capabilities->enabled('purchasing'), 'electronic_payment_provider' => false, 'accounting' => $this->capabilities->enabled('accounting-tax')]];
    }

    public function setup(Admin $actor, array $data): array
    {
        abort_unless($actor->canAccess('retail.setup.manage', 'global'), 403);
        $data = validator($data, ['name' => ['required', 'string', 'max:150'], 'branch_name' => ['nullable', 'string', 'max:150'], 'legal_name' => ['nullable', 'string', 'max:200']])->validate();
        $catalog = $this->merchandise->createCatalog(['name' => $data['name'], 'code' => 'RET-'.Str::upper(Str::random(8))], (int) $actor->id);
        $legal = $this->insert('retail_legal_entities', ['name' => $data['legal_name'] ?? $data['name']]);
        $catalogId = DB::table('merch_catalogs')->where('public_id', $catalog['public_id'])->value('id');
        $chain = $this->insert('retail_chains', ['name' => $data['name'], 'catalog_id' => $catalogId, 'website_key' => app(SiteContext::class)->websiteKey()]);
        DB::table('retail_memberships')->insert(['admin_id' => $actor->id, 'chain_id' => $chain->id, 'branch_id' => 0, 'preset' => 'hq', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return $this->createBranch($actor, ['chain_id' => $chain->public_id, 'legal_entity_id' => $legal->public_id, 'name' => $data['branch_name'] ?? 'Cửa hàng chính', 'code' => 'MAIN']);
    }

    public function createBranch(Admin $actor, array $data): array
    {
        $this->permission($actor, 'retail.branch.manage');
        $data = validator($data, ['chain_id' => ['required', 'uuid'], 'legal_entity_id' => ['required', 'uuid'], 'name' => ['required', 'string', 'max:150'], 'code' => ['required', 'regex:/^[A-Za-z0-9_-]{1,40}$/'], 'timezone' => ['nullable', 'timezone']])->validate();
        $chain = $this->find('retail_chains', $data['chain_id']);
        $this->hq($actor, $chain);
        $legal = $this->find('retail_legal_entities', $data['legal_entity_id']);
        abort_unless($actor->isSuperAdmin() || DB::table('retail_branches')->where('chain_id', $chain->id)->where('legal_entity_id', $legal->id)->exists(), 403);
        $branch = $this->insert('retail_branches', ['chain_id' => $chain->id, 'legal_entity_id' => $legal->id, 'name' => $data['name'], 'code' => $data['code'], 'timezone' => $data['timezone'] ?? 'Asia/Ho_Chi_Minh']);
        $warehouse = $this->inventory->createWarehouse(['name' => $branch->name, 'code' => 'RET-'.$branch->id.'-'.Str::upper(Str::random(5)), 'catalog_id' => (int) $chain->catalog_id, 'branch_id' => $branch->id, 'legal_entity_ref' => $legal->public_id]);
        DB::table('retail_branches')->where('id', $branch->id)->update(['warehouse_id' => $warehouse['database_id'] ?? $warehouse['id']]);
        $terminal = $this->insert('retail_terminals', ['branch_id' => $branch->id, 'code' => 'POS-01', 'name' => 'Quầy 1']);

        return $this->branchPayload($this->find('retail_branches', $branch->public_id));
    }

    public function branch(Admin $actor, string $publicId, string $permission = 'retail.view', bool $lock = false): object
    {
        $this->permission($actor, $permission);
        $branch = $this->find('retail_branches', $publicId, $lock);
        $chain = DB::table('retail_chains')->where('id', $branch->chain_id)->first();
        abort_unless($chain && $chain->website_key === app(SiteContext::class)->websiteKey() && $branch->status === 'active' && $this->member($actor, $branch), 404);

        return $branch;
    }

    public function products(Admin $actor, string $branchId, string $query = '', bool $includeCatalog = false): array
    {
        $b = $this->branch($actor, $branchId, 'retail.product.view');
        $catalog = DB::table('retail_chains')->where('id', $b->chain_id)->value('catalog_id');
        if ($includeCatalog) {
            $this->permission($actor, 'retail.product.manage');
        }
        $skus = DB::table('merch_skus as s')->leftJoin('retail_assortments as a', fn ($join) => $join->on('a.sku_id', '=', 's.id')->where('a.branch_id', $b->id))->where('s.catalog_id', $catalog)->where('s.is_active', true)
            ->when(! $includeCatalog, fn ($q) => $q->where('a.is_active', true))
            ->when($query !== '', fn ($q) => $q->where(fn ($x) => $x->where('s.name', 'like', '%'.$query.'%')->orWhere('s.sku', 'like', '%'.$query.'%')->orWhereIn('s.id', DB::table('merch_barcodes')->where('barcode', $query)->select('sku_id'))))
            ->orderBy('s.name')->limit(100)->get(['s.*', 'a.price_minor', 'a.version as price_version']);

        return $skus->map(function ($sku) use ($b, $catalog, $query): array {
            $item = $this->inventory->ensureItem((int) $sku->id);
            $barcode = DB::table('merch_barcodes')->where('catalog_id', $catalog)->where('sku_id', $sku->id)->where('barcode', $query)->exists() ? $this->merchandise->resolveBarcode((int) $catalog, $query) : null;

            return ['id' => $sku->public_id, 'database_id' => $sku->id, 'public_id' => $sku->public_id, 'sku' => $sku->sku, 'name' => $sku->name, 'base_unit' => $sku->base_unit, 'price_minor' => (string) ($sku->price_minor ?? '0'), 'price_version' => $sku->price_version, 'is_listed' => $sku->price_version !== null, 'quantity_multiplier' => $barcode['quantity_multiplier'] ?? '1.000000', 'packaging_id' => $barcode['packaging_id'] ?? null, 'barcodes' => DB::table('merch_barcodes')->where('sku_id', $sku->id)->get(['barcode'])->all(), 'available' => $this->inventory->availability((int) $b->warehouse_id, $item), 'inventory_item_id' => $item];
        })->all();
    }

    public function saveProduct(Admin $actor, array $data): array
    {
        $b = $this->branch($actor, (string) ($data['branch_id'] ?? ''), 'retail.product.manage', true);
        $chain = DB::table('retail_chains')->where('id', $b->chain_id)->first();
        $this->hq($actor, $chain);
        $price = N::money($data['price_minor'] ?? null);
        if (! empty($data['barcode'])) {
            $data['barcodes'][] = ['barcode' => (string) $data['barcode']];
        }
        $sku = $this->merchandise->saveSku((int) $chain->catalog_id, $data);
        $skuId = DB::table('merch_skus')->where('public_id', $sku['public_id'] ?? $sku['id'])->value('id') ?? $sku['id'];
        DB::table('retail_assortments')->updateOrInsert(['branch_id' => $b->id, 'sku_id' => $skuId], ['price_minor' => $price, 'is_active' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->inventory->ensureItem((int) $skuId);
        if (isset($data['opening_quantity']) && BigDecimal::of((string) $data['opening_quantity'])->isGreaterThan(0)) {
            $this->permission($actor, 'inventory.receipt.manage');
            $this->receipt($actor, array_merge($data, ['sku_id' => $sku['public_id'] ?? $sku['id'], 'quantity' => $data['opening_quantity']]), 'opening:'.$b->public_id.':'.$skuId);
        }

        return array_replace($sku, ['id' => $sku['public_id'], 'price_minor' => (string) $price]);
    }

    public function receipt(Admin $actor, array $data, string $key): array
    {
        $b = $this->branch($actor, (string) ($data['branch_id'] ?? ''), 'retail.inventory.view', true);
        $this->permission($actor, 'inventory.receipt.manage');
        $sku = $this->skuForBranch($b, (string) ($data['sku_id'] ?? ''));

        return $this->inventory->receive(['warehouse_id' => (int) $b->warehouse_id, 'item_id' => $this->inventory->ensureItem((int) $sku->id), 'quantity' => N::quantity($data['quantity'] ?? null), 'unit_cost' => (string) ($data['unit_cost'] ?? '0'), 'source_id' => 'retail:'.$key, 'legal_entity_ref' => DB::table('retail_legal_entities')->where('id', $b->legal_entity_id)->value('public_id'), 'batch_code' => $data['batch_code'] ?? null, 'expires_at' => $data['expires_at'] ?? null], $key, (int) $actor->id);
    }

    public function assortment(Admin $actor, array $data): array
    {
        $b = $this->branch($actor, (string) ($data['branch_id'] ?? ''), 'retail.product.manage', true);
        $this->permission($actor, 'retail.price.manage');
        $this->hq($actor, DB::table('retail_chains')->where('id', $b->chain_id)->first());
        $sku = $this->skuForBranch($b, (string) ($data['sku_id'] ?? ''));
        $price = N::money($data['price_minor'] ?? null);
        $old = DB::table('retail_assortments')->where('branch_id', $b->id)->where('sku_id', $sku->id)->lockForUpdate()->first();
        if ($old) {
            abort_unless(isset($data['expected_version']) && (int) $data['expected_version'] === (int) $old->version, 409, 'Bảng giá đã đổi.');
        }
        DB::table('retail_assortments')->updateOrInsert(['branch_id' => $b->id, 'sku_id' => $sku->id], ['price_minor' => $price, 'is_active' => true, 'version' => $old ? $old->version + 1 : 1, 'created_at' => $old?->created_at ?? now(), 'updated_at' => now()]);

        return ['id' => $sku->public_id, 'branch_id' => $b->public_id, 'price_minor' => (string) $price, 'version' => $old ? $old->version + 1 : 1];
    }

    public function createTerminal(Admin $actor, array $data): array
    {
        $b = $this->branch($actor, (string) ($data['branch_id'] ?? ''), 'retail.branch.manage', true);
        $this->hq($actor, DB::table('retail_chains')->where('id', $b->chain_id)->first());
        $values = validator($data, ['code' => ['required', 'string', 'max:40'], 'name' => ['required', 'string', 'max:100']])->validate();

        return $this->present($this->insert('retail_terminals', $values + ['branch_id' => $b->id]));
    }

    public function openShift(Admin $actor, array $data): array
    {
        $b = $this->branch($actor, (string) ($data['branch_id'] ?? ''), 'retail.shift.manage', true);
        $t = $this->find('retail_terminals', (string) ($data['terminal_id'] ?? ''), true);
        abort_unless($t->branch_id === $b->id && $t->status === 'active', 404);
        abort_if(DB::table('retail_shifts')->where('terminal_id', $t->id)->where('status', 'open')->exists(), 409, 'Quầy đang có ca mở.');
        $amount = N::money($data['opening_minor'] ?? '0');

        return $this->shiftPayload($this->insert('retail_shifts', ['branch_id' => $b->id, 'terminal_id' => $t->id, 'admin_id' => $actor->id, 'business_date' => now($b->timezone)->toDateString(), 'opening_minor' => $amount, 'expected_minor' => $amount]));
    }

    public function closeShift(Admin $actor, string $id, array $data, bool $reconcile = false): array
    {
        $shift = $this->find('retail_shifts', $id, true);
        $b = DB::table('retail_branches')->where('id', $shift->branch_id)->first();
        $this->branch($actor, $b->public_id, 'retail.shift.manage', true);
        $this->version($shift, $data);
        if ($reconcile) {
            $this->hq($actor, DB::table('retail_chains')->where('id', $b->chain_id)->first());
            abort_unless($shift->status === 'closed', 409, 'Ca chưa đóng.');
            DB::table('retail_shifts')->where('id', $shift->id)->update(['status' => 'reconciled', 'reconciled_at' => now(), 'version' => $shift->version + 1, 'updated_at' => now()]);
        } else {
            abort_unless($shift->status === 'open' && ((int) $shift->admin_id === $actor->id || $actor->isSuperAdmin()), 403);
            abort_if(DB::table('retail_sales')->where('shift_id', $shift->id)->whereNotIn('status', ['completed', 'cancelled'])->exists(), 409, 'Còn đơn chưa kết thúc.');
            abort_if(DB::table('retail_returns as r')->join('retail_sales as s', 's.id', '=', 'r.sale_id')->where('s.shift_id', $shift->id)->where('r.refund_status', 'pending')->exists(), 409, 'Còn tiền hoàn chưa xử lý.');
            $count = N::money($data['counted_minor'] ?? null);
            DB::table('retail_shifts')->where('id', $shift->id)->update(['status' => 'closed', 'counted_minor' => $count, 'variance_minor' => $count - $shift->expected_minor, 'closed_at' => now(), 'version' => $shift->version + 1, 'updated_at' => now()]);
        }

        return $this->shiftPayload($this->find('retail_shifts', $id));
    }

    public function createSale(Admin $actor, array $data): array
    {
        $b = $this->branch($actor, (string) ($data['branch_id'] ?? ''), 'retail.sale.create', true);
        $shift = $this->find('retail_shifts', (string) ($data['shift_id'] ?? ''), true);
        $terminal = $this->find('retail_terminals', (string) ($data['terminal_id'] ?? ''));
        abort_unless($shift->branch_id === $b->id && $shift->terminal_id === $terminal->id && $terminal->status === 'active' && $shift->status === 'open' && ((int) $shift->admin_id === $actor->id || $actor->isSuperAdmin()), 403);
        $rows = validator($data, ['lines' => ['required', 'array', 'min:1', 'max:300'], 'lines.*.sku_id' => ['required', 'uuid'], 'lines.*.quantity' => ['required'], 'lines.*.packaging_id' => ['nullable', 'integer', 'min:1']])->validate()['lines'];
        $lines = [];
        $total = 0;
        foreach ($rows as $row) {
            $sku = $this->skuForBranch($b, $row['sku_id']);
            $qty = N::quantity($row['quantity']);
            if (isset($row['packaging_id'])) {
                $qty = $this->merchandise->convertQuantity((int) $sku->id, $qty, (int) $row['packaging_id']);
            }
            if ((int) $sku->quantity_scale < 6 && ! BigDecimal::of($qty)->isEqualTo(BigDecimal::of($qty)->toScale((int) $sku->quantity_scale, RoundingMode::Down))) {
                N::invalid('quantity');
            }
            $assort = DB::table('retail_assortments')->where('branch_id', $b->id)->where('sku_id', $sku->id)->where('is_active', true)->lockForUpdate()->first();
            abort_unless($assort, 422, 'Hàng chưa được bán tại chi nhánh.');
            $amount = N::amount($qty, (int) $assort->price_minor);
            $total += $amount;
            N::money((string) $total);
            $lines[] = ['sku_id' => $sku->id, 'inventory_item_id' => $this->inventory->ensureItem((int) $sku->id), 'sku' => $sku->sku, 'name' => $sku->name, 'unit' => $sku->base_unit, 'quantity' => $qty, 'price_minor' => $assort->price_minor, 'price_version' => $assort->version, 'gross_minor' => $amount, 'total_minor' => $amount];
        }
        $customer = null;
        $sharedCustomer = null;
        $snapshot = null;
        $customerContext = $this->customerContext($actor, $b->public_id, true);
        if ($customerContext['mode'] === 'shared') {
            $selected = $data['shared_customer_id'] ?? $data['customer_id'] ?? null;
            if ($selected) {
                abort_unless($customerContext['ready'], 503, 'Customers dùng chung chưa sẵn sàng; không chuyển về dữ liệu cũ.');
                app(RetailCustomersProvider::class)->authorize($actor, app(SiteContext::class)->websiteKey(), $b->public_id, 'attach');
                $profile = app(ConsumerCustomerService::class)->snapshot($actor, 'retail-pos', $b->public_id, (string) $selected);
                $sharedCustomer = $profile['public_id'] ?? $profile['id'];
                $snapshot = $this->buyerSnapshot($profile, 'customers');
            }
        } else {
            abort_if(! empty($data['shared_customer_id']), 422, 'Chi nhánh chưa chuyển sang Customers dùng chung.');
            if (! empty($data['customer_id'])) {
                app(RetailCustomersProvider::class)->authorize($actor, app(SiteContext::class)->websiteKey(), $b->public_id, 'attach');
                $customer = $this->find('retail_customers', $data['customer_id']);
                abort_unless($customer->chain_id === $b->chain_id && $customer->status === 'active', 404);
                $snapshot = $this->buyerSnapshot((array) $customer, 'retail-pos');
            }
        }
        $sale = $this->insert('retail_sales', ['branch_id' => $b->id, 'legal_entity_id' => $b->legal_entity_id, 'terminal_id' => $terminal->id, 'shift_id' => $shift->id, 'customer_id' => $customer?->id, 'shared_customer_id' => $sharedCustomer, 'buyer_snapshot' => $snapshot ? json_encode($snapshot, JSON_THROW_ON_ERROR) : null, 'actor_id' => $actor->id, 'subtotal_minor' => $total, 'total_minor' => $total]);
        foreach ($lines as $line) {
            $this->insert('retail_sale_lines', $line + ['sale_id' => $sale->id]);
        }

        return $this->salePayload($sale);
    }

    public function completeSale(Admin $actor, string $id, array $data, string $key): array
    {
        $sale = $this->find('retail_sales', $id, true);
        $b = DB::table('retail_branches')->where('id', $sale->branch_id)->first();
        $this->branch($actor, $b->public_id, 'retail.payment.collect', true);
        $this->version($sale, $data);
        abort_unless($sale->status === 'priced', 409, 'Đơn đã kết thúc hoặc đang chờ đối soát.');
        $shift = DB::table('retail_shifts')->where('id', $sale->shift_id)->lockForUpdate()->first();
        abort_unless($shift->status === 'open' && ((int) $shift->admin_id === $actor->id || $actor->isSuperAdmin()), 403);
        abort_if(now()->greaterThanOrEqualTo($this->quoteExpiresAt($sale)), 409, 'Báo giá đã hết hạn; hủy giỏ hiện tại và tạo lại để xác nhận giá mới.');
        $lines = DB::table('retail_sale_lines')->where('sale_id', $sale->id)->orderBy('inventory_item_id')->orderBy('id')->lockForUpdate()->get();
        $catalog = DB::table('retail_chains')->where('id', $b->chain_id)->value('catalog_id');
        foreach ($lines as $line) {
            // Lock every quoted identity before the first stock or cash mutation.
            $sku = DB::table('merch_skus')->where('id', $line->sku_id)->lockForUpdate()->first();
            $assortment = DB::table('retail_assortments')->where('branch_id', $b->id)->where('sku_id', $line->sku_id)->lockForUpdate()->first();
            abort_unless($sku && $sku->is_active && (int) $sku->catalog_id === (int) $catalog && $assortment && $assortment->is_active
                && (int) $assortment->version === (int) $line->price_version && (string) $assortment->price_minor === (string) $line->price_minor,
                409, 'Bảng giá hoặc danh mục bán đã đổi; hủy giỏ hiện tại và tạo lại để xác nhận giá mới.');
        }
        if ($sale->shared_customer_id) {
            $context = $this->customerContext($actor, $b->public_id, true);
            abort_unless($context['mode'] === 'shared' && $context['ready'], 503, 'Customers dùng chung chưa sẵn sàng.');
            app(RetailCustomersProvider::class)->authorize($actor, app(SiteContext::class)->websiteKey(), $b->public_id, 'attach');
            app(ConsumerCustomerService::class)->assertAccess($actor, 'retail-pos', $b->public_id, 'attach');
            $profile = app(ConsumerCustomerService::class)->profile($actor, 'retail-pos', $b->public_id, $sale->shared_customer_id);
            abort_unless(($profile['status'] ?? null) === 'active', 422, 'Khách hàng đã lưu trữ.');
        }
        $discount = N::money($data['discount_minor'] ?? '0');
        abort_if($discount > $sale->subtotal_minor, 422, 'Giảm giá vượt tổng đơn.');
        if ($discount > 0) {
            $this->permission($actor, 'retail.price.manage');
            validator($data, ['reason' => ['required', 'string', 'min:3', 'max:500']])->validate();
        }
        $total = (int) $sale->subtotal_minor - $discount;
        $payments = validator($data, ['tenders' => ['present', 'array', 'max:5'], 'tenders.*.method' => ['required', 'in:cash,manual_transfer'], 'tenders.*.amount_minor' => ['required'], 'tenders.*.tendered_minor' => ['nullable'], 'tenders.*.reference' => ['nullable', 'string', 'max:120']])->validate()['tenders'];
        $sum = 0;
        $cash = 0;
        $methods = [];
        foreach ($payments as $payment) {
            abort_if(in_array($payment['method'], $methods, true), 422, 'Phương thức bị lặp.');
            $methods[] = $payment['method'];
            $amount = N::money($payment['amount_minor']);
            abort_if($amount <= 0, 422, 'Số tiền phải dương.');
            $sum += $amount;
            if ($payment['method'] === 'manual_transfer') {
                $this->permission($actor, 'retail.settings.manage');
                validator($payment, ['reference' => ['required', 'string', 'min:3', 'max:120']])->validate();
            }
            if ($payment['method'] === 'cash') {
                $cash += $amount;
                abort_if(N::money($payment['tendered_minor'] ?? $amount) < $amount, 422, 'Tiền khách đưa chưa đủ.');
            }
        }
        abort_unless($sum === $total, 422, 'Tổng thanh toán phải bằng số tiền cần thu.');
        $parts = [];
        $allocated = 0;
        foreach ($lines as $line) {
            $parts[$line->id] = $sale->subtotal_minor > 0 ? BigDecimal::of((string) $discount)->multipliedBy((string) $line->gross_minor)->dividedBy((string) $sale->subtotal_minor, 0, RoundingMode::Down)->toInt() : 0;
            $allocated += $parts[$line->id];
        }
        foreach ($lines as $line) {
            if ($allocated === $discount) {
                break;
            } if ($parts[$line->id] < $line->gross_minor) {
                $parts[$line->id]++;
                $allocated++;
            }
        }
        foreach ($lines as $index => $line) {
            $part = $parts[$line->id];
            $reservation = $this->inventory->reserve((int) $b->warehouse_id, (int) $line->inventory_item_id, (string) $line->quantity, 'retail-sale:'.$line->public_id, 'reserve:'.$line->public_id, (int) $actor->id);
            $posted = $this->inventory->commitReservation((string) $reservation['public_id'], 'commit:'.$line->public_id, (int) $actor->id);
            DB::table('retail_sale_lines')->where('id', $line->id)->update(['discount_minor' => $part, 'total_minor' => $line->gross_minor - $part, 'cost' => $posted['total_cost'] ?? null, 'stock_allocations' => json_encode($posted['allocations'] ?? [], JSON_THROW_ON_ERROR), 'updated_at' => now()]);
        }
        foreach ($payments as $p) {
            $amount = N::money($p['amount_minor']);
            $tendered = $p['method'] === 'cash' ? N::money($p['tendered_minor'] ?? $amount) : $amount;
            $this->insert('retail_tenders', ['sale_id' => $sale->id, 'method' => $p['method'], 'status' => 'confirmed', 'amount_minor' => $amount, 'tendered_minor' => $tendered, 'change_minor' => $tendered - $amount, 'reference' => $p['reference'] ?? null]);
        }
        if ($cash > 0) {
            $this->cash($shift, 'sale', $cash, 'sale:'.$sale->public_id, $actor);
        }
        DB::table('retail_sales')->where('id', $sale->id)->update(['status' => 'completed', 'discount_minor' => $discount, 'total_minor' => $total, 'version' => $sale->version + 1, 'completed_at' => now(), 'updated_at' => now()]);
        $this->event('sale.completed', $sale->public_id, (int) $b->id, ['sale_id' => $sale->public_id, 'legal_entity_id' => $b->legal_entity_id, 'total_minor' => (string) $total]);

        return $this->salePayload($this->find('retail_sales', $id));
    }

    public function returnSale(Admin $actor, string $id, array $data, string $key): array
    {
        $sale = $this->find('retail_sales', $id, true);
        $b = DB::table('retail_branches')->where('id', $sale->branch_id)->first();
        $this->branch($actor, $b->public_id, 'retail.return.manage', true);
        $this->version($sale, $data);
        abort_unless($sale->status === 'completed', 409, 'Chỉ trả đơn đã hoàn tất.');
        $valid = validator($data, ['reason' => ['required', 'string', 'min:3', 'max:500'], 'lines' => ['required', 'array', 'min:1', 'max:300'], 'lines.*.sale_line_id' => ['required', 'uuid', 'distinct'], 'lines.*.quantity' => ['required'], 'lines.*.disposition' => ['required', 'in:saleable,quarantine,damaged']])->validate();
        $return = $this->insert('retail_returns', ['sale_id' => $sale->id, 'branch_id' => $b->id, 'actor_id' => $actor->id, 'amount_minor' => 0, 'reason' => $valid['reason']]);
        $amount = 0;
        foreach ($valid['lines'] as $input) {
            $line = $this->find('retail_sale_lines', $input['sale_line_id'], true);
            abort_unless($line->sale_id === $sale->id, 404);
            $qty = N::quantity($input['quantity']);
            $newQty = BigDecimal::of((string) $line->returned_quantity)->plus($qty);
            abort_if($newQty->isGreaterThan((string) $line->quantity), 409, 'Số lượng trả vượt phần còn lại.');
            $cumulative = $newQty->isEqualTo((string) $line->quantity) ? (int) $line->total_minor : $newQty->multipliedBy((string) $line->total_minor)->dividedBy((string) $line->quantity, 0, RoundingMode::Down)->toInt();
            $refund = $cumulative - (int) $line->refunded_minor;
            $amount += $refund;
            $this->insert('retail_return_lines', ['return_id' => $return->id, 'sale_line_id' => $line->id, 'quantity' => $qty, 'amount_minor' => $refund, 'disposition' => $input['disposition']], false);
            $allocations = json_decode($line->stock_allocations ?? '[]', true, flags: JSON_THROW_ON_ERROR);
            if ($allocations === []) {
                $allocations = [['quantity' => (string) $line->quantity, 'batch_id' => null, 'unit_cost' => $line->cost !== null ? (string) BigDecimal::of((string) $line->cost)->dividedBy((string) $line->quantity, 6, RoundingMode::HalfUp) : '0']];
            }
            $skip = BigDecimal::of((string) $line->returned_quantity);
            $remaining = BigDecimal::of($qty);
            foreach ($allocations as $index => $allocation) {
                $available = BigDecimal::of((string) $allocation['quantity']);
                if ($skip->isGreaterThanOrEqualTo($available)) {
                    $skip = $skip->minus($available);

                    continue;
                }
                $available = $available->minus($skip);
                $skip = BigDecimal::zero();
                $take = $remaining->isLessThan($available) ? $remaining : $available;
                if ($take->isZero()) {
                    break;
                }
                $this->inventory->receive(['warehouse_id' => (int) $b->warehouse_id, 'item_id' => (int) $line->inventory_item_id, 'quantity' => (string) $take, 'unit_cost' => (string) $allocation['unit_cost'], 'batch_id' => $allocation['batch_id'] ?? null, 'disposition' => $input['disposition'], 'source_id' => 'retail-return:'.$return->public_id.':'.$line->public_id.':'.$index], $key.':'.$line->id.':'.$index, (int) $actor->id);
                $remaining = $remaining->minus($take);
            }
            abort_unless($remaining->isZero(), 409, 'Không đủ lineage lô hàng để hoàn trả.');
            DB::table('retail_sale_lines')->where('id', $line->id)->update(['returned_quantity' => (string) $newQty, 'refunded_minor' => $cumulative, 'updated_at' => now()]);
        }
        DB::table('retail_returns')->where('id', $return->id)->update(['amount_minor' => $amount]);
        DB::table('retail_sales')->where('id', $sale->id)->update(['refunded_minor' => $sale->refunded_minor + $amount, 'version' => $sale->version + 1, 'updated_at' => now()]);
        $this->event('return.posted', $return->public_id, (int) $b->id, ['return_id' => $return->public_id, 'sale_id' => $sale->public_id, 'amount_minor' => (string) $amount]);

        return $this->present($this->find('retail_returns', $return->public_id));
    }

    public function refund(Admin $actor, string $id, array $data): array
    {
        $r = $this->find('retail_returns', $id, true);
        $b = DB::table('retail_branches')->where('id', $r->branch_id)->first();
        $this->branch($actor, $b->public_id, 'retail.return.manage', true);
        $this->permission($actor, 'retail.payment.collect');
        abort_unless($r->refund_status === 'pending', 409, 'Khoản hoàn đã xử lý.');
        $shift = $this->find('retail_shifts', (string) ($data['shift_id'] ?? ''), true);
        abort_unless($shift->branch_id === $b->id && $shift->status === 'open' && ((int) $shift->admin_id === $actor->id || $actor->isSuperAdmin()), 403);
        $method = $data['method'] ?? 'cash';
        abort_unless(in_array($method, ['cash', 'manual_transfer'], true), 422);
        if ($method === 'cash') {
            $this->cash($shift, 'refund', -(int) $r->amount_minor, 'refund:'.$r->public_id, $actor);
        } else {
            $this->permission($actor, 'retail.settings.manage');
            validator($data, ['reference' => ['required', 'string', 'min:3', 'max:120']])->validate();
        }
        DB::table('retail_returns')->where('id', $r->id)->update(['refund_status' => 'confirmed', 'status' => 'completed', 'updated_at' => now()]);
        $this->event('refund.confirmed', $r->public_id, (int) $b->id, ['method' => $method, 'amount_minor' => (string) $r->amount_minor, 'shift_id' => $shift->public_id, 'reference' => $data['reference'] ?? null]);

        return $this->present($this->find('retail_returns', $id));
    }

    public function saveCustomer(Admin $actor, array $data, ?string $id = null, ?string $key = null): array
    {
        $b = $this->branch($actor, (string) ($data['branch_id'] ?? ''), 'retail.customer.manage', true);
        $context = $this->customerContext($actor, $branchId = $b->public_id, true);
        if ($context['mode'] === 'shared') {
            abort_unless($context['ready'], 503, 'Customers dùng chung chưa sẵn sàng; không ghi dữ liệu cũ.');
            abort_unless($key, 422, 'Cần Idempotency-Key.');
            $customers = app(ConsumerCustomerService::class);
            $customerKey = 'retail-customer:'.hash('sha256', $key);

            return $id ? $customers->update($actor, 'retail-pos', $branchId, $id, $data, $customerKey) : $customers->create($actor, 'retail-pos', $branchId, $data, $customerKey);
        }
        $valid = validator($data, ['name' => ['required', 'string', 'max:150'], 'phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:150'], 'status' => ['nullable', 'in:active,archived']])->validate();
        if ($id) {
            $r = $this->find('retail_customers', $id, true);
            abort_unless($r->chain_id === $b->chain_id, 404);
            $this->version($r, $data);
            DB::table('retail_customers')->where('id', $r->id)->update($valid + ['version' => $r->version + 1, 'updated_at' => now()]);
        } else {
            $r = $this->insert('retail_customers', $valid + ['chain_id' => $b->chain_id]);
        }

        return $this->present($this->find('retail_customers', $r->public_id));
    }

    public function read(Admin $actor, string $kind, string $branchId, array $filters = []): array
    {
        $permission = match ($kind) {
            'sales' => 'retail.sale.view','shifts' => 'retail.shift.manage','returns' => 'retail.return.manage','customers' => 'retail.customer.view','reports','dashboard' => 'retail.report.view','inventory' => 'retail.inventory.view',default => 'retail.view'
        };
        $b = $this->branch($actor, $branchId, $permission);
        if ($kind === 'customers' && ($context = $this->customerContext($actor, $branchId))['mode'] === 'shared') {
            abort_unless($context['ready'], 503, 'Customers dùng chung chưa sẵn sàng; không đọc dữ liệu cũ.');

            return app(ConsumerCustomerService::class)->lookup($actor, 'retail-pos', $branchId, (string) ($filters['query'] ?? ''), 100);
        }
        if ($kind === 'inventory') {
            return $this->products($actor, $branchId, (string) ($filters['query'] ?? ''));
        }
        if ($kind === 'reports' || $kind === 'dashboard') {
            $sales = DB::table('retail_sales')->where('branch_id', $b->id)->where('status', 'completed');
            $from = $filters['from'] ?? now($b->timezone)->startOfDay()->utc()->toDateTimeString();
            $to = $filters['to'] ?? now()->toDateTimeString();
            validator(['from' => $from, 'to' => $to], ['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']])->validate();
            $sales->whereBetween('completed_at', [$from, $to]);
            $ids = (clone $sales)->pluck('id');

            return ['branch_id' => $b->public_id, 'sale_count' => $sales->count(), 'sales_count' => $sales->count(), 'gross_minor' => (string) (clone $sales)->sum('subtotal_minor'), 'discount_minor' => (string) (clone $sales)->sum('discount_minor'), 'revenue_minor' => (string) (clone $sales)->sum('total_minor'), 'net_sales_minor' => (string) ((int) (clone $sales)->sum('total_minor') - (int) DB::table('retail_returns')->where('branch_id', $b->id)->whereBetween('created_at', [$from, $to])->sum('amount_minor')), 'refunds_minor' => (string) DB::table('retail_returns')->where('branch_id', $b->id)->whereBetween('created_at', [$from, $to])->sum('amount_minor'), 'pending_refunds' => DB::table('retail_returns')->where('branch_id', $b->id)->where('refund_status', 'pending')->count(), 'open_shifts' => DB::table('retail_shifts')->where('branch_id', $b->id)->where('status', 'open')->count(), 'as_of' => now()->toIso8601String(), 'offline' => false, 'cost_status' => 'unavailable'];
        }
        $table = match ($kind) {
            'sales' => 'retail_sales','shifts' => 'retail_shifts','returns' => 'retail_returns','customers' => 'retail_customers','terminals' => 'retail_terminals',default => abort(404)
        };
        $q = DB::table($table)->where($kind === 'customers' ? 'chain_id' : 'branch_id', $kind === 'customers' ? $b->chain_id : $b->id)->orderByDesc('id')->limit(100);

        if ($kind === 'customers' && trim((string) ($filters['query'] ?? '')) !== '') {
            $query = mb_substr(trim((string) $filters['query']), 0, 150);
            $q->where(fn ($filter) => $filter->where('name', 'like', '%'.$query.'%')->orWhere('phone', 'like', '%'.$query.'%')->orWhere('email', 'like', '%'.$query.'%'));
        }

        return $q->get()->map(fn ($r) => match ($kind) {
            'sales' => $this->salePayload($r),'shifts' => $this->shiftPayload($r),default => $this->present($r)
        })->all();
    }

    public function customerContext(Admin $actor, string $branchId, bool $lock = false): array
    {
        // Customer-less sales keep working without granting customer-directory access.
        $this->branch($actor, $branchId, $actor->hasPermission('retail.view') ? 'retail.view' : 'retail.sale.create');
        if (! class_exists(ConsumerCustomerService::class)) {
            return ['mode' => 'legacy', 'available' => false, 'ready' => true, 'book_id' => null];
        }

        $context = app(ConsumerCustomerService::class)->status($actor, 'retail-pos', $branchId, $lock);
        if ($context['mode'] === 'legacy') {
            $context['ready'] = true;
            $context['reason'] = null;
        }

        return $context;
    }

    public function customerHistory(Admin $actor, string $branchId, string $customerId): array
    {
        $branch = $this->branch($actor, $branchId, 'retail.customer.view');
        $this->permission($actor, 'retail.sale.view');
        $context = $this->customerContext($actor, $branchId);
        $legacyIds = [$customerId];
        $sharedIds = [];
        if ($context['mode'] === 'shared') {
            abort_unless($context['ready'], 503, 'Customers dùng chung chưa sẵn sàng.');
            $customers = app(ConsumerCustomerService::class);
            $profile = $customers->profile($actor, 'retail-pos', $branchId, $customerId);
            $sharedIds = $customers->profileIds($actor, 'retail-pos', $branchId, $customerId);
            $legacyIds = $customers->legacyIds($actor, 'retail-pos', $branchId, $customerId);
        } else {
            $customer = $this->find('retail_customers', $customerId);
            abort_unless((int) $customer->chain_id === (int) $branch->chain_id, 404);
        }
        $legacyDatabaseIds = DB::table('retail_customers')->where('chain_id', $branch->chain_id)->whereIn('public_id', $legacyIds)->pluck('id');
        $sales = DB::table('retail_sales')->where('branch_id', $branch->id)->where('status', 'completed')->where(function ($q) use ($legacyDatabaseIds, $sharedIds): void {
            $q->whereIn('customer_id', $legacyDatabaseIds);
            if ($sharedIds !== []) {
                $q->orWhereIn('shared_customer_id', $sharedIds);
            }
        })->orderByDesc('id')->limit(100)->get();

        return ['branch_id' => $branchId, 'customer_id' => $customerId, 'scope' => 'branch', 'items' => $sales->map(fn ($sale) => $this->salePayload($sale))->all()];
    }

    private function buyerSnapshot(array $profile, string $source): array
    {
        return ['source_module' => $source, 'id' => $profile['public_id'] ?? $profile['id'], 'type' => $profile['type'] ?? 'person', 'code' => $profile['code'] ?? null, 'name' => $profile['name'], 'phone' => $profile['phone'] ?? null, 'email' => $profile['email'] ?? null, 'version' => (int) ($profile['version'] ?? 1)];
    }

    public function commandStatus(Admin $actor, string $key): array
    {
        $this->permission($actor, 'retail.view');
        $row = DB::table('retail_commands')->where('actor_id', $actor->id)->where('website_key', app(SiteContext::class)->websiteKey())->where('command_key', $key)->first();
        if ($row) {
            $this->authorizeIntent($actor, $row->operation, json_decode($row->intent, true, flags: JSON_THROW_ON_ERROR));
        }

        return $row ? ['status' => 'committed', 'resource' => json_decode($row->response, true, flags: JSON_THROW_ON_ERROR)] : ['status' => 'unknown'];
    }

    public function find(string $table, string $id, bool $lock = false): object
    {
        abort_unless(Str::isUuid($id), 404);
        $r = DB::table($table)->where('public_id', $id)->when($lock, fn ($q) => $q->lockForUpdate())->first();
        abort_unless($r, 404);

        return $r;
    }

    private function insert(string $table, array $values, bool $uuid = true): object
    {
        if ($uuid) {
            $values['public_id'] = (string) Str::uuid();
        } $id = DB::table($table)->insertGetId($values + ['created_at' => now(), 'updated_at' => now()]);

        return DB::table($table)->where('id', $id)->first();
    }

    private function permission(Admin $actor, string $permission): void
    {
        abort_unless($actor->hasPermission($permission), 403);
    }

    private function member(Admin $actor, object $b): bool
    {
        return $actor->isSuperAdmin() || DB::table('retail_memberships')->where('admin_id', $actor->id)->where('chain_id', $b->chain_id)->where('is_active', true)->where(fn ($q) => $q->where('branch_id', 0)->orWhere('branch_id', $b->id))->exists();
    }

    private function hq(Admin $actor, object $chain): void
    {
        abort_unless($chain->website_key === app(SiteContext::class)->websiteKey(), 404);
        abort_unless($actor->isSuperAdmin() || DB::table('retail_memberships')->where('admin_id', $actor->id)->where('chain_id', $chain->id)->where('branch_id', 0)->where('preset', 'hq')->where('is_active', true)->exists(), 403);
    }

    private function skuForBranch(object $branch, string $publicId): object
    {
        $sku = $this->find('merch_skus', $publicId);
        $catalog = DB::table('retail_chains')->where('id', $branch->chain_id)->value('catalog_id');
        abort_unless($sku->catalog_id === $catalog && $sku->is_active, 404);

        return $sku;
    }

    private function version(object $r, array $data): void
    {
        abort_unless(isset($data['expected_version']) && (string) $data['expected_version'] === (string) $r->version, 409, 'Dữ liệu đã đổi; tải lại trước khi thao tác.');
    }

    private function present(object $r): array
    {
        $data = (array) $r;
        $data['database_id'] = $data['id'];
        $data['id'] = $data['public_id'] ?? $data['id'];

        return $data;
    }

    private function branchPayload(object $r, mixed $terminals = null): array
    {
        $data = $this->present($r);
        $data['chain_id'] = DB::table('retail_chains')->where('id', $r->chain_id)->value('public_id');
        $data['legal_entity_id'] = DB::table('retail_legal_entities')->where('id', $r->legal_entity_id)->value('public_id');
        $data['terminals'] = ($terminals ?? DB::table('retail_terminals')->where('branch_id', $r->id)->get())->filter(fn ($t) => $t->branch_id === $r->id)->map(fn ($t) => $this->present($t))->values()->all();

        return $data;
    }

    private function shiftPayload(object $r): array
    {
        return array_replace($this->present($r), ['branch_id' => DB::table('retail_branches')->where('id', $r->branch_id)->value('public_id'), 'terminal_id' => DB::table('retail_terminals')->where('id', $r->terminal_id)->value('public_id')]);
    }

    private function salePayload(object $r): array
    {
        return array_replace($this->present($r), ['buyer_snapshot' => $r->buyer_snapshot ? json_decode($r->buyer_snapshot, true, flags: JSON_THROW_ON_ERROR) : null, 'branch_id' => DB::table('retail_branches')->where('id', $r->branch_id)->value('public_id'), 'quote_expires_at' => $this->quoteExpiresAt($r)->utc()->toIso8601String(), 'lines' => DB::table('retail_sale_lines')->where('sale_id', $r->id)->get()->map(fn ($l) => $this->present($l))->all(), 'tenders' => DB::table('retail_tenders')->where('sale_id', $r->id)->get()->map(fn ($t) => $this->present($t))->all()]);
    }

    private function quoteExpiresAt(object $sale): CarbonImmutable
    {
        return CarbonImmutable::parse($sale->created_at, config('app.timezone', 'UTC'))->addSeconds(self::QUOTE_TTL_SECONDS);
    }

    private function cash(object $shift, string $kind, int $amount, string $source, Admin $actor): void
    {
        $this->insert('retail_cash_movements', ['shift_id' => $shift->id, 'kind' => $kind, 'amount_minor' => $amount, 'source_id' => $source, 'actor_id' => $actor->id]);
        DB::table('retail_shifts')->where('id', $shift->id)->increment('expected_minor', $amount, ['updated_at' => now()]);
    }

    private function event(string $type, string $source, int $branch, array $data): void
    {
        $this->insert('retail_outbox', ['event_type' => $type, 'source_id' => $source, 'branch_id' => $branch, 'payload' => json_encode($data, JSON_THROW_ON_ERROR)]);
    }

    private function canonical(array $data): array
    {
        ksort($data);
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = $this->canonical($value);
            }
        }

        return $data;
    }

    public function cancelSale(Admin $actor, string $id, array $data): array
    {
        $sale = $this->find('retail_sales', $id, true);
        $branch = DB::table('retail_branches')->where('id', $sale->branch_id)->first();
        $this->branch($actor, $branch->public_id, 'retail.sale.create', true);
        $this->version($sale, $data);
        abort_unless($sale->status === 'priced' && ! DB::table('retail_tenders')->where('sale_id', $sale->id)->exists(), 409, 'Đơn có tiền hoặc đã kết thúc không thể hủy nháp.');
        DB::table('retail_sales')->where('id', $sale->id)->update(['status' => 'cancelled', 'version' => $sale->version + 1, 'updated_at' => now()]);

        return $this->salePayload($this->find('retail_sales', $id));
    }

    public function sanitize(Admin $actor, mixed $value, ?bool $canViewCost = null): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $canViewCost ??= $actor->hasPermission('retail.report.cost.view');
        foreach ($value as $key => &$item) {
            if ($key === 'buyer_snapshot' && is_array($item) && ! $actor->hasPermission('retail.customer.view')) {
                unset($item['phone'], $item['email']);
            }
            if (! $canViewCost && in_array($key, ['cost', 'unit_cost', 'total_cost', 'stock_allocations'], true)) {
                unset($value[$key]);

                continue;
            }
            $item = $this->sanitize($actor, $item, $canViewCost);
        }

        return $value;
    }

    private function authorizeIntent(Admin $actor, string $operation, array $intent): void
    {
        $data = $intent['data'] ?? [];
        $permission = match (explode('.', $operation)[0]) {
            'setup' => 'retail.setup.manage','branch','terminal' => 'retail.branch.manage','product','assortment' => 'retail.product.manage','receipt' => 'retail.inventory.view',
            'sale' => str_ends_with($operation, '.complete') ? 'retail.payment.collect' : (str_ends_with($operation, '.return') ? 'retail.return.manage' : 'retail.sale.create'),
            'return' => 'retail.return.manage','shift' => 'retail.shift.manage','customer' => 'retail.customer.manage','membership' => 'retail.membership.manage','transfer' => 'retail.transfer.manage','stocktake' => 'retail.stocktake.manage','integration' => 'retail.integration.manage',default => 'retail.view',
        };
        $this->permission($actor, $permission);
        if (in_array($operation, ['setup', 'membership.save'], true)) {
            abort_unless($actor->canAccess($permission, 'global'), 403);
        }
        $branches = [];
        foreach (['branch_id', 'source_branch_id', 'destination_branch_id'] as $field) {
            if (! empty($data[$field])) {
                $branches[$field] = $this->branch($actor, (string) $data[$field], $permission);
            }
        }
        if (! empty($data['chain_id'])) {
            $this->hq($actor, $this->find('retail_chains', $data['chain_id']));
        }
        $table = match (explode('.', $operation)[0]) {
            'sale' => 'retail_sales','shift' => 'retail_shifts','return' => 'retail_returns',default => null
        };
        $row = null;
        $resourceBranch = null;
        if ($table && ! empty($intent['resource'])) {
            $row = $this->find($table, $intent['resource']);
            if (isset($row->branch_id)) {
                $resourceBranch = DB::table('retail_branches')->where('id', $row->branch_id)->first();
                abort_unless($resourceBranch, 404);
                $this->branch($actor, $resourceBranch->public_id, $permission);
            }
        }
        // Re-authorize the original privilege, never rerun its already-committed state machine.
        // Versions, quote expiry, stock, open/closed status and master prices may have changed.
        if (in_array($operation, ['product.create', 'assortment.save', 'terminal.create'], true)) {
            $branch = $branches['branch_id'] ?? null;
            abort_unless($branch, 404);
            $this->hq($actor, DB::table('retail_chains')->where('id', $branch->chain_id)->first());
        }
        if ($operation === 'receipt.create' || ($operation === 'product.create' && BigDecimal::of((string) ($data['opening_quantity'] ?? '0'))->isPositive())) {
            $this->permission($actor, 'inventory.receipt.manage');
        }
        if ($operation === 'assortment.save' || ($operation === 'sale.complete' && BigDecimal::of((string) ($data['discount_minor'] ?? '0'))->isPositive())) {
            $this->permission($actor, 'retail.price.manage');
        }
        if ($operation === 'sale.complete' && collect($data['tenders'] ?? [])->contains(fn ($tender) => ($tender['method'] ?? null) === 'manual_transfer')) {
            $this->permission($actor, 'retail.settings.manage');
        }
        if ($operation === 'return.refund') {
            $this->permission($actor, 'retail.payment.collect');
            if (($data['method'] ?? 'cash') === 'manual_transfer') {
                $this->permission($actor, 'retail.settings.manage');
            }
        }
        if (in_array($operation, ['sale.create', 'sale.complete', 'return.refund', 'shift.close'], true)) {
            $shift = match ($operation) {
                'sale.complete' => DB::table('retail_shifts')->where('id', $row?->shift_id)->first(),
                'shift.close' => $row,
                default => $this->find('retail_shifts', (string) ($data['shift_id'] ?? '')),
            };
            $branch = $resourceBranch ?? ($branches['branch_id'] ?? null);
            abort_unless($shift && $branch && (int) $shift->branch_id === (int) $branch->id && ((int) $shift->admin_id === (int) $actor->id || $actor->isSuperAdmin()), 403);
            if ($operation === 'sale.create') {
                $terminal = $this->find('retail_terminals', (string) ($data['terminal_id'] ?? ''));
                abort_unless((int) $terminal->branch_id === (int) $branch->id && (int) $shift->terminal_id === (int) $terminal->id, 404);
            }
        }
        if ($operation === 'shift.reconcile') {
            abort_unless($resourceBranch, 404);
            $this->hq($actor, DB::table('retail_chains')->where('id', $resourceBranch->chain_id)->first());
        }
        if ($operation === 'customer.update' && isset($branches['branch_id'])) {
            $context = $this->customerContext($actor, $branches['branch_id']->public_id);
            if ($context['mode'] === 'shared') {
                abort_unless($context['ready'], 503);
                app(ConsumerCustomerService::class)->profile($actor, 'retail-pos', $branches['branch_id']->public_id, (string) $intent['resource']);
            } else {
                $customer = $this->find('retail_customers', (string) $intent['resource']);
                abort_unless((int) $customer->chain_id === (int) $branches['branch_id']->chain_id, 404);
            }
        }
        if (str_starts_with($operation, 'transfer.')) {
            $this->permission($actor, 'inventory.transfer.manage');
        }
        if (str_starts_with($operation, 'stocktake.')) {
            $this->permission($actor, 'inventory.stocktake.manage');
        }
        if (in_array($operation, ['transfer.receive', 'transfer.dispatch', 'stocktake.post'], true)) {
            $document = $this->find(str_starts_with($operation, 'transfer.') ? 'inv_transfers' : 'inv_stocktakes', (string) $intent['resource']);
            $warehouse = $document->destination_warehouse_id ?? $document->warehouse_id;
            $branch = DB::table('retail_branches')->where('warehouse_id', $warehouse)->first();
            abort_unless($branch, 404);
            $this->branch($actor, $branch->public_id, $permission);
        }
        if ($operation === 'integration.mapping' && ($data['enabled'] ?? false)) {
            app(RetailAccountingBridge::class)->assertNative($actor, (int) $data['organization_id']);
        }
        if ($operation === 'integration.reconcile' && isset($branches['branch_id'])) {
            $mapping = DB::table('retail_accounting_mappings')->where('branch_id', $branches['branch_id']->id)->first();
            if ($mapping) {
                app(RetailAccountingBridge::class)->assertNative($actor, (int) $mapping->organization_id);
            }
        }
    }
}
