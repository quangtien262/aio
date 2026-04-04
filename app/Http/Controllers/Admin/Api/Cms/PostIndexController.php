<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Core\Access\AdminDataScope;
use App\Http\Controllers\Admin\Api\Cms\Concerns\InteractsWithScopedCmsRecords;
use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsPost;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostIndexController
{
    use InteractsWithScopedCmsRecords;

    public function __invoke(Request $request, AdminDataScope $adminDataScope): JsonResponse
    {
        /** @var EloquentBuilder<CmsPost> $query */
        $query = (new CmsPost())->newQuery();
        $query->with(['category', 'featuredMedia'])->orderByDesc('updated_at');
        $this->applyAdminScope($query, $request, $adminDataScope);

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
            'website_key' => $post->website_key,
            'owner_key' => $post->owner_key,
            'tenant_key' => $post->tenant_key,
            'public_url' => url('/blog/'.$post->slug),
            'preview_url' => url('/preview/posts/'.$post->id),
        ])->values()->all();

        /** @var EloquentBuilder<CmsCategory> $categoryQuery */
        $categoryQuery = (new CmsCategory())->newQuery();
        $categoryQuery->orderBy('name');
        /** @var EloquentBuilder<CmsMedia> $mediaQuery */
        $mediaQuery = (new CmsMedia())->newQuery();
        $mediaQuery->latest();
        $this->applyAdminScope($categoryQuery, $request, $adminDataScope);
        $this->applyAdminScope($mediaQuery, $request, $adminDataScope);

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
                'metrics' => [
                    'published' => collect($items)->where('status', 'published')->count(),
                    'draft' => collect($items)->where('status', 'draft')->count(),
                ],
                'categories' => $categoryQuery->get(['id', 'name'])->map(fn (CmsCategory $category): array => ['label' => $category->name, 'value' => $category->id])->values()->all(),
                'media' => $mediaQuery->get(['id', 'title', 'file_url'])->map(fn (CmsMedia $media): array => ['id' => $media->id, 'title' => $media->title, 'file_url' => $media->file_url])->values()->all(),
                'scopes' => $request->user('admin')?->scopeMatrix() ?? [],
            ],
        ]);
    }
}
