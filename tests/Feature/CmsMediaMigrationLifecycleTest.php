<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CmsMediaMigrationLifecycleTest extends TestCase
{
    public function test_core_media_upgrade_skips_an_uninstalled_cms_module(): void
    {
        Schema::shouldReceive('hasTable')
            ->twice()
            ->with('cms_media')
            ->andReturnFalse();
        Schema::shouldReceive('hasColumn')->never();
        DB::shouldReceive('getDriverName')->never();
        DB::shouldReceive('statement')->never();

        $migration = require database_path(
            'migrations/2026_07_17_000003_make_cms_media_file_path_nullable.php',
        );

        $migration->up();
        $migration->down();
    }

    public function test_media_upgrade_still_alters_an_existing_mysql_cms_table(): void
    {
        Schema::shouldReceive('hasTable')
            ->once()
            ->with('cms_media')
            ->andReturnTrue();
        Schema::shouldReceive('hasColumn')
            ->once()
            ->with('cms_media', 'file_path')
            ->andReturnTrue();
        DB::shouldReceive('getDriverName')->once()->andReturn('mysql');
        DB::shouldReceive('statement')
            ->once()
            ->with('ALTER TABLE cms_media MODIFY file_path VARCHAR(2048) NULL');

        $migration = require database_path(
            'migrations/2026_07_17_000003_make_cms_media_file_path_nullable.php',
        );

        $migration->up();
    }
}
