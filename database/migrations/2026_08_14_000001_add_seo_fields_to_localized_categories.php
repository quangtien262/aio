<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['catalog_categories', 'cms_service_categories', 'cms_project_categories'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('meta_title')->nullable()->after('description');
                $table->text('meta_description')->nullable()->after('meta_title');
            });
        }
    }

    public function down(): void
    {
        foreach (['catalog_categories', 'cms_service_categories', 'cms_project_categories'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['meta_title', 'meta_description']);
            });
        }
    }
};
