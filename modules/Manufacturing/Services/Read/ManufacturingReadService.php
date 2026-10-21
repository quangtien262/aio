<?php

namespace Modules\Manufacturing\Services\Read;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\Admin;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Services\InventoryV2Service;
use Modules\Manufacturing\Services\Access\ManufacturingAccessService;
use Modules\Manufacturing\Services\Documents\ManufacturingDocumentService;
use Modules\Manufacturing\Services\Engineering\EngineeringService;
use Modules\Manufacturing\Services\ManufacturingData as D;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ManufacturingReadService
{
    private array $keys = [];

    private bool $costs = false;

    private bool $documents = false;

    private ?int $factoryContext = null;

    private array $childRows = [];

    private array $documentsByResource = [];

    private array $custodyLines = [];

    public function read(Admin $actor, string $kind, ?string $factoryId, array $query = []): array
    {
        $this->keys = [];
        $this->childRows = [];
        $this->documentsByResource = [];
        $this->custodyLines = [];
        $access = app(ManufacturingAccessService::class);
        $actor = $access->actor($actor);
        $access->permission($actor, 'manufacturing.view');
        if ($kind === 'settings' && ! $factoryId) {
            $access->permission($actor, 'manufacturing.settings');

            return $access->bootstrap($actor) + ['items' => [], 'total' => 0];
        }
        abort_unless($factoryId, 422, 'Cần chọn nhà máy.');
        $factory = $access->factory($actor, $factoryId, match ($kind) {
            'costing' => 'manufacturing.cost.view', 'settings' => 'manufacturing.settings', default => 'manufacturing.view'
        });
        $this->costs = $this->canCost($actor, $factory);
        $this->factoryContext = (int) $factory->id;
        $this->documents = $this->canPermission($actor, $factory, 'manufacturing.documents');
        if ($kind === 'reports') {
            return $this->reports($factory);
        }
        if ($kind === 'materials') {
            return $this->materials($actor, $factory, $query);
        }
        if ($kind === 'order-mrp') {
            return $this->mrp($actor, $factory, $query);
        }
        if ($kind === 'documents') {
            return app(ManufacturingDocumentService::class)->listing($actor, $factoryId, $query);
        }
        if ($kind === 'settings') {
            $bootstrap = $access->bootstrap($actor, $factoryId);
            $bootstrap['memberships'] = DB::table('mfg_memberships as m')->join('admins as a', 'a.id', '=', 'm.admin_id')
                ->where('m.company_id', $factory->company_id)->where(fn ($q) => $q->whereNull('m.factory_id')->orWhere('m.factory_id', $factory->id))
                ->select('m.*', 'a.name as admin_name')->limit(500)->get()->map(fn ($r) => $this->serialize($r))->all();
            $bootstrap['operations'] = [
                'outbox' => DB::table('mfg_outbox')->where('company_id', $factory->company_id)->where('factory_id', $factory->id)
                    ->latest('id')->limit(100)->get(['event_id', 'event_type', 'aggregate_type', 'aggregate_id', 'aggregate_version', 'destination', 'status', 'attempts', 'available_at', 'delivered_at', 'last_error'])->map(fn ($r) => (array) $r)->all(),
                'commands' => DB::table('mfg_commands as c')->leftJoin('admins as a', 'a.id', '=', 'c.actor_id')
                    ->where('c.factory_id', $factory->id)->where('c.actor_id', $actor->id)
                    ->latest('c.id')->limit(100)->get(['c.public_id', 'c.operation', 'c.actor_id', 'a.name as actor_name', 'c.status', 'c.created_at', 'c.updated_at'])->map(fn ($r) => (array) $r)->all(),
            ];

            return $bootstrap + ['items' => [], 'total' => 0];
        }
        $table = match ($kind) {
            'styles' => 'mfg_styles', 'revisions' => 'mfg_style_revisions', 'samples' => 'mfg_samples', 'demands' => 'mfg_demands',
            'orders' => 'mfg_orders', 'bundles' => 'mfg_bundles', 'handovers' => 'mfg_handovers', 'quality' => 'mfg_quality_inspections',
            'reworks' => 'mfg_reworks', 'subcontracts' => 'mfg_subcontracts', 'shipments' => 'mfg_shipments', 'cartons' => 'mfg_cartons', 'costing' => 'mfg_cost_runs',
            default => abort(404, 'Danh sách sản xuất không tồn tại.'),
        };
        $builder = DB::table($table)->where('company_id', $factory->company_id);
        if (! in_array($kind, ['styles', 'revisions', 'samples'], true)) {
            if ($kind === 'handovers') {
                $builder->where(fn ($q) => $q->where('from_factory_id', $factory->id)->orWhere('to_factory_id', $factory->id));
            } elseif ($kind === 'orders') {
                $builder->where(fn ($q) => $q->where('factory_id', $factory->id)->orWhereExists(fn ($b) => $b->selectRaw('1')->from('mfg_bundles as custody')
                    ->whereColumn('custody.order_id', 'mfg_orders.id')->where('custody.factory_id', $factory->id)->whereNotIn('custody.status', ['merged', 'cancelled'])));
            } else {
                $builder->where('factory_id', $factory->id);
            }
        }
        $result = $this->page($builder, $table, $query);
        $this->preload($table, $result['items']);
        $result['items'] = array_map(fn ($row) => $this->enrich($table, $row), $result['items']);
        if ($kind === 'bundles') {
            $result['handovers'] = DB::table('mfg_handovers')->where('company_id', $factory->company_id)->where(fn ($q) => $q->where('from_factory_id', $factory->id)->orWhere('to_factory_id', $factory->id))->latest('id')->limit(100)->get()->map(fn ($r) => $this->serialize($r))->all();
        }
        if ($kind === 'quality') {
            $result['reworks'] = $this->children('mfg_reworks', $factory);
        }
        if ($kind === 'shipments') {
            $result['cartons'] = $this->children('mfg_cartons', $factory);
        }
        if ($kind === 'costing') {
            $result['entries'] = $this->children('mfg_cost_entries', $factory);
        }

        return $this->mask($result);
    }

    private function enrich(string $table, object $row): array
    {
        $data = $this->serialize($row);
        $children = $this->childDefinitions($table);
        foreach ($children as $key => [$child, $fk]) {
            $rows = $this->childRows[$child][$row->id] ?? [];
            if ($table === 'mfg_orders' && (int) $row->factory_id !== $this->factoryContext) {
                $rows = array_values(array_filter($rows, fn ($r) => $child === 'mfg_order_lines'
                    ? in_array($r->id, $this->custodyLines[$row->id] ?? [], true) : (int) $r->factory_id === $this->factoryContext));
            }
            $data[$key] = array_map(fn ($r) => $this->serialize($r), array_slice($rows, 0, 500));
        }
        if ($table === 'mfg_styles') {
            $revisions = $this->childRows['mfg_style_revisions'][$row->id] ?? [];
            usort($revisions, fn ($a, $b) => $b->revision <=> $a->revision);
            $current = $revisions[0] ?? null;
            if ($current) {
                $data['revision_id'] = $current->public_id;
                $data['revision'] = $current->revision;
                $data['revision_status'] = $current->status;
                $data['bom'] = D::json($current->bom);
                $data['routing'] = D::json($current->routing);
                $data['measurements'] = D::json($current->measurements);
                $data['attachments'] = D::json($current->attachments);
            }
        }
        if ($table === 'mfg_orders' || $table === 'mfg_demands') {
            $data['quantity'] = $this->sum(array_column($data['lines'], 'quantity'));
        }
        if ($table === 'mfg_orders' && (int) $row->factory_id !== $this->factoryContext) {
            $data['custody_only'] = true;
            if (isset($data['released_snapshot']['lines'])) {
                $data['released_snapshot']['lines'] = $data['lines'];
            }
        }
        if ($table === 'mfg_quality_inspections' && $row->bundle_id) {
            $data['bundle_version'] = (int) ($this->keys['mfg_bundles:'.$row->bundle_id]?->version ?? 0);
        }
        if ($this->documents && in_array($table, ['mfg_styles', 'mfg_orders'], true)) {
            $data['documents'] = $this->documentsByResource[$row->public_id] ?? [];
        }

        return $data;
    }

    private function childDefinitions(string $table): array
    {
        return match ($table) {
            'mfg_styles' => ['revisions' => ['mfg_style_revisions', 'style_id'], 'samples' => ['mfg_samples', 'style_id']],
            'mfg_demands' => ['lines' => ['mfg_demand_lines', 'demand_id']],
            'mfg_orders' => ['lines' => ['mfg_order_lines', 'order_id'], 'allocations' => ['mfg_allocations', 'order_id'], 'material_requirements' => ['mfg_material_requirements', 'order_id']],
            'mfg_shipments' => ['shipment_lines' => ['mfg_shipment_lines', 'shipment_id']],
            default => [],
        };
    }

    private function preload(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $ids = array_column($rows, 'id');
        $company = $rows[0]->company_id;
        $all = $rows;
        foreach ($rows as $row) {
            $this->keys[$table.':'.$row->id] = $row;
        }
        foreach ($this->childDefinitions($table) as [$child, $foreign]) {
            foreach (DB::table($child)->where('company_id', $company)->whereIn($foreign, $ids)->orderBy('id')->get() as $row) {
                $this->childRows[$child][$row->{$foreign}][] = $row;
                $this->keys[$child.':'.$row->id] = $row;
                $all[] = $row;
            }
        }
        if ($table === 'mfg_orders') {
            foreach (DB::table('mfg_bundles')->whereIn('order_id', $ids)->where('factory_id', $this->factoryContext)->whereNotIn('status', ['merged', 'cancelled'])->get(['order_id', 'order_line_id']) as $bundle) {
                $this->custodyLines[$bundle->order_id][] = $bundle->order_line_id;
            }
        }
        foreach ($this->links() as $field => $related) {
            $relatedIds = [];
            foreach ($all as $row) {
                $id = $row->{$field} ?? (isset($row->metadata) ? (D::json($row->metadata)[$field] ?? null) : null);
                if ($id !== null && is_numeric($id) && ! array_key_exists($related.':'.$id, $this->keys)) {
                    $relatedIds[] = $id;
                }
            }
            if ($relatedIds !== []) {
                foreach (DB::table($related)->whereIn('id', array_unique($relatedIds))->get() as $row) {
                    $this->keys[$related.':'.$row->id] = $row;
                }
            }
        }
        if ($this->documents && in_array($table, ['mfg_styles', 'mfg_orders'], true)) {
            foreach (DB::table('mfg_documents')->where('company_id', $company)->where('factory_id', $this->factoryContext)->where('resource_type', $table === 'mfg_styles' ? 'style' : 'order')
                ->whereIn('resource_id', array_column($rows, 'public_id'))->where('status', 'ready')->latest('id')->get(['resource_id', 'public_id', 'original_name', 'mime_type', 'size', 'checksum', 'version', 'status']) as $document) {
                $data = (array) $document;
                unset($data['resource_id']);
                $this->documentsByResource[$document->resource_id][] = $data + ['id' => $document->public_id, 'download_url' => '/admin/api/manufacturing/documents/'.$document->public_id];
            }
        }
    }

    private function materials(Admin $actor, object $factory, array $query): array
    {
        $this->inventoryReady();
        if (isset($query['allocation_type'])) {
            validator($query, ['allocation_type' => 'required|in:issues,reservations,stocktake-lines,suppliers'])->validate();
            if ($query['allocation_type'] === 'suppliers') {
                abort_unless(app(ModuleCapabilityChecker::class)->enabled('purchasing') && $actor->canAccess('purchasing.view', 'global'), 403);
                $result = $this->page(DB::table('purchase_suppliers')->where('is_active', true)->select('id', 'public_id', 'code', 'name'), 'purchase_suppliers', $query);
                $result['items'] = array_map(fn ($row) => (array) $row, $result['items']);

                return $result;
            }
            if ($query['allocation_type'] === 'stocktake-lines') {
                abort_unless($actor->canAccess('inventory.stocktake.manage', 'global'), 403);
                validator($query, ['stocktake_id' => 'required|uuid'])->validate();
                $take = DB::table('inv_stocktakes')->where('warehouse_id', $factory->warehouse_id ?? 0)->where('public_id', $query['stocktake_id'])->first();
                abort_unless($take, 404);

                return $this->stocktakeLines($factory, $take, $query);
            }

            return $this->mask($this->materialReferences($query['allocation_type'] === 'issues' ? 'inv_production_issues' : 'inv_reservations', $factory, $query));
        }
        $builder = DB::table('inv_trace_units')->where('warehouse_id', $factory->warehouse_id ?? 0);
        $result = $this->page($builder, 'inv_trace_units', $query, 'disposition');
        $batchIds = array_column($result['items'], 'batch_id');
        $items = DB::table('inv_items')->whereIn('id', array_column($result['items'], 'item_id'))->get()->keyBy('id');
        $skus = DB::table('merch_skus')->whereIn('id', $items->pluck('merchandise_sku_id')->filter()->all())->get()->keyBy('id');
        $balances = DB::table('inv_stock_balances')->where('warehouse_id', $factory->warehouse_id ?? 0)->whereIn('batch_id', $batchIds)->get()->groupBy('batch_id');
        $costs = [];
        if ($this->costs && $batchIds !== []) {
            DB::table('inv_cost_layers')->where('warehouse_id', $factory->warehouse_id ?? 0)->whereIn('batch_id', $batchIds)->where('quantity_remaining', '>', 0)
                ->select('id', 'batch_id', 'quantity_remaining', 'unit_cost')->orderBy('id')->chunkById(500, function ($layers) use (&$costs): void {
                    foreach ($layers as $layer) {
                        $costs[$layer->batch_id] ??= ['cost' => BigDecimal::zero(), 'quantity' => BigDecimal::zero()];
                        $costs[$layer->batch_id]['cost'] = $costs[$layer->batch_id]['cost']->plus(BigDecimal::of((string) $layer->quantity_remaining)->multipliedBy((string) $layer->unit_cost));
                        $costs[$layer->batch_id]['quantity'] = $costs[$layer->batch_id]['quantity']->plus((string) $layer->quantity_remaining);
                    }
                });
        }
        $result['items'] = array_map(function ($row) use ($items, $skus, $balances, $costs): array {
            $data = $this->serialize($row);
            $item = $items[$row->item_id] ?? null;
            $sku = $skus[$item?->merchandise_sku_id] ?? null;
            $rollBalances = $balances[$row->batch_id] ?? collect();
            $onHand = BigDecimal::of($this->sum($rollBalances->pluck('quantity_on_hand')->all()));
            $reserved = BigDecimal::of($this->sum($rollBalances->pluck('quantity_reserved')->all()));
            $data += ['name' => $item?->name, 'material_code' => $sku?->sku ?? $item?->sku, 'sku_id' => $sku?->public_id,
                'quantity' => (string) $onHand, 'remaining_quantity' => (string) $onHand, 'reserved_quantity' => (string) $reserved,
                'available_quantity' => $row->disposition === 'saleable' ? (string) $onHand->minus($reserved) : '0.000000', 'uom' => $row->primary_unit, 'status' => $row->disposition, 'roll_code' => $row->code];
            if ($this->costs) {
                $cost = $costs[$row->batch_id]['cost'] ?? BigDecimal::zero();
                $layerQuantity = $costs[$row->batch_id]['quantity'] ?? BigDecimal::zero();
                $data['base_total_cost'] = (string) $cost->toScale(6, RoundingMode::HalfUp);
                $data['base_unit_cost'] = $layerQuantity->isPositive() ? (string) $cost->dividedBy($layerQuantity, 6, RoundingMode::HalfUp) : '0.000000';
                $data['cost_basis'] = 'FIFO base layers before append-only valuation adjustments';
            }

            return $data;
        }, $result['items']);
        $result['reservations'] = $this->materialReferences('inv_reservations', $factory);
        $result['issues'] = $this->materialReferences('inv_production_issues', $factory);
        $result['stocktakes'] = [];
        if ($actor->canAccess('inventory.stocktake.manage', 'global')) {
            $result['stocktakes'] = DB::table('inv_stocktakes')->where('warehouse_id', $factory->warehouse_id ?? 0)->where('status', 'counting')->latest('id')->limit(10)->get()
                ->map(function ($take) use ($factory): array {
                    $lines = $this->stocktakeLines($factory, $take, ['per_page' => 100]);

                    return ['id' => $take->public_id, 'public_id' => $take->public_id, 'status' => $take->status, 'version' => $take->version, 'maker_id' => $take->maker_id, 'checker_id' => $take->checker_id,
                        'created_at' => $take->created_at, 'lines' => $lines['items'], 'lines_total' => $lines['total'], 'lines_truncated' => $lines['total'] > count($lines['items'])];
                })->all();
        }
        $result['purchase_orders'] = [];
        $result['suppliers'] = [];
        if (app(ModuleCapabilityChecker::class)->enabled('purchasing') && $actor->canAccess('purchasing.view', 'global')) {
            $result['suppliers'] = DB::table('purchase_suppliers')->where('is_active', true)->orderBy('code')->limit(200)->get(['id', 'public_id', 'code', 'name'])->map(fn ($row) => (array) $row)->all();
            $orders = DB::table('purchase_orders as p')->leftJoin('purchase_suppliers as s', 's.id', '=', 'p.supplier_id')
                ->where('p.warehouse_id', $factory->warehouse_id ?? 0)->latest('p.id')->limit(100)->get(['p.*', 's.code as supplier_code', 's.name as supplier_name']);
            $lines = DB::table('purchase_order_lines')->whereIn('order_id', $orders->pluck('id'))->get()->groupBy('order_id');
            $receipts = app(ModuleCapabilityChecker::class)->has('purchasing', 'purchasing.receipt_lines.identity.v1') ? $this->purchaseReceiptLines($factory, $orders->pluck('id')->all()) : [];
            $skuIds = $lines->flatten(1)->pluck('sku_id')->filter()->unique();
            foreach (DB::table('merch_skus')->whereIn('id', $skuIds)->get() as $sku) {
                $this->keys['merch_skus:'.$sku->id] = $sku;
            }
            foreach ($orders as $order) {
                $this->keys['purchase_orders:'.$order->id] = $order;
            }
            $result['purchase_orders'] = $orders->map(function ($r) use ($lines, $receipts): array {
                $data = $this->serialize($r);
                $data['lines'] = ($lines[$r->id] ?? collect())->map(fn ($l) => $this->serialize($l, ['order_id' => 'purchase_orders']))->all();
                $data['receipt_lines'] = array_map(fn ($line) => $line + ['order_version' => $r->version], $receipts[$r->id] ?? []);

                return $data;
            })->all();
        }

        return $this->mask($result);
    }

    private function purchaseReceiptLines(object $factory, array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }
        $balances = DB::table('inv_stock_balances')->where('warehouse_id', $factory->warehouse_id ?? 0)->groupBy('batch_id')
            ->selectRaw('batch_id, SUM(quantity_on_hand) AS on_hand, SUM(quantity_reserved) AS reserved');
        $rows = DB::table('purchase_receipt_lines as l')->join('purchase_receipts as p', 'p.id', '=', 'l.receipt_id')->whereIn('p.order_id', $orderIds)
            ->join('purchase_order_lines as ol', 'ol.id', '=', 'l.order_line_id')->join('merch_skus as s', 's.id', '=', 'ol.sku_id')
            ->join('inv_trace_units as r', 'r.id', '=', 'l.trace_unit_id')->where('r.warehouse_id', $factory->warehouse_id ?? 0)->where('r.source_type', 'purchasing.receipt')
            ->leftJoinSub($balances, 'b', 'b.batch_id', '=', 'r.batch_id')
            ->get(['p.order_id', 'p.public_id as receipt_id', 'l.public_id', 'ol.public_id as order_line_id', 's.public_id as sku_id', 's.sku as material_code', 's.base_unit',
                'l.quantity', 'l.quantity_returned', 'l.secondary_quantity', 'l.secondary_unit', 'r.public_id as roll_id', 'r.code as roll_code', 'r.disposition', 'r.secondary_on_hand', 'b.on_hand', 'b.reserved']);
        $result = [];
        foreach ($rows as $row) {
            $remaining = BigDecimal::max(BigDecimal::of((string) $row->quantity)->minus((string) $row->quantity_returned), BigDecimal::zero());
            $available = BigDecimal::max(BigDecimal::of((string) ($row->on_hand ?? '0'))->minus((string) ($row->reserved ?? '0')), BigDecimal::zero());
            $result[$row->order_id][] = ['id' => $row->public_id, 'public_id' => $row->public_id, 'receipt_id' => $row->receipt_id, 'order_line_id' => $row->order_line_id,
                'sku_id' => $row->sku_id, 'material_code' => $row->material_code, 'roll_id' => $row->roll_id, 'roll_code' => $row->roll_code, 'disposition' => $row->disposition,
                'received_quantity' => (string) BigDecimal::of((string) $row->quantity)->toScale(6), 'returned_quantity' => (string) BigDecimal::of((string) $row->quantity_returned)->toScale(6),
                'returnable_quantity' => (string) BigDecimal::min($remaining, $available)->toScale(6), 'primary_unit' => $row->base_unit,
                'received_secondary_quantity' => $row->secondary_quantity, 'secondary_unit' => $row->secondary_unit, 'roll_secondary_quantity' => $row->secondary_on_hand];
        }

        return $result;
    }

    private function materialReferences(string $table, object $factory, ?array $query = null): array
    {
        $builder = DB::table($table.' as r')->where('r.warehouse_id', $factory->warehouse_id ?? 0);
        if ($table === 'inv_reservations') {
            $builder->where('r.purpose', 'production');
        }
        $builder->join('inv_items as i', 'i.id', '=', 'r.item_id')->leftJoin('merch_skus as s', 's.id', '=', 'i.merchandise_sku_id')
            ->leftJoin('mfg_orders as o', fn ($join) => $join->on('o.public_id', '=', 'r.order_ref')->where('o.company_id', $factory->company_id))
            ->select('r.*', 'i.name', 's.sku as material_code', 's.public_id as material_sku_id', 'o.code as order_code');
        if ($table === 'inv_production_issues') {
            $builder->selectRaw("'issued' AS status");
        }
        $serialize = function ($row): array {
            $data = $this->serialize($row);
            $data['sku_id'] = $row->material_sku_id;
            $data['code'] = ($row->order_code ?? $row->order_ref).' · '.($row->material_code ?? $row->name).' · '.substr($row->public_id, 0, 8);
            unset($data['material_sku_id']);

            return $data;
        };
        if ($query !== null) {
            $result = $this->page(DB::query()->fromSub($builder, 'material_references'), 'material-references', $query);
            $result['items'] = array_map($serialize, $result['items']);

            return $result;
        }

        return $builder->latest('r.id')->limit(100)->get()->map($serialize)->all();
    }

    private function stocktakeLines(object $factory, object $take, array $query): array
    {
        $batchTotals = DB::table('inv_stock_balances')->where('warehouse_id', $factory->warehouse_id ?? 0)->groupBy('batch_id')->selectRaw('batch_id, SUM(quantity_on_hand) AS primary_quantity');
        $builder = DB::table('inv_stocktake_lines as l')->where('l.stocktake_id', $take->id)
            ->join('inv_stock_balances as b', fn ($join) => $join->on('b.id', '=', 'l.balance_id')->where('b.warehouse_id', $factory->warehouse_id ?? 0))
            ->join('inv_items as i', 'i.id', '=', 'b.item_id')->leftJoin('merch_skus as s', 's.id', '=', 'i.merchandise_sku_id')
            ->leftJoin('inv_trace_units as r', fn ($join) => $join->on('r.batch_id', '=', 'b.batch_id')->on('r.warehouse_id', '=', 'b.warehouse_id'))
            ->leftJoinSub($batchTotals, 'totals', 'totals.batch_id', '=', 'b.batch_id')
            ->select('l.id', 'l.balance_id', 'l.expected_quantity', 'l.counted_quantity', 'i.name', 'i.unit as primary_unit', 's.sku as material_code',
                'r.public_id', 'r.code', 'r.disposition', 'r.secondary_unit', 'r.secondary_on_hand as roll_secondary_quantity', 'totals.primary_quantity as roll_primary_quantity');
        $result = $this->page(DB::query()->fromSub($builder, 'stocktake_lines'), 'stocktake-lines', $query, 'disposition');
        $result['items'] = array_map(function ($row): array {
            $primary = BigDecimal::of((string) ($row->roll_primary_quantity ?? '0'));
            $secondary = $row->roll_secondary_quantity === null ? null : BigDecimal::of((string) $row->roll_secondary_quantity);

            return ['id' => $row->public_id ?? (string) $row->balance_id, 'balance_id' => $row->balance_id, 'roll_id' => $row->public_id, 'roll_code' => $row->code,
                'material_code' => $row->material_code, 'name' => $row->name, 'disposition' => $row->disposition, 'primary_unit' => $row->primary_unit, 'uom' => $row->primary_unit, 'secondary_unit' => $row->secondary_unit,
                'expected_quantity' => (string) BigDecimal::of((string) $row->expected_quantity)->toScale(6), 'counted_quantity' => $row->counted_quantity,
                'expected_secondary_quantity' => $secondary === null ? null : ($primary->isPositive() ? (string) $secondary->multipliedBy((string) $row->expected_quantity)->dividedBy($primary, 6, RoundingMode::HalfUp) : '0.000000'),
                'roll_secondary_quantity' => $row->roll_secondary_quantity, 'secondary_basis' => 'Current roll measurement allocated in proportion to primary balance; checker records actual secondary count'];
        }, $result['items']);

        return $result + ['stocktake_id' => $take->public_id, 'version' => $take->version];
    }

    private function mrp(Admin $actor, object $factory, array $query): array
    {
        $this->inventoryReady();
        $order = D::row('mfg_orders', (string) ($query['order_id'] ?? ''), $factory->company_id, $factory->id);
        $requirements = DB::table('mfg_material_requirements')->where('order_id', $order->id)->get()->map(fn ($r) => (array) $r)->all();
        if ($requirements === []) {
            $revision = D::row('mfg_style_revisions', (int) $order->style_revision_id, $factory->company_id);
            $requirements = app(EngineeringService::class)->requirements($revision, DB::table('mfg_order_lines')->where('order_id', $order->id)->get()->map(fn ($r) => (array) $r)->all());
        }
        $rows = [];
        foreach ($requirements as $requirement) {
            $sku = DB::table('merch_skus')->find($requirement['sku_id']);
            $item = DB::table('inv_items')->where('merchandise_sku_id', $requirement['sku_id'])->first();
            $available = $item ? app(InventoryV2Service::class)->availability((int) $factory->warehouse_id, (int) $item->id) : '0.000000';
            $inbound = BigDecimal::zero();
            if (app(ModuleCapabilityChecker::class)->enabled('purchasing') && $actor->canAccess('purchasing.view', 'global')) {
                $lines = DB::table('purchase_order_lines as l')->join('purchase_orders as p', 'p.id', '=', 'l.order_id')->where('p.warehouse_id', $factory->warehouse_id)
                    ->whereIn('p.status', ['approved', 'partially_received'])->where('l.sku_id', $requirement['sku_id'])->where(fn ($q) => $q->whereNull('p.expected_at')->orWhere('p.expected_at', '<=', $order->due_date))->get(['l.quantity_ordered', 'l.quantity_received']);
                foreach ($lines as $line) {
                    $inbound = $inbound->plus(BigDecimal::of((string) $line->quantity_ordered)->minus((string) $line->quantity_received));
                }
            }
            $remaining = BigDecimal::of((string) $requirement['required_quantity'])->minus((string) ($requirement['consumed_quantity'] ?? '0'));
            $held = $item ? BigDecimal::of($this->sum(DB::table('inv_reservations')->where('warehouse_id', $factory->warehouse_id)->where('item_id', $item->id)
                ->where('purpose', 'production')->where('order_ref', $order->public_id)->where('status', 'active')->pluck('quantity')->all())) : BigDecimal::zero();
            $shortage = $remaining->minus($available)->minus($inbound)->minus($held);
            $rows[] = ['sku_id' => $sku?->public_id, 'material_code' => $sku?->sku, 'name' => $sku?->name, 'uom' => $requirement['base_unit'],
                'required_quantity' => (string) $remaining->toScale(6), 'available_quantity' => (string) BigDecimal::of($available)->toScale(6),
                'inbound_quantity' => (string) $inbound->toScale(6), 'held_for_order' => (string) $held->toScale(6), 'shortage_quantity' => (string) ($shortage->isPositive() ? $shortage : BigDecimal::zero())->toScale(6)];
        }

        return ['items' => $rows, 'requirements' => $rows, 'total' => count($rows), 'order_id' => $order->public_id, 'basis' => 'required-minus-consumed-and-unallocated-availability; approved inbound due by order due date'];
    }

    private function reports(object $factory): array
    {
        $open = DB::table('mfg_orders')->where('factory_id', $factory->id)->whereNotIn('status', ['completed', 'cancelled', 'closed'])->count();
        $wip = $this->quantityAggregate(DB::table('mfg_bundles')->where('factory_id', $factory->id), 'wip_quantity');
        $hold = $this->quantityAggregate(DB::table('mfg_bundles')->where('factory_id', $factory->id)->where('status', 'quality_hold'), 'quantity');
        $shipped = $this->quantityAggregate(DB::table('mfg_shipments')->where('factory_id', $factory->id)->where('status', 'shipped'), 'quantity');
        $summary = ['open_orders' => $open, 'wip_quantity' => $wip, 'quality_hold_quantity' => $hold, 'shipped_quantity' => $shipped];
        $metrics = [['key' => 'open_orders', 'label' => 'Lệnh đang mở', 'value' => $open], ['key' => 'wip_quantity', 'label' => 'Dở dang chưa nhập thành phẩm hoặc loại bỏ', 'value' => $wip, 'unit' => 'chiếc'],
            ['key' => 'quality_hold_quantity', 'label' => 'Bó đang giữ QC', 'value' => $hold, 'unit' => 'chiếc'], ['key' => 'shipped_quantity', 'label' => 'Đã giao', 'value' => $shipped, 'unit' => 'chiếc']];
        $materialTotals = [];
        if (app(ModuleCapabilityChecker::class)->has('inventory', 'inventory.production.post.v1')) {
            foreach (DB::table('inv_stock_balances as b')->join('inv_trace_units as r', fn ($join) => $join->on('r.batch_id', '=', 'b.batch_id')->on('r.warehouse_id', '=', 'b.warehouse_id'))
                ->where('b.warehouse_id', $factory->warehouse_id ?? 0)->where('r.source_type', '!=', 'production.output')
                ->groupBy('r.primary_unit')->selectRaw('r.primary_unit, SUM(b.quantity_on_hand) AS quantity')->get() as $balance) {
                $materialTotals[$balance->primary_unit] = (string) BigDecimal::of((string) $balance->quantity)->toScale(6, RoundingMode::HalfUp);
            }
            foreach ($materialTotals as $unit => $quantity) {
                $metrics[] = ['key' => 'material_'.$unit, 'label' => 'Nguyên liệu tại kho', 'value' => $quantity, 'unit' => $unit];
            }
            $summary['material_quantity'] = count($materialTotals) === 1 ? reset($materialTotals) : null;
        }
        if ($this->costs) {
            $latest = DB::table('mfg_cost_runs')->where('factory_id', $factory->id)->where('status', '!=', 'adjusted')->selectRaw('MAX(id) AS id')->groupBy('order_id');
            $summary['cost_amount'] = $this->financialAggregate(DB::table('mfg_cost_runs')->whereIn('id', $latest), 'total_cost');
            $summary['cost_adjustment_amount'] = $this->financialAggregate(DB::table('mfg_cost_runs')->where('factory_id', $factory->id)->where('status', 'adjusted'), 'total_cost');
            $metrics[] = ['key' => 'cost_amount', 'label' => 'Giá thành theo lần tính gần nhất', 'value' => $summary['cost_amount'], 'unit' => 'VND'];
        }

        $recent = DB::table('mfg_orders')->where('factory_id', $factory->id)->latest('id')->limit(10)->get()->all();
        $this->preload('mfg_orders', $recent);

        return ['summary' => $summary, 'metrics' => $metrics, 'material_totals' => $materialTotals, 'recent_orders' => array_map(fn ($r) => $this->enrich('mfg_orders', $r), $recent)];
    }

    private function quantityAggregate(Builder $query, string $column): string
    {
        // MySQL sums DECIMAL exactly. Quantity-only SQLite test sums are rounded
        // for display here; financial totals never use its floating SUM.
        return (string) BigDecimal::of((string) $query->sum($column))->toScale(6, RoundingMode::HalfUp);
    }

    private function financialAggregate(Builder $query, string $column): string
    {
        $sum = BigDecimal::zero();
        $query->select('id', $column)->orderBy('id')->chunkById(500, function ($rows) use (&$sum, $column): void {
            foreach ($rows as $row) {
                $sum = $sum->plus((string) $row->{$column});
            }
        });

        return (string) $sum->toScale(6, RoundingMode::HalfUp);
    }

    private function page(Builder $builder, string $table, array $query, string $statusColumn = 'status'): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($query['per_page'] ?? $query['page_size'] ?? 25)));
        if (! empty($query['selected_id'])) {
            validator($query, ['selected_id' => 'required|uuid'])->validate();
            $builder->where('public_id', $query['selected_id']);
            unset($query['q'], $query['search'], $query['status']);
            $page = 1;
        }
        if (! empty($query['status'])) {
            $builder->where($statusColumn, (string) $query['status']);
        }
        $fields = match ($table) {
            'mfg_styles', 'purchase_suppliers' => ['code', 'name'], 'mfg_style_revisions' => [], 'mfg_samples' => ['type', 'notes'], 'mfg_handovers', 'mfg_quality_inspections', 'mfg_reworks' => [],
            'material-references' => ['name', 'material_code', 'order_ref', 'order_code'], 'stocktake-lines' => ['code', 'name', 'material_code'], default => ['code']
        };
        if (($search = trim((string) ($query['q'] ?? $query['search'] ?? ''))) !== '' && $fields !== []) {
            $search = str_starts_with($search, 'bundle:') ? substr($search, 7) : $search;
            $fields[] = 'public_id';
            $builder->where(function (Builder $q) use ($fields, $search): void {
                foreach ($fields as $field) {
                    $q->orWhere($field, 'like', '%'.$search.'%');
                }
            });
        }
        $total = (clone $builder)->count();

        return ['items' => $builder->latest('id')->offset(($page - 1) * $perPage)->limit($perPage)->get()->all(), 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    private function children(string $table, object $factory): array
    {
        return DB::table($table)->where('company_id', $factory->company_id)->where('factory_id', $factory->id)->latest('id')->limit(100)->get()->map(fn ($r) => $this->serialize($r))->all();
    }

    private function serialize(object $row, array $overrides = []): array
    {
        $data = D::resource($row);
        foreach (['permissions', 'metadata', 'settings', 'variant'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = D::json($data[$field]);
            }
        }
        foreach (['metadata', 'variant'] as $field) {
            foreach (($data[$field] ?? []) as $key => $value) {
                if (! array_key_exists($key, $data)) {
                    $data[$key] = $value;
                }
            }
        }
        foreach (array_replace($this->links(), $overrides) as $field => $table) {
            if (isset($data[$field]) && is_numeric($data[$field])) {
                $key = $table.':'.$data[$field];
                if (! array_key_exists($key, $this->keys)) {
                    $this->keys[$key] = DB::table($table)->where('id', $data[$field])->first();
                }
                $related = $this->keys[$key];
                $data[$field] = $related?->public_id;
                if ($related && isset($related->code)) {
                    $data[str_replace('_id', '_code', $field)] = $related->code;
                }
            }
        }

        return $this->mask($data);
    }

    private function links(): array
    {
        return ['company_id' => 'mfg_companies', 'factory_id' => 'mfg_factories', 'from_factory_id' => 'mfg_factories', 'to_factory_id' => 'mfg_factories',
            'style_id' => 'mfg_styles', 'style_revision_id' => 'mfg_style_revisions', 'order_id' => 'mfg_orders', 'order_line_id' => 'mfg_order_lines', 'demand_id' => 'mfg_demands',
            'demand_line_id' => 'mfg_demand_lines', 'workshop_id' => 'mfg_workshops', 'to_workshop_id' => 'mfg_workshops', 'line_id' => 'mfg_lines', 'shift_id' => 'mfg_shifts',
            'bundle_id' => 'mfg_bundles', 'inspection_id' => 'mfg_quality_inspections', 'sku_id' => 'merch_skus', 'cost_run_id' => 'mfg_cost_runs', 'carton_id' => 'mfg_cartons', 'shipment_id' => 'mfg_shipments'];
    }

    private function canCost(Admin $actor, object $factory): bool
    {
        return $this->canPermission($actor, $factory, 'manufacturing.cost.view');
    }

    private function canPermission(Admin $actor, object $factory, string $permission): bool
    {
        if (! $actor->canAccess($permission, 'global')) {
            return false;
        }
        try {
            app(ManufacturingAccessService::class)->factory($actor, (int) $factory->id, $permission);

            return true;
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 403) {
                return false;
            } throw $e;
        }
    }

    private function mask(array $data): array
    {
        return $this->costs ? $data : D::withoutCosts($data);
    }

    private function sum(array $values): string
    {
        $sum = BigDecimal::zero();
        foreach ($values as $value) {
            $sum = $sum->plus((string) ($value ?? '0'));
        }

        return (string) $sum->toScale(6);
    }

    private function inventoryReady(): void
    {
        abort_unless(app(ModuleCapabilityChecker::class)->has('inventory', 'inventory.production.post.v1'), 503, 'Inventory production chưa sẵn sàng.');
    }
}
