<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(Schema::getIndexes('landing_pages'))->keyBy('name');

        Schema::table('landing_pages', function (Blueprint $table) use ($indexes): void {
            if ($indexes->has('landing_pages_website_key_slug_unique')) {
                $table->dropUnique('landing_pages_website_key_slug_unique');
            }

            if (! $indexes->has('landing_pages_website_theme_slug_unique')) {
                $table->unique(['website_key', 'theme_key', 'slug'], 'landing_pages_website_theme_slug_unique');
            }
        });
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('landing_pages'))->keyBy('name');
        $hasDuplicateLegacyKeys = DB::table('landing_pages')
            ->select('website_key', 'slug')
            ->groupBy('website_key', 'slug')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        Schema::table('landing_pages', function (Blueprint $table) use ($indexes, $hasDuplicateLegacyKeys): void {
            if ($indexes->has('landing_pages_website_theme_slug_unique')) {
                $table->dropUnique('landing_pages_website_theme_slug_unique');
            }

            if (! $hasDuplicateLegacyKeys && ! $indexes->has('landing_pages_website_key_slug_unique')) {
                $table->unique(['website_key', 'slug'], 'landing_pages_website_key_slug_unique');
            }
        });
    }
};
