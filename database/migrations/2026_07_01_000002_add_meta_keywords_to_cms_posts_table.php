<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_posts')) {
            return;
        }

        Schema::table('cms_posts', function (Blueprint $table): void {
            if (! Schema::hasColumn('cms_posts', 'meta_keywords')) {
                $table->text('meta_keywords')->nullable()->after('meta_description');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cms_posts')) {
            return;
        }

        Schema::table('cms_posts', function (Blueprint $table): void {
            if (Schema::hasColumn('cms_posts', 'meta_keywords')) {
                $table->dropColumn('meta_keywords');
            }
        });
    }
};
