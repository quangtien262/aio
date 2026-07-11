<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_pages', function (Blueprint $table): void {
            $table->dropUnique('landing_pages_website_key_slug_unique');
            $table->unique(['website_key', 'theme_key', 'slug'], 'landing_pages_website_theme_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table): void {
            $table->dropUnique('landing_pages_website_theme_slug_unique');
            $table->unique(['website_key', 'slug'], 'landing_pages_website_key_slug_unique');
        });
    }
};
