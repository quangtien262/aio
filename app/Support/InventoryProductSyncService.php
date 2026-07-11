<?php

namespace App\Support;

use App\Models\CatalogProduct;
use App\Models\InvItem;
use App\Models\InvSyncRun;
use App\Models\InvWarehouse;
use Illuminate\Support\Facades\DB;
use Throwable;

class InventoryProductSyncService
{
    public function __construct(private readonly InventoryStockService $stockService)
    {
    }

    public function sync(?int $adminId = null): InvSyncRun
    {
        return DB::transaction(function () use ($adminId): InvSyncRun {
            $run = InvSyncRun::query()->create([
                'source' => 'catalog_products',
                'created_by_admin_id' => $adminId,
                'started_at' => now(),
            ]);

            $defaultWarehouse = InvWarehouse::query()->where('is_default', true)->first();
            $counts = [
                'created_count' => 0,
                'updated_count' => 0,
                'skipped_count' => 0,
                'failed_count' => 0,
            ];

            CatalogProduct::query()
                ->orderBy('id')
                ->chunk(100, function ($products) use ($run, $defaultWarehouse, &$counts): void {
                    foreach ($products as $product) {
                        try {
                            $result = $this->syncProduct($product, $defaultWarehouse);
                            $counts[$result['counter']]++;

                            $run->lines()->create([
                                'catalog_product_id' => $product->id,
                                'item_id' => $result['item']->id ?? null,
                                'sku' => $product->sku,
                                'name' => $product->name,
                                'action' => $result['action'],
                                'message' => $result['message'],
                                'payload' => $result['payload'],
                            ]);
                        } catch (Throwable $exception) {
                            $counts['failed_count']++;

                            $run->lines()->create([
                                'catalog_product_id' => $product->id,
                                'sku' => $product->sku,
                                'name' => $product->name,
                                'action' => 'failed',
                                'message' => $exception->getMessage(),
                                'payload' => [],
                            ]);
                        }
                    }
                });

            $run->forceFill([
                ...$counts,
                'finished_at' => now(),
            ])->save();

            return $run->fresh('lines');
        });
    }

    private function syncProduct(CatalogProduct $product, ?InvWarehouse $defaultWarehouse): array
    {
        $payload = [
            'catalog_product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'unit' => 'pcs',
            'sale_price' => $product->price ?? 0,
            'image_url' => $product->image_url,
            'is_active' => (bool) $product->is_active,
            'last_synced_at' => now(),
            'sync_snapshot' => [
                'catalog_product_id' => $product->id,
                'slug' => $product->slug,
                'category_id' => $product->catalog_category_id,
                'stock_at_sync' => (int) ($product->stock ?? 0),
                'synced_at' => now()->toIso8601String(),
            ],
        ];

        $item = InvItem::query()->where('catalog_product_id', $product->id)->first();

        if (! $item && filled($product->sku)) {
            $item = InvItem::query()
                ->whereNull('catalog_product_id')
                ->where('sku', $product->sku)
                ->first();
        }

        if (! $item) {
            $item = InvItem::query()->create($payload);
            $this->seedInitialBalance($item, $product, $defaultWarehouse);

            return [
                'counter' => 'created_count',
                'action' => 'created',
                'item' => $item,
                'message' => 'Da tao hang hoa moi tu san pham Catalog.',
                'payload' => $payload,
            ];
        }

        $dirtyPayload = collect($payload)
            ->except(['last_synced_at', 'sync_snapshot'])
            ->filter(fn (mixed $value, string $key): bool => (string) ($item->{$key} ?? '') !== (string) ($value ?? ''))
            ->all();

        $item->forceFill($payload)->save();

        return [
            'counter' => $dirtyPayload === [] ? 'skipped_count' : 'updated_count',
            'action' => $dirtyPayload === [] ? 'skipped' : 'updated',
            'item' => $item,
            'message' => $dirtyPayload === [] ? 'Khong co thay doi.' : 'Da cap nhat snapshot hang hoa.',
            'payload' => [
                'changed_fields' => array_keys($dirtyPayload),
                'snapshot' => $payload,
            ],
        ];
    }

    private function seedInitialBalance(InvItem $item, CatalogProduct $product, ?InvWarehouse $defaultWarehouse): void
    {
        if (! $defaultWarehouse || (int) ($product->stock ?? 0) <= 0) {
            return;
        }

        $this->stockService->createOpeningBalance(
            $item->id,
            $defaultWarehouse->id,
            (int) $product->stock,
            0,
            null,
            'catalog-sync-'.$product->id,
        );
    }
}
