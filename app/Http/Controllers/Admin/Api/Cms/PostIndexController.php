<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsPost;
use App\Models\CmsTag;
use App\Support\FrontendLocalization;
use App\Support\CmsPostTags;
use App\Support\Localization\AdminLocalizedContentList;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostIndexController
{
    public function __construct(
        private readonly AdminLocalizedContentList $localizedList,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var EloquentBuilder<CmsPost> $query */
        $query = (new CmsPost)->newQuery();
        $tagsAvailable = CmsPostTags::available();
        $query->with($tagsAvailable ? ['category', 'featuredMedia', 'tags'] : ['category', 'featuredMedia'])->orderByDesc('updated_at');

        $topicsAvailable = \App\Models\CmsTopic::available();
        if ($topicsAvailable) $query->with('topics');
        $items = $query->get()->map(fn (CmsPost $post): array => [
            'id' => $post->id,
            'topic_ids' => $topicsAvailable ? $post->topics->pluck('id')->all() : [],
            'tags' => $tagsAvailable ? $post->tags->pluck('name')->all() : [],
            'title' => $post->title,
            'slug' => $post->slug,
            'status' => $post->status,
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'meta_title' => $post->meta_title,
            'meta_description' => $post->meta_description,
            'meta_keywords' => $post->meta_keywords,
            'publish_at' => $post->publish_at?->toAtomString(),
            'category_id' => $post->category_id,
            'category_name' => $post->category?->name,
            'is_highlight' => $post->is_highlight,
            'featured_media_id' => $post->featured_media_id,
            'featured_media_url' => $post->featuredMedia?->file_url,
            'public_url' => route('site.blog.show', array_merge(FrontendLocalization::routeParameterDefaults(null), ['slug' => $post->slug])),
            'preview_url' => route('site.preview.posts', array_merge(FrontendLocalization::routeParameterDefaults(null), ['post' => $post->id])),
        ])->values()->all();
        $items = $this->localizedList->overlay(
            $items,
            'cms_post',
            $request->query('locale'),
        );

        /** @var EloquentBuilder<CmsCategory> $categoryQuery */
        $categoryQuery = (new CmsCategory)->newQuery();
        $categoryQuery->orderBy('name');
        /** @var EloquentBuilder<CmsMedia> $mediaQuery */
        $mediaQuery = (new CmsMedia)->newQuery();
        $mediaQuery->latest();

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
                'metrics' => [
                    'published' => collect($items)->where('status', 'published')->count(),
                    'draft' => collect($items)->where('status', 'draft')->count(),
                    'highlight' => collect($items)->where('is_highlight', true)->count(),
                ],
                'topicsAvailable' => $topicsAvailable,
                'topics' => $topicsAvailable ? \App\Models\CmsTopic::orderBy('name')->get()->map(fn ($topic) => ['value' => $topic->id, 'label' => $topic->name])->all() : [],
                'tagsAvailable' => $tagsAvailable,
                'tagOptions' => $tagsAvailable ? CmsTag::query()->orderBy('name')->get()->map(fn ($tag): array => ['id' => $tag->id, 'label' => $tag->name, 'value' => $tag->name])->all() : [],
                'categories' => $categoryQuery->get(['id', 'name'])->map(fn (CmsCategory $category): array => ['label' => $category->name, 'value' => $category->id])->values()->all(),
                'media' => $mediaQuery->get(['id', 'title', 'file_path', 'file_url'])->map(fn (CmsMedia $media): array => ['id' => $media->id, 'title' => $media->title, 'file_url' => $media->file_url])->values()->all(),
            ],
        ]);
    }
}
