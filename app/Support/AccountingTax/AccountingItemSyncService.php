<?php

namespace App\Support\AccountingTax;

use App\Models\AcctItem;
use App\Models\AcctItemSource;
use App\Models\AcctOrganization;
use App\Models\CatalogProduct;
use App\Models\CmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountingItemSyncService
{
    public function __construct(private readonly ModuleCapabilityService $capabilities) {}

    /**
     * @return array{catalog:int,cms_services:int,inventory_enabled:bool}
     */
    public function syncEnabledSources(int $organizationId): array
    {
        $catalog = $this->capabilities->has('catalog', 'catalog.items.read.v1')
            ? $this->syncCatalogProducts($organizationId)
            : 0;

        $services = $this->capabilities->has('cms', 'cms.services.read.v1')
            ? $this->syncCmsServices($organizationId)
            : 0;

        return [
            'catalog' => $catalog,
            'cms_services' => $services,
            'inventory_enabled' => $this->capabilities->has('inventory', 'inventory.stock.read.v1'),
        ];
    }

    public function syncCatalogProducts(int $organizationId): int
    {
        if (! $this->capabilities->has('catalog', 'catalog.items.read.v1') || ! Schema::hasTable('catalog_products')) {
            return 0;
        }

        $websiteKeys = $this->websiteKeys($organizationId);

        if ($websiteKeys === []) {
            return 0;
        }

        $count = 0;

        CatalogProduct::query()
            ->withoutGlobalScope('current_website')
            ->whereIn('website_key', $websiteKeys)
            ->orderBy('id')
            ->chunkById(100, function ($products) use ($organizationId, &$count): void {
                foreach ($products as $product) {
                    $this->upsertItemFromSource($organizationId, [
                        'source_module' => 'catalog',
                        'source_type' => 'catalog.product',
                        'source_id' => (string) $product->id,
                        'source_key' => $product->sku ?: $product->slug,
                        'kind' => 'goods',
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'unit' => 'pcs',
                        'default_price' => (string) $product->price,
                        'is_stock_tracked' => $this->capabilities->has('inventory', 'inventory.stock.read.v1'),
                        'source_updated_at' => $product->updated_at,
                        'snapshot' => [
                            'website_key' => $product->website_key,
                            'slug' => $product->slug,
                            'stock' => $product->stock,
                            'is_active' => (bool) $product->is_active,
                        ],
                    ]);
                    $count++;
                }
            });

        return $count;
    }

    public function syncCmsServices(int $organizationId): int
    {
        if (! $this->capabilities->has('cms', 'cms.services.read.v1') || ! Schema::hasTable('cms_services')) {
            return 0;
        }

        $websiteKeys = $this->websiteKeys($organizationId);

        if ($websiteKeys === []) {
            return 0;
        }

        $count = 0;

        CmsService::query()
            ->withoutGlobalScope('current_website')
            ->whereIn('website_key', $websiteKeys)
            ->orderBy('id')
            ->chunkById(100, function ($services) use ($organizationId, &$count): void {
                foreach ($services as $service) {
                    $this->upsertItemFromSource($organizationId, [
                        'source_module' => 'cms',
                        'source_type' => 'cms.service',
                        'source_id' => (string) $service->id,
                        'source_key' => $service->slug,
                        'kind' => 'service',
                        'name' => $service->title,
                        'sku' => null,
                        'unit' => 'service',
                        'default_price' => 0,
                        'is_stock_tracked' => false,
                        'source_updated_at' => $service->updated_at,
                        'snapshot' => [
                            'website_key' => $service->website_key,
                            'slug' => $service->slug,
                            'status' => $service->status,
                        ],
                    ]);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertItemFromSource(int $organizationId, array $payload): void
    {
        DB::transaction(function () use ($organizationId, $payload): void {
            $source = AcctItemSource::query()
                ->where('organization_id', $organizationId)
                ->where('source_module', $payload['source_module'])
                ->where('source_type', $payload['source_type'])
                ->where('source_id', $payload['source_id'])
                ->first();

            $snapshot = $payload['snapshot'] ?? [];
            $hash = hash('sha256', json_encode([
                'name' => $payload['name'],
                'sku' => $payload['sku'],
                'unit' => $payload['unit'],
                'default_price' => $payload['default_price'],
                'snapshot' => $snapshot,
            ], JSON_THROW_ON_ERROR));

            /** @var AcctItem $item */
            $item = $source?->item ?? AcctItem::query()->create([
                'organization_id' => $organizationId,
                'kind' => $payload['kind'],
                'name' => $payload['name'],
                'sku' => $payload['sku'],
                'unit' => $payload['unit'],
                'default_price' => $payload['default_price'],
                'tax_category' => 'standard',
                'is_stock_tracked' => $payload['is_stock_tracked'],
                'status' => 'active',
            ]);

            if ($source !== null) {
                if ((int) $item->organization_id !== $organizationId) {
                    throw new \LogicException('Accounting item source cannot cross organization boundaries.');
                }

                $item->forceFill([
                    'name' => $payload['name'],
                    'sku' => $payload['sku'],
                    'unit' => $payload['unit'],
                    'default_price' => $payload['default_price'],
                    'is_stock_tracked' => $payload['is_stock_tracked'],
                ])->save();
            }

            AcctItemSource::query()->updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'source_module' => $payload['source_module'],
                    'source_type' => $payload['source_type'],
                    'source_id' => $payload['source_id'],
                ],
                [
                    'organization_id' => $organizationId,
                    'accounting_item_id' => $item->id,
                    'source_key' => $payload['source_key'],
                    'source_updated_at' => $payload['source_updated_at'],
                    'source_hash' => $hash,
                    'synced_at' => now(),
                    'sync_status' => 'synced',
                    'snapshot' => $snapshot,
                ],
            );
        });
    }

    /** @return list<string> */
    private function websiteKeys(int $organizationId): array
    {
        return AcctOrganization::query()
            ->findOrFail($organizationId)
            ->websites()
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->pluck('website_key')
            ->map(fn (mixed $key): string => (string) $key)
            ->values()
            ->all();
    }
}
