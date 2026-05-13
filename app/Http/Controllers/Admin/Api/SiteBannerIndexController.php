<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\SiteBanner;
use App\Models\SiteProfile;
use Illuminate\Http\JsonResponse;

class SiteBannerIndexController
{
    public function __invoke(): JsonResponse
    {
        $activeThemeKey = (string) (SiteProfile::query()->first()?->active_theme_key ?? '');

        $query = SiteBanner::query()
            ->when($activeThemeKey !== '', function ($builder) use ($activeThemeKey) {
                $builder->where(function ($scopedQuery) use ($activeThemeKey) {
                    $scopedQuery
                        ->whereNull('theme_key')
                        ->orWhere('theme_key', $activeThemeKey);
                });
            })
            ->orderBy('placement')
            ->orderBy('sort_order')
            ->orderByDesc('updated_at');

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
            'image_position' => data_get($banner->metadata, 'image_position', 'center'),
            'show_caption' => (bool) data_get($banner->metadata, 'show_caption', true),
            'sort_order' => $banner->sort_order,
            'is_active' => $banner->is_active,
        ])->values()->all();

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
                'active_theme_key' => $activeThemeKey,
            ],
        ]);
    }
}
