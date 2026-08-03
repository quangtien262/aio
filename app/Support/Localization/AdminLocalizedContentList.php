<?php

namespace App\Support\Localization;

use App\Core\Cms\CmsMenuLocalization;
use App\Enums\TranslationStatus;
use App\Models\ContentTranslation;
use App\Support\SiteContext;

class AdminLocalizedContentList
{
    public function __construct(
        private readonly LocaleContext $localeContext,
        private readonly SiteContext $siteContext,
        private readonly CmsMenuLocalization $menuLocalization,
    ) {}

    /**
     * Overlay a list response with the exact editable locale selected in Admin.
     *
     * Missing translations deliberately keep the source label as a visual
     * fallback, but expose a `missing` status so editors can find the record.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function overlay(
        array $items,
        string $resourceType,
        ?string $requestedLocale,
    ): array {
        $websiteKey = $this->siteContext->websiteKey();
        $locale = filled($requestedLocale)
            ? $this->localeContext->resolveEditable((string) $requestedLocale, $websiteKey)
            : $this->localeContext->defaultLocale($websiteKey);
        $sourceLocale = $this->localeContext->sourceLocale();

        if ($locale === $sourceLocale || $items === []) {
            return array_map(
                function (array $item) use (
                    $locale,
                    $sourceLocale,
                    $resourceType,
                ): array {
                    $localized = $this->withLocaleMetadata(
                        $item,
                        $locale,
                        $sourceLocale,
                        $this->sourceStatus($item),
                        true,
                    );

                    if ($resourceType === 'cms_menu') {
                        $localized['_translation_progress'] = $this->sourceMenuProgress(
                            is_array($item['items'] ?? null) ? $item['items'] : [],
                        );
                    }

                    return $localized;
                },
                $items,
            );
        }

        $resourceIds = collect($items)
            ->pluck('id')
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
        $translations = ContentTranslation::query()
            ->forWebsite($websiteKey)
            ->where('resource_type', $resourceType)
            ->where('locale', $locale)
            ->whereIn('resource_id', $resourceIds)
            ->get()
            ->keyBy(fn (ContentTranslation $translation): string => (
                (string) $translation->resource_id
            ));

        return array_map(function (array $item) use (
            $locale,
            $sourceLocale,
            $translations,
            $resourceType,
        ): array {
            /** @var ContentTranslation|null $translation */
            $translation = $translations->get((string) ($item['id'] ?? ''));

            if ($translation === null) {
                $localized = $this->withLocaleMetadata(
                    $item,
                    $locale,
                    $sourceLocale,
                    TranslationStatus::Missing->value,
                    false,
                );

                if ($resourceType === 'cms_menu') {
                    $localized['_translation_progress'] = $this->menuLocalization->progress(
                        is_array($item['items'] ?? null) ? $item['items'] : [],
                        [],
                    );
                }

                return $localized;
            }

            $status = $translation->translation_status instanceof TranslationStatus
                ? $translation->translation_status->value
                : (string) $translation->translation_status;
            $payload = (array) ($translation->payload ?? []);
            $localized = $resourceType === 'cms_menu'
                ? array_replace($item, [
                    'items' => $this->menuLocalization->localizedItems(
                        is_array($item['items'] ?? null) ? $item['items'] : [],
                        $payload,
                    ),
                ])
                : array_replace($item, $payload);

            $localized = $this->withLocaleMetadata(
                $localized,
                $locale,
                $sourceLocale,
                $status,
                false,
            );

            if ($resourceType === 'cms_menu') {
                $localized['_translation_progress'] = $this->menuLocalization->progress(
                    is_array($item['items'] ?? null) ? $item['items'] : [],
                    $payload,
                );
            }

            return $localized;
        }, $items);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function withLocaleMetadata(
        array $item,
        string $locale,
        string $sourceLocale,
        string $translationStatus,
        bool $source,
    ): array {
        if (! $source && array_key_exists('status', $item)) {
            $item['status'] = $translationStatus;
        }

        if (! $source && array_key_exists('is_active', $item)) {
            $item['is_active'] = $translationStatus === TranslationStatus::Published->value;
        }

        $item['_content_locale'] = $locale;
        $item['_content_source_locale'] = $sourceLocale;
        $item['_translation_status'] = $translationStatus;

        return $item;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function sourceStatus(array $item): string
    {
        if (array_key_exists('status', $item)) {
            return (string) ($item['status'] ?: TranslationStatus::Draft->value);
        }

        if (array_key_exists('is_active', $item)) {
            return $item['is_active']
                ? TranslationStatus::Published->value
                : TranslationStatus::Draft->value;
        }

        return TranslationStatus::Published->value;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array{translated: int, total: int, percentage: int, complete: bool}
     */
    private function sourceMenuProgress(array $items): array
    {
        $total = $this->menuLocalization->progress(
            $items,
            ['items' => $items],
        )['total'];

        return [
            'translated' => $total,
            'total' => $total,
            'percentage' => 100,
            'complete' => true,
        ];
    }
}
