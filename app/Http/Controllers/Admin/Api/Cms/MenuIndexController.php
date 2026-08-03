<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Core\Cms\CmsMenuLocationRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsProjectCategory;
use App\Models\CmsService;
use App\Models\CmsServiceCategory;
use App\Models\LandingPage;
use App\Support\FrontendRouteUrl;
use App\Support\Localization\AdminLocalizedContentList;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuIndexController
{
    public function __construct(
        private readonly AdminLocalizedContentList $localizedList,
    ) {}

    public function __invoke(
        Request $request,
        CmsMenuLocationRegistry $locationRegistry,
    ): JsonResponse {
        $query = CmsMenu::query()->orderBy('location')->orderBy('name');

        $items = $query->get()->map(fn (CmsMenu $menu): array => [
            'id' => $menu->id,
            'name' => $menu->name,
            'location' => $menu->location,
            'items' => $menu->items ?? [],
        ])->values()->all();
        $items = $this->localizedList->overlay(
            $items,
            'cms_menu',
            $request->query('locale'),
        );

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
                            'url' => $page->slug === 'home' ? '/' : FrontendRouteUrl::pagePath($page->slug),
                        ])
                        ->values()
                        ->all(),
                    'landingPages' => LandingPage::query()
                        ->where(
                            'website_key',
                            app(SiteContext::class)->websiteKey(),
                        )
                        ->where('is_home', false)
                        ->with(['data' => fn ($query) => $query
                            ->where('locale', config('localization.source_locale', 'vi'))])
                        ->orderBy('sort_order')
                        ->orderBy('slug')
                        ->get()
                        ->map(function (LandingPage $page): array {
                            $translation = $page->data->first();
                            $slug = (string) ($translation?->slug ?: $page->slug);

                            return [
                                'label' => $translation?->title ?: $slug,
                                'value' => (string) $page->id,
                                'url' => FrontendRouteUrl::landingPath($slug),
                            ];
                        })
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
                    'products' => CatalogProduct::query()
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get()
                        ->map(fn (CatalogProduct $product): array => [
                            'label' => $product->name,
                            'value' => (string) $product->id,
                            'url' => FrontendRouteUrl::productPath($product->slug),
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
                    'posts' => CmsPost::query()
                        ->orderBy('title')
                        ->get()
                        ->map(fn (CmsPost $post): array => [
                            'label' => $post->title,
                            'value' => (string) $post->id,
                            'url' => FrontendRouteUrl::postPath($post->slug),
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
                            'url' => FrontendRouteUrl::serviceCategoryPath($category->slug),
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
                            'url' => FrontendRouteUrl::servicePath($service->slug),
                        ])
                        ->values()
                        ->all(),
                    'projectCategories' => CmsProjectCategory::query()
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get()
                        ->map(fn (CmsProjectCategory $category): array => [
                            'label' => $category->name,
                            'value' => (string) $category->id,
                            'url' => FrontendRouteUrl::projectCategoryPath($category->slug),
                        ])
                        ->values()
                        ->all(),
                    'projects' => CmsProject::query()
                        ->orderBy('sort_order')
                        ->orderBy('title')
                        ->get()
                        ->map(fn (CmsProject $project): array => [
                            'label' => $project->title,
                            'value' => (string) $project->id,
                            'url' => FrontendRouteUrl::projectPath($project->slug),
                        ])
                        ->values()
                        ->all(),
                ],
            ],
        ]);
    }
}
