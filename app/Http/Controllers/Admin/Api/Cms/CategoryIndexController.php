<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsCategory;
use Illuminate\Http\JsonResponse;

class CategoryIndexController
{
    public function __invoke(): JsonResponse
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

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
            ],
        ]);
    }
}
