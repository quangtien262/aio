<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsMedia;
use App\Models\CmsService;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;

class ServiceIndexController
{
    public function __invoke(): JsonResponse
    {
        /** @var EloquentBuilder<CmsService> $query */
        $query = CmsService::query();
        $query->with(['category:id,name', 'images.media'])->orderBy('sort_order')->orderByDesc('updated_at');

        $items = $query->get()->map(fn (CmsService $service): array => $this->serialize($service))->values()->all();

        /** @var EloquentBuilder<CmsMedia> $mediaQuery */
        $mediaQuery = CmsMedia::query();
        $mediaQuery->latest();

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
                'metrics' => [
                    'published' => collect($items)->where('status', 'published')->count(),
                    'draft' => collect($items)->where('status', 'draft')->count(),
                    'featured' => collect($items)->where('is_highlight', true)->count(),
                ],
                'media' => $mediaQuery->get(['id', 'title', 'file_path', 'file_url', 'alt_text'])
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

    private function serialize(CmsService $service): array
    {
        $images = $service->images->map(fn ($image): array => [
            'id' => $image->id,
            'cms_media_id' => $image->cms_media_id,
            'image_url' => $image->media?->file_url ?: $image->image_url,
            'alt_text' => $image->alt_text,
            'caption' => $image->caption,
            'is_featured' => $image->is_featured,
            'sort_order' => $image->sort_order,
        ])->values()->all();
        $featuredImage = collect($images)->firstWhere('is_featured', true) ?? ($images[0] ?? null);

        return [
            'id' => $service->id,
            'cms_service_category_id' => $service->cms_service_category_id,
            'category_name' => $service->category?->name,
            'title' => $service->title,
            'slug' => $service->slug,
            'status' => $service->status,
            'summary' => $service->summary,
            'content' => $service->content,
            'icon' => $service->icon,
            'button_label' => $service->button_label,
            'link_url' => $service->link_url,
            'meta_title' => $service->meta_title,
            'meta_description' => $service->meta_description,
            'meta_keywords' => $service->meta_keywords,
            'publish_at' => $service->publish_at?->toAtomString(),
            'is_featured' => $service->is_featured,
            'is_highlight' => $service->is_highlight,
            'sort_order' => $service->sort_order,
            'website_key' => $service->website_key,
            'owner_key' => $service->owner_key,
            'tenant_key' => $service->tenant_key,
            'featured_image_url' => $featuredImage['image_url'] ?? null,
            'featured_image_alt' => $featuredImage['alt_text'] ?? null,
            'images' => $images,
        ];
    }
}
