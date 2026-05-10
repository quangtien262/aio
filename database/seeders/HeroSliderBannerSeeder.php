<?php

namespace Database\Seeders;

use App\Models\SiteBanner;
use Illuminate\Database\Seeder;

class HeroSliderBannerSeeder extends Seeder
{
    public function run(): void
    {
        $slides = [
            [
                'theme_key' => 'SER0100',
                'placement' => 'hero-slider',
                'sort_order' => 0,
                'title' => 'Vận hành tour đoàn gọn và rõ',
                'image_url' => url('theme-demo/service/ser-tour-ops-hero.svg'),
                'link_url' => '/vi',
                'metadata' => [
                    'eyebrow' => 'Tour ops',
                    'summary' => 'Nhìn nhanh block vận hành, hỗ trợ chốt tuyến tour và shuttle nội đô trong cùng một màn hình.',
                    'button_label' => 'Xem chi tiết',
                    'image_position' => 'center',
                    'show_caption' => true,
                ],
            ],
            [
                'theme_key' => 'SER0100',
                'placement' => 'hero-slider',
                'sort_order' => 1,
                'title' => 'Đưa đón đúng giờ cho khách lẻ và đoàn nhỏ',
                'image_url' => url('theme-demo/service/service-banner-1.svg'),
                'link_url' => '/vi',
                'metadata' => [
                    'eyebrow' => 'Airport pickup',
                    'summary' => 'Phù hợp cho lead hotline, chuyển đổi nhanh và các tuyến pickup cần thông tin rõ ràng.',
                    'button_label' => 'Xem chi tiết',
                    'image_position' => 'left center',
                    'show_caption' => true,
                ],
            ],
            [
                'theme_key' => 'SER0100',
                'placement' => 'hero-slider',
                'sort_order' => 2,
                'title' => 'Banner phụ cho tuyến nội đô và VIP charter',
                'image_url' => url('theme-demo/service/service-banner-2.svg'),
                'link_url' => '/vi',
                'metadata' => [
                    'eyebrow' => 'City transfer',
                    'summary' => 'Giữ nhịp visual đều tay hơn bộ card cũ nhưng vẫn đồng nhất tông màu của theme.',
                    'button_label' => 'Xem chi tiết',
                    'image_position' => 'left center',
                    'show_caption' => true,
                ],
            ],
            [
                'theme_key' => 'SER0100',
                'placement' => 'hero-slider',
                'sort_order' => 3,
                'title' => 'Đi xe sân bay và shuttle doanh nghiệp',
                'image_url' => url('theme-demo/service/service-hero-main.svg'),
                'link_url' => '/vi',
                'metadata' => [
                    'eyebrow' => 'Điều phối nhanh',
                    'summary' => 'Tập trung lead nóng, lịch xe trong ngày và nhu cầu đặt chuyến gấp của doanh nghiệp.',
                    'button_label' => 'Xem chi tiết',
                    'image_position' => 'right center',
                    'show_caption' => false,
                ],
            ],

            // TH0001
            [
                'theme_key' => 'TH0001',
                'placement' => 'hero-slider',
                'sort_order' => 0,
                'title' => 'Buffet cuối tuần cho nhóm 4-6 người',
                'image_url' => 'https://picsum.photos/seed/th0001-hero-buffet/1280/720',
                'link_url' => '/vi',
                'metadata' => [
                    'eyebrow' => 'Flash sale',
                    'summary' => 'Deal mở màn thiên về ăn uống, dễ chuyển đổi và hợp bố cục hero kiểu marketplace.',
                    'button_label' => 'Xem deal ngay',
                    'image_position' => 'center',
                    'show_caption' => true,
                ],
            ],
            [
                'theme_key' => 'TH0001',
                'placement' => 'hero-slider',
                'sort_order' => 1,
                'title' => 'Voucher spa đôi khung giờ linh hoạt',
                'image_url' => 'https://picsum.photos/seed/th0001-hero-spa/1280/720',
                'link_url' => '/vi',
                'metadata' => [
                    'eyebrow' => 'Hot trend',
                    'summary' => 'Giữ nhịp lifestyle cho hero, caption rõ và gọn để storefront nhìn sạch hơn.',
                    'button_label' => 'Giữ chỗ hôm nay',
                    'image_position' => 'left center',
                    'show_caption' => true,
                ],
            ],
            [
                'theme_key' => 'TH0001',
                'placement' => 'hero-slider',
                'sort_order' => 2,
                'title' => 'Combo nghỉ dưỡng ngắn ngày',
                'image_url' => 'https://picsum.photos/seed/th0001-hero-resort/1280/720',
                'link_url' => '/vi',
                'metadata' => [
                    'eyebrow' => 'Combo mới',
                    'summary' => 'Thêm một nhóm deal nghỉ dưỡng để hero đa ngành hơn mà vẫn giữ nhịp thương mại điện tử.',
                    'button_label' => 'Mở ưu đãi',
                    'image_position' => 'right center',
                    'show_caption' => true,
                ],
            ],
            [
                'theme_key' => 'TH0001',
                'placement' => 'hero-slider',
                'sort_order' => 3,
                'title' => 'Gói gym tháng cho dân văn phòng',
                'image_url' => 'https://picsum.photos/seed/th0001-hero-gym/1280/720',
                'link_url' => '/vi',
                'metadata' => [
                    'eyebrow' => 'Ưu đãi mới',
                    'summary' => 'Giữ CTA đồng bộ nhưng rút gọn caption để slide cuối không bị vỡ nhịp khi xuống dòng.',
                    'button_label' => 'Săn deal ngay',
                    'image_position' => 'center top',
                    'show_caption' => true,
                ],
            ],
        ];

        foreach ($slides as $slide) {
            SiteBanner::query()->updateOrCreate(
                [
                    'theme_key' => $slide['theme_key'],
                    'placement' => $slide['placement'],
                    'sort_order' => $slide['sort_order'],
                ],
                [
                    'title' => $slide['title'],
                    'subtitle' => null,
                    'image_url' => $slide['image_url'],
                    'link_url' => $slide['link_url'],
                    'badge' => null,
                    'metadata' => $slide['metadata'],
                    'is_active' => true,
                ]
            );
        }
    }
}
