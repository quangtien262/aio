<?php

namespace App\Core\Themes\Demo;

use App\Models\CmsMenu;
use App\Models\CmsPartner;
use App\Models\CmsService;
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

class Xd0306DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'XD0306';

    private const PRESET_KEY = 'xd0306-digital-agency';

    public function __construct(private readonly LandingPageBuilder $landingPageBuilder) {}

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
        return ['key' => self::PRESET_KEY, 'label' => 'Black Digital Agency', 'description' => 'Dữ liệu mẫu agency số gồm chiến lược thương hiệu, thiết kế, nội dung và quảng cáo.'];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho XD0306.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $now = now();
            $image = fn (string $id, int $width = 1200): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=85";

            foreach ([
                ['Chiến lược thương hiệu', 'Định vị khác biệt, kiến trúc thương hiệu và thông điệp nhất quán trên mọi điểm chạm.', 'photo-1552664730-d307ca884978'],
                ['Thiết kế trải nghiệm số', 'Thiết kế website và sản phẩm số đặt trải nghiệm người dùng và hiệu quả kinh doanh làm trung tâm.', 'photo-1558655146-9f40138edfeb'],
                ['Nội dung sáng tạo', 'Xây dựng hệ thống nội dung có cá tính, linh hoạt cho social, website và chiến dịch.', 'photo-1542744094-3a31f272c490'],
                ['Tăng trưởng đa kênh', 'Triển khai quảng cáo và tối ưu chuyển đổi dựa trên dữ liệu đo lường minh bạch.', 'photo-1460925895917-afdab827c52f'],
            ] as $index => [$title, $summary, $photo]) {
                $service = CmsService::query()->create(['title' => $title, 'slug' => Str::slug('xd0306-digital-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'button_label' => 'Khám phá', 'link_url' => '#lien-he', 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
                CmsServiceImage::query()->create(['cms_service_id' => $service->id, 'image_url' => $image($photo, 900), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($service);
            }

            foreach ([
                ['NOVA', 'Công nghệ', 'photo-1519389950473-47ba0277781c'],
                ['MOTION', 'Sáng tạo', 'photo-1552664730-d307ca884978'],
                ['ORBIT', 'Sản phẩm số', 'photo-1558655146-d09347e92766'],
                ['KINETIC', 'Truyền thông', 'photo-1521737711867-e3b97375f902'],
                ['MONO', 'Kiến trúc', 'photo-1497366754035-f200968a6e72'],
                ['APEX', 'Thương mại', 'photo-1460925895917-afdab827c52f'],
            ] as $index => [$title, $description, $photo]) {
                $partner = CmsPartner::query()->create(['title' => $title, 'slug' => Str::slug('xd0306-partner-'.$title), 'description' => $description, 'image_url' => $image($photo, 420), 'image_alt' => $title, 'link_url' => '#top', 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($partner);
            }

            foreach ([
                ['Biến ý tưởng thành thương hiệu có sức ảnh hưởng', 'Chiến lược sắc nét, thiết kế táo bạo và trải nghiệm số tạo ra tăng trưởng thật.', 'photo-1552664730-d307ca884978'],
                ['Sáng tạo để thương hiệu không bị hòa lẫn', 'Từ câu chuyện thương hiệu đến sản phẩm số, mọi điểm chạm đều có chủ đích.', 'photo-1556761175-b413da4baf72'],
            ] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create(['theme_key' => self::THEME_KEY, 'placement' => 'xd0306-hero-slider', 'title' => $title, 'subtitle' => $summary, 'image_url' => $image($photo, 1920), 'link_url' => '#lien-he', 'badge' => 'Black Digital Studio', 'metadata' => ['kicker' => 'Black Digital Studio', 'summary' => $summary, 'button_label' => 'Bắt đầu dự án'], 'sort_order' => $index, 'is_active' => true]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create(['name' => 'XD0306 Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Studio', 'url' => '#top'], ['label' => 'Dịch vụ', 'url' => '#dich-vu'], ['label' => 'Về Black', 'url' => '#gioi-thieu'], ['label' => 'Dự án', 'url' => '#thu-vien'], ['label' => 'Góc nhìn', 'url' => '#tin-tuc'], ['label' => 'Liên hệ', 'url' => '#footer']]]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'Black Digital', 'website_type' => 'service', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['company_name' => 'Black Digital', 'company_description' => 'Creative agency kết hợp chiến lược, thiết kế và công nghệ để tạo nên thương hiệu khác biệt.', 'support_hotline' => '1900 9477', 'support_email' => 'hello@blackdigital.vn', 'support_location' => 'Quận 3, TP.HCM'])])->save();

            $existingPage = LandingPage::query()->where('website_key', app(SiteContext::class)->websiteKey())->where('theme_key', self::THEME_KEY)->where('is_home', true)->first();
            $page = $this->landingPageBuilder->resolveHome(app(SiteContext::class)->websiteKey(), self::THEME_KEY, true);
            if ($page && $existingPage === null) {
                $this->record($page);
            }

            return ['preset' => $this->preset(), 'counts' => ['services' => 4, 'partners' => 6, 'banners' => 2, 'menus' => 1, 'landing_pages' => $existingPage === null && $page ? 1 : 0], 'purged' => $purged];
        });
    }

    public function delete(): array
    {
        $records = ThemeDemoRecord::query()->where('theme_key', self::THEME_KEY)->get();
        $ids = fn (string $type): array => $records->where('model_type', $type)->pluck('model_id')->all();
        $counts = ['banners' => 0, 'menus' => 0, 'services' => 0, 'partners' => 0, 'landing_pages' => 0];

        if ($serviceIds = $ids(CmsService::class)) {
            CmsServiceImage::query()->whereIn('cms_service_id', $serviceIds)->delete();
            $counts['services'] = CmsService::query()->whereKey($serviceIds)->delete();
        }
        if ($partnerIds = $ids(CmsPartner::class)) {
            $counts['partners'] = CmsPartner::query()->whereKey($partnerIds)->delete();
        }
        if ($pageIds = $ids(LandingPage::class)) {
            $blockIds = LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->pluck('id');
            LandingPageBlockData::query()->whereIn('landing_page_block_id', $blockIds)->delete();
            LandingPageBlock::query()->whereIn('landing_page_id', $pageIds)->delete();
            LandingPageData::query()->whereIn('landing_page_id', $pageIds)->delete();
            $counts['landing_pages'] = LandingPage::query()->whereKey($pageIds)->delete();
        }
        if ($menuIds = $ids(CmsMenu::class)) {
            $counts['menus'] = CmsMenu::query()->whereKey($menuIds)->delete();
        }
        if ($bannerIds = $ids(SiteBanner::class)) {
            $counts['banners'] = SiteBanner::query()->whereKey($bannerIds)->delete();
        }
        ThemeDemoRecord::query()->whereKey($records->pluck('id'))->delete();

        return $counts;
    }

    private function record(Model $model): void
    {
        ThemeDemoRecord::query()->create(['theme_key' => self::THEME_KEY, 'preset_key' => self::PRESET_KEY, 'model_type' => $model::class, 'model_id' => $model->getKey()]);
    }
}
