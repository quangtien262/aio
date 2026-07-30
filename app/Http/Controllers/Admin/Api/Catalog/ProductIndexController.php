<?php

namespace App\Http\Controllers\Admin\Api\Catalog;

use App\Models\CatalogProduct;
use App\Support\FrontendLocalization;
use App\Support\FrontendRouteUrl;
use App\Support\Localization\AdminLocalizedContentList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductIndexController
{
    public function __construct(
        private readonly AdminLocalizedContentList $localizedList,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $query = CatalogProduct::query()->with(['category', 'images'])->orderBy('name');

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
                'meta_title' => $product->meta_title,
                'meta_description' => $product->meta_description,
                'meta_keywords' => $product->meta_keywords,
                'highlights' => $product->highlights,
                'usage_terms' => $product->usage_terms,
                'usage_location' => $product->usage_location,
                'image_url' => $product->image_url,
                'gallery_images' => $product->images->pluck('image_url')->all(),
                'sold_count' => $product->sold_count,
                'deal_end_at' => $product->deal_end_at?->toIso8601String(),
                'is_featured' => $product->is_featured,
                'is_highlight' => $product->is_highlight,
                'sort_order' => $product->sort_order,
                'is_active' => $product->is_active,
                'public_url' => $product->slug
                    ? FrontendRouteUrl::product($product->slug, FrontendLocalization::defaultLocale())
                    : null,
                'preview_url' => FrontendRouteUrl::previewProduct($product->id, FrontendLocalization::defaultLocale()),
            ])
            ->values()
            ->all();
        $products = $this->localizedList->overlay(
            $products,
            'catalog_product',
            $request->query('locale'),
        );

        return response()->json([
            'data' => [
                'items' => $products,
                'total' => count($products),
                'metrics' => [
                    'in_stock' => CatalogProduct::query()->where('stock', '>', 0)->count(),
                    'inventory_units' => (int) CatalogProduct::query()->sum('stock'),
                ],
            ],
        ]);
    }
}
