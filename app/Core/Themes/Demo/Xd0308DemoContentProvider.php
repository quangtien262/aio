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

class Xd0308DemoContentProvider implements ThemeDemoContentProvider
{
    private const THEME_KEY = 'XD0308';

    private const PRESET_KEY = 'xd0308-study-abroad';

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
        return ['key' => self::PRESET_KEY, 'label' => 'Comgo Education', 'description' => 'Dữ liệu mẫu tư vấn du học gồm chọn trường, hồ sơ, visa, học bổng và quốc gia nổi bật.'];
    }

    public function generate(string $presetKey): array
    {
        if ($presetKey !== self::PRESET_KEY) {
            throw new InvalidArgumentException('Preset demo không hợp lệ cho XD0308.');
        }

        return DB::transaction(function (): array {
            $purged = $this->delete();
            $now = now();
            $image = fn (string $id, int $width = 1200): string => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$width}&q=85";

            foreach ([
                ['Tư vấn chọn trường', 'Đánh giá năng lực, mục tiêu và ngân sách để xây dựng danh sách trường phù hợp.', 'photo-1523240795612-9a054b0db644'],
                ['Hoàn thiện hồ sơ', 'Lộ trình chuẩn bị hồ sơ học tập, bài luận và giấy tờ rõ ràng theo từng mốc.', 'photo-1456406644174-8ddd4cd52a06'],
                ['Hỗ trợ visa', 'Rà soát hồ sơ visa, luyện phỏng vấn và đồng hành đến khi nhận kết quả.', 'photo-1434030216411-0b793f4b4173'],
                ['Săn học bổng', 'Tìm cơ hội học bổng phù hợp và tăng sức thuyết phục cho bộ hồ sơ ứng tuyển.', 'photo-1523580846011-d3a5bc25702b'],
            ] as $index => [$title, $summary, $photo]) {
                $service = CmsService::query()->create(['title' => $title, 'slug' => Str::slug('xd0308-'.$title), 'status' => 'published', 'summary' => $summary, 'content' => '<p>'.$summary.'</p>', 'button_label' => 'Xem chi tiết', 'link_url' => '#hotline', 'is_featured' => true, 'is_highlight' => true, 'sort_order' => $index, 'publish_at' => $now]);
                CmsServiceImage::query()->create(['cms_service_id' => $service->id, 'image_url' => $image($photo, 900), 'alt_text' => $title, 'is_featured' => true, 'sort_order' => 0]);
                $this->record($service);
            }

            foreach ([
                ['Australia', 'Du học Úc', 'photo-1523482580672-f109ba8cb9be'],
                ['Canada', 'Du học Canada', 'photo-1517935706615-2717063c2225'],
                ['United Kingdom', 'Du học Anh', 'photo-1513635269975-59663e0ac1ad'],
                ['United States', 'Du học Mỹ', 'photo-1485738422979-f5c462d49f74'],
                ['New Zealand', 'Du học New Zealand', 'photo-1469521669194-babb45599def'],
                ['Singapore', 'Du học Singapore', 'photo-1525625293386-3f8f99389edd'],
            ] as $index => [$title, $description, $photo]) {
                $partner = CmsPartner::query()->create(['title' => $title, 'slug' => Str::slug('xd0308-'.$title), 'description' => $description, 'image_url' => $image($photo, 420), 'image_alt' => $title, 'link_url' => '#top', 'status' => 'published', 'publish_at' => $now, 'is_featured' => true, 'sort_order' => $index]);
                $this->record($partner);
            }

            foreach ([
                ['Mở cánh cửa đến hành trình học tập toàn cầu', 'Từ chọn ngành, chọn trường đến hồ sơ và visa, Comgo đồng hành trên từng cột mốc.', 'photo-1523240795612-9a054b0db644'],
                ['Một lộ trình du học được thiết kế riêng cho bạn', 'Quyết định dựa trên năng lực, mục tiêu nghề nghiệp và ngân sách thực tế.', 'photo-1523050854058-8df90110c9f1'],
            ] as $index => [$title, $summary, $photo]) {
                $banner = SiteBanner::query()->create(['theme_key' => self::THEME_KEY, 'placement' => 'xd0308-hero-slider', 'title' => $title, 'subtitle' => $summary, 'image_url' => $image($photo, 1920), 'link_url' => '#lien-he', 'badge' => 'Comgo Education', 'metadata' => ['kicker' => 'Comgo Education', 'summary' => $summary, 'button_label' => 'Đăng ký tư vấn'], 'sort_order' => $index, 'is_active' => true]);
                $this->record($banner);
            }

            $menu = CmsMenu::query()->create(['name' => 'XD0308 Main Menu', 'location' => 'primary-navigation', 'items' => [['label' => 'Trang chủ', 'url' => '#top'], ['label' => 'Về Comgo', 'url' => '#gioi-thieu'], ['label' => 'Quy trình', 'url' => '#quy-trinh'], ['label' => 'Dịch vụ', 'url' => '#dich-vu'], ['label' => 'Quốc gia', 'url' => '#quoc-gia'], ['label' => 'Tư vấn', 'url' => '#lien-he']]]);
            $this->record($menu);

            $profile = SiteProfile::query()->firstOrNew();
            $profile->forceFill(['site_name' => 'Comgo Education', 'website_type' => 'service', 'active_theme_key' => self::THEME_KEY, 'branding' => array_merge((array) $profile->branding, ['company_name' => 'Comgo Education', 'company_description' => 'Tư vấn lộ trình du học cá nhân hóa, minh bạch và đồng hành đến ngày nhập học.', 'support_hotline' => '1900 9477', 'support_email' => 'hello@comgo.edu.vn', 'support_location' => 'Hà Nội và TP.HCM'])])->save();

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
