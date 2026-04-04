<?php

namespace App\Http\Controllers\Admin\Api\Catalog;

use App\Core\Access\AdminDataScope;
use App\Models\CatalogCategory;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryIndexController
{
    public function __invoke(Request $request, AdminDataScope $adminDataScope): JsonResponse
    {
        /** @var EloquentBuilder<CatalogCategory> $query */
        $query = CatalogCategory::query()
            ->withCount(['children', 'products'])
            ->with('parent:id,name')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($admin = $request->user('admin')) {
            $adminDataScope->apply($query, $admin);
        }

        $items = $query->get()->map(fn (CatalogCategory $category): array => [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'parent_name' => $category->parent?->name,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image_url' => $category->image_url,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
            'children_count' => $category->children_count,
            'products_count' => $category->products_count,
            'website_key' => $category->website_key,
            'owner_key' => $category->owner_key,
            'tenant_key' => $category->tenant_key,
        ])->values()->all();

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
            ],
        ]);
    }
}
