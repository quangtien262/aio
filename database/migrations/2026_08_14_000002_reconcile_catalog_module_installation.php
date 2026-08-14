<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('module_installations')
            || ! Schema::hasTable('catalog_categories')
            || ! Schema::hasTable('catalog_products')
        ) {
            return;
        }

        $hasCatalogContent = DB::table('catalog_categories')->exists()
            || DB::table('catalog_products')->exists();

        if (! $hasCatalogContent || DB::table('module_installations')->where('key', 'catalog')->exists()) {
            return;
        }

        DB::table('module_installations')->insert([
            'key' => 'catalog',
            'name' => 'Catalog',
            'version' => '0.2.0',
            'status' => 'enabled',
            'website_types' => json_encode(['ecommerce']),
            'dependencies' => json_encode(['cms']),
            'installed_at' => now(),
            'enabled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // This migration reconciles a legacy installation record and must not disable live catalog data.
    }
};
