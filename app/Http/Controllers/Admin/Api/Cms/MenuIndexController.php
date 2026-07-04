<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Core\Cms\CmsMenuLocationRegistry;
use App\Models\CatalogCategory;
use App\Models\CmsCategory;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use Illuminate\Http\JsonResponse;

class MenuIndexController
{
    public function __invoke(CmsMenuLocationRegistry $locationRegistry): JsonResponse
    {
        $query = CmsMenu::query()->orderBy('location')->orderBy('name');

        $items = $query->get()->map(fn (CmsMenu $menu): array => [
            'id' => $menu->id,
            'name' => $menu->name,
            'location' => $menu->location,
            'items' => $menu->items ?? [],
        ])->values()->all();

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
                'locations' => $locationRegistry->all(),
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
                            'url' => '/c/'.$category->slug,
                        ])
                        ->values()
                        ->all(),
                ],
            ],
        ]);
    }
}
