<?php

namespace App\Support\Localization;

use App\Models\CmsPageTranslation;
use App\Models\LocalizedRoute;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

final class CmsPageRouteRepair
{
    public function __construct(
        private readonly CmsPageLocalization $localization,
    ) {}

    /**
     * @return array{
     *     translations_scanned:int,
     *     routes_repaired:int,
     *     routes_unchanged:int,
     *     errors:list<array<string,mixed>>
     * }
     */
    public function run(
        ?string $websiteKey = null,
        bool $dryRun = false,
        bool $failOnError = false,
    ): array {
        $report = [
            'translations_scanned' => 0,
            'routes_repaired' => 0,
            'routes_unchanged' => 0,
            'errors' => [],
        ];

        if (
            ! Schema::hasTable('cms_page_translations')
            || ! Schema::hasTable('localized_routes')
        ) {
            return $report;
        }

        CmsPageTranslation::query()
            ->withoutGlobalScopes()
            ->when($websiteKey, fn ($query) => $query
                ->where('website_key', $websiteKey))
            ->orderBy('id')
            ->get()
            ->each(function (CmsPageTranslation $translation) use (
                &$report,
                $dryRun,
            ): void {
                $report['translations_scanned']++;
                $expectedPath = '/p/'.trim((string) $translation->slug, '/');

                if ($this->routeIsSynchronized($translation, $expectedPath)) {
                    $report['routes_unchanged']++;

                    return;
                }

                if ($dryRun) {
                    $report['routes_repaired']++;

                    return;
                }

                try {
                    $this->localization->syncRoutes($translation);
                    $report['routes_repaired']++;
                } catch (Throwable $exception) {
                    $report['errors'][] = [
                        'translation_id' => $translation->id,
                        'website_key' => $translation->website_key,
                        'locale' => $translation->locale,
                        'cms_page_id' => $translation->cms_page_id,
                        'path' => $expectedPath,
                        'message' => $exception->getMessage(),
                    ];
                }
            });

        if ($failOnError && $report['errors'] !== []) {
            throw new RuntimeException(
                'Không thể đồng bộ canonical route cho '
                .count($report['errors'])
                .' bản dịch CMS Page.',
            );
        }

        return $report;
    }

    private function routeIsSynchronized(
        CmsPageTranslation $translation,
        string $expectedPath,
    ): bool {
        $isPublished = $translation->isPublishedTranslation();
        $routes = LocalizedRoute::query()
            ->withoutGlobalScopes()
            ->where('website_key', $translation->website_key)
            ->where('locale', $translation->locale)
            ->where('resource_type', CmsPageLocalization::ROUTE_RESOURCE_TYPE)
            ->where('resource_id', (string) $translation->cms_page_id)
            ->get();
        $canonical = $routes->where('is_canonical', true);
        $expected = $routes->firstWhere('path', $expectedPath);

        return $canonical->count() === 1
            && $expected !== null
            && (bool) $expected->is_canonical
            && (bool) $expected->is_published === $isPublished
            && (
                ! $isPublished
                || $routes
                    ->where('path', '!=', $expectedPath)
                    ->every(fn (LocalizedRoute $route): bool => (
                        ! $route->is_canonical
                        && $route->redirect_to === $expectedPath
                    ))
            );
    }
}
