<?php

namespace App\Support;

use App\Models\AcctInventoryWarehouseMapping;
use App\Models\AcctOrganizationWebsite;
use App\Models\CatalogProduct;
use App\Models\InvItem;
use App\Support\AccountingTax\ModuleCapabilityService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Schema;

class InventoryAvailabilityResolver
{
    public function __construct(
        private readonly ModuleCapabilityService $capabilities,
        private readonly SiteContext $siteContext,
    ) {}

    /**
     * Resolve the quantity that the storefront is allowed to sell.
     *
     * A null quantity is only possible while Catalog remains the authority and
     * the product itself has no stock limit. Once Inventory is enabled and its
     * contract is complete, missing item or legal-entity mappings fail closed.
     *
     * @return array{quantity: int|null, source: 'catalog'|'inventory', inventory_item_id: int|null, warehouse_ids: list<int>|null}
     */
    public function resolve(CatalogProduct $product, ?string $websiteKey = null): array
    {
        if (! $this->inventoryIsAuthoritative()) {
            return [
                'quantity' => $product->stock === null ? null : max(0, (int) $product->stock),
                'source' => 'catalog',
                'inventory_item_id' => null,
                'warehouse_ids' => null,
            ];
        }

        $item = InvItem::query()
            ->where('catalog_product_id', $product->getKey())
            ->where('is_active', true)
            ->first();

        if ($item === null) {
            return [
                'quantity' => 0,
                'source' => 'inventory',
                'inventory_item_id' => null,
                'warehouse_ids' => [],
            ];
        }

        $warehouseIds = $this->warehouseScope(
            $this->normalizeWebsiteKey($websiteKey ?: $product->website_key),
        );

        $balances = $item->balances()
            ->whereHas('warehouse', fn ($query) => $query->where('is_active', true))
            ->when($warehouseIds !== null, fn ($query) => $query->whereIn('warehouse_id', $warehouseIds))
            ->get(['quantity_on_hand', 'quantity_reserved']);
        $available = $balances->reduce(
            fn (BigDecimal $total, $balance): BigDecimal => $total->plus((string) $balance->quantity_on_hand)
                ->minus((string) $balance->quantity_reserved),
            BigDecimal::zero(),
        );

        return [
            'quantity' => max(0, $available->toScale(0, RoundingMode::Floor)->toInt()),
            'source' => 'inventory',
            'inventory_item_id' => (int) $item->getKey(),
            'warehouse_ids' => $warehouseIds,
        ];
    }

    public function quantity(CatalogProduct $product, ?string $websiteKey = null): ?int
    {
        return $this->resolve($product, $websiteKey)['quantity'];
    }

    private function inventoryIsAuthoritative(): bool
    {
        return $this->capabilities->has('inventory', 'inventory.stock.read.v1')
            && Schema::hasTable('inv_items')
            && Schema::hasColumns('inv_items', ['catalog_product_id', 'is_active'])
            && Schema::hasTable('inv_warehouses')
            && Schema::hasColumns('inv_warehouses', ['id', 'is_active'])
            && Schema::hasTable('inv_stock_balances')
            && Schema::hasColumns('inv_stock_balances', [
                'item_id',
                'warehouse_id',
                'quantity_on_hand',
                'quantity_reserved',
            ]);
    }

    /**
     * Null means the legacy, unscoped Inventory installation may use all active
     * warehouses. An empty list deliberately means no warehouse is authorized.
     *
     * @return list<int>|null
     */
    private function warehouseScope(string $websiteKey): ?array
    {
        if (! $this->capabilities->moduleEnabled('accounting-tax')
            || ! Schema::hasTable('acct_organization_websites')
            || ! Schema::hasColumns('acct_organization_websites', ['organization_id', 'website_key'])
            || ! Schema::hasTable('acct_inventory_warehouse_mappings')
            || ! Schema::hasColumns('acct_inventory_warehouse_mappings', [
                'organization_id',
                'inventory_warehouse_id',
            ])) {
            return null;
        }

        if (! AcctInventoryWarehouseMapping::query()->exists()) {
            return null;
        }

        $organizationIds = AcctOrganizationWebsite::query()
            ->where('website_key', $websiteKey)
            ->pluck('organization_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($organizationIds->count() !== 1) {
            return [];
        }

        return AcctInventoryWarehouseMapping::query()
            ->where('organization_id', $organizationIds->first())
            ->pluck('inventory_warehouse_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeWebsiteKey(?string $websiteKey): string
    {
        return $this->siteContext->normalizeWebsiteKey($websiteKey);
    }
}
