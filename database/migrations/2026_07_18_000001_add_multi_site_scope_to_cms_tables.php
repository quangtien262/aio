<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_WEBSITE_KEY = 'website-main';

    public function up(): void
    {
        $this->createSitesTable();
        $this->scopeSiteProfiles();

        $this->ensureWebsiteColumns('cms_pages', withIndex: true);
        $this->ensureWebsiteColumns('cms_service_categories');
        $this->ensureWebsiteColumns('cms_project_categories');
        $this->ensureWebsiteColumns('cms_media_folders', ownerColumns: false);

        $this->backfillWebsiteKey([
            'site_profiles',
            'cms_pages',
            'cms_categories',
            'cms_posts',
            'cms_media',
            'cms_menus',
            'cms_featured_categories',
            'cms_side_promos',
            'cms_services',
            'cms_service_categories',
            'cms_projects',
            'cms_project_categories',
            'cms_testimonials',
            'cms_team_members',
            'cms_partners',
            'cms_media_folders',
            'theme_translations',
            'theme_demo_records',
        ]);

        $this->scopeSlug('cms_pages');
        $this->scopeSlug('cms_categories');
        $this->scopeSlug('cms_posts');
        $this->scopeSlug('cms_services');
        $this->scopeSlug('cms_service_categories');
        $this->scopeSlug('cms_projects');
        $this->scopeSlug('cms_project_categories');
        $this->scopeSlug('cms_team_members');
        $this->scopeSlug('cms_partners');

        $this->ensureIndex('cms_pages', ['website_key', 'status'], 'cms_pages_website_status_idx');
        $this->ensureIndex('cms_categories', ['website_key', 'parent_id'], 'cms_categories_website_parent_idx');
        $this->ensureIndex('cms_posts', ['website_key', 'category_id'], 'cms_posts_website_category_idx');
        $this->ensureIndex('cms_posts', ['website_key', 'status', 'publish_at'], 'cms_posts_website_status_publish_idx');
        $this->ensureIndex('cms_service_categories', ['website_key', 'parent_id'], 'cms_service_categories_website_parent_idx');
        $this->ensureIndex('cms_services', ['website_key', 'cms_service_category_id'], 'cms_services_website_category_idx');
        $this->ensureIndex('cms_services', ['website_key', 'status', 'publish_at'], 'cms_services_website_status_publish_idx');
        $this->ensureIndex('cms_project_categories', ['website_key', 'parent_id'], 'cms_project_categories_website_parent_idx');
        $this->ensureIndex('cms_projects', ['website_key', 'cms_project_category_id'], 'cms_projects_website_category_idx');
        $this->ensureIndex('cms_projects', ['website_key', 'status', 'publish_at'], 'cms_projects_website_status_publish_idx');
        $this->ensureIndex('cms_team_members', ['website_key', 'status', 'publish_at'], 'cms_team_members_website_status_publish_idx');
        $this->ensureIndex('cms_testimonials', ['website_key', 'status', 'publish_at'], 'cms_testimonials_website_status_publish_idx');
        $this->ensureIndex('cms_testimonials', ['website_key', 'is_featured', 'sort_order'], 'cms_testimonials_website_featured_sort_idx');
        $this->ensureIndex('cms_media', ['website_key', 'folder_path'], 'cms_media_website_folder_idx');

        $this->scopeLocationName('cms_menus', ['website_key', 'location', 'name'], 'cms_menus_website_location_name_unique');
        $this->scopeLocationName('cms_featured_categories', ['website_key', 'location', 'name'], 'cms_featured_categories_website_location_name_unique');
        $this->scopeLocationName('cms_side_promos', ['website_key', 'location', 'name'], 'cms_side_promos_website_location_name_unique');
        $this->scopeMediaFolderPath();
        $this->scopeThemeTables();
    }

    public function down(): void
    {
        // Intentionally keep columns/data. Rolling this back would require
        // merging multi-site content and can destroy valid demo records.
    }

    private function createSitesTable(): void
    {
        if (! Schema::hasTable('sites')) {
            Schema::create('sites', function (Blueprint $table): void {
                $table->id();
                $table->string('domain')->nullable()->unique();
                $table->string('website_key')->unique();
                $table->string('theme_key')->nullable()->index();
                $table->string('name')->nullable();
                $table->string('status', 40)->default('active')->index();
                $table->json('settings')->nullable();
                $table->timestamps();
            });
        }

        DB::table('sites')->updateOrInsert(
            ['website_key' => self::DEFAULT_WEBSITE_KEY],
            [
                'domain' => null,
                'theme_key' => Schema::hasTable('site_profiles') ? DB::table('site_profiles')->value('active_theme_key') : null,
                'name' => Schema::hasTable('site_profiles') ? DB::table('site_profiles')->value('site_name') : 'AIO Website',
                'status' => 'active',
                'settings' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function scopeSiteProfiles(): void
    {
        if (! Schema::hasTable('site_profiles')) {
            return;
        }

        $this->ensureWebsiteColumns('site_profiles', ownerColumns: false);
        $this->ensureUnique('site_profiles', ['website_key'], 'site_profiles_website_key_unique');
    }

    private function ensureWebsiteColumns(string $table, bool $withIndex = true, bool $ownerColumns = false): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $withIndex, $ownerColumns): void {
            if (! Schema::hasColumn($table, 'website_key')) {
                $column = $blueprint->string('website_key')->nullable();
                if ($withIndex) {
                    $column->index();
                }
            }

            if ($ownerColumns && ! Schema::hasColumn($table, 'owner_key')) {
                $blueprint->string('owner_key')->nullable()->index();
            }

            if ($ownerColumns && ! Schema::hasColumn($table, 'tenant_key')) {
                $blueprint->string('tenant_key')->nullable()->index();
            }
        });
    }

    /**
     * @param  list<string>  $tables
     */
    private function backfillWebsiteKey(array $tables): void
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'website_key')) {
                continue;
            }

            DB::table($table)->whereNull('website_key')->update(['website_key' => self::DEFAULT_WEBSITE_KEY]);
        }
    }

    private function scopeSlug(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'slug') || ! Schema::hasColumn($table, 'website_key')) {
            return;
        }

        $this->dropUniqueOnColumns($table, ['slug']);
        $this->ensureUnique($table, ['website_key', 'slug'], $table.'_website_slug_unique');
    }

    /**
     * @param  list<string>  $columns
     */
    private function scopeLocationName(string $table, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        $this->ensureUnique($table, $columns, $indexName);
    }

    private function scopeMediaFolderPath(): void
    {
        if (! Schema::hasTable('cms_media_folders') || ! Schema::hasColumn('cms_media_folders', 'path')) {
            return;
        }

        $this->dropUniqueOnColumns('cms_media_folders', ['path']);
        $this->ensureUnique('cms_media_folders', ['website_key', 'path'], 'cms_media_folders_website_path_unique');
    }

    private function scopeThemeTables(): void
    {
        $this->ensureWebsiteColumns('theme_translations', ownerColumns: false);
        $this->ensureWebsiteColumns('theme_demo_records', ownerColumns: false);

        if (Schema::hasTable('theme_translations')) {
            $this->dropUniqueOnColumns('theme_translations', ['theme_key', 'locale', 'group', 'translation_key']);
            $this->ensureUnique(
                'theme_translations',
                ['website_key', 'theme_key', 'locale', 'group', 'translation_key'],
                'theme_translations_website_unique_entry'
            );
            $this->ensureIndex('theme_translations', ['website_key', 'theme_key', 'locale'], 'theme_translations_website_theme_locale_idx');
        }

        if (Schema::hasTable('theme_demo_records')) {
            $this->dropUniqueOnColumns('theme_demo_records', ['model_type', 'model_id']);
            $this->ensureUnique('theme_demo_records', ['website_key', 'model_type', 'model_id'], 'theme_demo_records_website_model_unique');
            $this->ensureIndex('theme_demo_records', ['website_key', 'theme_key'], 'theme_demo_records_website_theme_idx');
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function ensureIndex(string $table, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($table) || $this->hasIndex($table, $indexName)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, fn (Blueprint $blueprint): mixed => $blueprint->index($columns, $indexName));
    }

    /**
     * @param  list<string>  $columns
     */
    private function ensureUnique(string $table, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($table) || $this->hasIndex($table, $indexName)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, fn (Blueprint $blueprint): mixed => $blueprint->unique($columns, $indexName));
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropUniqueOnColumns(string $table, array $columns): void
    {
        foreach (Schema::getIndexes($table) as $index) {
            $indexColumns = array_values($index['columns'] ?? []);

            if (($index['unique'] ?? false) && $indexColumns === $columns) {
                Schema::table($table, fn (Blueprint $blueprint): mixed => $blueprint->dropUnique($index['name']));
            }
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $index): bool => ($index['name'] ?? '') === $indexName);
    }
};
