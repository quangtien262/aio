<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SiteDataPurger
{
    /**
     * @return array<string, int>
     */
    public function purge(string $websiteKey, bool $includeProfile = true, bool $allowDefaultWebsite = false): array
    {
        $websiteKey = trim($websiteKey);

        if (
            $websiteKey === ''
            || (! $allowDefaultWebsite && $websiteKey === SiteContext::DEFAULT_WEBSITE_KEY)
        ) {
            return [];
        }

        return DB::transaction(function () use ($websiteKey, $includeProfile): array {
            $counts = [];

            $counts['landing_pages'] = $this->deleteLandingPages($websiteKey);

            foreach ([
                'theme_translations',
                'theme_demo_records',
                'content_translations',
                'localized_routes',
                'cms_page_translations',
                'contact_inquiries',
                'orders',
                'real_estate_listings',
                'real_estate_property_types',
                'site_banners',
                'cms_side_promos',
                'cms_featured_categories',
                'cms_menus',
                'cms_testimonials',
                'cms_partners',
            ] as $table) {
                $counts[$table] = $this->deleteWebsiteRows($table, $websiteKey);
            }

            $counts['cms_team_members'] = $this->deleteRowsWithChildren(
                table: 'cms_team_members',
                websiteKey: $websiteKey,
                childTable: 'cms_team_member_images',
                childForeignKey: 'cms_team_member_id',
            );

            $counts['cms_services'] = $this->deleteRowsWithChildren(
                table: 'cms_services',
                websiteKey: $websiteKey,
                childTable: 'cms_service_images',
                childForeignKey: 'cms_service_id',
            );
            $counts['cms_service_categories'] = $this->deleteWebsiteRows('cms_service_categories', $websiteKey);

            $counts['cms_projects'] = $this->deleteRowsWithChildren(
                table: 'cms_projects',
                websiteKey: $websiteKey,
                childTable: 'cms_project_images',
                childForeignKey: 'cms_project_id',
            );
            $counts['cms_project_categories'] = $this->deleteWebsiteRows('cms_project_categories', $websiteKey);

            $counts['cms_posts'] = $this->deleteWebsiteRows('cms_posts', $websiteKey);
            $counts['cms_categories'] = $this->deleteWebsiteRows('cms_categories', $websiteKey);
            $counts['cms_pages'] = $this->deleteWebsiteRows('cms_pages', $websiteKey);

            $counts['catalog_products'] = $this->deleteRowsWithChildren(
                table: 'catalog_products',
                websiteKey: $websiteKey,
                childTable: 'catalog_product_images',
                childForeignKey: 'catalog_product_id',
            );
            $counts['catalog_categories'] = $this->deleteWebsiteRows('catalog_categories', $websiteKey);

            $counts['cms_media'] = $this->deleteWebsiteRows('cms_media', $websiteKey);
            $counts['cms_media_folders'] = $this->deleteWebsiteRows('cms_media_folders', $websiteKey);

            if ($includeProfile) {
                $counts['site_profiles'] = $this->deleteWebsiteRows('site_profiles', $websiteKey);
            }

            return array_filter($counts, fn (int $count): bool => $count > 0);
        });
    }

    private function deleteLandingPages(string $websiteKey): int
    {
        if (! $this->isWebsiteTable('landing_pages')) {
            return 0;
        }

        $pageIds = DB::table('landing_pages')
            ->where('website_key', $websiteKey)
            ->pluck('id')
            ->all();

        if ($pageIds === []) {
            return 0;
        }

        return DB::table('landing_pages')->whereIn('id', $pageIds)->delete();
    }

    private function deleteRowsWithChildren(string $table, string $websiteKey, string $childTable, string $childForeignKey): int
    {
        if (! $this->isWebsiteTable($table)) {
            return 0;
        }

        $ids = DB::table($table)->where('website_key', $websiteKey)->pluck('id')->all();

        if ($ids === []) {
            return 0;
        }

        if (Schema::hasTable($childTable) && Schema::hasColumn($childTable, $childForeignKey)) {
            DB::table($childTable)->whereIn($childForeignKey, $ids)->delete();
        }

        return DB::table($table)->whereIn('id', $ids)->delete();
    }

    private function deleteWebsiteRows(string $table, string $websiteKey): int
    {
        if (! $this->isWebsiteTable($table)) {
            return 0;
        }

        return DB::table($table)->where('website_key', $websiteKey)->delete();
    }

    private function isWebsiteTable(string $table): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, 'website_key');
    }
}
