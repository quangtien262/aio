<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SiteContentCopier
{
    /**
     * Copy the business content owned by one website to another website.
     * Existing target records with the same slug (or product SKU) are updated.
     *
     * @return array<string, int>
     */
    public function copy(string $sourceWebsiteKey, string $targetWebsiteKey): array
    {
        return DB::transaction(function () use ($sourceWebsiteKey, $targetWebsiteKey): array {
            [$productCategoryMap, $productCategoryCount] = $this->copyCategoryTree(
                'catalog_categories',
                $sourceWebsiteKey,
                $targetWebsiteKey,
            );
            [$postCategoryMap, $postCategoryCount] = $this->copyCategoryTree(
                'cms_categories',
                $sourceWebsiteKey,
                $targetWebsiteKey,
            );
            [$projectCategoryMap, $projectCategoryCount] = $this->copyCategoryTree(
                'cms_project_categories',
                $sourceWebsiteKey,
                $targetWebsiteKey,
            );
            [$serviceCategoryMap, $serviceCategoryCount] = $this->copyCategoryTree(
                'cms_service_categories',
                $sourceWebsiteKey,
                $targetWebsiteKey,
            );

            [$mediaMap, $mediaCount] = $this->copyMediaLibrary($sourceWebsiteKey, $targetWebsiteKey);

            $counts = [
                'product_categories' => $productCategoryCount,
                'products' => $this->copyProducts(
                    $sourceWebsiteKey,
                    $targetWebsiteKey,
                    $productCategoryMap,
                ),
                'post_categories' => $postCategoryCount,
                'posts' => $this->copyContentRows(
                    table: 'cms_posts',
                    sourceWebsiteKey: $sourceWebsiteKey,
                    targetWebsiteKey: $targetWebsiteKey,
                    categoryColumn: 'category_id',
                    categoryMap: $postCategoryMap,
                    mediaColumn: 'featured_media_id',
                    mediaMap: $mediaMap,
                ),
                'project_categories' => $projectCategoryCount,
                'projects' => $this->copyContentRows(
                    table: 'cms_projects',
                    sourceWebsiteKey: $sourceWebsiteKey,
                    targetWebsiteKey: $targetWebsiteKey,
                    categoryColumn: 'cms_project_category_id',
                    categoryMap: $projectCategoryMap,
                    imageTable: 'cms_project_images',
                    imageForeignKey: 'cms_project_id',
                    mediaMap: $mediaMap,
                ),
                'service_categories' => $serviceCategoryCount,
                'services' => $this->copyContentRows(
                    table: 'cms_services',
                    sourceWebsiteKey: $sourceWebsiteKey,
                    targetWebsiteKey: $targetWebsiteKey,
                    categoryColumn: 'cms_service_category_id',
                    categoryMap: $serviceCategoryMap,
                    imageTable: 'cms_service_images',
                    imageForeignKey: 'cms_service_id',
                    mediaMap: $mediaMap,
                ),
            ];

            $counts['pages'] = $this->copySimpleRows('cms_pages', $sourceWebsiteKey, $targetWebsiteKey, ['slug'], ['featured_media_id'], $mediaMap);
            $counts['team_members'] = $this->copySimpleRows(
                table: 'cms_team_members',
                sourceWebsiteKey: $sourceWebsiteKey,
                targetWebsiteKey: $targetWebsiteKey,
                uniqueColumns: ['slug'],
                mediaColumns: [],
                mediaMap: $mediaMap,
                imageTable: 'cms_team_member_images',
                imageForeignKey: 'cms_team_member_id',
            );
            $counts['testimonials'] = $this->copySimpleRows('cms_testimonials', $sourceWebsiteKey, $targetWebsiteKey, ['name', 'company']);
            $counts['partners'] = $this->copySimpleRows('cms_partners', $sourceWebsiteKey, $targetWebsiteKey, ['slug']);
            $counts['menus'] = $this->copySimpleRows('cms_menus', $sourceWebsiteKey, $targetWebsiteKey, ['location', 'name']);
            $counts['featured_categories'] = $this->copySimpleRows('cms_featured_categories', $sourceWebsiteKey, $targetWebsiteKey, ['location', 'name']);
            $counts['side_promos'] = $this->copySimpleRows('cms_side_promos', $sourceWebsiteKey, $targetWebsiteKey, ['location', 'name']);
            $counts['banners'] = $this->copySimpleRows('site_banners', $sourceWebsiteKey, $targetWebsiteKey, ['theme_key', 'placement', 'title']);
            $counts['theme_translations'] = $this->copySimpleRows('theme_translations', $sourceWebsiteKey, $targetWebsiteKey, ['theme_key', 'locale', 'group', 'translation_key']);
            $counts['landing_pages'] = $this->copyLandingPages($sourceWebsiteKey, $targetWebsiteKey);
            $counts['media'] = $mediaCount;

            return $counts;
        });
    }

    /**
     * @return array{0: array<int, int>, 1: int}
     */
    private function copyCategoryTree(string $table, string $sourceWebsiteKey, string $targetWebsiteKey): array
    {
        if (! $this->isWebsiteTable($table)) {
            return [[], 0];
        }

        $sourceRows = DB::table($table)
            ->where('website_key', $sourceWebsiteKey)
            ->orderBy('id')
            ->get();
        $idMap = [];

        foreach ($sourceRows as $sourceRow) {
            $source = (array) $sourceRow;
            $attributes = $this->copyableAttributes($source, $targetWebsiteKey);
            $attributes['parent_id'] = null;

            $targetId = DB::table($table)
                ->where('website_key', $targetWebsiteKey)
                ->where('slug', $source['slug'])
                ->value('id');

            if ($targetId === null) {
                $targetId = DB::table($table)->insertGetId($attributes);
            } else {
                DB::table($table)->where('id', $targetId)->update($this->updateAttributes($attributes));
            }

            $idMap[(int) $source['id']] = (int) $targetId;
        }

        foreach ($sourceRows as $sourceRow) {
            $source = (array) $sourceRow;
            $sourceParentId = $source['parent_id'] ?? null;

            DB::table($table)
                ->where('id', $idMap[(int) $source['id']])
                ->update([
                    'parent_id' => $sourceParentId !== null
                        ? ($idMap[(int) $sourceParentId] ?? null)
                        : null,
                ]);
        }

        return [$idMap, count($idMap)];
    }

    /**
     * @param  array<int, int>  $categoryMap
     */
    private function copyProducts(string $sourceWebsiteKey, string $targetWebsiteKey, array $categoryMap): int
    {
        if (! $this->isWebsiteTable('catalog_products')) {
            return 0;
        }

        $sourceRows = DB::table('catalog_products')
            ->where('website_key', $sourceWebsiteKey)
            ->orderBy('id')
            ->get();

        foreach ($sourceRows as $sourceRow) {
            $source = (array) $sourceRow;
            $attributes = $this->copyableAttributes($source, $targetWebsiteKey);
            $attributes['catalog_category_id'] = $this->mappedForeignKey(
                $source['catalog_category_id'] ?? null,
                $categoryMap,
            );

            $targetQuery = DB::table('catalog_products')->where('website_key', $targetWebsiteKey);
            $targetId = $targetQuery->where(function ($query) use ($source): void {
                $query->where('sku', $source['sku']);

                if (filled($source['slug'] ?? null)) {
                    $query->orWhere('slug', $source['slug']);
                }
            })->value('id');

            if ($targetId === null) {
                $targetId = DB::table('catalog_products')->insertGetId($attributes);
            } else {
                DB::table('catalog_products')->where('id', $targetId)->update($this->updateAttributes($attributes));
            }

            $this->copyImages(
                table: 'catalog_product_images',
                foreignKey: 'catalog_product_id',
                sourceId: (int) $source['id'],
                targetId: (int) $targetId,
                sourceWebsiteKey: $sourceWebsiteKey,
                targetWebsiteKey: $targetWebsiteKey,
            );
        }

        return $sourceRows->count();
    }

    /**
     * @param  array<int, int>  $categoryMap
     * @param  array<int, int>  $mediaMap
     */
    private function copyContentRows(
        string $table,
        string $sourceWebsiteKey,
        string $targetWebsiteKey,
        string $categoryColumn,
        array $categoryMap,
        ?string $mediaColumn = null,
        ?string $imageTable = null,
        ?string $imageForeignKey = null,
        array &$mediaMap = [],
    ): int {
        if (! $this->isWebsiteTable($table)) {
            return 0;
        }

        $sourceRows = DB::table($table)
            ->where('website_key', $sourceWebsiteKey)
            ->orderBy('id')
            ->get();

        foreach ($sourceRows as $sourceRow) {
            $source = (array) $sourceRow;
            $attributes = $this->copyableAttributes($source, $targetWebsiteKey);
            $attributes[$categoryColumn] = $this->mappedForeignKey(
                $source[$categoryColumn] ?? null,
                $categoryMap,
            );

            if ($mediaColumn !== null && array_key_exists($mediaColumn, $source)) {
                $attributes[$mediaColumn] = $this->copyMedia(
                    $source[$mediaColumn],
                    $targetWebsiteKey,
                    $mediaMap,
                );
            }

            $targetId = DB::table($table)
                ->where('website_key', $targetWebsiteKey)
                ->where('slug', $source['slug'])
                ->value('id');

            if ($targetId === null) {
                $targetId = DB::table($table)->insertGetId($attributes);
            } else {
                DB::table($table)->where('id', $targetId)->update($this->updateAttributes($attributes));
            }

            if ($imageTable !== null && $imageForeignKey !== null) {
                $this->copyImages(
                    table: $imageTable,
                    foreignKey: $imageForeignKey,
                    sourceId: (int) $source['id'],
                    targetId: (int) $targetId,
                    sourceWebsiteKey: $sourceWebsiteKey,
                    targetWebsiteKey: $targetWebsiteKey,
                    mediaMap: $mediaMap,
                );
            }
        }

        return $sourceRows->count();
    }

    /**
     * @param  array<int, int>  $mediaMap
     */
    private function copyImages(
        string $table,
        string $foreignKey,
        int $sourceId,
        int $targetId,
        string $sourceWebsiteKey,
        string $targetWebsiteKey,
        array &$mediaMap = [],
    ): void {
        if (! Schema::hasTable($table)) {
            return;
        }

        $sourceImages = DB::table($table)->where($foreignKey, $sourceId)->orderBy('id')->get();
        DB::table($table)->where($foreignKey, $targetId)->delete();

        foreach ($sourceImages as $sourceImage) {
            $attributes = (array) $sourceImage;
            unset($attributes['id']);
            $attributes[$foreignKey] = $targetId;

            if (array_key_exists('cms_media_id', $attributes)) {
                $attributes['cms_media_id'] = $this->copyMedia(
                    $attributes['cms_media_id'],
                    $targetWebsiteKey,
                    $mediaMap,
                );
            }

            DB::table($table)->insert($attributes);
        }
    }

    /**
     * @param  list<string>  $uniqueColumns
     * @param  list<string>  $mediaColumns
     * @param  array<int, int>  $mediaMap
     */
    private function copySimpleRows(
        string $table,
        string $sourceWebsiteKey,
        string $targetWebsiteKey,
        array $uniqueColumns,
        array $mediaColumns = [],
        array &$mediaMap = [],
        ?string $imageTable = null,
        ?string $imageForeignKey = null,
    ): int {
        if (! $this->isWebsiteTable($table)) {
            return 0;
        }

        $sourceRows = DB::table($table)
            ->where('website_key', $sourceWebsiteKey)
            ->orderBy('id')
            ->get();

        foreach ($sourceRows as $sourceRow) {
            $source = (array) $sourceRow;
            $attributes = $this->copyableAttributes($source, $targetWebsiteKey);

            foreach ($mediaColumns as $mediaColumn) {
                if (array_key_exists($mediaColumn, $source)) {
                    $attributes[$mediaColumn] = $this->copyMedia($source[$mediaColumn], $targetWebsiteKey, $mediaMap);
                }
            }

            $targetId = $this->findTargetId($table, $source, $targetWebsiteKey, $uniqueColumns);

            if ($targetId === null) {
                $targetId = DB::table($table)->insertGetId($attributes);
            } else {
                DB::table($table)->where('id', $targetId)->update($this->updateAttributes($attributes));
            }

            if ($imageTable !== null && $imageForeignKey !== null) {
                $this->copyImages(
                    table: $imageTable,
                    foreignKey: $imageForeignKey,
                    sourceId: (int) $source['id'],
                    targetId: (int) $targetId,
                    sourceWebsiteKey: $sourceWebsiteKey,
                    targetWebsiteKey: $targetWebsiteKey,
                    mediaMap: $mediaMap,
                );
            }
        }

        return $sourceRows->count();
    }

    /**
     * @return array{0: array<int, int>, 1: int}
     */
    private function copyMediaLibrary(string $sourceWebsiteKey, string $targetWebsiteKey): array
    {
        $mediaMap = [];

        if (! $this->isWebsiteTable('cms_media')) {
            return [$mediaMap, 0];
        }

        if ($this->isWebsiteTable('cms_media_folders')) {
            $this->copySimpleRows('cms_media_folders', $sourceWebsiteKey, $targetWebsiteKey, ['path']);
        }

        $sourceRows = DB::table('cms_media')
            ->where('website_key', $sourceWebsiteKey)
            ->orderBy('id')
            ->get();

        foreach ($sourceRows as $sourceRow) {
            $source = (array) $sourceRow;
            $targetId = $this->copyMedia($source['id'] ?? null, $targetWebsiteKey, $mediaMap);

            if ($targetId !== null) {
                $mediaMap[(int) $source['id']] = $targetId;
            }
        }

        return [$mediaMap, count($mediaMap)];
    }

    private function copyLandingPages(string $sourceWebsiteKey, string $targetWebsiteKey): int
    {
        if (! $this->isWebsiteTable('landing_pages')) {
            return 0;
        }

        $sourcePages = DB::table('landing_pages')
            ->where('website_key', $sourceWebsiteKey)
            ->orderBy('id')
            ->get();

        foreach ($sourcePages as $sourcePage) {
            $source = (array) $sourcePage;
            $attributes = $this->copyableAttributes($source, $targetWebsiteKey);

            $targetId = DB::table('landing_pages')
                ->where('website_key', $targetWebsiteKey)
                ->where('theme_key', $source['theme_key'])
                ->where('slug', $source['slug'])
                ->value('id');

            if ($targetId === null) {
                $targetId = DB::table('landing_pages')->insertGetId($attributes);
            } else {
                DB::table('landing_pages')->where('id', $targetId)->update($this->updateAttributes($attributes));
            }

            $this->copyLandingPageData((int) $source['id'], (int) $targetId);
            $this->copyLandingPageBlocks((int) $source['id'], (int) $targetId);
        }

        return $sourcePages->count();
    }

    private function copyLandingPageData(int $sourcePageId, int $targetPageId): void
    {
        if (! Schema::hasTable('landing_page_data')) {
            return;
        }

        DB::table('landing_page_data')->where('landing_page_id', $targetPageId)->delete();

        DB::table('landing_page_data')
            ->where('landing_page_id', $sourcePageId)
            ->orderBy('id')
            ->get()
            ->each(function (object $sourceRow) use ($targetPageId): void {
                $attributes = (array) $sourceRow;
                unset($attributes['id']);
                $attributes['landing_page_id'] = $targetPageId;
                DB::table('landing_page_data')->insert($attributes);
            });
    }

    private function copyLandingPageBlocks(int $sourcePageId, int $targetPageId): void
    {
        if (! Schema::hasTable('landing_page_blocks')) {
            return;
        }

        $targetBlockIds = DB::table('landing_page_blocks')
            ->where('landing_page_id', $targetPageId)
            ->pluck('id')
            ->all();

        if ($targetBlockIds !== [] && Schema::hasTable('landing_page_block_data')) {
            DB::table('landing_page_block_data')->whereIn('landing_page_block_id', $targetBlockIds)->delete();
        }

        DB::table('landing_page_blocks')->where('landing_page_id', $targetPageId)->delete();

        DB::table('landing_page_blocks')
            ->where('landing_page_id', $sourcePageId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(function (object $sourceBlock) use ($targetPageId): void {
                $source = (array) $sourceBlock;
                $sourceBlockId = (int) $source['id'];
                unset($source['id']);
                $source['landing_page_id'] = $targetPageId;

                $targetBlockId = DB::table('landing_page_blocks')->insertGetId($source);

                if (! Schema::hasTable('landing_page_block_data')) {
                    return;
                }

                DB::table('landing_page_block_data')
                    ->where('landing_page_block_id', $sourceBlockId)
                    ->orderBy('id')
                    ->get()
                    ->each(function (object $sourceData) use ($targetBlockId): void {
                        $attributes = (array) $sourceData;
                        unset($attributes['id']);
                        $attributes['landing_page_block_id'] = $targetBlockId;
                        DB::table('landing_page_block_data')->insert($attributes);
                    });
            });
    }

    /**
     * @param  array<int, int>  $mediaMap
     */
    private function copyMedia(mixed $sourceMediaId, string $targetWebsiteKey, array &$mediaMap): ?int
    {
        if ($sourceMediaId === null || ! $this->isWebsiteTable('cms_media')) {
            return null;
        }

        $sourceMediaId = (int) $sourceMediaId;

        if (isset($mediaMap[$sourceMediaId])) {
            return $mediaMap[$sourceMediaId];
        }

        $source = (array) (DB::table('cms_media')->where('id', $sourceMediaId)->first() ?? []);

        if ($source === []) {
            return null;
        }

        $targetQuery = DB::table('cms_media')->where('website_key', $targetWebsiteKey);

        if (filled($source['file_path'] ?? null)) {
            $targetQuery->where('file_path', $source['file_path']);
        } else {
            $targetQuery->where('file_url', $source['file_url']);
        }

        $targetId = $targetQuery->value('id');
        $attributes = $this->copyableAttributes($source, $targetWebsiteKey);

        if ($targetId === null) {
            $targetId = DB::table('cms_media')->insertGetId($attributes);
        } else {
            DB::table('cms_media')->where('id', $targetId)->update($this->updateAttributes($attributes));
        }

        return $mediaMap[$sourceMediaId] = (int) $targetId;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function copyableAttributes(array $source, string $targetWebsiteKey): array
    {
        unset($source['id']);
        $source['website_key'] = $targetWebsiteKey;
        $source['updated_at'] = now();

        return $source;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function updateAttributes(array $attributes): array
    {
        unset($attributes['created_at']);

        return $attributes;
    }

    /**
     * @param  array<int, int>  $map
     */
    private function mappedForeignKey(mixed $sourceId, array $map): ?int
    {
        return $sourceId !== null ? ($map[(int) $sourceId] ?? null) : null;
    }

    /**
     * @param  list<string>  $uniqueColumns
     */
    private function findTargetId(string $table, array $source, string $targetWebsiteKey, array $uniqueColumns): ?int
    {
        $query = DB::table($table)->where('website_key', $targetWebsiteKey);
        $usableColumns = array_values(array_filter(
            $uniqueColumns,
            fn (string $column): bool => Schema::hasColumn($table, $column) && array_key_exists($column, $source) && filled($source[$column]),
        ));

        if ($usableColumns === []) {
            return null;
        }

        foreach ($usableColumns as $column) {
            $query->where($column, $source[$column]);
        }

        $id = $query->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function isWebsiteTable(string $table): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, 'website_key');
    }
}
