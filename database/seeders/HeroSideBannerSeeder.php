<?php

namespace Database\Seeders;

use App\Models\SiteBanner;
use Illuminate\Database\Seeder;

class HeroSideBannerSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'theme_key' => 'TH0001',
                'placement' => 'hero-side',
                'sort_order' => 0,
                'title' => 'Voucher cuối tuần',
                'subtitle' => 'Ăn uống nhóm nhỏ giá tốt',
                'image_url' => 'https://picsum.photos/seed/th0001-side-voucher/360/180',
                'link_url' => '/vi#featured',
            ],
            [
                'theme_key' => 'TH0001',
                'placement' => 'hero-side',
                'sort_order' => 1,
                'title' => 'Hot trend',
                'subtitle' => 'Spa đôi linh hoạt khung giờ',
                'image_url' => 'https://picsum.photos/seed/th0001-side-spa/360/180',
                'link_url' => '/vi#featured',
            ],
            [
                'theme_key' => 'TH0001',
                'placement' => 'hero-side',
                'sort_order' => 2,
                'title' => 'Top sản phẩm',
                'subtitle' => 'Gói tập phòng gym bán chạy',
                'image_url' => 'https://picsum.photos/seed/th0001-side-gym/360/180',
                'link_url' => '/vi#featured',
            ],
            [
                'theme_key' => 'TH0001',
                'placement' => 'hero-side',
                'sort_order' => 3,
                'title' => 'Combo mới',
                'subtitle' => 'Nghỉ dưỡng ngắn ngày dễ chốt',
                'image_url' => 'https://picsum.photos/seed/th0001-side-resort/360/180',
                'link_url' => '/vi#featured',
            ],
        ];

        foreach ($items as $item) {
            SiteBanner::query()->updateOrCreate(
                [
                    'theme_key' => $item['theme_key'],
                    'placement' => $item['placement'],
                    'sort_order' => $item['sort_order'],
                ],
                [
                    'title' => $item['title'],
                    'subtitle' => $item['subtitle'],
                    'image_url' => $item['image_url'],
                    'link_url' => $item['link_url'],
                    'badge' => null,
                    'metadata' => [],
                    'is_active' => true,
                ]
            );
        }
    }
}
