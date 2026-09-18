<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('landing_page_blocks') || ! Schema::hasTable('landing_page_block_data')) {
            return;
        }
        DB::transaction(function (): void {
            foreach (DB::table('landing_page_blocks')->where('theme_key', 'NEWS88')->where('block_type', 'news88_latest_video')->get() as $source) {
                if (DB::table('landing_page_blocks')->where('landing_page_id', $source->landing_page_id)->where('block_type', 'news88_video_posts')->exists()) {
                    continue;
                }
                $block = (array) $source;
                unset($block['id']);
                $settings = json_decode($source->settings ?: '{}', true) ?: [];
                $settings['limit'] = 2;
                $block['settings'] = json_encode($settings);
                $block['block_type'] = 'news88_video_posts';
                $block['anchor_id'] = 'video';
                $block['sort_order'] = $source->sort_order + 1;
                $id = DB::table('landing_page_blocks')->insertGetId($block);
                foreach (DB::table('landing_page_block_data')->where('landing_page_block_id', $source->id)->get() as $row) {
                    $data = (array) $row;
                    unset($data['id']);
                    $data['landing_page_block_id'] = $id;
                    $data['title'] = 'Video';
                    $content = json_decode($row->content ?: '{}', true) ?: [];
                    $content['items'] = array_slice($content['items'] ?? [], 6, 2);
                    $data['content'] = json_encode($content, JSON_UNESCAPED_UNICODE);
                    DB::table('landing_page_block_data')->insert($data);
                }
            }
        });
    }

    public function down(): void
    {
        // Keep independently edited video content when rolling back deployment.
    }
};
