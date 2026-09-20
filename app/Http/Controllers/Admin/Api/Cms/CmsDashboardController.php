<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\{CmsPage, CmsPost, CmsMedia, CmsMenu};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CmsDashboardController
{
    public function __invoke(Request $request): JsonResponse
    {
        $admin = $request->user('admin');
        $canReadPosts = $admin?->hasPermission('cms.post.view');
        $posts = null;
        $recent = [];
        if ($canReadPosts) {
            $posts = [
                'total' => CmsPost::count(),
                'draft' => CmsPost::where('status', 'draft')->count(),
                'scheduled' => CmsPost::where('status', 'published')->where('publish_at', '>', now())->count(),
                'published' => CmsPost::where('status', 'published')->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))->count(),
            ];
            $recent = CmsPost::orderByDesc('updated_at')->orderByDesc('id')->limit(6)
                ->get(['id', 'title', 'status', 'publish_at', 'updated_at']);
        }
        return response()->json(['data' => [
            'pages' => CmsPage::count(),
            'media' => $admin?->hasPermission('cms.media.manage') ? CmsMedia::count() : null,
            'menus' => $admin?->hasPermission('cms.menu.manage') ? CmsMenu::count() : null,
            'posts' => $posts,
            'recent_posts' => $recent,
        ]]);
    }
}
