<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('catalog_categories')) {
            DB::table('catalog_categories')->whereNull('website_key')->update(['website_key' => 'website-main']);

            Schema::table('catalog_categories', function (Blueprint $table): void {
                $table->dropUnique('catalog_categories_slug_unique');
                $table->unique(['website_key', 'slug'], 'catalog_categories_website_slug_unique');
                $table->index(['website_key', 'parent_id'], 'catalog_categories_website_parent_idx');
            });
        }

        if (Schema::hasTable('catalog_products')) {
            DB::table('catalog_products')->whereNull('website_key')->update(['website_key' => 'website-main']);

            Schema::table('catalog_products', function (Blueprint $table): void {
                $table->dropUnique('catalog_products_sku_unique');
                $table->unique(['website_key', 'sku'], 'catalog_products_website_sku_unique');
                $table->index(['website_key', 'slug'], 'catalog_products_website_slug_idx');
                $table->index(['website_key', 'catalog_category_id'], 'catalog_products_website_category_idx');
            });
        }
    }

    public function down(): void
    {
        // Keep the website-scoped indexes. Reverting to global uniqueness can
        // fail after valid multi-domain content has been copied.
    }
};
