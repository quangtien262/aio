<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('landing_pages') || Schema::hasColumn('landing_pages', 'sort_order')) {
            return;
        }

        Schema::table('landing_pages', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0)->after('is_home');
            $table->index('sort_order', 'landing_pages_sort_order_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('landing_pages') || ! Schema::hasColumn('landing_pages', 'sort_order')) {
            return;
        }

        Schema::table('landing_pages', function (Blueprint $table): void {
            $table->dropIndex('landing_pages_sort_order_index');
            $table->dropColumn('sort_order');
        });
    }
};
