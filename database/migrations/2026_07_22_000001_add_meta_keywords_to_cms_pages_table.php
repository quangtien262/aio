<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_pages') || Schema::hasColumn('cms_pages', 'meta_keywords')) {
            return;
        }

        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->text('meta_keywords')->nullable()->after('meta_description');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cms_pages') || ! Schema::hasColumn('cms_pages', 'meta_keywords')) {
            return;
        }

        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->dropColumn('meta_keywords');
        });
    }
};
