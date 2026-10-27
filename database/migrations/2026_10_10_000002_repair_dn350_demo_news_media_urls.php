<?php

use App\Models\CmsMedia;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_media') || ! Schema::hasTable('theme_demo_records')) {
            return;
        }

        // Preserve IDs, article content, and user uploads. Repair only marked DN350 demo covers.
        foreach (['gallery-team.webp', 'gallery-kitchen-work.webp', 'gallery-moving.webp'] as $image) {
            $path = 'theme-demo/dn350/'.$image;
            DB::table('cms_media')
                ->where('file_path', $path)
                ->whereIn('id', DB::table('theme_demo_records')
                    ->select('model_id')
                    ->where('theme_key', 'DN350')
                    ->where('preset_key', 'dn350-cleaning')
                    ->where('model_type', CmsMedia::class)
                    ->whereColumn('theme_demo_records.website_key', 'cms_media.website_key'))
                ->update(['file_path' => '', 'file_url' => '/'.$path, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Keep working public URLs when rolling back; do not restore broken storage paths.
    }
};
