<?php

namespace App\Core\Cms;

use App\Enums\TranslationStatus;
use App\Support\Localization\LocaleContext;
use App\Support\Localization\TranslationRevision;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CmsMenuTranslationBackfill
{
    public const MIGRATION_NAME = 'cms_menu_translation_v2_backfill';

    public function __construct(
        private readonly CmsMenuItemKeyNormalizer $itemKeyNormalizer,
        private readonly CmsMenuLocalization $localization,
        private readonly LocaleContext $localeContext,
    ) {}

    /**
     * Backfill canonical source records and migrate every existing target
     * translation to the item_key-based v2 payload.
     *
     * Missing target locales intentionally remain without a database row. The
     * Admin and readiness audit treat that absence as TranslationStatus::Missing.
     *
     * @return array<string, int>
     */
    public function run(?string $websiteKey = null): array
    {
        $report = $this->emptyReport();

        if (
            ! Schema::hasTable('cms_menus')
            || ! Schema::hasTable('content_translations')
        ) {
            return $report;
        }

        $sourceLocale = (string) config('localization.source_locale', 'vi');
        $menus = DB::table('cms_menus')
            ->when(
                filled($websiteKey),
                fn ($query) => $query->where('website_key', $websiteKey),
            )
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();
        $currentMenuIds = $menus
            ->unique(fn (object $menu): string => (
                strtolower((string) ($menu->website_key ?? 'website-main'))
                .'|'.(string) $menu->location
            ))
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        foreach ($menus->sortBy('id')->values() as $menu) {
            $report['menus_scanned']++;
            $sourceItems = $this->decodeArray($menu->items);
            $normalizedItems = $this->itemKeyNormalizer->normalize($sourceItems);

            if ($normalizedItems !== $sourceItems) {
                DB::table('cms_menus')
                    ->where('id', $menu->id)
                    ->update([
                        'items' => $this->encode($normalizedItems),
                        'updated_at' => now(),
                    ]);
                $report['menus_normalized']++;
                $sourceItems = $normalizedItems;
            }

            $menuWebsiteKey = strtolower(trim((string) (
                $menu->website_key
                ?? 'website-main'
            ))) ?: 'website-main';
            $sourcePayload = ['items' => $sourceItems];
            $sourceRevision = TranslationRevision::fingerprint($sourcePayload);
            $sourceResult = $this->upsertSourceTranslation(
                $menu,
                $menuWebsiteKey,
                $sourceLocale,
                $sourcePayload,
                $sourceRevision,
            );
            $report[$sourceResult]++;

            $existingTargets = DB::table('content_translations')
                ->where('website_key', $menuWebsiteKey)
                ->where('resource_type', 'cms_menu')
                ->where('resource_id', (string) $menu->id)
                ->where('locale', '!=', $sourceLocale)
                ->get()
                ->keyBy('locale');
            $legacyByLocale = in_array((string) $menu->id, $currentMenuIds, true)
                ? $this->legacyTranslationsByLocale(
                    $menu,
                    $menuWebsiteKey,
                    $sourceLocale,
                    $sourceItems,
                )
                : collect();
            $locales = $existingTargets->keys()
                ->merge($legacyByLocale->keys())
                ->unique()
                ->sort()
                ->values();

            foreach ($locales as $locale) {
                $existing = $existingTargets->get($locale);
                /** @var Collection<int, object> $legacyRows */
                $legacyRows = $legacyByLocale->get($locale, collect());
                $targetResult = $this->upsertTargetTranslation(
                    $menu,
                    $menuWebsiteKey,
                    (string) $locale,
                    $sourceItems,
                    $sourceRevision,
                    $existing,
                    $legacyRows,
                );

                if ($targetResult === null) {
                    continue;
                }

                $report[$targetResult['write']]++;
                $report['legacy_entries_migrated'] += $targetResult['legacy_entries'];
                $report['target_'.$targetResult['status']]++;
            }
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $sourcePayload
     */
    private function upsertSourceTranslation(
        object $menu,
        string $websiteKey,
        string $sourceLocale,
        array $sourcePayload,
        string $sourceRevision,
    ): string {
        $existing = $this->translation(
            $websiteKey,
            (string) $menu->id,
            $sourceLocale,
        );
        $translatedAt = $existing?->translated_at
            ?? $menu->updated_at
            ?? now();
        $publishedAt = $existing?->translation_published_at
            ?? $menu->updated_at
            ?? now();
        $metadata = array_replace(
            $this->decodeArray($existing?->translation_meta),
            [
                'migration' => self::MIGRATION_NAME,
                'source_table' => 'cms_menus',
            ],
        );
        $attributes = [
            'slug' => null,
            'payload' => $this->encode($sourcePayload),
            'translation_status' => TranslationStatus::Published->value,
            'source_revision' => $sourceRevision,
            'translation_revision' => $sourceRevision,
            'is_machine_translated' => false,
            'translation_meta' => $this->encode($metadata),
            'translated_at' => $translatedAt,
            'reviewed_at' => $existing?->reviewed_at ?? $publishedAt,
            'translation_published_at' => $publishedAt,
        ];

        return $this->persistTranslation(
            $existing,
            $websiteKey,
            (string) $menu->id,
            $sourceLocale,
            $attributes,
            'source_created',
            'source_updated',
            'source_unchanged',
        );
    }

    /**
     * @param  array<int, mixed>  $sourceItems
     * @param  Collection<int, object>  $legacyRows
     * @return array{write:string,status:string,legacy_entries:int}|null
     */
    private function upsertTargetTranslation(
        object $menu,
        string $websiteKey,
        string $locale,
        array $sourceItems,
        string $sourceRevision,
        ?object $existing,
        Collection $legacyRows,
    ): ?array {
        $existingPayload = $this->decodeArray($existing?->payload);
        $isV2 = (int) data_get(
            $existingPayload,
            'items.schema_version',
        ) === CmsMenuLocalization::SCHEMA_VERSION;
        $payload = $this->localization->storagePayload(
            $sourceItems,
            $existingPayload,
        );
        $byKey = (array) data_get($payload, 'items.by_key', []);
        $usedLegacyRows = collect();

        if (! $isV2) {
            foreach ($legacyRows as $row) {
                $itemKey = trim((string) ($row->resolved_item_key ?? ''));
                $value = trim((string) ($row->value ?? ''));

                if ($itemKey === '' || $value === '') {
                    continue;
                }

                $byKey[$itemKey] = ['label' => (string) $row->value];
                $usedLegacyRows->push($row);
            }
        }

        data_set($payload, 'items.by_key', $byKey);

        if ($existing === null && $usedLegacyRows->isEmpty()) {
            return null;
        }

        $sourceLabels = $this->labelsByKey($sourceItems);
        $translatedLabels = collect($sourceLabels)
            ->mapWithKeys(fn (string $sourceLabel, string $itemKey): array => [
                $itemKey => trim((string) data_get(
                    $payload,
                    "items.by_key.{$itemKey}.label",
                    '',
                )),
            ])
            ->all();
        $translatedCount = collect($translatedLabels)
            ->filter(fn (string $label): bool => $label !== '')
            ->count();
        $complete = $translatedCount === count($sourceLabels);
        $identicalToSource = $complete
            && $sourceLabels !== []
            && collect($sourceLabels)->every(
                fn (string $label, string $itemKey): bool => (
                    trim($label) === $translatedLabels[$itemKey]
                ),
            );
        $sourceChanged = $existing !== null
            && filled($existing->source_revision)
            && (string) $existing->source_revision !== $sourceRevision;
        $status = $this->targetStatus(
            $existing,
            $usedLegacyRows,
            $translatedCount,
            $complete,
            $identicalToSource,
            $sourceChanged,
            $this->localeContext->isPublic($locale, $websiteKey),
        );
        $metadata = array_replace(
            $this->decodeArray($existing?->translation_meta),
            [
                'migration' => self::MIGRATION_NAME,
                'schema_version' => CmsMenuLocalization::SCHEMA_VERSION,
            ],
        );

        if ($usedLegacyRows->isNotEmpty()) {
            $metadata['legacy_theme_translation_ids'] = $usedLegacyRows
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all();
        }

        $translatedAt = $existing?->translated_at
            ?? $usedLegacyRows->max('translated_at')
            ?? $usedLegacyRows->max('updated_at')
            ?? now();
        $publishedAt = $status === TranslationStatus::Published
            ? (
                $existing?->translation_published_at
                ?? $usedLegacyRows->max('translation_published_at')
                ?? $usedLegacyRows->max('updated_at')
                ?? now()
            )
            : null;
        $reviewedAt = in_array($status, [
            TranslationStatus::InReview,
            TranslationStatus::Ready,
            TranslationStatus::Published,
        ], true)
            ? ($existing?->reviewed_at ?? $publishedAt ?? $translatedAt)
            : null;
        $machineTranslated = (bool) ($existing?->is_machine_translated ?? false)
            || $usedLegacyRows->contains(
                fn (object $row): bool => (bool) $row->is_machine_translated,
            );
        $attributes = [
            'slug' => null,
            'payload' => $this->encode($payload),
            'translation_status' => $status->value,
            'source_revision' => (
                $status === TranslationStatus::Outdated
                && $sourceChanged
            )
                ? $existing->source_revision
                : $sourceRevision,
            'translation_revision' => TranslationRevision::fingerprint($payload),
            'is_machine_translated' => $machineTranslated,
            'translation_meta' => $this->encode($metadata),
            'translated_at' => $translatedAt,
            'reviewed_at' => $reviewedAt,
            'translation_published_at' => $publishedAt,
        ];
        $write = $this->persistTranslation(
            $existing,
            $websiteKey,
            (string) $menu->id,
            $locale,
            $attributes,
            'target_created',
            'target_updated',
            'target_unchanged',
        );

        return [
            'write' => $write,
            'status' => $status->value,
            'legacy_entries' => $usedLegacyRows->count(),
        ];
    }

    /**
     * @param  Collection<int, object>  $legacyRows
     */
    private function targetStatus(
        ?object $existing,
        Collection $legacyRows,
        int $translatedCount,
        bool $complete,
        bool $identicalToSource,
        bool $sourceChanged,
        bool $localeIsPublic,
    ): TranslationStatus {
        if ($translatedCount === 0) {
            return TranslationStatus::Missing;
        }

        if ($identicalToSource) {
            return TranslationStatus::NeedsTranslation;
        }

        $existingStatus = TranslationStatus::tryFrom(
            (string) ($existing?->translation_status ?? ''),
        );

        if ($existingStatus !== null) {
            if (
                $existingStatus === TranslationStatus::Published
                && ! $localeIsPublic
            ) {
                return TranslationStatus::Draft;
            }

            if (
                $sourceChanged
                && ! in_array($existingStatus, [
                    TranslationStatus::Missing,
                    TranslationStatus::NeedsTranslation,
                ], true)
            ) {
                return TranslationStatus::Outdated;
            }

            if ($existingStatus === TranslationStatus::Published && ! $complete) {
                return TranslationStatus::Draft;
            }

            if (in_array($existingStatus, [
                TranslationStatus::Missing,
                TranslationStatus::NeedsTranslation,
            ], true)) {
                return TranslationStatus::Draft;
            }

            return $existingStatus;
        }

        $allLegacyEntriesPublished = $legacyRows->isNotEmpty()
            && $legacyRows->every(
                fn (object $row): bool => (
                    (string) $row->translation_status
                    === TranslationStatus::Published->value
                ),
            );

        if ($complete && $allLegacyEntriesPublished && $localeIsPublic) {
            return TranslationStatus::Published;
        }

        return $legacyRows->contains(
            fn (object $row): bool => (bool) $row->is_machine_translated,
        )
            ? TranslationStatus::MachineDraft
            : TranslationStatus::Draft;
    }

    /**
     * @param  array<int, mixed>  $sourceItems
     * @return Collection<string, Collection<int, object>>
     */
    private function legacyTranslationsByLocale(
        object $menu,
        string $websiteKey,
        string $sourceLocale,
        array $sourceItems,
    ): Collection {
        if (! Schema::hasTable('theme_translations')) {
            return collect();
        }

        $keyMap = $this->legacyKeyMap(
            $sourceItems,
            (string) $menu->location,
            (string) $menu->id,
        );

        if ($keyMap === []) {
            return collect();
        }

        return DB::table('theme_translations')
            ->where('theme_key', 'site-content:'.$websiteKey)
            ->where('group', 'content')
            ->where('locale', '!=', $sourceLocale)
            ->where('translation_key', 'like', 'cms_menu.%')
            ->orderBy('id')
            ->get()
            ->filter(fn (object $row): bool => isset(
                $keyMap[(string) $row->translation_key],
            ))
            ->map(function (object $row) use ($keyMap): object {
                $row->resolved_item_key = $keyMap[(string) $row->translation_key];

                return $row;
            })
            ->groupBy('locale');
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<string, string>
     */
    private function legacyKeyMap(
        array $items,
        string $location,
        string $menuId,
        string $path = '',
    ): array {
        $map = [];

        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemPath = $path === ''
                ? (string) $index
                : $path.'.children.'.$index;
            $itemKey = trim((string) ($item['item_key'] ?? ''));

            if ($itemKey !== '') {
                foreach ([
                    "cms_menu.{$location}.{$itemPath}.label",
                    "cms_menu.{$location}.items.{$itemPath}.label",
                    "cms_menu.{$menuId}.items.{$itemPath}.label",
                ] as $legacyKey) {
                    $map[$legacyKey] = $itemKey;
                }
            }

            if (is_array($item['children'] ?? null)) {
                $map = array_replace(
                    $map,
                    $this->legacyKeyMap(
                        $item['children'],
                        $location,
                        $menuId,
                        $itemPath,
                    ),
                );
            }
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<string, string>
     */
    private function labelsByKey(array $items): array
    {
        $labels = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemKey = trim((string) ($item['item_key'] ?? ''));

            if ($itemKey !== '') {
                $labels[$itemKey] = trim((string) ($item['label'] ?? ''));
            }

            if (is_array($item['children'] ?? null)) {
                $labels = array_replace(
                    $labels,
                    $this->labelsByKey($item['children']),
                );
            }
        }

        return $labels;
    }

    private function translation(
        string $websiteKey,
        string $menuId,
        string $locale,
    ): ?object {
        return DB::table('content_translations')
            ->where('website_key', $websiteKey)
            ->where('resource_type', 'cms_menu')
            ->where('resource_id', $menuId)
            ->where('locale', $locale)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persistTranslation(
        ?object $existing,
        string $websiteKey,
        string $menuId,
        string $locale,
        array $attributes,
        string $createdResult,
        string $updatedResult,
        string $unchangedResult,
    ): string {
        if ($existing === null) {
            DB::table('content_translations')->insert(array_merge([
                'website_key' => $websiteKey,
                'resource_type' => 'cms_menu',
                'resource_id' => $menuId,
                'locale' => $locale,
                'created_at' => now(),
                'updated_at' => now(),
            ], $attributes));

            return $createdResult;
        }

        if (! $this->translationChanged($existing, $attributes)) {
            return $unchangedResult;
        }

        DB::table('content_translations')
            ->where('id', $existing->id)
            ->update(array_merge($attributes, ['updated_at' => now()]));

        return $updatedResult;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function translationChanged(object $existing, array $attributes): bool
    {
        foreach ($attributes as $field => $value) {
            $current = $existing->{$field} ?? null;

            if (in_array($field, ['payload', 'translation_meta'], true)) {
                if ($this->decodeArray($current) !== $this->decodeArray($value)) {
                    return true;
                }

                continue;
            }

            if ($field === 'is_machine_translated') {
                if ((bool) $current !== (bool) $value) {
                    return true;
                }

                continue;
            }

            if ((string) ($current ?? '') !== (string) ($value ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, int>
     */
    private function emptyReport(): array
    {
        $statuses = collect(TranslationStatus::cases())
            ->mapWithKeys(fn (TranslationStatus $status): array => [
                'target_'.$status->value => 0,
            ])
            ->all();

        return array_merge([
            'menus_scanned' => 0,
            'menus_normalized' => 0,
            'source_created' => 0,
            'source_updated' => 0,
            'source_unchanged' => 0,
            'target_created' => 0,
            'target_updated' => 0,
            'target_unchanged' => 0,
            'legacy_entries_migrated' => 0,
        ], $statuses);
    }

    /**
     * @return array<mixed>
     */
    private function decodeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function encode(array $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
