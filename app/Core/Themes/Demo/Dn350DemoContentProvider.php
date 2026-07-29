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

class Dn350DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'DN350';

    private const PRESET_KEY = 'dn350-cleaning';

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
            'label' => 'DN350 Prinash Cleaning',
            'description' => 'Dữ liệu mẫu doanh nghiệp vệ sinh gồm dịch vụ, tin tức, banner, menu và landing page DN350.',
        ];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo is not valid for DN350.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $websiteKey = app(SiteContext::class)->websiteKey();

            $serviceCategory = CmsServiceCategory::query()->create([
                'name' => 'Dịch vụ vệ sinh DN350',
                'slug' => 'dn350-dich-vu-ve-sinh',
                'description' => 'Giải pháp vệ sinh nhà ở, văn phòng, nội thất và sân vườn.',
                'sort_order' => 0,
                'is_active' => true,
            ]);
            $this->record($serviceCategory);

            $services = [
                ['Giúp việc theo giờ', 'Chăm sóc nhà cửa linh hoạt theo khung giờ.', 'service-hourly.webp', 'fa-solid fa-clock'],
                ['Tổng vệ sinh', 'Làm sạch chuyên sâu nhà ở và văn phòng.', 'service-deep-clean.webp', 'fa-solid fa-broom'],
                ['Vệ sinh sofa, rèm, nệm', 'Giặt hút chuyên dụng, loại bỏ bụi và mùi.', 'service-upholstery.webp', 'fa-solid fa-couch'],
                ['Vệ sinh máy lạnh', 'Bảo dưỡng điều hòa an toàn và sạch sâu.', 'service-air-conditioner.webp', 'fa-solid fa-wind'],
                ['Môi giới giúp việc', 'Kết nối nhân sự tận tâm và đáng tin cậy.', 'service-housekeeper.webp', 'fa-solid fa-people-group'],
                ['Chăm sóc dọn vườn', 'Cắt tỉa và chăm sóc khuôn viên xanh sạch.', 'service-garden.webp', 'fa-solid fa-seedling'],
            ];
            foreach ($services as $index => [$title, $summary, $image, $icon]) {
                $service = CmsService::query()->create([
                    'cms_service_category_id' => $serviceCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('dn350-'.$title),
                    'status' => 'published',
                    'summary' => $summary,
                    'content' => '<p>'.$summary.'</p><p>Quy trình minh bạch, nhân sự được đào tạo và vật tư an toàn cho sức khỏe.</p>',
                    'icon' => $icon,
                    'button_label' => 'Xem chi tiết',
                    'is_featured' => true,
                    'is_highlight' => true,
                    'sort_order' => $index,
                    'publish_at' => now(),
                ]);
                CmsServiceImage::query()->create([
                    'cms_service_id' => $service->id,
                    'image_url' => '/theme-demo/dn350/'.$image,
                    'alt_text' => $title,
                    'is_featured' => true,
                    'sort_order' => 0,
                ]);
                $this->record($service);
            }

            $postCategory = CmsCategory::query()->create([
                'name' => 'Cẩm nang vệ sinh DN350',
                'slug' => 'dn350-cam-nang-ve-sinh',
                'description' => 'Mẹo vệ sinh và chăm sóc không gian sống.',
            ]);
            $this->record($postCategory);

            $posts = [
                ['Những điểm thường thấy của người nghiện dọn nhà', 'Liệu bạn có những đặc điểm đáng tiền, ẩn tiện này không?', 'gallery-team.webp'],
                ['Khi nào nên thuê người dọn nhà một lần?', 'Gợi ý giúp bạn chọn đúng thời điểm và phạm vi làm sạch chuyên sâu.', 'gallery-kitchen-work.webp'],
                ['Đừng giữ lại những thứ hết hạn này khi chuyển nhà', 'Checklist nhỏ giúp việc dọn nhà và sắp xếp không gian dễ dàng hơn.', 'gallery-moving.webp'],
            ];
            foreach ($posts as $index => [$title, $excerpt, $image]) {
                $media = CmsMedia::query()->create([
                    'title' => $title,
                    'file_path' => 'theme-demo/dn350/'.$image,
                    'file_url' => '/theme-demo/dn350/'.$image,
                    'mime_type' => 'image/webp',
                    'size' => 0,
                    'alt_text' => $title,
                ]);
                $this->record($media);
                $post = CmsPost::query()->create([
                    'category_id' => $postCategory->id,
                    'title' => $title,
                    'slug' => Str::slug('dn350-'.$title),
                    'status' => 'published',
                    'excerpt' => $excerpt,
                    'body' => '<p>'.$excerpt.'</p><p>Prinash chia sẻ kinh nghiệm thực tế để việc chăm sóc không gian trở nên nhẹ nhàng và hiệu quả hơn.</p>',
                    'featured_media_id' => $media->id,
                    'publish_at' => now()->subDays($index + 1),
                    'is_highlight' => $index === 0,
                ]);
                $this->record($post);
            }

            foreach ([
                ['Chúng tôi là lựa chọn tốt nhất cho bạn', 'Chi phí phải chăng, chất lượng làm sạch không đổi.', '/theme-demo/dn350/hero-pressure-washing.webp'],
                ['Sạch chuẩn từng không gian', 'Đội ngũ chuyên nghiệp, linh hoạt và đúng giờ.', '/theme-demo/dn350/clean-home.webp'],
            ] as $index => [$title, $summary, $image]) {
                $banner = SiteBanner::query()->create([
                    'theme_key' => self::THEME_KEY,
                    'placement' => 'dn350-hero-slider',
                    'title' => $title,
                    'subtitle' => $summary,
                    'image_url' => $image,
                    'link_url' => '#dich-vu',
                    'metadata' => ['summary' => $summary],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create([
                'name' => 'DN350 Primary Navigation',
                'location' => 'primary-navigation',
                'items' => [
                    ['label' => 'Trang chủ', 'url' => '#trang-chu'],
                    ['label' => 'Giới thiệu', 'url' => '#gioi-thieu'],
                    ['label' => 'Dịch vụ', 'url' => '#dich-vu'],
                    ['label' => 'Tin tức', 'url' => route('site.blog.index', [], false)],
                    ['label' => 'Thư viện', 'url' => '#thu-vien'],
                    ['label' => 'Liên hệ', 'url' => route('site.contact', [], false)],
                ],
            ]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew(['website_key' => $websiteKey]);
            $profile->forceFill([
                'site_name' => 'Prinash Cleaning',
                'website_type' => 'service',
                'active_theme_key' => self::THEME_KEY,
                'branding' => array_merge((array) $profile->branding, [
                    'company_name' => 'Prinash',
                    'company_description' => 'Giải pháp vệ sinh toàn diện cho nhà ở, văn phòng và sân vườn.',
                    'support_hotline' => '1900 9477',
                    'support_email' => 'hello@prinash.vn',
                    'support_location' => '344 Huỳnh Tấn Phát, Quận 7, TP. Hồ Chí Minh',
                ]),
            ])->save();

            $existingPage = LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome($websiteKey, self::THEME_KEY, true);
            if ($page && $existingPage === null) {
                $this->record($page);
            }

            return [
                'preset' => $this->preset(),
                'counts' => ['service_categories' => 1, 'services' => 6, 'post_categories' => 1, 'posts' => 3, 'media' => 3, 'banners' => 2, 'menus' => 1, 'landing_pages' => $existingPage === null && $page ? 1 : 0],
                'purged' => $purged,
            ];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->where('preset_key', self::PRESET_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['banners' => 0, 'menus' => 0, 'posts' => 0, 'media' => 0, 'services' => 0, 'service_categories' => 0, 'post_categories' => 0, 'landing_pages' => 0];

        if ($serviceIds = $ids(CmsService::class)) {
            CmsServiceImage::query()->whereIn('cms_service_id', $serviceIds)->delete();
            $counts['services'] = CmsService::query()->whereKey($serviceIds)->delete();
        }
        if ($pageIds = $ids(LandingPage::class)) {
            $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id');
            LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete();
            LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete();
            LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete();
            $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete();
        }
        foreach ([[CmsPost::class, 'posts'], [CmsMedia::class, 'media'], [CmsCategory::class, 'post_categories'], [CmsServiceCategory::class, 'service_categories'], [CmsMenu::class, 'menus'], [SiteBanner::class, 'banners']] as [$model, $key]) {
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
