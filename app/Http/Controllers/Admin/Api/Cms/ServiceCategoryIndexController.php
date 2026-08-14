<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsServiceCategory;
use App\Support\Localization\AdminLocalizedContentList;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceCategoryIndexController
{
    public function __invoke(Request $request, AdminLocalizedContentList $localizedList): JsonResponse
    {
        /** @var EloquentBuilder<CmsServiceCategory> $query */
        $query = CmsServiceCategory::query()
            ->withCount(['children', 'services'])
            ->with('parent:id,name')
            ->orderBy('sort_order')
            ->orderBy('name');

        $items = $query->get()->map(fn (CmsServiceCategory $category): array => [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'parent_name' => $category->parent?->name,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'meta_title' => $category->meta_title,
            'meta_description' => $category->meta_description,
            'image_url' => $category->image_url,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
            'children_count' => $category->children_count,
            'services_count' => $category->services_count,
        ])->values()->all();

        $items = $localizedList->overlay($items, 'cms_service_category', $request->query('locale'));

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
            ],
        ]);
    }
}
