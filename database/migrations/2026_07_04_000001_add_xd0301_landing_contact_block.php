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
            || ! Schema::hasTable('landing_page_data')
            || ! Schema::hasTable('landing_page_blocks')
            || ! Schema::hasTable('landing_page_block_data')
        ) {
            return;
        }

        $now = now();
        $defaults = [
            'vi' => [
                'title' => 'CÔNG TY CP PHỤ GIA VÀ HOÁ CHẤT DẦU KHÍ',
                'subtitle' => 'Thông tin liên hệ',
                'description' => 'Hãy cho chúng tôi biết nhu cầu, quy mô và thời gian dự kiến. Đội ngũ tư vấn sẽ kiểm tra và đề xuất hướng triển khai phù hợp.',
                'button_label' => 'Gửi liên hệ',
                'content' => [
                    'form_title' => 'Gửi yêu cầu liên hệ',
                    'note_title' => 'Chia sẻ nhu cầu, chúng tôi tư vấn đúng giải pháp.',
                    'note_text' => 'Hãy gửi thêm địa điểm, diện tích, tiến độ mong muốn hoặc yêu cầu kỹ thuật để đội ngũ chuẩn bị phương án phù hợp ngay từ lần phản hồi đầu tiên.',
                ],
            ],
            'en' => [
                'title' => 'PETROLEUM ADDITIVES AND CHEMICALS JSC',
                'subtitle' => 'Contact info',
                'description' => 'Tell us about your project, timeline and expected scope. We will review and advise the next practical step.',
                'button_label' => 'Send request',
                'content' => [
                    'form_title' => 'Send a request',
                    'note_title' => 'Share the essentials, we will shape the right solution.',
                    'note_text' => 'Add your site location, surface area, expected timeline or technical requirements so our team can prepare a practical recommendation.',
                ],
            ],
        ];

        DB::table('landing_pages')
            ->where('theme_key', 'XD0301')
            ->where('is_home', true)
            ->orderBy('id')
            ->get(['id', 'theme_key'])
            ->each(function (object $page) use ($defaults, $now): void {
                $exists = DB::table('landing_page_blocks')
                    ->where('landing_page_id', $page->id)
                    ->where('block_type', 'landing_contact')
                    ->exists();

                if ($exists) {
                    return;
                }

                $maxSort = (int) DB::table('landing_page_blocks')
                    ->where('landing_page_id', $page->id)
                    ->max('sort_order');

                $blockId = DB::table('landing_page_blocks')->insertGetId([
                    'landing_page_id' => $page->id,
                    'theme_key' => 'XD0301',
                    'block_type' => 'landing_contact',
                    'sort_order' => $maxSort + 10,
                    'is_visible' => true,
                    'anchor_id' => 'lien-he',
                    'settings' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'media' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $locales = DB::table('landing_page_data')
                    ->where('landing_page_id', $page->id)
                    ->pluck('locale')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if ($locales === []) {
                    $locales = ['vi', 'en'];
                }

                foreach ($locales as $locale) {
                    $data = $defaults[$locale] ?? $defaults['vi'];

                    DB::table('landing_page_block_data')->insert([
                        'landing_page_block_id' => $blockId,
                        'locale' => $locale,
                        'title' => $data['title'],
                        'subtitle' => $data['subtitle'],
                        'description' => $data['description'],
                        'button_label' => $data['button_label'],
                        'content' => json_encode($data['content'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('landing_page_blocks')) {
            return;
        }

        DB::table('landing_page_blocks')
            ->where('theme_key', 'XD0301')
            ->where('block_type', 'landing_contact')
            ->delete();
    }
};
