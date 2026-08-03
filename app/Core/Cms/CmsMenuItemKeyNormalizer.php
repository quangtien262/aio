<?php

namespace App\Core\Cms;

use Illuminate\Support\Str;

final class CmsMenuItemKeyNormalizer
{
    /**
     * Add a stable UUID to every valid menu item while preserving the submitted
     * structure and every existing field.
     *
     * Existing items are used as an identity reference for trusted server-side
     * writers (for example demo regenerators) that submit the same items without
     * echoing item_key back.
     *
     * @param  array<int|string, mixed>  $items
     * @param  array<int|string, mixed>  $existingItems
     * @return array<int, mixed>
     */
    public function normalize(array $items, array $existingItems = []): array
    {
        $usedKeys = [];

        return $this->normalizeItems($items, $existingItems, $usedKeys);
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @param  array<int|string, mixed>  $existingItems
     * @param  array<string, true>  $usedKeys
     * @return array<int, mixed>
     */
    private function normalizeItems(
        array $items,
        array $existingItems,
        array &$usedKeys,
    ): array {
        $normalized = [];
        $claimedExistingIndexes = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                $normalized[] = $item;

                continue;
            }

            $existingItem = $this->matchExistingItem(
                $item,
                $existingItems,
                $claimedExistingIndexes,
            );
            $itemKey = trim((string) ($item['item_key'] ?? ''));
            $existingKey = trim((string) ($existingItem['item_key'] ?? ''));

            if (! Str::isUuid($itemKey) || isset($usedKeys[$itemKey])) {
                $itemKey = Str::isUuid($existingKey) && ! isset($usedKeys[$existingKey])
                    ? $existingKey
                    : '';
            }

            if ($itemKey === '') {
                $itemKey = $this->newUniqueKey($usedKeys);
            }

            $usedKeys[$itemKey] = true;
            $item['item_key'] = $itemKey;

            if (array_key_exists('children', $item) && is_array($item['children'])) {
                $item['children'] = $this->normalizeItems(
                    $item['children'],
                    is_array($existingItem['children'] ?? null)
                        ? $existingItem['children']
                        : [],
                    $usedKeys,
                );
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int|string, mixed>  $existingItems
     * @param  array<int, true>  $claimedIndexes
     * @return array<string, mixed>
     */
    private function matchExistingItem(
        array $item,
        array $existingItems,
        array &$claimedIndexes,
    ): array {
        $existingItems = array_values($existingItems);
        $itemKey = trim((string) ($item['item_key'] ?? ''));

        if (Str::isUuid($itemKey)) {
            foreach ($existingItems as $index => $candidate) {
                if (
                    ! isset($claimedIndexes[$index])
                    && is_array($candidate)
                    && hash_equals($itemKey, trim((string) ($candidate['item_key'] ?? '')))
                ) {
                    $claimedIndexes[$index] = true;

                    return $candidate;
                }
            }
        }

        foreach ($this->identityCandidates($item) as $identity) {
            foreach ($existingItems as $index => $candidate) {
                if (
                    isset($claimedIndexes[$index])
                    || ! is_array($candidate)
                    || ! in_array($identity, $this->identityCandidates($candidate), true)
                ) {
                    continue;
                }

                $claimedIndexes[$index] = true;

                return $candidate;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function identityCandidates(array $item): array
    {
        $identities = [];
        $linkType = trim((string) ($item['link_type'] ?? ''));
        $linkValue = trim((string) ($item['link_value'] ?? ''));
        $customUrl = trim((string) ($item['custom_url'] ?? ''));
        $url = trim((string) ($item['url'] ?? ''));
        $label = trim((string) ($item['label'] ?? ''));

        if ($linkType !== '' && $linkValue !== '') {
            $identities[] = 'link:'.$linkType.':'.$linkValue;
        }

        if ($customUrl !== '' && $customUrl !== '#') {
            $identities[] = 'custom-url:'.$customUrl;
        }

        if ($url !== '' && $url !== '#') {
            $identities[] = 'url:'.$url;
        }

        if ($label !== '') {
            $identities[] = 'label:'.mb_strtolower($label);
        }

        return $identities;
    }

    /**
     * @param  array<string, true>  $usedKeys
     */
    private function newUniqueKey(array $usedKeys): string
    {
        do {
            $itemKey = (string) Str::uuid();
        } while (isset($usedKeys[$itemKey]));

        return $itemKey;
    }
}
