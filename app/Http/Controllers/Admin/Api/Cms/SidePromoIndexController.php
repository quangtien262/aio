<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CatalogCategory;
use App\Models\CmsCategory;
use App\Models\CmsPage;
use App\Models\CmsSidePromo;
use Illuminate\Http\JsonResponse;

class SidePromoIndexController
{
    private const DEFAULT_LOCATIONS = [
        ['label' => 'Home hero side promos', 'value' => 'home-hero-side-promos'],
        ['label' => 'Home secondary side promos', 'value' => 'home-secondary-side-promos'],
    ];

    public function __invoke(): JsonResponse
    {
        $items = CmsSidePromo::query()
            ->orderBy('location')
            ->orderBy('name')
            ->get()
            ->map(fn (CmsSidePromo $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'location' => $group->location,
                'items' => $group->items ?? [],
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
                'locations' => self::DEFAULT_LOCATIONS,
                'linkOptions' => [
                    'pages' => CmsPage::query()
                        ->orderBy('title')
                        ->get()
                        ->map(fn (CmsPage $page): array => [
                            'label' => $page->title,
                            'value' => (string) $page->id,
                            'url' => $page->slug === 'home' ? '/' : '/'.$page->slug,
                        ])
                        ->values()
                        ->all(),
                    'productCategories' => CatalogCategory::query()
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get()
                        ->map(fn (CatalogCategory $category): array => [
                            'label' => $category->name,
                            'value' => (string) $category->id,
                            'url' => '/danh-muc/'.$category->slug,
                        ])
                        ->values()
                        ->all(),
                    'postCategories' => CmsCategory::query()
                        ->orderBy('name')
                        ->get()
                        ->map(fn (CmsCategory $category): array => [
                            'label' => $category->name,
                            'value' => (string) $category->id,
                            'url' => '/tin-tuc?category='.$category->slug,
                        ])
                        ->values()
                        ->all(),
                ],
            ],
        ]);
    }
}
