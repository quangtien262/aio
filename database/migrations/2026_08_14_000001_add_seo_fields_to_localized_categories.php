<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['catalog_categories', 'cms_service_categories', 'cms_project_categories'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                if (! Schema::hasColumn($table->getTable(), 'meta_title')) {
                    $table->string('meta_title')->nullable()->after('description');
                }

                if (! Schema::hasColumn($table->getTable(), 'meta_description')) {
                    $table->text('meta_description')->nullable()->after('meta_title');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['catalog_categories', 'cms_service_categories', 'cms_project_categories'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $columns = collect(['meta_title', 'meta_description'])
                    ->filter(fn (string $column): bool => Schema::hasColumn($table->getTable(), $column))
                    ->all();

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
