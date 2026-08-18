<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalog_products')) {
            return;
        }

        Schema::table('catalog_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('catalog_products', 'meta_keywords')) {
                $table->text('meta_keywords')->nullable()->after('meta_description');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('catalog_products')) {
            return;
        }

        Schema::table('catalog_products', function (Blueprint $table): void {
            if (Schema::hasColumn('catalog_products', 'meta_keywords')) {
                $table->dropColumn('meta_keywords');
            }
        });
    }
};
