<?php

use App\Support\Localization\TranslationRevision;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_menus') || ! Schema::hasColumn('cms_menus', 'items')) {
            return;
        }

        DB::table('cms_menus')
            ->orderBy('id')
            ->chunkById(100, function ($menus): void {
                foreach ($menus as $menu) {
                    $items = $this->decodeArray($menu->items);
                    $usedKeys = [];
                    $normalizedItems = $this->normalizeItems($items, $usedKeys);

                    if ($normalizedItems !== $items) {
                        DB::table('cms_menus')
                            ->where('id', $menu->id)
                            ->update(['items' => $this->encode($normalizedItems)]);
                    }

                    $this->alignContentTranslations(
                        (string) $menu->id,
                        (string) ($menu->website_key ?? 'website-main'),
                        $normalizedItems,
                    );
                }
            });
    }

    public function down(): void
    {
        // item_key is additive identity data. Removing it would break translation
        // references created after this migration and is intentionally avoided.
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @param  array<string, true>  $usedKeys
     * @return array<int, mixed>
     */
    private function normalizeItems(array $items, array &$usedKeys): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                $normalized[] = $item;

                continue;
            }

            $itemKey = trim((string) ($item['item_key'] ?? ''));

            if (! Str::isUuid($itemKey) || isset($usedKeys[$itemKey])) {
                do {
                    $itemKey = (string) Str::uuid();
                } while (isset($usedKeys[$itemKey]));
            }

            $usedKeys[$itemKey] = true;
            $item['item_key'] = $itemKey;

            if (array_key_exists('children', $item) && is_array($item['children'])) {
                $item['children'] = $this->normalizeItems($item['children'], $usedKeys);
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    /**
     * Keep existing localized labels and URLs, but align every translated item
     * with the stable key at the same source-tree position.
     *
     * @param  array<int, mixed>  $sourceItems
     */
    private function alignContentTranslations(
        string $menuId,
        string $websiteKey,
        array $sourceItems,
    ): void {
        if (! Schema::hasTable('content_translations')) {
            return;
        }

        $sourceLocale = (string) config('localization.source_locale', 'vi');
        $translations = DB::table('content_translations')
            ->where('website_key', $websiteKey)
            ->where('resource_type', 'cms_menu')
            ->where('resource_id', $menuId)
            ->orderByRaw('CASE WHEN locale = ? THEN 0 ELSE 1 END', [$sourceLocale])
            ->orderBy('id')
            ->get();
        $sourceRevision = null;

        foreach ($translations as $translation) {
            $payload = $this->decodeArray($translation->payload);
            $translatedItems = $payload['items'] ?? null;

            if (! is_array($translatedItems)) {
                continue;
            }

            $usedKeys = [];
            $payload['items'] = $this->alignTranslatedItems(
                $translatedItems,
                $sourceItems,
                $usedKeys,
            );
            $revision = TranslationRevision::fingerprint($payload);
            $isSourceLocale = (string) $translation->locale === $sourceLocale;

            if ($isSourceLocale) {
                $sourceRevision = $revision;
            }

            DB::table('content_translations')
                ->where('id', $translation->id)
                ->update([
                    'payload' => $this->encode($payload),
                    'source_revision' => $isSourceLocale
                        ? $revision
                        : ($sourceRevision ?: $translation->source_revision),
                    'translation_revision' => $revision,
                ]);
        }
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @param  array<int, mixed>  $sourceItems
     * @param  array<string, true>  $usedKeys
     * @return array<int, mixed>
     */
    private function alignTranslatedItems(
        array $items,
        array $sourceItems,
        array &$usedKeys,
    ): array {
        $aligned = [];

        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                $aligned[] = $item;

                continue;
            }

            $sourceItem = $sourceItems[$index] ?? null;
            $sourceKey = is_array($sourceItem)
                ? trim((string) ($sourceItem['item_key'] ?? ''))
                : '';
            $existingKey = trim((string) ($item['item_key'] ?? ''));
            $itemKey = Str::isUuid($sourceKey) && ! isset($usedKeys[$sourceKey])
                ? $sourceKey
                : $existingKey;

            if (! Str::isUuid($itemKey) || isset($usedKeys[$itemKey])) {
                do {
                    $itemKey = (string) Str::uuid();
                } while (isset($usedKeys[$itemKey]));
            }

            $usedKeys[$itemKey] = true;
            $item['item_key'] = $itemKey;

            if (array_key_exists('children', $item) && is_array($item['children'])) {
                $sourceChildren = is_array($sourceItem)
                    && is_array($sourceItem['children'] ?? null)
                        ? $sourceItem['children']
                        : [];
                $item['children'] = $this->alignTranslatedItems(
                    $item['children'],
                    $sourceChildren,
                    $usedKeys,
                );
            }

            $aligned[] = $item;
        }

        return $aligned;
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
};
