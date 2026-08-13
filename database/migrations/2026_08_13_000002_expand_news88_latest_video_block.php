<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('landing_pages') || ! Schema::hasTable('landing_page_blocks')) {
            return;
        }

        DB::table('landing_page_blocks as blocks')
            ->join('landing_pages as pages', 'pages.id', '=', 'blocks.landing_page_id')
            ->where('pages.theme_key', 'NEWS88')
            ->where('blocks.block_type', 'news88_latest_video')
            ->select(['blocks.id', 'blocks.settings'])
            ->orderBy('blocks.id')
            ->get()
            ->each(function (object $block): void {
                $settings = json_decode((string) $block->settings, true);
                $settings = is_array($settings) ? $settings : [];

                if ((int) ($settings['limit'] ?? 0) >= 8) {
                    return;
                }

                $settings['limit'] = 8;

                DB::table('landing_page_blocks')
                    ->where('id', $block->id)
                    ->update([
                        'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // A later editor change may intentionally depend on the larger feed.
    }
};
