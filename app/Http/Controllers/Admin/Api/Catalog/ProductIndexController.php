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
        $query = CatalogProduct::query()->with(['category', 'images'])->orderBy('name');

        if ($admin) {
            $adminDataScope->apply($query, $admin);
        }

        $products = $query->get()
            ->map(fn (CatalogProduct $product): array => [
                'id' => $product->id,
                'catalog_category_id' => $product->catalog_category_id,
                'category_name' => $product->category?->name,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'price' => (float) $product->price,
                'original_price' => $product->original_price !== null ? (float) $product->original_price : null,
                'stock' => $product->stock,
                'short_description' => $product->short_description,
                'detail_content' => $product->detail_content,
                'highlights' => $product->highlights,
                'usage_terms' => $product->usage_terms,
                'usage_location' => $product->usage_location,
                'image_url' => $product->image_url,
                'gallery_images' => $product->images->pluck('image_url')->all(),
                'sold_count' => $product->sold_count,
                'deal_end_at' => $product->deal_end_at?->toIso8601String(),
                'is_featured' => $product->is_featured,
                'sort_order' => $product->sort_order,
                'is_active' => $product->is_active,
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
