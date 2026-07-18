<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE cms_media MODIFY file_path VARCHAR(2048) NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE cms_media SET file_path = '' WHERE file_path IS NULL");

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE cms_media MODIFY file_path VARCHAR(2048) NOT NULL DEFAULT ''");
    }
};
