<?php

use App\Core\Themes\Demo\Ser103HomepageDataRepair;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $repair = app(Ser103HomepageDataRepair::class);
        foreach (DB::table('landing_pages')->where('theme_key', 'SER103')->where('is_home', true)->distinct()->pluck('website_key') as $websiteKey) {
            $repair->repairForWebsite($websiteKey);
        }
    }

    public function down(): void
    {
        // Keep links tied to published CMS content instead of restoring obsolete sample URLs.
    }
};
