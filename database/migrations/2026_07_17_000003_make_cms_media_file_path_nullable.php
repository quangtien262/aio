<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Core migrations run before optional modules are installed. The CMS
        // lifecycle replays this migration after creating its own tables.
        if (! Schema::hasTable('cms_media') || ! Schema::hasColumn('cms_media', 'file_path')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE cms_media MODIFY file_path VARCHAR(2048) NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('cms_media') || ! Schema::hasColumn('cms_media', 'file_path')) {
            return;
        }

        DB::statement("UPDATE cms_media SET file_path = '' WHERE file_path IS NULL");

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE cms_media MODIFY file_path VARCHAR(2048) NOT NULL DEFAULT ''");
    }
};
