<?php

namespace Modules\Cms\Database\Seeders;

use App\Models\CmsMenu;
use App\Models\CmsPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (! class_exists(__NAMESPACE__.'\\CmsModuleSeeder', false)) {
    class CmsModuleSeeder extends Seeder
    {
        public function run(): void
        {
            CmsPage::query()->withoutGlobalScopes()->updateOrCreate(
                ['website_key' => 'website-main', 'slug' => 'home'],
                [
                    'title' => 'Trang chủ',
                    'status' => 'published',
                    'body' => 'Nội dung mặc định cho module CMS.',
                    'website_key' => 'website-main',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            foreach ([
                'gioi-thieu' => [
                    'title' => 'Giới thiệu',
                    'body' => 'XD0301 là đơn vị thiết kế và thi công xây dựng theo mô hình trọn gói, hỗ trợ chủ đầu tư từ tư vấn ý tưởng, dự toán, hồ sơ kỹ thuật đến giám sát hoàn thiện.',
                ],
                'lien-he' => [
                    'title' => 'Liên hệ',
                    'body' => 'Liên hệ đội ngũ XD0301 để nhận tư vấn thiết kế, thi công và báo giá sơ bộ cho công trình nhà ở, văn phòng hoặc dự án thương mại.',
                ],
            ] as $slug => $page) {
                CmsPage::query()->withoutGlobalScopes()->updateOrCreate(
                    ['website_key' => 'website-main', 'slug' => $slug],
                    [
                        'title' => $page['title'],
                        'status' => 'published',
                        'body' => $page['body'],
                        'website_key' => 'website-main',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }

            if (Schema::hasTable('site_profiles') && Schema::hasTable('cms_menus')) {
                $activeThemeKey = (string) (DB::table('site_profiles')->value('active_theme_key') ?? '');
                $siteProfile = DB::table('site_profiles')->first();

                if (strtoupper($activeThemeKey) === 'XD0301' && $siteProfile) {
                    $branding = json_decode((string) ($siteProfile->branding ?? '{}'), true);
                    $branding = is_array($branding) ? $branding : [];

                    if (empty($branding['logo_url'])) {
                        $branding['logo_url'] = url('theme-previews/XD0301/logo-xd0301.svg');
                        $branding['favicon_url'] = $branding['favicon_url'] ?? $branding['logo_url'];

                        DB::table('site_profiles')->where('id', $siteProfile->id)->update([
                            'branding' => json_encode($branding, JSON_UNESCAPED_UNICODE),
                            'updated_at' => now(),
                        ]);
                    }
                }

                if (strtoupper($activeThemeKey) === 'XD0301' && Schema::hasTable('site_banners')) {
                    $hasHeroSlides = DB::table('site_banners')
                        ->where('theme_key', 'XD0301')
                        ->where('placement', 'xd0301-hero-slider')
                        ->exists();

                    if (! $hasHeroSlides) {
                        foreach ([
                            [
                                'title' => 'Xây dựng ngôi nhà mơ ước',
                                'subtitle' => 'Từ bản vẽ, vật liệu đến thi công hoàn thiện, XD0301 giúp chủ đầu tư kiểm soát chất lượng và tiến độ rõ ràng.',
                                'image_url' => 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=1920&q=85',
                                'link_url' => '#du-an',
                                'badge' => 'Residential',
                                'metadata' => ['eyebrow' => 'Residential', 'summary' => 'Từ bản vẽ, vật liệu đến thi công hoàn thiện, XD0301 giúp chủ đầu tư kiểm soát chất lượng và tiến độ rõ ràng.', 'button_label' => 'Xem dự án →'],
                            ],
                            [
                                'title' => 'Thi công không gian kinh doanh',
                                'subtitle' => 'Đội ngũ kỹ sư và kiến trúc sư phối hợp để bàn giao showroom, văn phòng, khách sạn đúng chuẩn vận hành.',
                                'image_url' => 'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?auto=format&fit=crop&w=1920&q=85',
                                'link_url' => '#dich-vu',
                                'badge' => 'Commercial',
                                'metadata' => ['eyebrow' => 'Commercial', 'summary' => 'Đội ngũ kỹ sư và kiến trúc sư phối hợp để bàn giao showroom, văn phòng, khách sạn đúng chuẩn vận hành.', 'button_label' => 'Xem dịch vụ →'],
                            ],
                            [
                                'title' => 'Quản lý dự án minh bạch',
                                'subtitle' => 'Quy trình báo cáo theo mốc, nghiệm thu từng hạng mục và tối ưu chi phí ngay từ giai đoạn thiết kế.',
                                'image_url' => 'https://images.unsplash.com/photo-1485083269755-a7b559a4fe5e?auto=format&fit=crop&w=1920&q=85',
                                'link_url' => '#danh-gia',
                                'badge' => 'Planning',
                                'metadata' => ['eyebrow' => 'Planning', 'summary' => 'Quy trình báo cáo theo mốc, nghiệm thu từng hạng mục và tối ưu chi phí ngay từ giai đoạn thiết kế.', 'button_label' => 'Xem đánh giá →'],
                            ],
                        ] as $index => $slide) {
                            DB::table('site_banners')->insert([
                                'theme_key' => 'XD0301',
                                'placement' => 'xd0301-hero-slider',
                                'title' => $slide['title'],
                                'subtitle' => $slide['subtitle'],
                                'image_url' => $slide['image_url'],
                                'link_url' => $slide['link_url'],
                                'badge' => $slide['badge'],
                                'metadata' => json_encode($slide['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                'sort_order' => $index,
                                'is_active' => true,
                                'website_key' => 'website-main',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }

                $primaryMenu = CmsMenu::query()
                    ->withoutGlobalScopes()
                    ->where('location', 'primary-navigation')
                    ->first();
                $primaryMenuItems = $primaryMenu?->items ?? [];
                $hasForeignDemoLink = collect(is_array($primaryMenuItems) ? $primaryMenuItems : [])
                    ->contains(fn (mixed $item): bool => is_array($item) && preg_match('/demo[-_\s]*th\d+/i', (string) ($item['url'] ?? '')) === 1);

                if (strtoupper($activeThemeKey) === 'XD0301' && $primaryMenu && $hasForeignDemoLink) {
                    $primaryMenu->update([
                        'items' => [
                            ['label' => 'Tin tức', 'url' => '/tin-tuc', 'target' => '_self'],
                            ['label' => 'Giới thiệu', 'url' => '/gioi-thieu', 'target' => '_self'],
                            ['label' => 'Liên hệ', 'url' => '/lien-he', 'target' => '_self'],
                        ],
                    ]);
                }
            }

            if (Schema::hasTable('cms_services') && ! DB::table('cms_services')->exists()) {

                $services = [
                    [
                        'title' => 'Thiết kế căn hộ chung cư',
                        'slug' => 'thiet-ke-can-ho-chung-cu',
                        'summary' => 'Tối ưu mặt bằng, ánh sáng, công năng và vật liệu để tạo không gian sống gọn, bền và dễ bảo trì.',
                        'content' => 'Dịch vụ tư vấn thiết kế căn hộ từ khảo sát hiện trạng, bố trí công năng đến phối cảnh và hồ sơ thi công.',
                        'icon' => '▦',
                        'image_url' => 'https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=900&q=80',
                    ],
                    [
                        'title' => 'Thiết kế phòng ngủ',
                        'slug' => 'thiet-ke-phong-ngu',
                        'summary' => 'Phối hợp màu sắc, hệ tủ, giường và chiếu sáng để phòng ngủ yên tĩnh nhưng vẫn giàu cá tính.',
                        'content' => 'Tư vấn không gian nghỉ ngơi theo thói quen sử dụng, ngân sách và phong cách nội thất riêng của từng gia đình.',
                        'icon' => '▤',
                        'image_url' => 'https://images.unsplash.com/photo-1615873968403-89e068629265?auto=format&fit=crop&w=900&q=80',
                    ],
                    [
                        'title' => 'Thiết kế nhà có tầng hầm',
                        'slug' => 'thiet-ke-nha-co-tang-ham',
                        'summary' => 'Tư vấn kết cấu, thông gió, chống thấm và giao thông nội bộ cho các nhà phố diện tích hạn chế.',
                        'content' => 'Đội ngũ kỹ thuật phối hợp kiến trúc và kết cấu để đảm bảo tầng hầm vận hành an toàn, khô ráo và thuận tiện.',
                        'icon' => '⌂',
                        'image_url' => 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=900&q=80',
                    ],
                ];

                foreach ($services as $index => $service) {
                    $serviceId = DB::table('cms_services')->insertGetId([
                        'title' => $service['title'],
                        'slug' => $service['slug'],
                        'status' => 'published',
                        'summary' => $service['summary'],
                        'content' => $service['content'],
                        'icon' => $service['icon'],
                        'button_label' => 'Tìm hiểu ngay',
                        'link_url' => '#lien-he',
                        'is_featured' => true,
                        'sort_order' => $index,
                        'website_key' => 'website-main',
                        'publish_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('cms_service_images')->insert([
                        'cms_service_id' => $serviceId,
                        'image_url' => $service['image_url'],
                        'alt_text' => $service['title'],
                        'is_featured' => true,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if (Schema::hasTable('cms_projects') && ! DB::table('cms_projects')->exists()) {

                $projects = [
                    [
                        'title' => 'Công trình trường mầm non',
                        'slug' => 'cong-trinh-truong-mam-non',
                        'summary' => 'Điểm nổi bật của thiết kế là những mảng xanh, sân chơi mở và luồng di chuyển an toàn cho trẻ.',
                        'content' => 'Dự án trường mầm non được triển khai theo tiêu chí ánh sáng tự nhiên, thông gió tốt và vật liệu bền vững.',
                        'image_url' => 'https://images.unsplash.com/photo-1592595896616-c37162298647?auto=format&fit=crop&w=900&q=80',
                    ],
                    [
                        'title' => 'Công trình nhà văn phòng',
                        'slug' => 'cong-trinh-nha-van-phong',
                        'summary' => 'Không gian làm việc hiện đại, mặt dựng kính lớn và bố cục vận hành linh hoạt cho doanh nghiệp.',
                        'content' => 'Dự án văn phòng tập trung vào hiệu quả khai thác, kiểm soát ánh sáng và trải nghiệm sử dụng hằng ngày.',
                        'image_url' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=900&q=80',
                    ],
                    [
                        'title' => 'Công trình khách sạn',
                        'slug' => 'cong-trinh-khach-san',
                        'summary' => 'Phối hợp kết cấu, điện nước và hoàn thiện để tạo trải nghiệm lưu trú chuyên nghiệp.',
                        'content' => 'Dự án khách sạn được quản lý theo từng giai đoạn nghiệm thu, đảm bảo tiến độ khai thác sau bàn giao.',
                        'image_url' => 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=900&q=80',
                    ],
                    [
                        'title' => 'Công trình biệt thự nhà vườn',
                        'slug' => 'cong-trinh-biet-thu-nha-vuon',
                        'summary' => 'Thiết kế tạo môi trường sống xanh, thoáng và riêng tư cho gia đình nhiều thế hệ.',
                        'content' => 'Dự án biệt thự nhà vườn kết hợp cảnh quan, vật liệu tự nhiên và mặt bằng sinh hoạt mở.',
                        'image_url' => 'https://images.unsplash.com/photo-1600607688969-a5bfcd646154?auto=format&fit=crop&w=900&q=80',
                    ],
                ];

                foreach ($projects as $index => $project) {
                    $projectId = DB::table('cms_projects')->insertGetId([
                        'title' => $project['title'],
                        'slug' => $project['slug'],
                        'status' => 'published',
                        'summary' => $project['summary'],
                        'content' => $project['content'],
                        'button_label' => 'Xem chi tiết',
                        'link_url' => '#lien-he',
                        'is_featured' => true,
                        'sort_order' => $index,
                        'website_key' => 'website-main',
                        'publish_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('cms_project_images')->insert([
                        'cms_project_id' => $projectId,
                        'image_url' => $project['image_url'],
                        'alt_text' => $project['title'],
                        'is_featured' => true,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if (Schema::hasTable('cms_testimonials') && ! DB::table('cms_testimonials')->exists()) {

                $testimonials = [
                    [
                        'name' => 'Sharah Albert',
                        'role' => 'Chủ đầu tư',
                        'company' => 'Biệt thự Thảo Điền',
                        'quote' => 'Là đơn vị thiết kế và thi công chuyên nghiệp nhất mà tôi từng hợp tác. Thiết kế nhà rất đẹp, thi công chuẩn từng chi tiết.',
                        'image_url' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=300&q=80',
                    ],
                    [
                        'name' => 'Emily Blunt',
                        'role' => 'Khách hàng',
                        'company' => 'Nhà phố Quận 3',
                        'quote' => 'Công ty luôn cung cấp mẫu thiết kế đa dạng theo yêu cầu. Ngôi nhà của chúng tôi đẹp và phù hợp nhu cầu sử dụng của gia đình.',
                        'image_url' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=300&q=80',
                    ],
                ];

                foreach ($testimonials as $index => $testimonial) {
                    DB::table('cms_testimonials')->insert([
                        'name' => $testimonial['name'],
                        'role' => $testimonial['role'],
                        'company' => $testimonial['company'],
                        'quote' => $testimonial['quote'],
                        'image_url' => $testimonial['image_url'],
                        'image_alt' => $testimonial['name'],
                        'link_url' => '#lien-he',
                        'status' => 'published',
                        'is_featured' => true,
                        'sort_order' => $index,
                        'website_key' => 'website-main',
                        'publish_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if (Schema::hasTable('cms_team_members') && ! DB::table('cms_team_members')->exists()) {

                $members = [
                    [
                        'name' => 'Jhon Castellon',
                        'slug' => 'jhon-castellon',
                        'role' => 'Giám sát',
                        'department' => 'Thi công',
                        'summary' => 'Phụ trách giám sát hiện trường, an toàn và chất lượng thi công.',
                        'image_url' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=700&q=80',
                    ],
                    [
                        'name' => 'José Carpio',
                        'slug' => 'jose-carpio',
                        'role' => 'Quản lí',
                        'department' => 'Dự án',
                        'summary' => 'Điều phối tiến độ, ngân sách và nghiệm thu từng giai đoạn.',
                        'image_url' => 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?auto=format&fit=crop&w=700&q=80',
                    ],
                    [
                        'name' => 'Valentin Lacoste',
                        'slug' => 'valentin-lacoste',
                        'role' => 'Kiến trúc sư',
                        'department' => 'Thiết kế',
                        'summary' => 'Phát triển concept, mặt bằng và chi tiết kiến trúc.',
                        'image_url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=700&q=80',
                    ],
                    [
                        'name' => 'Kyle Frederick',
                        'slug' => 'kyle-frederick',
                        'role' => 'Trưởng nhóm',
                        'department' => 'Kỹ thuật',
                        'summary' => 'Phụ trách phối hợp kỹ thuật và kiểm soát hồ sơ thi công.',
                        'image_url' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=700&q=80',
                    ],
                ];

                foreach ($members as $index => $member) {
                    $memberId = DB::table('cms_team_members')->insertGetId([
                        'name' => $member['name'],
                        'slug' => $member['slug'],
                        'role' => $member['role'],
                        'department' => $member['department'],
                        'summary' => $member['summary'],
                        'bio' => $member['summary'],
                        'link_url' => '#lien-he',
                        'status' => 'published',
                        'is_featured' => true,
                        'sort_order' => $index,
                        'website_key' => 'website-main',
                        'publish_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('cms_team_member_images')->insert([
                        'cms_team_member_id' => $memberId,
                        'image_url' => $member['image_url'],
                        'alt_text' => $member['name'],
                        'is_featured' => true,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if (Schema::hasTable('cms_partners') && ! DB::table('cms_partners')->exists()) {
                $partners = [
                    ['title' => 'HOABINH', 'slug' => 'hoabinh'],
                    ['title' => 'HUNG THINH LAND', 'slug' => 'hung-thinh-land'],
                    ['title' => 'VIET THANG', 'slug' => 'viet-thang'],
                    ['title' => 'TLC', 'slug' => 'tlc'],
                    ['title' => 'HUNG PHUOC', 'slug' => 'hung-phuoc'],
                    ['title' => 'HOA SEN GROUP', 'slug' => 'hoa-sen-group'],
                ];

                foreach ($partners as $index => $partner) {
                    DB::table('cms_partners')->insert([
                        'title' => $partner['title'],
                        'slug' => $partner['slug'],
                        'description' => 'Đối tác chiến lược trong hệ sinh thái thiết kế và thi công.',
                        'image_url' => null,
                        'image_alt' => $partner['title'],
                        'link_url' => '#top',
                        'status' => 'published',
                        'is_featured' => true,
                        'sort_order' => $index,
                        'website_key' => 'website-main',
                        'publish_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
