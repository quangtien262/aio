<?php

namespace App\Http\Controllers\Admin\Api\Catalog;

use App\Core\Access\AdminDataScope;
use App\Models\CatalogProduct;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductIndexController
{
    public function __invoke(Request $request, AdminDataScope $adminDataScope): JsonResponse
    {
        $admin = $request->user('admin');
        $query = CatalogProduct::query()->orderBy('name');

        if ($admin) {
            $adminDataScope->apply($query, $admin);
        }

        $products = $query->get(['id', 'name', 'sku', 'price', 'stock', 'website_key', 'owner_key', 'tenant_key'])
            ->map(fn (CatalogProduct $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (float) $product->price,
                'stock' => $product->stock,
                'website_key' => $product->website_key,
                'owner_key' => $product->owner_key,
                'tenant_key' => $product->tenant_key,
            ])
            ->values()
            ->all();

        /** @var EloquentBuilder<CatalogProduct> $inStockQuery */
        $inStockQuery = CatalogProduct::query();
        /** @var EloquentBuilder<CatalogProduct> $inventoryUnitsQuery */
        $inventoryUnitsQuery = CatalogProduct::query();

        if ($admin) {
            $adminDataScope->apply($inStockQuery, $admin);
            $adminDataScope->apply($inventoryUnitsQuery, $admin);
        }

        return response()->json([
            'data' => [
                'items' => $products,
                'total' => count($products),
                'metrics' => [
                    'in_stock' => $inStockQuery->where('stock', '>', 0)->count(),
                    'inventory_units' => (int) $inventoryUnitsQuery->sum('stock'),
                ],
                'scopes' => $admin?->scopeMatrix() ?? [],
            ],
        ]);
    }
}
