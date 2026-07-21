<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsMedia;
use App\Models\CmsPartner;
use Illuminate\Http\JsonResponse;

class PartnerIndexController
{
    public function __invoke(): JsonResponse
    {
        $items = CmsPartner::query()
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (CmsPartner $partner): array => $this->serialize($partner))
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
                'metrics' => [
                    'published' => collect($items)->where('status', 'published')->count(),
                    'draft' => collect($items)->where('status', 'draft')->count(),
                    'featured' => collect($items)->where('is_featured', true)->count(),
                ],
                'media' => CmsMedia::query()
                    ->latest()
                    ->get(['id', 'title', 'file_path', 'file_url', 'alt_text'])
                    ->map(fn (CmsMedia $media): array => [
                        'id' => $media->id,
                        'title' => $media->title,
                        'file_url' => $media->file_url,
                        'alt_text' => $media->alt_text,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    private function serialize(CmsPartner $partner): array
    {
        return [
            'id' => $partner->id,
            'title' => $partner->title,
            'slug' => $partner->slug,
            'description' => $partner->description,
            'image_url' => $partner->image_url,
            'image_alt' => $partner->image_alt,
            'link_url' => $partner->link_url,
            'status' => $partner->status,
            'publish_at' => $partner->publish_at?->toAtomString(),
            'is_featured' => $partner->is_featured,
            'sort_order' => $partner->sort_order,
            'website_key' => $partner->website_key,
        ];
    }
}
