<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsPost;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;

class PostIndexController
{
    public function __invoke(): JsonResponse
    {
        /** @var EloquentBuilder<CmsPost> $query */
        $query = (new CmsPost())->newQuery();
        $query->with(['category', 'featuredMedia'])->orderByDesc('updated_at');

        $items = $query->get()->map(fn (CmsPost $post): array => [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'status' => $post->status,
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'meta_title' => $post->meta_title,
            'meta_description' => $post->meta_description,
            'publish_at' => $post->publish_at?->toAtomString(),
            'category_id' => $post->category_id,
            'category_name' => $post->category?->name,
            'featured_media_id' => $post->featured_media_id,
            'featured_media_url' => $post->featuredMedia?->file_url,
            'public_url' => url('/tin-tuc/'.$post->slug),
            'preview_url' => url('/preview/posts/'.$post->id),
        ])->values()->all();

        /** @var EloquentBuilder<CmsCategory> $categoryQuery */
        $categoryQuery = (new CmsCategory())->newQuery();
        $categoryQuery->orderBy('name');
        /** @var EloquentBuilder<CmsMedia> $mediaQuery */
        $mediaQuery = (new CmsMedia())->newQuery();
        $mediaQuery->latest();

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
                'metrics' => [
                    'published' => collect($items)->where('status', 'published')->count(),
                    'draft' => collect($items)->where('status', 'draft')->count(),
                ],
                'categories' => $categoryQuery->get(['id', 'name'])->map(fn (CmsCategory $category): array => ['label' => $category->name, 'value' => $category->id])->values()->all(),
                'media' => $mediaQuery->get(['id', 'title', 'file_path', 'file_url'])->map(fn (CmsMedia $media): array => ['id' => $media->id, 'title' => $media->title, 'file_url' => $media->file_url])->values()->all(),
            ],
        ]);
    }
}
