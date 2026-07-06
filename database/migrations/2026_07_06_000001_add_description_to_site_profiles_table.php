<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_profiles', 'description')) {
                $table->text('description')->nullable()->after('site_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('site_profiles', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
