<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsMedia;
use App\Models\CmsProject;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;

class ProjectIndexController
{
    public function __invoke(): JsonResponse
    {
        /** @var EloquentBuilder<CmsProject> $query */
        $query = CmsProject::query();
        $query->with(['category', 'images'])->orderBy('sort_order')->orderByDesc('updated_at');

        $items = $query->get()->map(fn (CmsProject $project): array => $this->serialize($project))->values()->all();

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

    private function serialize(CmsProject $project): array
    {
        $images = $project->images->map(fn ($image): array => [
            'id' => $image->id,
            'cms_media_id' => $image->cms_media_id,
            'image_url' => $image->image_url,
            'alt_text' => $image->alt_text,
            'caption' => $image->caption,
            'is_featured' => $image->is_featured,
            'sort_order' => $image->sort_order,
        ])->values()->all();
        $featuredImage = collect($images)->firstWhere('is_featured', true) ?? ($images[0] ?? null);

        return [
            'id' => $project->id,
            'cms_project_category_id' => $project->cms_project_category_id,
            'category_name' => $project->category?->name,
            'title' => $project->title,
            'slug' => $project->slug,
            'status' => $project->status,
            'summary' => $project->summary,
            'content' => $project->content,
            'button_label' => $project->button_label,
            'link_url' => $project->link_url,
            'meta_title' => $project->meta_title,
            'meta_description' => $project->meta_description,
            'publish_at' => $project->publish_at?->toAtomString(),
            'is_featured' => $project->is_featured,
            'is_highlight' => $project->is_highlight,
            'sort_order' => $project->sort_order,
            'website_key' => $project->website_key,
            'featured_image_url' => $featuredImage['image_url'] ?? null,
            'featured_image_alt' => $featuredImage['alt_text'] ?? null,
            'images' => $images,
        ];
    }
}
