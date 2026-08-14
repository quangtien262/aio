<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsCategory;
use App\Support\Localization\AdminLocalizedContentList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryIndexController
{
    public function __invoke(Request $request, AdminLocalizedContentList $localizedList): JsonResponse
    {
        $query = CmsCategory::query()->orderBy('name');

        $items = $query->get()->map(fn (CmsCategory $category): array => [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'meta_title' => $category->meta_title,
            'meta_description' => $category->meta_description,
            'parent_id' => $category->parent_id,
        ])->values()->all();

        $items = $localizedList->overlay($items, 'cms_category', $request->query('locale'));

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
            ],
        ]);
    }
}
