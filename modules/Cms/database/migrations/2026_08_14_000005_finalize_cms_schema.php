<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Core migrations intentionally skip optional CMS tables when the module
     * has not been installed yet. Re-run their idempotent schema/data upgrades
     * from the owning package so a later install reaches the same latest shape.
     */
    public function up(): void
    {
        foreach ([
            '2026_06_29_000001_add_is_highlight_to_content_tables.php',
            '2026_06_29_000003_create_cms_service_categories_table.php',
            '2026_07_01_000002_add_meta_keywords_to_cms_posts_table.php',
            '2026_07_16_000001_add_meta_keywords_to_cms_services_table.php',
            '2026_07_17_000001_add_folders_to_cms_media.php',
            '2026_07_17_000002_create_cms_project_categories_table.php',
            '2026_07_17_000003_make_cms_media_file_path_nullable.php',
            '2026_07_18_000001_add_multi_site_scope_to_cms_tables.php',
            '2026_07_22_000001_add_meta_keywords_to_cms_pages_table.php',
            '2026_07_30_000002_create_cms_page_translations_table.php',
            '2026_07_31_000001_add_stable_item_keys_to_cms_menus.php',
            '2026_07_31_000002_backfill_cms_menu_translations.php',
            '2026_07_31_000003_repair_localized_navigation_contract.php',
            '2026_08_13_000003_create_cms_post_comments_table.php',
            '2026_08_14_000001_add_seo_fields_to_localized_categories.php',
        ] as $migrationFile) {
            /** @var Migration $migration */
            $migration = require database_path('migrations/'.$migrationFile);
            $migration->up();
        }

        $this->addDeferredServiceInterestForeignKey();
    }

    public function down(): void
    {
        // These are additive, data-preserving upgrades shared with core.
        // The module lifecycle never purges customer content on uninstall.
    }

    private function addDeferredServiceInterestForeignKey(): void
    {
        $tableName = 'customer_service_interests';
        $foreignName = 'customer_service_interests_cms_service_id_foreign';

        if (
            ! Schema::hasTable($tableName)
            || ! Schema::hasColumn($tableName, 'cms_service_id')
            || collect(Schema::getForeignKeys($tableName))
                ->contains(fn (array $foreign): bool => ($foreign['name'] ?? '') === $foreignName)
        ) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($foreignName): void {
            $table->foreign('cms_service_id', $foreignName)
                ->references('id')
                ->on('cms_services')
                ->nullOnDelete();
        });
    }
};
