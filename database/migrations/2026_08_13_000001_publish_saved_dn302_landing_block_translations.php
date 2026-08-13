<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('landing_pages')
            || ! Schema::hasTable('landing_page_blocks')
            || ! Schema::hasTable('landing_page_block_data')
        ) {
            return;
        }

        $blockIds = DB::table('landing_page_blocks as blocks')
            ->join('landing_pages as pages', 'pages.id', '=', 'blocks.landing_page_id')
            ->where('pages.theme_key', 'DN302')
            ->pluck('blocks.id');

        if ($blockIds->isEmpty()) {
            return;
        }

        DB::table('landing_page_block_data')
            ->whereIn('landing_page_block_id', $blockIds)
            ->where('translation_status', 'draft')
            ->where('is_machine_translated', false)
            ->where('translation_meta', 'like', '%"editor":"landing.blocks"%')
            ->update([
                'translation_status' => 'published',
                'reviewed_at' => DB::raw('COALESCE(reviewed_at, CURRENT_TIMESTAMP)'),
                'translation_published_at' => DB::raw('COALESCE(translation_published_at, CURRENT_TIMESTAMP)'),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Publishing is a user-visible content action and must not be reverted
        // automatically because later edits may already depend on this state.
    }
};
