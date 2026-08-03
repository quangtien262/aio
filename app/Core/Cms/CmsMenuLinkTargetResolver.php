<?php

namespace App\Core\Cms;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsPage;
use App\Models\CmsPageTranslation;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsProjectCategory;
use App\Models\CmsService;
use App\Models\CmsServiceCategory;
use App\Models\ContentTranslation;
use App\Models\LandingPageData;
use App\Support\FrontendRouteUrl;
use App\Support\Localization\LocalizedRouteRegistry;

final class CmsMenuLinkTargetResolver
{
    /**
     * @var array<string, array<string, array{resource_type:string,resource_id:string}>>
     */
    private array $pathIdentityCache = [];

    public function __construct(
        private readonly CmsMenuLinkRegistry $links,
        private readonly LocalizedRouteRegistry $routes,
    ) {}

    /**
     * Resolve explicit identity first, then recover legacy URL-only nodes.
     *
     * @return array{resource_type:string,resource_id:string}|null
     */
    public function identity(
        array $item,
        string $websiteKey,
        string $sourceLocale,
        string $resourcePath,
    ): ?array {
        $identity = $this->links->identity($item);

        if ($identity !== null) {
            return $identity;
        }

        $registered = $this->routes->resolvePublic(
            $sourceLocale,
            $resourcePath,
            $websiteKey,
        );

        if ($registered !== null && $this->links->linkType(
            (string) $registered->resource_type,
        ) !== null) {
            return [
                'resource_type' => (string) $registered->resource_type,
                'resource_id' => (string) $registered->resource_id,
            ];
        }

        return $this->pathIdentities($websiteKey, $sourceLocale)[$resourcePath]
            ?? null;
    }

    public function specialLinkType(string $resourcePath): ?string
    {
        $path = $this->normalizePath($resourcePath);

        return match ($path) {
            '/' => 'home',
            '/contact' => 'contact',
            '/c' => 'post-index',
            '/s' => 'service-index',
            '/pj' => 'project-index',
            '/bds' => 'real-estate-index',
            default => $path === FrontendRouteUrl::catalogSearchPath()
                ? 'catalog-index'
                : null,
        };
    }

    /**
     * @return array<string, array{resource_type:string,resource_id:string}>
     */
    private function pathIdentities(
        string $websiteKey,
        string $sourceLocale,
    ): array {
        $cacheKey = strtolower($websiteKey).'|'.strtolower($sourceLocale);

        if (array_key_exists($cacheKey, $this->pathIdentityCache)) {
            return $this->pathIdentityCache[$cacheKey];
        }

        $identities = [];

        CmsPageTranslation::query()
            ->withoutGlobalScopes()
            ->where('website_key', $websiteKey)
            ->where('locale', $sourceLocale)
            ->get(['cms_page_id', 'slug'])
            ->each(function (CmsPageTranslation $translation) use (&$identities): void {
                $identities[FrontendRouteUrl::pagePath($translation->slug)] = [
                    'resource_type' => 'cms_page',
                    'resource_id' => (string) $translation->cms_page_id,
                ];
            });

        // Compatibility for a legacy Page that predates its source
        // translation row.
        CmsPage::query()
            ->withoutGlobalScopes()
            ->where('website_key', $websiteKey)
            ->get(['id', 'slug'])
            ->each(function (CmsPage $page) use (&$identities): void {
                $identities[FrontendRouteUrl::pagePath($page->slug)] ??= [
                    'resource_type' => 'cms_page',
                    'resource_id' => (string) $page->id,
                ];
            });

        LandingPageData::query()
            ->withoutGlobalScopes()
            ->where('locale', $sourceLocale)
            ->whereHas('landingPage', fn ($query) => $query
                ->where('website_key', $websiteKey)
                ->where('is_home', false))
            ->get(['landing_page_id', 'slug'])
            ->each(function (LandingPageData $translation) use (&$identities): void {
                $identities[FrontendRouteUrl::landingPath($translation->slug)] = [
                    'resource_type' => 'landing_page',
                    'resource_id' => (string) $translation->landing_page_id,
                ];
            });

        ContentTranslation::query()
            ->withoutGlobalScopes()
            ->where('website_key', $websiteKey)
            ->where('locale', $sourceLocale)
            ->whereNotNull('slug')
            ->whereIn('resource_type', $this->resourceTypesWithRoutes())
            ->get(['resource_type', 'resource_id', 'slug'])
            ->each(function (ContentTranslation $translation) use (
                &$identities,
                $sourceLocale,
            ): void {
                $path = $this->pathForResource(
                    $translation->resource_type,
                    (string) $translation->slug,
                    $sourceLocale,
                );

                if ($path !== null) {
                    $identities[$path] = [
                        'resource_type' => $translation->resource_type,
                        'resource_id' => (string) $translation->resource_id,
                    ];
                }
            });

        foreach ($this->legacyModelDefinitions() as $resourceType => $definition) {
            $modelClass = $definition['model'];
            $labelField = $definition['slug'];

            $modelClass::query()
                ->withoutGlobalScopes()
                ->where('website_key', $websiteKey)
                ->get(['id', $labelField])
                ->each(function ($model) use (
                    &$identities,
                    $resourceType,
                    $labelField,
                    $sourceLocale,
                ): void {
                    $slug = trim((string) $model->getAttribute($labelField));
                    $path = $slug !== ''
                        ? $this->pathForResource($resourceType, $slug, $sourceLocale)
                        : null;

                    if ($path !== null) {
                        $identities[$path] ??= [
                            'resource_type' => $resourceType,
                            'resource_id' => (string) $model->getKey(),
                        ];
                    }
                });
        }

        return $this->pathIdentityCache[$cacheKey] = $identities;
    }

    /**
     * @return list<string>
     */
    private function resourceTypesWithRoutes(): array
    {
        return array_keys($this->legacyModelDefinitions());
    }

    /**
     * @return array<string, array{model:class-string,slug:string}>
     */
    private function legacyModelDefinitions(): array
    {
        return [
            'catalog_category' => ['model' => CatalogCategory::class, 'slug' => 'slug'],
            'catalog_product' => ['model' => CatalogProduct::class, 'slug' => 'slug'],
            'cms_category' => ['model' => CmsCategory::class, 'slug' => 'slug'],
            'cms_post' => ['model' => CmsPost::class, 'slug' => 'slug'],
            'cms_service_category' => ['model' => CmsServiceCategory::class, 'slug' => 'slug'],
            'cms_service' => ['model' => CmsService::class, 'slug' => 'slug'],
            'cms_project_category' => ['model' => CmsProjectCategory::class, 'slug' => 'slug'],
            'cms_project' => ['model' => CmsProject::class, 'slug' => 'slug'],
        ];
    }

    private function pathForResource(
        string $resourceType,
        string $slug,
        string $locale,
    ): ?string {
        return match ($resourceType) {
            'catalog_category' => FrontendRouteUrl::categoryPath($slug, $locale),
            'catalog_product' => FrontendRouteUrl::productPath($slug, $locale),
            'cms_category' => FrontendRouteUrl::blogCategoryPath($slug),
            'cms_post' => FrontendRouteUrl::postPath($slug),
            'cms_service_category' => FrontendRouteUrl::serviceCategoryPath($slug),
            'cms_service' => FrontendRouteUrl::servicePath($slug),
            'cms_project_category' => FrontendRouteUrl::projectCategoryPath($slug),
            'cms_project' => FrontendRouteUrl::projectPath($slug),
            default => null,
        };
    }

    private function normalizePath(string $path): string
    {
        $path = '/'.ltrim(trim($path), '/');

        return $path === '/' ? $path : rtrim($path, '/');
    }
}
