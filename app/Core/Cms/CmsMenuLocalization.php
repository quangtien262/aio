<?php

namespace App\Core\Cms;

use Illuminate\Validation\ValidationException;

final class CmsMenuLocalization
{
    public const SCHEMA_VERSION = 2;

    /**
     * Convert the source-shaped tree submitted by Admin into stable, compact
     * label overrides keyed by item_key.
     *
     * @param  array<int, mixed>  $sourceItems
     * @param  array<string, mixed>  $submittedPayload
     * @return array{items: array{schema_version: int, by_key: array<string, array{label: string}>}}
     */
    public function storagePayload(array $sourceItems, array $submittedPayload): array
    {
        $submittedItems = is_array($submittedPayload['items'] ?? null)
            ? $submittedPayload['items']
            : [];
        $submittedLabels = $this->extractLabels($submittedItems, $sourceItems);
        $byKey = [];

        $this->walkSourceItems(
            $sourceItems,
            function (array $item) use (&$byKey, $submittedLabels): void {
                $itemKey = trim((string) ($item['item_key'] ?? ''));

                if ($itemKey === '') {
                    return;
                }

                $byKey[$itemKey] = [
                    'label' => (string) ($submittedLabels[$itemKey] ?? ''),
                ];
            },
        );

        return [
            'items' => [
                'schema_version' => self::SCHEMA_VERSION,
                'by_key' => $byKey,
            ],
        ];
    }

    /**
     * Return a source-shaped tree for the Admin editor. Only labels are
     * localized; link targets, hierarchy and ordering always come from source.
     *
     * @param  array<int, mixed>  $sourceItems
     * @param  array<string, mixed>  $payload
     * @return array<int, mixed>
     */
    public function editableItems(
        array $sourceItems,
        array $payload,
        bool $fallbackToSource = false,
    ): array {
        $labels = $this->extractLabels(
            is_array($payload['items'] ?? null) ? $payload['items'] : [],
            $sourceItems,
        );

        return $this->mapSourceItems(
            $sourceItems,
            function (array $sourceItem) use ($labels, $fallbackToSource): array {
                $itemKey = trim((string) ($sourceItem['item_key'] ?? ''));
                $hasTranslation = $itemKey !== '' && array_key_exists($itemKey, $labels);
                $sourceLabel = (string) ($sourceItem['label'] ?? '');

                $translatedLabel = $hasTranslation
                    ? (string) $labels[$itemKey]
                    : '';
                $sourceItem['label'] = $hasTranslation && (! $fallbackToSource || trim($translatedLabel) !== '')
                    ? $translatedLabel
                    : ($fallbackToSource ? $sourceLabel : '');

                if (! $fallbackToSource) {
                    $sourceItem['_source_label'] = $sourceLabel;
                }

                return $sourceItem;
            },
        );
    }

    /**
     * Merge a published translation into the canonical source structure.
     *
     * @param  array<int, mixed>  $sourceItems
     * @param  array<string, mixed>  $payload
     * @return array<int, mixed>
     */
    public function localizedItems(array $sourceItems, array $payload): array
    {
        return $this->editableItems($sourceItems, $payload, true);
    }

    /**
     * @param  array<int, mixed>  $sourceItems
     * @param  array<string, mixed>  $payload
     * @return array{translated: int, total: int, percentage: int, complete: bool}
     */
    public function progress(array $sourceItems, array $payload): array
    {
        $labels = $this->extractLabels(
            is_array($payload['items'] ?? null) ? $payload['items'] : [],
            $sourceItems,
        );
        $total = 0;
        $translated = 0;

        $this->walkSourceItems(
            $sourceItems,
            function (array $item) use (&$total, &$translated, $labels): void {
                $itemKey = trim((string) ($item['item_key'] ?? ''));

                if ($itemKey === '') {
                    return;
                }

                $total++;

                if (trim((string) ($labels[$itemKey] ?? '')) !== '') {
                    $translated++;
                }
            },
        );

        return [
            'translated' => $translated,
            'total' => $total,
            'percentage' => $total > 0
                ? (int) floor(($translated / $total) * 100)
                : 100,
            'complete' => $translated === $total,
        ];
    }

    /**
     * Prevent a target locale from being published with untranslated labels.
     *
     * @param  array<int, mixed>  $sourceItems
     * @param  array<string, mixed>  $payload
     */
    public function assertPublishable(array $sourceItems, array $payload): void
    {
        $progress = $this->progress($sourceItems, $payload);

        if ($progress['complete']) {
            return;
        }

        throw ValidationException::withMessages([
            'payload.items' => sprintf(
                'Cần dịch đủ nhãn menu trước khi xuất bản (%d/%d nhãn đã dịch).',
                $progress['translated'],
                $progress['total'],
            ),
        ]);
    }

    /**
     * Extract labels from either the v2 keyed payload or the legacy full tree.
     *
     * @param  array<int|string, mixed>  $itemsPayload
     * @param  array<int, mixed>  $sourceItems
     * @return array<string, string>
     */
    private function extractLabels(array $itemsPayload, array $sourceItems): array
    {
        if (
            (int) ($itemsPayload['schema_version'] ?? 0) === self::SCHEMA_VERSION
            && is_array($itemsPayload['by_key'] ?? null)
        ) {
            return collect($itemsPayload['by_key'])
                ->filter(fn (mixed $value, mixed $key): bool => (
                    is_string($key) && $key !== '' && is_array($value)
                ))
                ->mapWithKeys(fn (array $value, string $key): array => [
                    $key => (string) ($value['label'] ?? ''),
                ])
                ->all();
        }

        $labels = [];
        $this->extractLegacyLabels(
            array_values($itemsPayload),
            array_values($sourceItems),
            $labels,
        );

        return $labels;
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  array<int, mixed>  $sourceItems
     * @param  array<string, string>  $labels
     */
    private function extractLegacyLabels(
        array $items,
        array $sourceItems,
        array &$labels,
    ): void {
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $sourceItem = is_array($sourceItems[$index] ?? null)
                ? $sourceItems[$index]
                : [];
            $itemKey = trim((string) (
                $item['item_key']
                ?? $sourceItem['item_key']
                ?? ''
            ));

            if ($itemKey !== '' && array_key_exists('label', $item)) {
                $labels[$itemKey] = (string) $item['label'];
            }

            $this->extractLegacyLabels(
                is_array($item['children'] ?? null)
                    ? array_values($item['children'])
                    : [],
                is_array($sourceItem['children'] ?? null)
                    ? array_values($sourceItem['children'])
                    : [],
                $labels,
            );
        }
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  callable(array<string, mixed>): array<string, mixed>  $mapper
     * @return array<int, mixed>
     */
    private function mapSourceItems(array $items, callable $mapper): array
    {
        return array_map(function (mixed $item) use ($mapper): mixed {
            if (! is_array($item)) {
                return $item;
            }

            $mapped = $mapper($item);

            if (is_array($item['children'] ?? null)) {
                $mapped['children'] = $this->mapSourceItems(
                    $item['children'],
                    $mapper,
                );
            }

            return $mapped;
        }, array_values($items));
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  callable(array<string, mixed>): void  $callback
     */
    private function walkSourceItems(array $items, callable $callback): void
    {
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $callback($item);

            if (is_array($item['children'] ?? null)) {
                $this->walkSourceItems($item['children'], $callback);
            }
        }
    }
}
