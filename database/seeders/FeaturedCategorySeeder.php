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
                'name' => 'Reusable footer featured categories',
                'location' => 'footer-featured-categories',
                'website_key' => 'website-main',
                'items' => [
                    [
                        'label' => 'Deal mới mỗi ngày',
                        'link_type' => 'custom',
                        'link_value' => null,
                        'custom_url' => '/c',
                        'url' => '/c',
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
                        'custom_url' => '/contact',
                        'url' => '/contact',
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
                    'items' => $group['items'],
                ],
            );
        }
    }
}
