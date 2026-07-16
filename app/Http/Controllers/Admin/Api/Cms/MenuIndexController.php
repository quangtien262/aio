<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Core\Cms\CmsMenuLocationRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\CmsService;
use App\Models\CmsServiceCategory;
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
                    'products' => CatalogProduct::query()
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get()
                        ->map(fn (CatalogProduct $product): array => [
                            'label' => $product->name,
                            'value' => (string) $product->id,
                            'url' => '/san-pham/'.$product->slug,
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
                    'posts' => CmsPost::query()
                        ->orderBy('title')
                        ->get()
                        ->map(fn (CmsPost $post): array => [
                            'label' => $post->title,
                            'value' => (string) $post->id,
                            'url' => '/n/'.$post->slug,
                        ])
                        ->values()
                        ->all(),
                    'serviceCategories' => CmsServiceCategory::query()
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get()
                        ->map(fn (CmsServiceCategory $category): array => [
                            'label' => $category->name,
                            'value' => (string) $category->id,
                            'url' => '/s/'.$category->slug,
                        ])
                        ->values()
                        ->all(),
                    'services' => CmsService::query()
                        ->orderBy('sort_order')
                        ->orderBy('title')
                        ->get()
                        ->map(fn (CmsService $service): array => [
                            'label' => $service->title,
                            'value' => (string) $service->id,
                            'url' => '/ser/'.$service->slug,
                        ])
                        ->values()
                        ->all(),
                ],
            ],
        ]);
    }
}
