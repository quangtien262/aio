<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsMedia;
use App\Models\CmsMediaFolder;
use Illuminate\Http\JsonResponse;

class MediaIndexController
{
    public function __invoke(): JsonResponse
    {
        $query = CmsMedia::query()->latest();

        $items = $query->get()->map(fn (CmsMedia $media): array => [
            'id' => $media->id,
            'title' => $media->title,
            'file_path' => $media->file_path,
            'file_url' => $media->file_url,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'alt_text' => $media->alt_text,
            'folder_path' => $media->folder_path,
        ])->values()->all();
        $folders = CmsMediaFolder::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'path', 'sort_order'])
            ->map(fn (CmsMediaFolder $folder): array => [
                'id' => $folder->id,
                'name' => $folder->name,
                'path' => $folder->path,
                'sort_order' => $folder->sort_order,
                'count' => collect($items)->where('folder_path', $folder->path)->count(),
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'items' => $items,
                'folders' => $folders,
                'total' => count($items),
            ],
        ]);
    }
}
