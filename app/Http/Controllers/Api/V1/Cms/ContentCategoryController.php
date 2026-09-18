<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Models\CmsCategory;
use Illuminate\Http\JsonResponse;

class ContentCategoryController
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => CmsCategory::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id'])]);
    }
}
