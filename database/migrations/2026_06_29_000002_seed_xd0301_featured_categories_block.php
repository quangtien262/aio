<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('landing_pages') || ! Schema::hasTable('landing_page_blocks') || ! Schema::hasTable('landing_page_block_data')) {
            return;
        }

        $locales = $this->locales();

        DB::table('landing_pages')
            ->where('theme_key', 'XD0301')
            ->where('is_home', true)
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $page) use ($locales): void {
                $exists = DB::table('landing_page_blocks')
                    ->where('landing_page_id', $page->id)
                    ->where('block_type', 'featured_categories')
                    ->exists();

                if ($exists) {
                    return;
                }

                $heroSort = (int) (DB::table('landing_page_blocks')
                    ->where('landing_page_id', $page->id)
                    ->where('block_type', 'hero_slider')
                    ->value('sort_order') ?? 10);

                $blockId = DB::table('landing_page_blocks')->insertGetId([
                    'landing_page_id' => $page->id,
                    'theme_key' => 'XD0301',
                    'block_type' => 'featured_categories',
                    'sort_order' => $heroSort + 5,
                    'is_visible' => true,
                    'anchor_id' => 'danh-muc-noi-bat',
                    'settings' => json_encode([
                        'source' => 'catalog_categories',
                        'limit' => 6,
                        'featured_only' => false,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'media' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($locales as $locale) {
                    $data = $locale === 'en' ? $this->englishData() : $this->vietnameseData();

                    DB::table('landing_page_block_data')->insert([
                        'landing_page_block_id' => $blockId,
                        'locale' => $locale,
                        'title' => $data['title'],
                        'subtitle' => $data['subtitle'],
                        'description' => $data['description'],
                        'button_label' => $data['button_label'],
                        'content' => json_encode($data['content'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('landing_page_blocks')) {
            return;
        }

        $blockIds = DB::table('landing_page_blocks')
            ->where('theme_key', 'XD0301')
            ->where('block_type', 'featured_categories')
            ->pluck('id');

        if ($blockIds->isNotEmpty() && Schema::hasTable('landing_page_block_data')) {
            DB::table('landing_page_block_data')->whereIn('landing_page_block_id', $blockIds)->delete();
        }

        DB::table('landing_page_blocks')->whereIn('id', $blockIds)->delete();
    }

    /**
     * @return array<int, string>
     */
    private function locales(): array
    {
        if (Schema::hasTable('system_locales')) {
            $locales = DB::table('system_locales')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('code')
                ->filter()
                ->values()
                ->all();

            if ($locales !== []) {
                return $locales;
            }
        }

        return ['vi', 'en'];
    }

    /**
     * @return array<string, mixed>
     */
    private function vietnameseData(): array
    {
        return [
            'title' => 'Danh mục trọng tâm',
            'subtitle' => 'Khám phá nhanh',
            'description' => 'Các nhóm nội dung quan trọng nhất giúp khách hàng đi thẳng tới nhu cầu chính.',
            'button_label' => 'Xem thêm',
            'content' => ['items' => [
                ['title' => 'Nhà ở dân dụng', 'summary' => 'Thiết kế, thi công và hoàn thiện nhà phố, biệt thự.', 'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=80', 'url' => '#dich-vu'],
                ['title' => 'Không gian thương mại', 'summary' => 'Showroom, văn phòng và khách sạn theo chuẩn vận hành.', 'image' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=900&q=80', 'url' => '#du-an'],
                ['title' => 'Cải tạo nội thất', 'summary' => 'Tối ưu công năng, vật liệu và trải nghiệm sử dụng.', 'image' => 'https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=900&q=80', 'url' => '#dich-vu'],
                ['title' => 'Tư vấn kỹ thuật', 'summary' => 'Kiểm soát tiến độ, chi phí và chất lượng công trình.', 'image' => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=900&q=80', 'url' => '#lien-he'],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function englishData(): array
    {
        return [
            'title' => 'Key categories',
            'subtitle' => 'Explore fast',
            'description' => 'Priority content groups that help visitors reach the right offer quickly.',
            'button_label' => 'View more',
            'content' => ['items' => [
                ['title' => 'Residential builds', 'summary' => 'Design, construction and finishing for houses and villas.', 'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=80', 'url' => '#dich-vu'],
                ['title' => 'Commercial spaces', 'summary' => 'Showrooms, offices and hotels ready for operation.', 'image' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=900&q=80', 'url' => '#du-an'],
                ['title' => 'Interior upgrades', 'summary' => 'Function, material and living-experience optimization.', 'image' => 'https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=900&q=80', 'url' => '#dich-vu'],
                ['title' => 'Technical consulting', 'summary' => 'Schedule, cost and quality control for each project.', 'image' => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=900&q=80', 'url' => '#lien-he'],
            ]],
        ];
    }
};
