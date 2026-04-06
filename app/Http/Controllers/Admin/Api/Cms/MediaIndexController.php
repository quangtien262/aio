<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsMedia;
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
        ])->values()->all();

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
            ],
        ]);
    }
}
