<?php

use App\Core\Cms\CmsMenuLinkIdentityBackfill;
use App\Support\Localization\CmsPageRouteRepair;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Additive and idempotent: existing URLs stay available as legacy
        // fallbacks while Menu gains stable resource identity and Page gains
        // the missing source-locale canonical route.
        app(CmsPageRouteRepair::class)->run(
            websiteKey: null,
            dryRun: false,
            failOnError: true,
        );
        app(CmsMenuLinkIdentityBackfill::class)->run();
    }

    public function down(): void
    {
        // Identity and canonical route rows are intentionally retained.
        // Removing them would reintroduce locale-dependent broken links.
    }
};
