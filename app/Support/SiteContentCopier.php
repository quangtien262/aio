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

            $mediaMap = [];

            return [
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

    private function isWebsiteTable(string $table): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, 'website_key');
    }
}
