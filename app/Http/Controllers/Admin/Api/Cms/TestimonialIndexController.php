<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsMedia;
use App\Models\CmsTestimonial;
use App\Support\Localization\AdminLocalizedContentList;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestimonialIndexController
{
    public function __construct(
        private readonly AdminLocalizedContentList $localizedList,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var EloquentBuilder<CmsTestimonial> $query */
        $query = CmsTestimonial::query();
        $query->orderBy('sort_order')->orderByDesc('updated_at');

        $items = $query->get()->map(fn (CmsTestimonial $testimonial): array => $this->serialize($testimonial))->values()->all();
        $items = $this->localizedList->overlay(
            $items,
            'cms_testimonial',
            $request->query('locale'),
        );

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

    private function serialize(CmsTestimonial $testimonial): array
    {
        return [
            'id' => $testimonial->id,
            'name' => $testimonial->name,
            'role' => $testimonial->role,
            'company' => $testimonial->company,
            'quote' => $testimonial->quote,
            'image_url' => $testimonial->image_url,
            'image_alt' => $testimonial->image_alt,
            'link_url' => $testimonial->link_url,
            'status' => $testimonial->status,
            'publish_at' => $testimonial->publish_at?->toAtomString(),
            'is_featured' => $testimonial->is_featured,
            'sort_order' => $testimonial->sort_order,
            'website_key' => $testimonial->website_key,
        ];
    }
}
