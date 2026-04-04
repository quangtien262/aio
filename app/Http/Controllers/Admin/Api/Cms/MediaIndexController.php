<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Core\Access\AdminDataScope;
use App\Http\Controllers\Admin\Api\Cms\Concerns\InteractsWithScopedCmsRecords;
use App\Models\CmsMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaIndexController
{
    use InteractsWithScopedCmsRecords;

    public function __invoke(Request $request, AdminDataScope $adminDataScope): JsonResponse
    {
        $query = CmsMedia::query()->latest();
        $this->applyAdminScope($query, $request, $adminDataScope);

        $items = $query->get()->map(fn (CmsMedia $media): array => [
            'id' => $media->id,
            'title' => $media->title,
            'file_path' => $media->file_path,
            'file_url' => $media->file_url,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'alt_text' => $media->alt_text,
            'website_key' => $media->website_key,
            'owner_key' => $media->owner_key,
            'tenant_key' => $media->tenant_key,
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
