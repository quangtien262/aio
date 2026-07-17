<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsProjectCategory;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;

class ProjectCategoryIndexController
{
    public function __invoke(): JsonResponse
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
            'image_url' => $category->image_url,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
            'children_count' => $category->children_count,
            'projects_count' => $category->projects_count,
        ])->values()->all();

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
            ],
        ]);
    }
}
