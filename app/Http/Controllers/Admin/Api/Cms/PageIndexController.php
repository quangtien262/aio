<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Core\Access\AdminDataScope;
use App\Models\CmsPage;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageIndexController
{
    public function __invoke(Request $request, AdminDataScope $adminDataScope): JsonResponse
    {
        $admin = $request->user('admin');
        $query = CmsPage::query()->orderBy('title');

        if ($admin) {
            $adminDataScope->apply($query, $admin);
        }

        $pages = $query->get(['id', 'title', 'slug', 'status', 'website_key', 'owner_key', 'tenant_key'])
            ->map(fn (CmsPage $page): array => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status,
                'body' => $page->body,
                'website_key' => $page->website_key,
                'owner_key' => $page->owner_key,
                'tenant_key' => $page->tenant_key,
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

        return response()->json([
            'data' => [
                'items' => $pages,
                'total' => count($pages),
                'metrics' => [
                    'published' => $publishedQuery->where('status', 'published')->count(),
                    'draft' => $draftQuery->where('status', 'draft')->count(),
                ],
                'scopes' => $admin?->scopeMatrix() ?? [],
            ],
        ]);
    }
}
