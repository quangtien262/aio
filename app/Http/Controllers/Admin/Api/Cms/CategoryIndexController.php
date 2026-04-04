<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Core\Access\AdminDataScope;
use App\Http\Controllers\Admin\Api\Cms\Concerns\InteractsWithScopedCmsRecords;
use App\Models\CmsCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryIndexController
{
    use InteractsWithScopedCmsRecords;

    public function __invoke(Request $request, AdminDataScope $adminDataScope): JsonResponse
    {
        $query = CmsCategory::query()->orderBy('name');
        $this->applyAdminScope($query, $request, $adminDataScope);

        $items = $query->get()->map(fn (CmsCategory $category): array => [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'meta_title' => $category->meta_title,
            'meta_description' => $category->meta_description,
            'parent_id' => $category->parent_id,
            'website_key' => $category->website_key,
            'owner_key' => $category->owner_key,
            'tenant_key' => $category->tenant_key,
        ])->values()->all();

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
                'scopes' => $request->user('admin')?->scopeMatrix() ?? [],
            ],
        ]);
    }
}
