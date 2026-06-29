<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addHighlightColumn('cms_posts');
        $this->addHighlightColumn('catalog_products');
        $this->addHighlightColumn('cms_services');
        $this->addHighlightColumn('cms_projects');

        foreach (['catalog_products', 'cms_services', 'cms_projects'] as $table) {
            if (Schema::hasColumn($table, 'is_featured')) {
                DB::table($table)->where('is_featured', true)->update(['is_highlight' => true]);
            }
        }
    }

    public function down(): void
    {
        foreach (['cms_projects', 'cms_services', 'catalog_products', 'cms_posts'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'is_highlight')) {
                Schema::table($table, function (Blueprint $table): void {
                    $table->dropColumn('is_highlight');
                });
            }
        }
    }

    private function addHighlightColumn(string $table): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'is_highlight')) {
            return;
        }

        Schema::table($table, function (Blueprint $table): void {
            $table->boolean('is_highlight')->default(false)->index();
        });
    }
};
