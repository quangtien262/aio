<?php

namespace App\Core\Themes\Demo;

use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsMenu;
use App\Models\CmsPost;
use App\Models\CmsService;
use App\Models\CmsServiceCategory;
use App\Models\CmsServiceImage;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Models\LandingPageData;
use App\Models\SiteBanner;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\SiteContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Ser103DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'SER103';

    private const PRESET_KEY = 'ser103-bohu-wedding';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder)
    {
    }

    public function themeKey(): string
    {
        return self::THEME_KEY;
    }

    public function defaultPreset(): string
    {
        return self::PRESET_KEY;
    }

    public function preset(): array
    {
        return [
            'key' => self::PRESET_KEY,
            'label' => 'SER103 Bøhu Wedding',
            'description' => 'Dữ liệu mẫu dịch vụ cưới gồm dịch vụ, tin tức, banner, menu và landing page SER103.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo is not valid for SER103.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = app(SiteContext::class)->websiteKey();

            $serviceCategory = CmsServiceCategory::query()->create([
                'name' => 'Dịch vụ cưới SER103',
                'slug' => 'ser103-dich-vu-cuoi',
                'description' => 'Giải pháp trọn gói cho một ngày cưới thanh lịch và đáng nhớ.',
                'sort_order' => 0,
                'is_active' => true,
            ]);
            $this->record($serviceCategory);

            $services = [
                ['Trang điểm cô dâu', 'Phong cách trang điểm tinh tế, tôn nét đẹp riêng của cô dâu.', 'service-makeup.webp', 'fa-solid fa-wand-magic-sparkles'],
                ['Chụp ảnh cưới', 'Lưu giữ câu chuyện tình yêu bằng những khung hình giàu cảm xúc.', 'service-photo.webp', 'fa-solid fa-camera'],
                ['Quay phóng sự cưới', 'Ghi lại trọn vẹn những khoảnh khắc tự nhiên trong ngày trọng đại.', 'service-video.webp', 'fa-solid fa-video'],
                ['Thuê xe cưới', 'Đội xe cưới sang trọng, đúng giờ và được trang trí chỉn chu.', 'service-car.webp', 'fa-solid fa-car-side'],
                ['Cho thuê bàn ghế', 'Không gian tiệc cưới đồng bộ với bàn ghế và phụ kiện thanh lịch.', 'service-banquet.webp', 'fa-solid fa-champagne-glasses'],
            ];
            foreach ($services as $index => [$title, $summary, $image, $icon]) {
                $service = CmsService::query()->create([
                    'cms_service_category_id' => $serviceCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('ser103-'.$title),
                    'status' => 'published',
                    'summary' => $summary,
                    'content' => '<p>'.$summary.'</p><p>Đội ngũ Bøhu đồng hành từ ý tưởng đến ngày tổ chức để mọi chi tiết đều trọn vẹn.</p>',
                    'icon' => $icon,
                    'button_label' => 'Xem chi tiết',
                    'is_featured' => true,
                    'is_highlight' => true,
                    'sort_order' => $index,
                    'publish_at' => now(),
                ]);
                $this->record($service);

                $serviceImage = CmsServiceImage::query()->create([
                    'cms_service_id' => $service->id,
                    'image_url' => '/theme-demo/ser103/'.$image,
                    'alt_text' => $title,
                    'is_featured' => true,
                    'sort_order' => 0,
                ]);
                $this->record($serviceImage);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Cẩm nang cưới SER103',
                'slug' => 'ser103-cam-nang-cuoi',
                'description' => 'Xu hướng, kinh nghiệm và cảm hứng dành cho ngày cưới.',
            ]);
            $this->record($postCategory);

            $posts = [
                ['Xu hướng các mẫu váy cưới đẹp nhất 2026', 'Những dáng váy tinh giản, thanh lịch đang được các cô dâu yêu thích.', 'news-minimal.webp'],
                ['Các mẫu váy cưới dẫn đầu xu hướng 2026', 'Thiết kế couture mềm mại giúp cô dâu tỏa sáng trong ngày trọng đại.', 'news-couture.webp'],
                ['Những kiểu váy cưới hot năm 2026', 'Cảm hứng váy cưới nhẹ nhàng dành cho lễ cưới ngoài trời.', 'news-garden.webp'],
            ];
            foreach ($posts as $index => [$title, $excerpt, $image]) {
                $media = CmsMedia::query()->create([
                    'title' => $title,
                    'file_path' => 'theme-demo/ser103/'.$image,
                    'file_url' => '/theme-demo/ser103/'.$image,
                    'mime_type' => 'image/webp',
                    'size' => 0,
                    'alt_text' => $title,
                ]);
                $this->record($media);

                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('ser103-'.$title),
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p><p>Bøhu tuyển chọn những gợi ý mới để ngày cưới mang đậm dấu ấn riêng của bạn.</p>',
                    'featured_media_id' => $media->id,
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => $index === 0,
                ]);
                $this->record($post);
            }

            foreach ([
                ['Lập kế hoạch cho đám cưới của bạn', 'Chúng tôi biến mong ước của hai bạn thành một ngày cưới đầy cảm xúc.', '/theme-demo/ser103/hero-wedding.webp'],
                ['Mỗi khoảnh khắc đều xứng đáng được lưu giữ', 'Dịch vụ cưới trọn gói với sự chăm chút trong từng chi tiết.', '/theme-demo/ser103/gallery-lake.webp'],
            ] as $index => [$title, $summary, $image]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'ser103-hero-slider',
                    'title' => $title,
                    'subtitle' => $summary,
                    'image_url' => $image,
                    'link_url' => '#lien-he',
                    'metadata' => ['summary' => $summary, 'button_label' => 'Đặt lịch hẹn'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create([
                'name' => 'SER103 Primary Navigation',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Giới thiệu', 'url' => '#gioi-thieu'],
                    ['label' => 'Dịch vụ', 'url' => '#dich-vu'],
                    ['label' => 'Tin tức', 'url' => '#tin-tuc'],
                    ['label' => 'Thư viện', 'url' => '#thu-vien'],
                    ['label' => 'Liên hệ', 'url' => '#lien-he'],
                ],
            ]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew(['website_key' => $websiteKey]);
            $profile->forceFill([
                'site_name' => 'Bøhu Wedding',
                'website_type' => 'service',
                'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge([
                    'company_name' => 'Bøhu.',
                    'company_description' => 'Studio và wedding planner đồng hành cùng các cặp đôi trong hành trình tạo nên ngày cưới mang dấu ấn riêng.',
                    'support_hotline' => '1900 9477',
                    'support_email' => 'hello@bohu.vn',
                    'support_location' => 'TP. Hồ Chí Minh, Việt Nam',
                ], (array) $profile->branding),
            ])->save();

            $existingPage = LandingPage::query()
                ->where('website_key', $websiteKey)
                ->where('theme_key', self::THEME_KEY)
                ->where('is_home', true)
                ->first();
            $page = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($page && $existingPage === null) {
                $this->record($page);
            }

            return [
                'preset' => $this->preset(),
                'counts' => [
                    'service_categories' => 1,
                    'services' => count($services),
                    'service_images' => count($services),
                    'post_categories' => 1,
                    'posts' => count($posts),
                    'media' => count($posts),
                    'banners' => 2,
                    'menus' => 1,
                    'landing_pages' => $existingPage === null && $page ? 1 : 0,
                ],
                'purged' => $purged,
            ];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()
            ->where('theme_key', self::THEME_KEY)
            ->where('preset_key', self::PRESET_KEY)
            ->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = [
            'banners' => 0,
            'menus' => 0,
            'posts' => 0,
            'media' => 0,
            'services' => 0,
            'service_images' => 0,
            'service_categories' => 0,
            'post_categories' => 0,
            'landing_pages' => 0,
        ];

        if ($imageIds = $ids(CmsServiceImage::class)) {
            $counts['service_images'] = CmsServiceImage::query()->whereKey($imageIds)->delete();
        }
        if ($serviceIds = $ids(CmsService::class)) {
            $counts['services'] = CmsService::query()->whereKey($serviceIds)->delete();
        }
        if ($pageIds = $ids(LandingPage::class)) {
            $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id');
            LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete();
            LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete();
            LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete();
            $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete();
        }
        foreach ([
            [CmsPost::class, 'posts'],
            [CmsMedia::class, 'media'],
            [CmsCategory::class, 'post_categories'],
            [CmsServiceCategory::class, 'service_categories'],
            [CmsMenu::class, 'menus'],
            [SiteBanner::class, 'banners'],
        ] as [$model, $key]) {
            if ($modelIds = $ids($model)) {
                $counts[$key] += $model::query()->whereKey($modelIds)->delete();
            }
        }
        ThemeDemoRecord::query()->whereKey($records->pluck('id'))->delete();

        return $counts;
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create([
            'theme_key' => self::THEME_KEY,
            'preset_key' => self::PRESET_KEY,
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
        ]);
    }
}
