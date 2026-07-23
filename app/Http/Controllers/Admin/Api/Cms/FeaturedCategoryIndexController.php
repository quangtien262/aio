<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CatalogCategory;
use App\Models\CmsCategory;
use App\Models\CmsFeaturedCategory;
use App\Models\CmsPage;
use App\Support\FrontendRouteUrl;
use Illuminate\Http\JsonResponse;

class FeaturedCategoryIndexController
{
    private const DEFAULT_LOCATIONS = [
        ['label' => 'Home featured categories', 'value' => 'home-featured-categories'],
        ['label' => 'Footer featured categories', 'value' => 'footer-featured-categories'],
    ];

    public function __invoke(): JsonResponse
    {
        $items = CmsFeaturedCategory::query()
            ->orderBy('location')
            ->orderBy('name')
            ->get()
            ->map(fn (CmsFeaturedCategory $group): array => [
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
                            'url' => $page->slug === 'home' ? '/' : FrontendRouteUrl::pagePath($page->slug),
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
                            'url' => FrontendRouteUrl::categoryPath($category->slug),
                        ])
                        ->values()
                        ->all(),
                    'postCategories' => CmsCategory::query()
                        ->orderBy('name')
                        ->get()
                        ->map(fn (CmsCategory $category): array => [
                            'label' => $category->name,
                            'value' => (string) $category->id,
                            'url' => FrontendRouteUrl::blogCategoryPath($category->slug),
                        ])
                        ->values()
                        ->all(),
                ],
            ],
        ]);
    }
}
