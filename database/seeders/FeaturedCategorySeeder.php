<?php

namespace Database\Seeders;

use App\Models\CmsFeaturedCategory;
use Illuminate\Database\Seeder;

class FeaturedCategorySeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'name' => 'TH0001 Home featured categories',
                'location' => 'home-featured-categories',
                'website_key' => 'website-main',
                'items' => [
                    [
                        'label' => 'Ăn uống cuối tuần',
                        'link_type' => 'custom',
                        'link_value' => null,
                        'custom_url' => '/danh-muc/an-uong',
                        'url' => '/danh-muc/an-uong',
                        'target' => '_self',
                    ],
                    [
                        'label' => 'Spa & làm đẹp',
                        'link_type' => 'custom',
                        'link_value' => null,
                        'custom_url' => '/danh-muc/spa-lam-dep',
                        'url' => '/danh-muc/spa-lam-dep',
                        'target' => '_self',
                    ],
                    [
                        'label' => 'Combo nghỉ dưỡng',
                        'link_type' => 'custom',
                        'link_value' => null,
                        'custom_url' => '/danh-muc/nghi-duong',
                        'url' => '/danh-muc/nghi-duong',
                        'target' => '_self',
                    ],
                    [
                        'label' => 'Vui chơi gia đình',
                        'link_type' => 'custom',
                        'link_value' => null,
                        'custom_url' => '/danh-muc/vui-choi',
                        'url' => '/danh-muc/vui-choi',
                        'target' => '_self',
                    ],
                    [
                        'label' => 'Voucher tập luyện',
                        'link_type' => 'custom',
                        'link_value' => null,
                        'custom_url' => '/danh-muc/the-thao',
                        'url' => '/danh-muc/the-thao',
                        'target' => '_self',
                    ],
                ],
            ],
            [
                'name' => 'Reusable footer featured categories',
                'location' => 'footer-featured-categories',
                'website_key' => 'website-main',
                'items' => [
                    [
                        'label' => 'Deal mới mỗi ngày',
                        'link_type' => 'custom',
                        'link_value' => null,
                        'custom_url' => '/tin-tuc',
                        'url' => '/tin-tuc',
                        'target' => '_self',
                    ],
                    [
                        'label' => 'Danh mục nổi bật',
                        'link_type' => 'custom',
                        'link_value' => null,
                        'custom_url' => '/danh-muc',
                        'url' => '/danh-muc',
                        'target' => '_self',
                    ],
                    [
                        'label' => 'Ưu đãi doanh nghiệp',
                        'link_type' => 'custom',
                        'link_value' => null,
                        'custom_url' => '/gioi-thieu',
                        'url' => '/gioi-thieu',
                        'target' => '_self',
                    ],
                    [
                        'label' => 'Liên hệ hợp tác',
                        'link_type' => 'custom',
                        'link_value' => null,
                        'custom_url' => '/lien-he',
                        'url' => '/lien-he',
                        'target' => '_self',
                    ],
                ],
            ],
        ];

        foreach ($groups as $group) {
            CmsFeaturedCategory::query()->updateOrCreate(
                [
                    'location' => $group['location'],
                    'name' => $group['name'],
                ],
                [
                    'website_key' => $group['website_key'],
                    'owner_key' => null,
                    'tenant_key' => null,
                    'items' => $group['items'],
                ],
            );
        }
    }
}
