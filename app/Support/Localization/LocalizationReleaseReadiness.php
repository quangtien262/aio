<?php

namespace App\Support\Localization;

use App\Enums\TranslationStatus;
use App\Models\CmsPage;
use App\Models\CmsPageTranslation;
use App\Models\CmsMenu;
use App\Models\ContentTranslation;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Models\LandingPageData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class LocalizationReleaseReadiness
{
    public function __construct(private readonly LocaleContext $localeContext) {}

    /**
     * @param  list<string>|null  $targetLocales
     * @return array<string, array<string, mixed>>
     */
    public function report(string $websiteKey, ?array $targetLocales = null): array
    {
        $websiteKey = trim($websiteKey) ?: 'website-main';
        $sourceLocale = $this->localeContext->sourceLocale();
        $locales = collect($targetLocales ?? $this->localeContext->publicLocales($websiteKey))
            ->map(fn (string $locale): string => LocaleCode::normalize($locale))
            ->reject(fn (string $locale): bool => $locale === $sourceLocale)
            ->unique()
            ->values();

        if ($locales->isEmpty()) {
            return [];
        }

        $validContentKeys = $this->validContentKeys($websiteKey);
        $sourceContent = ContentTranslation::query()
            ->withoutGlobalScopes()
            ->publishedTranslation()
            ->where('website_key', $websiteKey)
            ->where('locale', $sourceLocale)
            ->get(['resource_type', 'resource_id', 'translation_revision', 'source_revision'])
            ->filter(fn (ContentTranslation $translation): bool => $validContentKeys->contains(
                $translation->resource_type.'|'.$translation->resource_id,
            ))
            ->mapWithKeys(fn (ContentTranslation $translation): array => [
                $translation->resource_type.'|'.$translation->resource_id => (string) (
                    $translation->translation_revision ?: $translation->source_revision
                ),
            ])->toBase();
        $sourcePageIds = CmsPage::query()
            ->withoutGlobalScopes()
            ->where('website_key', $websiteKey)
            ->pluck('id');
        $sourcePages = CmsPageTranslation::query()
            ->withoutGlobalScopes()
            ->publishedTranslation()
            ->where('website_key', $websiteKey)
            ->where('locale', $sourceLocale)
            ->whereIn('cms_page_id', $sourcePageIds)
            ->get(['cms_page_id', 'translation_revision', 'source_revision'])
            ->mapWithKeys(fn (CmsPageTranslation $translation): array => [
                (string) $translation->cms_page_id => (string) (
                    $translation->translation_revision ?: $translation->source_revision
                ),
            ])->toBase();
        $sourceLandingPages = LandingPageData::query()
            ->withoutGlobalScopes()
            ->publishedTranslation()
            ->where('locale', $sourceLocale)
            ->whereHas('landingPage', fn ($query) => $query->where('website_key', $websiteKey))
            ->get(['landing_page_id', 'translation_revision', 'source_revision'])
            ->mapWithKeys(fn (LandingPageData $translation): array => [
                (string) $translation->landing_page_id => (string) (
                    $translation->translation_revision ?: $translation->source_revision
                ),
            ])->toBase();
        $sourceLandingBlocks = LandingPageBlockData::query()
            ->withoutGlobalScopes()
            ->publishedTranslation()
            ->where('locale', $sourceLocale)
            ->whereHas('landingPageBlock', fn ($query) => $query
                ->where('is_visible', true)
                ->where('block_type', '!=', 'footer_contact')
                ->whereHas('landingPage', fn ($pageQuery) => $pageQuery->where('website_key', $websiteKey)))
            ->get(['landing_page_block_id', 'translation_revision', 'source_revision'])
            ->mapWithKeys(fn (LandingPageBlockData $translation): array => [
                (string) $translation->landing_page_block_id => (string) (
                    $translation->translation_revision ?: $translation->source_revision
                ),
            ])->toBase();

        return $locales->mapWithKeys(function (string $locale) use (
            $sourceContent,
            $sourcePages,
            $sourceLandingPages,
            $sourceLandingBlocks,
            $websiteKey,
        ): array {
            $targetContent = ContentTranslation::query()
                ->withoutGlobalScopes()
                ->whereIn('translation_status', [
                    TranslationStatus::Ready->value,
                    TranslationStatus::Published->value,
                ])
                ->where('website_key', $websiteKey)
                ->where('locale', $locale)
                ->get(['resource_type', 'resource_id', 'source_revision'])
                ->mapWithKeys(fn (ContentTranslation $translation): array => [
                    $translation->resource_type.'|'.$translation->resource_id => (string) $translation->source_revision,
                ])->toBase();
            $targetPages = CmsPageTranslation::query()
                ->withoutGlobalScopes()
                ->whereIn('translation_status', [
                    TranslationStatus::Ready->value,
                    TranslationStatus::Published->value,
                ])
                ->where('website_key', $websiteKey)
                ->where('locale', $locale)
                ->get(['cms_page_id', 'source_revision'])
                ->mapWithKeys(fn (CmsPageTranslation $translation): array => [
                    (string) $translation->cms_page_id => (string) $translation->source_revision,
                ])->toBase();
            $targetLandingPages = LandingPageData::query()
                ->withoutGlobalScopes()
                ->whereIn('translation_status', [
                    TranslationStatus::Ready->value,
                    TranslationStatus::Published->value,
                ])
                ->where('locale', $locale)
                ->whereHas('landingPage', fn ($query) => $query->where('website_key', $websiteKey))
                ->get(['landing_page_id', 'source_revision'])
                ->mapWithKeys(fn (LandingPageData $translation): array => [
                    (string) $translation->landing_page_id => (string) $translation->source_revision,
                ])->toBase();
            $targetLandingBlocks = LandingPageBlockData::query()
                ->withoutGlobalScopes()
                ->whereIn('translation_status', [
                    TranslationStatus::Ready->value,
                    TranslationStatus::Published->value,
                ])
                ->where('locale', $locale)
                ->whereHas('landingPageBlock.landingPage', fn ($query) => $query->where('website_key', $websiteKey))
                ->get(['landing_page_block_id', 'source_revision'])
                ->mapWithKeys(fn (LandingPageBlockData $translation): array => [
                    (string) $translation->landing_page_block_id => (string) $translation->source_revision,
                ])->toBase();

            $scopes = [
                'content' => $this->scope($sourceContent, $targetContent),
                'cms_pages' => $this->scope($sourcePages, $targetPages),
                'landing_pages' => $this->scope($sourceLandingPages, $targetLandingPages),
                'landing_blocks' => $this->scope($sourceLandingBlocks, $targetLandingBlocks),
            ];
            $required = (int) collect($scopes)->sum('required');
            $translated = (int) collect($scopes)->sum('ready');
            $pending = $required - $translated;
            $criticalKeys = $this->criticalResourceKeys($websiteKey);
            $criticalPageIds = $criticalKeys
                ->filter(fn (string $key): bool => str_starts_with($key, 'cms_page|'))
                ->map(fn (string $key): string => substr($key, strlen('cms_page|')))
                ->values();
            $criticalContentKeys = $criticalKeys
                ->reject(fn (string $key): bool => str_starts_with($key, 'cms_page|'))
                ->merge($sourceContent->keys()->filter(function (string $key): bool {
                    $resourceType = explode('|', $key, 2)[0];

                    return in_array(
                        $resourceType,
                        (array) config('localized-content.release.critical_resource_types', []),
                        true,
                    );
                }))
                ->unique()
                ->values();
            $homeLandingIds = collect(LandingPage::query()
                ->withoutGlobalScopes()
                ->where('website_key', $websiteKey)
                ->where('is_home', true)
                ->pluck('id')
                ->map(fn (mixed $id): string => (string) $id)
                ->all());
            $homeBlockIds = collect(LandingPageBlock::query()
                ->withoutGlobalScopes()
                ->whereIn('landing_page_id', $homeLandingIds)
                ->where('is_visible', true)
                ->where('block_type', '!=', 'footer_contact')
                ->pluck('id')
                ->map(fn (mixed $id): string => (string) $id)
                ->all());
            $criticalSources = [
                'content' => $sourceContent->only($criticalContentKeys),
                'cms_pages' => $sourcePages->only($criticalPageIds),
                'landing_pages' => $sourceLandingPages->only($homeLandingIds),
                'landing_blocks' => $sourceLandingBlocks->only($homeBlockIds),
            ];
            $targets = [
                'content' => $targetContent,
                'cms_pages' => $targetPages,
                'landing_pages' => $targetLandingPages,
                'landing_blocks' => $targetLandingBlocks,
            ];
            $allSources = [
                'content' => $sourceContent,
                'cms_pages' => $sourcePages,
                'landing_pages' => $sourceLandingPages,
                'landing_blocks' => $sourceLandingBlocks,
            ];
            $criticalScopes = collect($criticalSources)
                ->map(fn (Collection $source, string $scope): array => $this->scope($source, $targets[$scope]))
                ->all();
            $extendedScopes = collect($allSources)
                ->map(fn (Collection $source, string $scope): array => $this->scope(
                    $source->except($criticalSources[$scope]->keys()),
                    $targets[$scope],
                ))
                ->all();
            $critical = $this->summary($criticalScopes);
            $extended = $this->summary($extendedScopes);

            return [$locale => [
                'ready' => $pending === 0,
                'strict_ready' => $pending === 0,
                'publishable' => $critical['pending'] === 0,
                'required' => $required,
                'translated' => $translated,
                'pending' => $pending,
                'coverage' => $this->coverage($translated, $required),
                'scopes' => $scopes,
                'critical' => $critical,
                'extended' => $extended,
            ]];
        })->all();
    }

    /** @return Collection<int, string> */
    private function criticalResourceKeys(string $websiteKey): Collection
    {
        return collect(CmsMenu::query()
            ->withoutGlobalScopes()
            ->where('website_key', $websiteKey)
            ->get(['items'])
            ->flatMap(fn (CmsMenu $menu): array => $this->menuResourceKeys((array) $menu->items))
            ->unique()
            ->values()
            ->all());
    }

    /** @param array<int, mixed> $items @return list<string> */
    private function menuResourceKeys(array $items): array
    {
        $keys = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $resourceType = trim((string) ($item['resource_type'] ?? ''));
            $resourceId = trim((string) ($item['resource_id'] ?? ''));

            if ($resourceType !== '' && $resourceId !== '') {
                $keys[] = $resourceType.'|'.$resourceId;
            }

            if (is_array($item['children'] ?? null)) {
                array_push($keys, ...$this->menuResourceKeys($item['children']));
            }
        }

        return $keys;
    }

    /** @param array<string, array{required:int,ready:int,pending:int,coverage:float}> $scopes */
    private function summary(array $scopes): array
    {
        $required = (int) collect($scopes)->sum('required');
        $ready = (int) collect($scopes)->sum('ready');

        return [
            'ready' => $required === $ready,
            'required' => $required,
            'translated' => $ready,
            'pending' => $required - $ready,
            'coverage' => $this->coverage($ready, $required),
            'scopes' => $scopes,
        ];
    }

    /** @return Collection<int, string> */
    private function validContentKeys(string $websiteKey): Collection
    {
        return collect((array) config('localized-content.resources', []))
            ->flatMap(function (array $definition, string $resourceType) use ($websiteKey): array {
                $modelClass = $definition['model'] ?? null;

                if (! is_string($modelClass) || ! class_exists($modelClass)) {
                    return [];
                }

                /** @var Model $model */
                $model = new $modelClass;

                if (! Schema::hasTable($model->getTable())) {
                    return [];
                }

                $query = $modelClass::query()->withoutGlobalScopes();

                if (Schema::hasColumn($model->getTable(), 'website_key')) {
                    $query->where('website_key', $websiteKey);
                }

                return $query->pluck($model->getKeyName())
                    ->map(fn (mixed $id): string => $resourceType.'|'.$id)
                    ->all();
            })
            ->values();
    }

    /** @return array{required:int,ready:int,pending:int,coverage:float} */
    private function scope(Collection $source, Collection $target): array
    {
        $ready = $source->filter(fn (string $revision, string $key): bool => (
            $revision !== '' && (string) $target->get($key) === $revision
        ))->count();
        $required = $source->count();

        return [
            'required' => $required,
            'ready' => $ready,
            'pending' => $required - $ready,
            'coverage' => $this->coverage($ready, $required),
        ];
    }

    private function coverage(int $ready, int $required): float
    {
        return $required === 0 ? 100.0 : round(($ready / $required) * 100, 1);
    }
}
