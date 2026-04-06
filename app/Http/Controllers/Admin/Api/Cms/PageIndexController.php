<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsMedia;
use App\Models\CmsPage;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;

class PageIndexController
{
    public function __invoke(): JsonResponse
    {
        /** @var EloquentBuilder<CmsPage> $query */
        $query = (new CmsPage())->newQuery();
        $query->with('featuredMedia')->orderBy('title');

        $pages = $query->get()
            ->map(fn (CmsPage $page): array => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status,
                'excerpt' => $page->excerpt,
                'body' => $page->body,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'template' => $page->template,
                'featured_media_id' => $page->featured_media_id,
                'featured_media_url' => $page->featuredMedia?->file_url,
                'publish_at' => $page->publish_at?->toAtomString(),
                'public_url' => $page->slug === 'home' ? url('/') : url('/'.$page->slug),
                'preview_url' => url('/preview/pages/'.$page->id),
            ])
            ->values()
            ->all();

        /** @var EloquentBuilder<CmsPage> $publishedQuery */
        $publishedQuery = CmsPage::query();
        /** @var EloquentBuilder<CmsPage> $draftQuery */
        $draftQuery = CmsPage::query();

        /** @var EloquentBuilder<CmsMedia> $mediaQuery */
        $mediaQuery = CmsMedia::query();

        return response()->json([
            'data' => [
                'items' => $pages,
                'total' => count($pages),
                'metrics' => [
                    'published' => $publishedQuery->where('status', 'published')->count(),
                    'draft' => $draftQuery->where('status', 'draft')->count(),
                ],
                'media' => $mediaQuery->latest()->get(['id', 'title', 'file_path', 'file_url'])->map(fn (CmsMedia $media): array => ['id' => $media->id, 'title' => $media->title, 'file_url' => $media->file_url])->values()->all(),
            ],
        ]);
    }
}
