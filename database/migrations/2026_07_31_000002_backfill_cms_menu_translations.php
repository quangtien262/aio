<?php

use App\Core\Cms\CmsMenuTranslationBackfill;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(CmsMenuTranslationBackfill::class)->run();
    }

    public function down(): void
    {
        // The v2 rows only add stable label overrides. Legacy positional rows
        // remain untouched as a rollback source, so destructive reversal is
        // intentionally avoided.
    }
};
