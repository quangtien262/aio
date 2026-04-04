<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Access\AdminDataScope;
use App\Models\SiteBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteBannerIndexController
{
    public function __invoke(Request $request, AdminDataScope $adminDataScope): JsonResponse
    {
        $query = SiteBanner::query()->orderBy('placement')->orderBy('sort_order')->orderByDesc('updated_at');

        if ($admin = $request->user('admin')) {
            $adminDataScope->apply($query, $admin);
        }

        $items = $query->get()->map(fn (SiteBanner $banner): array => [
            'id' => $banner->id,
            'theme_key' => $banner->theme_key,
            'placement' => $banner->placement,
            'title' => $banner->title,
            'subtitle' => $banner->subtitle,
            'image_url' => $banner->image_url,
            'link_url' => $banner->link_url,
            'badge' => $banner->badge,
            'eyebrow' => data_get($banner->metadata, 'eyebrow'),
            'summary' => data_get($banner->metadata, 'summary'),
            'button_label' => data_get($banner->metadata, 'button_label'),
            'sort_order' => $banner->sort_order,
            'is_active' => $banner->is_active,
            'website_key' => $banner->website_key,
            'owner_key' => $banner->owner_key,
            'tenant_key' => $banner->tenant_key,
        ])->values()->all();

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
            ],
        ]);
    }
}
