<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Core\Access\AdminDataScope;
use App\Http\Controllers\Admin\Api\Cms\Concerns\InteractsWithScopedCmsRecords;
use App\Models\CmsMedia;
use App\Models\CmsPage;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageIndexController
{
    use InteractsWithScopedCmsRecords;

    public function __invoke(Request $request, AdminDataScope $adminDataScope): JsonResponse
    {
        $admin = $request->user('admin');
        /** @var EloquentBuilder<CmsPage> $query */
        $query = (new CmsPage())->newQuery();
        $query->with('featuredMedia')->orderBy('title');

        if ($admin) {
            $adminDataScope->apply($query, $admin);
        }

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
                'website_key' => $page->website_key,
                'owner_key' => $page->owner_key,
                'tenant_key' => $page->tenant_key,
                'public_url' => $page->slug === 'home' ? url('/') : url('/'.$page->slug),
                'preview_url' => url('/preview/pages/'.$page->id),
            ])
            ->values()
            ->all();

        /** @var EloquentBuilder<CmsPage> $publishedQuery */
        $publishedQuery = CmsPage::query();
        /** @var EloquentBuilder<CmsPage> $draftQuery */
        $draftQuery = CmsPage::query();

        if ($admin) {
            $adminDataScope->apply($publishedQuery, $admin);
            $adminDataScope->apply($draftQuery, $admin);
        }

        /** @var EloquentBuilder<CmsMedia> $mediaQuery */
        $mediaQuery = CmsMedia::query();

        if ($admin) {
            $adminDataScope->apply($mediaQuery, $admin);
        }

        return response()->json([
            'data' => [
                'items' => $pages,
                'total' => count($pages),
                'metrics' => [
                    'published' => $publishedQuery->where('status', 'published')->count(),
                    'draft' => $draftQuery->where('status', 'draft')->count(),
                ],
                'media' => $mediaQuery->latest()->get(['id', 'title', 'file_url'])->map(fn (CmsMedia $media): array => ['id' => $media->id, 'title' => $media->title, 'file_url' => $media->file_url])->values()->all(),
                'scopes' => $admin?->scopeMatrix() ?? [],
            ],
        ]);
    }
}
