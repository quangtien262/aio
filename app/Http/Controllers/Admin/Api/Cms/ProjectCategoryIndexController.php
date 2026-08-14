<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsProjectCategory;
use App\Support\Localization\AdminLocalizedContentList;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectCategoryIndexController
{
    public function __invoke(Request $request, AdminLocalizedContentList $localizedList): JsonResponse
    {
        /** @var EloquentBuilder<CmsProjectCategory> $query */
        $query = CmsProjectCategory::query()
            ->withCount(['children', 'projects'])
            ->with('parent:id,name')
            ->orderBy('sort_order')
            ->orderBy('name');

        $items = $query->get()->map(fn (CmsProjectCategory $category): array => [
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
            'projects_count' => $category->projects_count,
        ])->values()->all();

        $items = $localizedList->overlay($items, 'cms_project_category', $request->query('locale'));

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
            ],
        ]);
    }
}
