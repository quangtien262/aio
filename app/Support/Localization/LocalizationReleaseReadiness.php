<?php

namespace App\Support\Localization;

use App\Enums\TranslationStatus;
use App\Models\CmsPage;
use App\Models\CmsPageTranslation;
use App\Models\ContentTranslation;
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
            ]);
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
            ]);
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
            ]);
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
            ]);

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
                ]);
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
                ]);
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
                ]);
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
                ]);

            $scopes = [
                'content' => $this->scope($sourceContent, $targetContent),
                'cms_pages' => $this->scope($sourcePages, $targetPages),
                'landing_pages' => $this->scope($sourceLandingPages, $targetLandingPages),
                'landing_blocks' => $this->scope($sourceLandingBlocks, $targetLandingBlocks),
            ];
            $required = (int) collect($scopes)->sum('required');
            $translated = (int) collect($scopes)->sum('ready');
            $pending = $required - $translated;

            return [$locale => [
                'ready' => $pending === 0,
                'required' => $required,
                'translated' => $translated,
                'pending' => $pending,
                'coverage' => $this->coverage($translated, $required),
                'scopes' => $scopes,
            ]];
        })->all();
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
