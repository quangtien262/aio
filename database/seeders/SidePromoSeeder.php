<?php

namespace Database\Seeders;

use App\Models\CmsSidePromo;
use Illuminate\Database\Seeder;

class SidePromoSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->groups() as $group) {
            CmsSidePromo::query()->updateOrCreate(
                [
                    'location' => $group['location'],
                    'name' => $group['name'],
                ],
                [
                    'website_key' => null,
                    'owner_key' => null,
                    'tenant_key' => null,
                    'items' => $group['items'],
                ]
            );
        }
    }

    private function groups(): array
    {
        return [
            [
                'location' => 'home-hero-side-promos',
                'name' => 'TH0001 Hero side promos',
                'items' => [
                    [
                        'sort_order' => 0,
                        'badge' => 'Flash deal',
                        'title' => 'Voucher cuối tuần',
                        'subtitle' => 'Ăn uống nhóm nhỏ giá tốt',
                        'cta_label' => 'Xem deal',
                        'image' => 'https://picsum.photos/seed/th0001-side-voucher/360/180',
                        'target' => '_self',
                        'link_type' => 'custom',
                        'link_value' => null,
                        'custom_url' => '/vi#featured',
                        'url' => '/vi#featured',
                    ],
                    [
                        'sort_order' => 1,
                        'badge' => 'Hot trend',
                        'title' => 'Hot trend',
                        'subtitle' => 'Spa đôi linh hoạt khung giờ',
                        'cta_label' => 'Giữ chỗ',
                        'image' => 'https://picsum.photos/seed/th0001-side-spa/360/180',
                        'target' => '_self',
                        'link_type' => 'custom',
                        'link_value' => null,
                        'custom_url' => '/vi#featured',
                        'url' => '/vi#featured',
                    ],
                    [
                        'sort_order' => 2,
                        'badge' => 'Top sale',
                        'title' => 'Top sản phẩm',
                        'subtitle' => 'Gói tập phòng gym bán chạy',
                        'cta_label' => 'Mở ngay',
                        'image' => 'https://picsum.photos/seed/th0001-side-gym/360/180',
                        'target' => '_self',
                        'link_type' => 'custom',
                        'link_value' => null,
                        'custom_url' => '/vi#featured',
                        'url' => '/vi#featured',
                    ],
                    [
                        'sort_order' => 3,
                        'badge' => 'Combo mới',
                        'title' => 'Combo mới',
                        'subtitle' => 'Nghỉ dưỡng ngắn ngày dễ chốt',
                        'cta_label' => 'Khám phá',
                        'image' => 'https://picsum.photos/seed/th0001-side-resort/360/180',
                        'target' => '_self',
                        'link_type' => 'custom',
                        'link_value' => null,
                        'custom_url' => '/vi#featured',
                        'url' => '/vi#featured',
                    ],
                ],
            ],
            [
                'location' => 'home-secondary-side-promos',
                'name' => 'Reusable secondary side promos',
                'items' => [
                    [
                        'sort_order' => 0,
                        'badge' => 'Giờ vàng',
                        'title' => 'Deal trưa văn phòng',
                        'subtitle' => 'Suất ăn nhanh cho team nhỏ',
                        'cta_label' => 'Xem ngay',
                        'image' => 'https://picsum.photos/seed/shared-side-lunch/360/180',
                        'target' => '_self',
                        'link_type' => 'custom',
                        'link_value' => null,
                        'custom_url' => '/vi#featured',
                        'url' => '/vi#featured',
                    ],
                    [
                        'sort_order' => 1,
                        'badge' => 'Cuối tuần',
                        'title' => 'Workshop cuối tuần',
                        'subtitle' => 'Hoạt động trải nghiệm theo nhóm',
                        'cta_label' => 'Đặt chỗ',
                        'image' => 'https://picsum.photos/seed/shared-side-workshop/360/180',
                        'target' => '_self',
                        'link_type' => 'custom',
                        'link_value' => null,
                        'custom_url' => '/vi#featured',
                        'url' => '/vi#featured',
                    ],
                    [
                        'sort_order' => 2,
                        'badge' => 'B2B',
                        'title' => 'Gói doanh nghiệp',
                        'subtitle' => 'Ưu đãi riêng cho booking số lượng',
                        'cta_label' => 'Liên hệ ngay',
                        'image' => 'https://picsum.photos/seed/shared-side-business/360/180',
                        'target' => '_self',
                        'link_type' => 'custom',
                        'link_value' => null,
                        'custom_url' => '/vi#featured',
                        'url' => '/vi#featured',
                    ],
                ],
            ],
        ];
    }
}
